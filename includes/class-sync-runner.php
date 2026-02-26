<?php
/**
 * Sync runner.
 *
 * Executes one sync rule end-to-end: reads the rule from term meta,
 * calls the YouTube API, evaluates conditions, imports videos, and
 * writes results back to term meta.
 *
 * @package YouSync
 */

namespace YouSync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Sync_Runner
 *
 * Orchestrates a single sync rule execution.
 */
class Sync_Runner {

	/**
	 * YouTube API wrapper.
	 *
	 * @var YouTube_API
	 */
	private YouTube_API $api;

	/**
	 * Condition evaluator.
	 *
	 * @var Condition_Evaluator
	 */
	private Condition_Evaluator $evaluator;

	/**
	 * Video importer.
	 *
	 * @var Video_Importer
	 */
	private Video_Importer $importer;

	/**
	 * Constructor.
	 *
	 * @param YouTube_API         $api       YouTube API wrapper.
	 * @param Condition_Evaluator $evaluator Condition evaluator.
	 * @param Video_Importer      $importer  Video importer.
	 */
	public function __construct( YouTube_API $api, Condition_Evaluator $evaluator, Video_Importer $importer ) {
		$this->api       = $api;
		$this->evaluator = $evaluator;
		$this->importer  = $importer;
	}

	// -------------------------------------------------------------------------
	// Public entry point
	// -------------------------------------------------------------------------

	/**
	 * Execute a sync rule.
	 *
	 * Called by Sync_Scheduler::dispatch_sync() when a WP Cron event fires.
	 *
	 * @param string $source_type  'channel' or 'playlist'.
	 * @param int    $term_id      WordPress term ID of the source.
	 * @param int    $rule_index   0-based index of the rule in sync_rules[].
	 * @return void
	 */
	public function run( string $source_type, int $term_id, int $rule_index ): void {
		$rule = $this->load_rule( $source_type, $term_id, $rule_index );

		// Rule missing or disabled — nothing to do.
		if ( null === $rule || empty( $rule['enabled'] ) ) {
			return;
		}

		$action = $rule['action'] ?? '';

		try {
			switch ( $action ) {
				// ---- Video sync ----
				case 'videos_sync_new':
					$this->handle_videos_sync_new( $source_type, $term_id, $rule );
					break;

				case 'videos_update_all':
				case 'videos_update_non_modified':
				case 'videos_update_specific_all':
				case 'videos_update_specific_non_modified':
					$this->handle_videos_update( $source_type, $term_id, $rule, $action );
					break;

				// ---- Channel metadata ----
				case 'channel_update_all':
				case 'channel_update_specific':
					$this->handle_channel_update( $term_id, $rule, $action );
					break;

				// ---- Playlists from channel ----
				case 'playlists_sync_new':
				case 'playlists_update_all':
				case 'playlists_update_non_modified':
				case 'playlists_update_specific_all':
				case 'playlists_update_specific_non_modified':
					$this->handle_playlists_sync( $term_id, $rule, $action );
					break;

				// ---- Playlist metadata (when source is a playlist term) ----
				case 'playlist_update_all':
				case 'playlist_update_specific':
					$this->handle_playlist_update( $term_id, $rule, $action );
					break;

				default:
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( "YouSync: unknown action '{$action}' (term {$term_id}, rule {$rule_index})." );
					return;
			}

			$this->record_success( $source_type, $term_id );

		} catch ( \Exception $e ) {
			$this->record_error( $source_type, $term_id, $e->getMessage(), 'exception' );
		}

		// For 'once' schedule: auto-disable the rule after it fires.
		if ( isset( $rule['schedule'] ) && 'once' === $rule['schedule'] ) {
			$this->disable_once_rule( $source_type, $term_id, $rule_index );
		}
	}

	// -------------------------------------------------------------------------
	// Action handlers
	// -------------------------------------------------------------------------

	/**
	 * Handle the videos_sync_new action.
	 *
	 * Fetches all videos from the channel uploads playlist or playlist,
	 * filters out already-imported videos, evaluates conditions, and
	 * imports new videos that pass.
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $term_id     Term ID of the source channel/playlist.
	 * @param array  $rule        Rule array from term meta.
	 * @return void
	 * @throws \RuntimeException On unrecoverable API failure.
	 */
	private function handle_videos_sync_new( string $source_type, int $term_id, array $rule ): void {
		$conditions = $rule['conditions'] ?? array();

		if ( 'channel' === $source_type ) {
			// Channels: need to get the uploads playlist ID first.
			$meta = $this->get_term_meta_data( $source_type, $term_id );
			if ( ! $meta || empty( $meta['channel_id'] ) ) {
				throw new \RuntimeException( 'Channel ID not found in term meta.' );
			}

			$channel_data = $this->api->get_channel_data( $meta['channel_id'] );
			if ( is_wp_error( $channel_data ) ) {
				$this->record_error( $source_type, $term_id, $channel_data->get_error_message(), $channel_data->get_error_code() );
				return;
			}

			$playlist_id = $channel_data['uploads_playlist_id'] ?? '';
			if ( empty( $playlist_id ) ) {
				throw new \RuntimeException( "Could not retrieve uploads playlist ID for channel '{$meta['channel_id']}'." );
			}
		} else {
			// Playlists: playlist ID is stored directly in term meta.
			$meta = $this->get_term_meta_data( $source_type, $term_id );
			if ( ! $meta || empty( $meta['playlist_id'] ) ) {
				throw new \RuntimeException( 'Playlist ID not found in term meta.' );
			}
			$playlist_id = $meta['playlist_id'];
		}

		// Fetch all video IDs from the playlist (paginated inside the API wrapper).
		$items = $this->api->get_playlist_items( $playlist_id );
		if ( is_wp_error( $items ) ) {
			$this->record_error( $source_type, $term_id, $items->get_error_message(), $items->get_error_code() );
			return;
		}

		if ( empty( $items ) ) {
			return; // Nothing to sync.
		}

		// Extract video IDs from the playlist items.
		$all_ids = array_filter( array_column( $items, 'video_id' ) );

		// Remove video IDs already imported to avoid duplicates.
		$existing_ids = $this->get_existing_video_ids();
		$new_ids      = array_values( array_diff( $all_ids, $existing_ids ) );

		if ( empty( $new_ids ) ) {
			return; // All videos already imported.
		}

		// Batch-fetch full video details and import.
		$this->batch_fetch_and_import( $new_ids, $conditions, $source_type, $term_id );
	}

	// -------------------------------------------------------------------------
	// Phase 2 handlers
	// -------------------------------------------------------------------------

	/**
	 * Handle channel_update_all and channel_update_specific.
	 *
	 * Fetches fresh channel metadata from the API and merges it into the
	 * channel's yousync_channel term meta. For the _specific variant, only
	 * the fields listed in $rule['specific_metadata'] are updated.
	 *
	 * @param int    $term_id Channel term ID.
	 * @param array  $rule    Sync rule array.
	 * @param string $action  Action slug.
	 * @return void
	 * @throws \RuntimeException On missing channel ID.
	 */
	private function handle_channel_update( int $term_id, array $rule, string $action ): void {
		$data = $this->get_term_meta_data( 'channel', $term_id );
		if ( ! $data || empty( $data['channel_id'] ) ) {
			throw new \RuntimeException( 'Channel ID not found in term meta.' );
		}

		$fresh = $this->api->get_channel_data( $data['channel_id'] );
		if ( is_wp_error( $fresh ) ) {
			$this->record_error( 'channel', $term_id, $fresh->get_error_message(), $fresh->get_error_code() );
			return;
		}

		// Map API field names to meta field names.
		$field_map = array(
			'channel_title'       => 'channel_title',
			'channel_description' => 'channel_description',
			'subscriber_count'    => 'subscriber_count',
			'video_count'         => 'video_count',
		);

		if ( 'channel_update_specific' === $action ) {
			$fields = $rule['specific_metadata'] ?? array();
		} else {
			$fields = array_keys( $field_map ); // All fields.
		}

		foreach ( $fields as $field ) {
			if ( isset( $field_map[ $field ], $fresh[ $field_map[ $field ] ] ) ) {
				$data[ $field_map[ $field ] ] = $fresh[ $field_map[ $field ] ];
			}
		}

		$data['etag'] = $fresh['etag'] ?? $data['etag'];
		update_term_meta( $term_id, 'yousync_channel', wp_json_encode( $data ) );
	}

	/**
	 * Handle playlists_sync_new and playlists_update_* actions.
	 *
	 * For sync_new: creates new yousync_playlist terms for playlists not yet imported.
	 * For update variants: updates term meta for already-imported playlist terms.
	 *
	 * @param int    $term_id Channel term ID.
	 * @param array  $rule    Sync rule array.
	 * @param string $action  Action slug.
	 * @return void
	 * @throws \RuntimeException On missing channel ID.
	 */
	private function handle_playlists_sync( int $term_id, array $rule, string $action ): void {
		$data = $this->get_term_meta_data( 'channel', $term_id );
		if ( ! $data || empty( $data['channel_id'] ) ) {
			throw new \RuntimeException( 'Channel ID not found in term meta.' );
		}

		$channel_id = $data['channel_id'];
		$playlists  = $this->api->get_channel_playlists( $channel_id );

		if ( is_wp_error( $playlists ) ) {
			$this->record_error( 'channel', $term_id, $playlists->get_error_message(), $playlists->get_error_code() );
			return;
		}

		$conditions        = $rule['conditions'] ?? array();
		$specific_metadata = $rule['specific_metadata'] ?? array();
		$is_update         = str_starts_with( $action, 'playlists_update' );
		$non_modified_only = str_contains( $action, 'non_modified' );
		$specific_only     = str_contains( $action, 'specific' );

		foreach ( $playlists as $playlist_data ) {
			if ( empty( $playlist_data['playlist_id'] ) ) {
				continue;
			}

			// Evaluate conditions against the playlist fields.
			if ( ! $this->evaluator->evaluate_all( $this->playlist_to_condition_data( $playlist_data ), $conditions ) ) {
				continue;
			}

			// Find existing playlist term by playlist_id flat meta key.
			$existing_term_id = $this->find_playlist_term_by_id( $playlist_data['playlist_id'] );

			if ( ! $is_update ) {
				// playlists_sync_new: create if missing.
				if ( ! $existing_term_id ) {
					$this->create_playlist_term( $playlist_data, $term_id );
				}
			} else {
				// Update variants: only process already-imported playlists.
				if ( ! $existing_term_id ) {
					continue;
				}

				$playlist_meta = $this->get_term_meta_data( 'playlist', $existing_term_id );

				// Skip manually-edited playlists for non_modified variants.
				if ( $non_modified_only && ! empty( $playlist_meta['manual_edits'] ) ) {
					continue;
				}

				if ( $specific_only ) {
					$this->update_playlist_term_meta_fields( $existing_term_id, $playlist_meta, $playlist_data, $specific_metadata );
				} else {
					$this->update_playlist_term_meta_all( $existing_term_id, $playlist_meta, $playlist_data );
				}
			}
		}
	}

	/**
	 * Handle playlist_update_all and playlist_update_specific (playlist source).
	 *
	 * Fetches fresh metadata for this playlist term's own playlist_id and
	 * writes it back to term meta.
	 *
	 * @param int    $term_id Playlist term ID.
	 * @param array  $rule    Sync rule array.
	 * @param string $action  Action slug.
	 * @return void
	 * @throws \RuntimeException On missing playlist ID.
	 */
	private function handle_playlist_update( int $term_id, array $rule, string $action ): void {
		$data = $this->get_term_meta_data( 'playlist', $term_id );
		if ( ! $data || empty( $data['playlist_id'] ) ) {
			throw new \RuntimeException( 'Playlist ID not found in term meta.' );
		}

		$fresh = $this->api->get_playlist_data( $data['playlist_id'] );
		if ( is_wp_error( $fresh ) ) {
			$this->record_error( 'playlist', $term_id, $fresh->get_error_message(), $fresh->get_error_code() );
			return;
		}

		$field_map = array(
			'playlist_title'       => 'playlist_title',
			'playlist_description' => 'playlist_description',
			'playlist_video_count' => 'playlist_video_count',
			'playlist_thumbnail'   => 'thumbnail_url',
		);

		if ( 'playlist_update_specific' === $action ) {
			$fields = $rule['specific_metadata'] ?? array();
		} else {
			$fields = array_keys( $field_map );
		}

		foreach ( $fields as $field ) {
			if ( 'playlist_thumbnail' === $field ) {
				// Only update the URL — full attachment re-download is Phase 3.
				if ( ! empty( $fresh['thumbnail_url'] ) ) {
					if ( is_array( $data['playlist_thumbnail'] ?? null ) ) {
						$data['playlist_thumbnail']['url'] = $fresh['thumbnail_url'];
					} else {
						$data['playlist_thumbnail'] = array( 'url' => $fresh['thumbnail_url'], 'attachment_id' => 0 );
					}
				}
			} elseif ( isset( $field_map[ $field ], $fresh[ $field_map[ $field ] ] ) ) {
				$data[ $field_map[ $field ] ] = $fresh[ $field_map[ $field ] ];
			}
		}

		$data['etag'] = $fresh['etag'] ?? $data['etag'];
		update_term_meta( $term_id, 'yousync_playlist', wp_json_encode( $data ) );
	}

	/**
	 * Handle videos_update_* actions (all four update modes).
	 *
	 * Fetches the full video list from the source, finds matching WP posts,
	 * and calls Video_Importer::update() on each.
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $term_id     Term ID.
	 * @param array  $rule        Sync rule array.
	 * @param string $action      Action slug.
	 * @return void
	 * @throws \RuntimeException On missing source ID.
	 */
	private function handle_videos_update( string $source_type, int $term_id, array $rule, string $action ): void {
		$conditions        = $rule['conditions'] ?? array();
		$specific_metadata = $rule['specific_metadata'] ?? array();

		// Map action to the mode string expected by Video_Importer::update().
		$mode_map = array(
			'videos_update_all'                  => 'update_all',
			'videos_update_non_modified'         => 'update_non_modified',
			'videos_update_specific_all'         => 'update_specific_all',
			'videos_update_specific_non_modified' => 'update_specific_non_modified',
		);
		$mode = $mode_map[ $action ] ?? 'update_all';

		// Get the playlist ID to iterate.
		if ( 'channel' === $source_type ) {
			$data = $this->get_term_meta_data( 'channel', $term_id );
			if ( ! $data || empty( $data['channel_id'] ) ) {
				throw new \RuntimeException( 'Channel ID not found in term meta.' );
			}
			$channel_data = $this->api->get_channel_data( $data['channel_id'] );
			if ( is_wp_error( $channel_data ) ) {
				$this->record_error( $source_type, $term_id, $channel_data->get_error_message(), $channel_data->get_error_code() );
				return;
			}
			$playlist_id = $channel_data['uploads_playlist_id'] ?? '';
		} else {
			$data = $this->get_term_meta_data( 'playlist', $term_id );
			if ( ! $data || empty( $data['playlist_id'] ) ) {
				throw new \RuntimeException( 'Playlist ID not found in term meta.' );
			}
			$playlist_id = $data['playlist_id'];
		}

		$items = $this->api->get_playlist_items( $playlist_id );
		if ( is_wp_error( $items ) ) {
			$this->record_error( $source_type, $term_id, $items->get_error_message(), $items->get_error_code() );
			return;
		}

		if ( empty( $items ) ) {
			return;
		}

		$all_ids = array_filter( array_column( $items, 'video_id' ) );
		$this->batch_fetch_and_update( $all_ids, $conditions, $source_type, $term_id, $mode, $specific_metadata );
	}

	// -------------------------------------------------------------------------
	// Batch fetch + import
	// -------------------------------------------------------------------------

	/**
	 * Fetch video details in batches of 50, evaluate conditions, and import.
	 *
	 * @param string[] $video_ids   Video IDs to process.
	 * @param array    $conditions  Conditions array from the rule.
	 * @param string   $source_type 'channel' or 'playlist'.
	 * @param int      $term_id     Source term ID.
	 * @return void
	 */
	private function batch_fetch_and_import(
		array $video_ids,
		array $conditions,
		string $source_type,
		int $term_id
	): void {
		$chunks = array_chunk( $video_ids, 50 );

		foreach ( $chunks as $chunk ) {
			$videos = $this->api->get_videos_by_ids( $chunk );

			if ( is_wp_error( $videos ) ) {
				// Record the error but continue — one bad batch shouldn't abort the rest.
				$this->record_error( $source_type, $term_id, $videos->get_error_message(), $videos->get_error_code() );
				continue;
			}

			foreach ( $videos as $video_data ) {
				if ( ! $this->evaluator->evaluate_all( $video_data, $conditions ) ) {
					continue; // Video does not pass conditions.
				}

				$result = $this->importer->import( $video_data, $source_type, $term_id );

				if ( is_wp_error( $result ) ) {
					// Log import errors but continue with remaining videos.
					$this->record_error( $source_type, $term_id, $result->get_error_message(), $result->get_error_code() );
				}
			}
		}
	}

	/**
	 * Fetch video details in batches of 50, evaluate conditions, and update.
	 *
	 * Only processes video IDs that already have a matching WP post.
	 *
	 * @param string[] $video_ids         All video IDs from the source playlist.
	 * @param array    $conditions        Conditions from the rule.
	 * @param string   $source_type       'channel' or 'playlist'.
	 * @param int      $term_id           Source term ID.
	 * @param string   $mode              Update mode (update_all, update_non_modified, etc.).
	 * @param string[] $specific_metadata Fields to update (for specific modes).
	 * @return void
	 */
	private function batch_fetch_and_update(
		array $video_ids,
		array $conditions,
		string $source_type,
		int $term_id,
		string $mode,
		array $specific_metadata
	): void {
		$chunks = array_chunk( $video_ids, 50 );

		foreach ( $chunks as $chunk ) {
			$videos = $this->api->get_videos_by_ids( $chunk );

			if ( is_wp_error( $videos ) ) {
				$this->record_error( $source_type, $term_id, $videos->get_error_message(), $videos->get_error_code() );
				continue;
			}

			foreach ( $videos as $video_data ) {
				if ( ! $this->evaluator->evaluate_all( $video_data, $conditions ) ) {
					continue;
				}

				$post_id = $this->importer->find_post_by_video_id( $video_data['video_id'] );
				if ( ! $post_id ) {
					continue; // Not imported yet — update modes skip new videos.
				}

				$result = $this->importer->update( $post_id, $video_data, $mode, $specific_metadata );
				if ( is_wp_error( $result ) ) {
					$this->record_error( $source_type, $term_id, $result->get_error_message(), $result->get_error_code() );
				}
			}
		}
	}

	// -------------------------------------------------------------------------
	// Playlist term helpers
	// -------------------------------------------------------------------------

	/**
	 * Find an existing yousync_playlist term by its YouTube playlist ID.
	 *
	 * Uses a flat yousync_playlist_id term meta key for indexed lookups.
	 *
	 * @param string $playlist_id YouTube playlist ID.
	 * @return int Term ID, or 0 if not found.
	 */
	private function find_playlist_term_by_id( string $playlist_id ): int {
		$terms = get_terms(
			array(
				'taxonomy'   => 'yousync_playlist',
				'hide_empty' => false,
				'fields'     => 'ids',
				'meta_query' => array(
					array(
						'key'   => 'yousync_playlist_id',
						'value' => $playlist_id,
					),
				),
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return 0;
		}

		return (int) $terms[0];
	}

	/**
	 * Create a new yousync_playlist term from API data and link it to a channel term.
	 *
	 * Stores a flat yousync_playlist_id meta key for fast future lookups.
	 * Stores a flat yousync_source_channel_term_id meta key so update actions
	 * can find playlists belonging to a specific channel.
	 *
	 * @param array $playlist_data      Playlist data from the API.
	 * @param int   $channel_term_id    WordPress term ID of the source channel.
	 * @return void
	 */
	private function create_playlist_term( array $playlist_data, int $channel_term_id ): void {
		$result = wp_insert_term(
			sanitize_text_field( $playlist_data['playlist_title'] ?: $playlist_data['playlist_id'] ),
			'yousync_playlist'
		);

		if ( is_wp_error( $result ) ) {
			return;
		}

		$new_term_id = (int) $result['term_id'];

		// Flat lookup keys.
		update_term_meta( $new_term_id, 'yousync_playlist_id', $playlist_data['playlist_id'] );
		update_term_meta( $new_term_id, 'yousync_source_channel_term_id', $channel_term_id );

		// Full JSON meta.
		$meta = array(
			'playlist_id'          => $playlist_data['playlist_id'],
			'playlist_title'       => $playlist_data['playlist_title'],
			'playlist_description' => $playlist_data['playlist_description'],
			'playlist_video_count' => $playlist_data['playlist_video_count'],
			'playlist_thumbnail'   => array(
				'url'           => $playlist_data['thumbnail_url'],
				'attachment_id' => 0,
			),
			'etag'                 => $playlist_data['etag'],
			'last_synced'          => time(),
			'sync_count'           => 0,
			'sync_errors'          => array(),
			'sync_rules'           => array(),
			'manual_edits'         => false,
		);

		update_term_meta( $new_term_id, 'yousync_playlist', wp_json_encode( $meta ) );
	}

	/**
	 * Update all metadata fields on an existing playlist term.
	 *
	 * @param int   $term_id      Playlist term ID.
	 * @param array $current_meta Current decoded yousync_playlist meta.
	 * @param array $fresh        Fresh playlist data from the API.
	 * @return void
	 */
	private function update_playlist_term_meta_all( int $term_id, array $current_meta, array $fresh ): void {
		$current_meta['playlist_title']       = $fresh['playlist_title'];
		$current_meta['playlist_description'] = $fresh['playlist_description'];
		$current_meta['playlist_video_count'] = $fresh['playlist_video_count'];
		$current_meta['etag']                 = $fresh['etag'];

		if ( ! empty( $fresh['thumbnail_url'] ) ) {
			if ( is_array( $current_meta['playlist_thumbnail'] ?? null ) ) {
				$current_meta['playlist_thumbnail']['url'] = $fresh['thumbnail_url'];
			} else {
				$current_meta['playlist_thumbnail'] = array( 'url' => $fresh['thumbnail_url'], 'attachment_id' => 0 );
			}
		}

		update_term_meta( $term_id, 'yousync_playlist', wp_json_encode( $current_meta ) );
	}

	/**
	 * Update only specific metadata fields on an existing playlist term.
	 *
	 * @param int      $term_id      Playlist term ID.
	 * @param array    $current_meta Current decoded yousync_playlist meta.
	 * @param array    $fresh        Fresh playlist data from the API.
	 * @param string[] $fields       Field names to update.
	 * @return void
	 */
	private function update_playlist_term_meta_fields( int $term_id, array $current_meta, array $fresh, array $fields ): void {
		$field_map = array(
			'playlist_title'       => 'playlist_title',
			'playlist_description' => 'playlist_description',
			'playlist_video_count' => 'playlist_video_count',
		);

		foreach ( $fields as $field ) {
			if ( 'playlist_thumbnail' === $field && ! empty( $fresh['thumbnail_url'] ) ) {
				if ( is_array( $current_meta['playlist_thumbnail'] ?? null ) ) {
					$current_meta['playlist_thumbnail']['url'] = $fresh['thumbnail_url'];
				} else {
					$current_meta['playlist_thumbnail'] = array( 'url' => $fresh['thumbnail_url'], 'attachment_id' => 0 );
				}
			} elseif ( isset( $field_map[ $field ] ) ) {
				$current_meta[ $field_map[ $field ] ] = $fresh[ $field_map[ $field ] ];
			}
		}

		$current_meta['etag'] = $fresh['etag'];
		update_term_meta( $term_id, 'yousync_playlist', wp_json_encode( $current_meta ) );
	}

	/**
	 * Convert a playlist data array into the shape expected by Condition_Evaluator.
	 *
	 * Playlist conditions use playlist_title, playlist_description,
	 * playlist_video_count fields — which map directly from the API response.
	 *
	 * @param array $playlist_data Playlist data from the API.
	 * @return array Normalised data array for evaluate_all().
	 */
	private function playlist_to_condition_data( array $playlist_data ): array {
		return array(
			'playlist_title'       => $playlist_data['playlist_title'] ?? '',
			'playlist_description' => $playlist_data['playlist_description'] ?? '',
			'playlist_video_count' => $playlist_data['playlist_video_count'] ?? 0,
		);
	}

	// -------------------------------------------------------------------------
	// Lookup helpers
	// -------------------------------------------------------------------------

	/**
	 * Get all YouTube video IDs already imported as yousync_videos posts.
	 *
	 * Uses the flat _yousync_video_id meta key for an indexed lookup.
	 *
	 * @return string[] Array of YouTube video IDs.
	 */
	private function get_existing_video_ids(): array {
		$query = new \WP_Query(
			array(
				'post_type'      => 'yousync_videos',
				'post_status'    => array( 'publish', 'draft', 'private', 'trash' ),
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'     => '_yousync_video_id',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		if ( empty( $query->posts ) ) {
			return array();
		}

		$video_ids = array();
		foreach ( $query->posts as $post_id ) {
			$vid = get_post_meta( (int) $post_id, '_yousync_video_id', true );
			if ( $vid ) {
				$video_ids[] = $vid;
			}
		}

		return $video_ids;
	}

	// -------------------------------------------------------------------------
	// Term meta helpers
	// -------------------------------------------------------------------------

	/**
	 * Load a rule from term meta.
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $term_id     Term ID.
	 * @param int    $rule_index  Index into sync_rules[].
	 * @return array|null Rule array, or null if not found.
	 */
	private function load_rule( string $source_type, int $term_id, int $rule_index ): ?array {
		$data = $this->get_term_meta_data( $source_type, $term_id );

		if ( ! $data ) {
			return null;
		}

		return $data['sync_rules'][ $rule_index ] ?? null;
	}

	/**
	 * Read and decode the source term's JSON meta.
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $term_id     Term ID.
	 * @return array|null Decoded data array, or null on failure.
	 */
	private function get_term_meta_data( string $source_type, int $term_id ): ?array {
		$meta_key = $this->meta_key( $source_type );
		$raw      = get_term_meta( $term_id, $meta_key, true );

		if ( ! $raw ) {
			return null;
		}

		$data = json_decode( $raw, true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Return the term meta key for a source type.
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @return string Meta key.
	 */
	private function meta_key( string $source_type ): string {
		return 'playlist' === $source_type ? 'yousync_playlist' : 'yousync_channel';
	}

	// -------------------------------------------------------------------------
	// Status recording
	// -------------------------------------------------------------------------

	/**
	 * Record a successful sync run in term meta.
	 *
	 * Updates last_synced, increments sync_count, sets sync_status = 'success',
	 * and clears sync_errors.
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $term_id     Term ID.
	 * @return void
	 */
	private function record_success( string $source_type, int $term_id ): void {
		$data = $this->get_term_meta_data( $source_type, $term_id );
		if ( ! $data ) {
			return;
		}

		$data['last_synced']  = time();
		$data['sync_count']   = (int) ( $data['sync_count'] ?? 0 ) + 1;
		$data['sync_status']  = 'success';
		$data['sync_errors']  = array();

		update_term_meta( $term_id, $this->meta_key( $source_type ), wp_json_encode( $data ) );
	}

	/**
	 * Append an error to term meta sync_errors (max 5 most recent).
	 *
	 * Sets sync_status = 'failed'.
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $term_id     Term ID.
	 * @param string $error       Human-readable error message.
	 * @param string $code        Error code string.
	 * @return void
	 */
	private function record_error( string $source_type, int $term_id, string $error, string $code = '' ): void {
		$data = $this->get_term_meta_data( $source_type, $term_id );
		if ( ! $data ) {
			return;
		}

		$entry = array(
			'timestamp' => time(),
			'error'     => $error,
			'code'      => $code,
		);

		$errors              = $data['sync_errors'] ?? array();
		$errors[]            = $entry;
		$data['sync_errors'] = array_slice( $errors, -5 ); // Keep max 5.
		$data['sync_status'] = 'failed';

		update_term_meta( $term_id, $this->meta_key( $source_type ), wp_json_encode( $data ) );
	}

	/**
	 * Set a 'once' rule's enabled flag to false after it fires.
	 *
	 * The rule remains visible in the UI with the toggle off so the user
	 * can see it ran and optionally re-enable it.
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $term_id     Term ID.
	 * @param int    $rule_index  Index of the rule to disable.
	 * @return void
	 */
	private function disable_once_rule( string $source_type, int $term_id, int $rule_index ): void {
		$data = $this->get_term_meta_data( $source_type, $term_id );
		if ( ! $data || ! isset( $data['sync_rules'][ $rule_index ] ) ) {
			return;
		}

		$data['sync_rules'][ $rule_index ]['enabled'] = false;
		update_term_meta( $term_id, $this->meta_key( $source_type ), wp_json_encode( $data ) );
	}
}
