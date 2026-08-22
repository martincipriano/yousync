<?php
declare(strict_types=1);
/**
 * Sync runner.
 *
 * Executes one sync rule end-to-end: reads the channel config, calls the
 * YouTube API, imports videos/playlists/channel data, and writes results
 * back to buoyvs_channel_config.
 *
 * @package Buoy_Video_Sync
 */

namespace Buoy_Video_Sync;

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
	 * Seconds before a sync lock is considered stale and ignored.
	 *
	 * Matches the progress-transient TTL. A crashed run self-clears after this.
	 */
	private const STALE_LOCK_SECONDS = 1800;

	/**
	 * Index of the rule currently being executed.
	 * Set at the start of run() so record_success/record_error can write to the correct rule.
	 *
	 * @var int
	 */
	private int $current_rule_index = 0;

	/**
	 * Unix timestamp recorded at the start of a sync run.
	 * Used to compute run duration for the history log.
	 *
	 * @var int
	 */
	private int $run_started_at = 0;

	/**
	 * Errors accumulated during the current sync run (non-terminal).
	 * Flushed into the history entry at the end of the run.
	 *
	 * @var array
	 */
	private array $run_errors = array();

	/**
	 * Number of items (videos/playlists/channels) processed in the current run.
	 * Set by each action handler; written into the history entry.
	 *
	 * @var int
	 */
	private int $current_run_count = 0;

	/**
	 * Whether the current run is the single option-based channel (Channels page).
	 *
	 * @var bool
	 */
	private bool $is_option_channel = false;

	/**
	 * In-memory copy of the source data when running an option-based channel.
	 * Returned by get_channel_data(). Kept in sync after
	 * every save_source_data() call so subsequent reads within the same run see
	 * the latest state.
	 *
	 * @var array|null
	 */
	private ?array $source_data_override = null;

	/**
	 * YouTube API wrapper.
	 *
	 * @var YouTube_API
	 */
	private YouTube_API $api;

	/**
	 * Video importer.
	 *
	 * @var Video_Importer
	 */
	private Video_Importer $importer;

	/**
	 * Constructor.
	 *
	 * @param YouTube_API    $api      YouTube API wrapper.
	 * @param Video_Importer $importer Video importer.
	 */
	public function __construct( YouTube_API $api, Video_Importer $importer ) {
		$this->api      = $api;
		$this->importer = $importer;
	}

	// -------------------------------------------------------------------------
	// Public entry point
	// -------------------------------------------------------------------------

	/**
	 * Execute a sync rule.
	 *
	 * Called by Sync_Scheduler::dispatch_config_sync() when a WP Cron event fires.
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

		// Concurrency guard (option-based channel): stop a second cron tick or a
		// manual trigger from running the same rule while it is already in flight.
		// A dedicated transient is used rather than the sync_status field, which
		// the Channels page pre-sets to 'syncing' before the cron fires.
		$lock_key = null;
		if ( $this->is_option_channel ) {
			$lock_key = 'buoyvs_lock_' . $rule_index;
			if ( get_transient( $lock_key ) ) {
				return;
			}
			set_transient( $lock_key, time(), self::STALE_LOCK_SECONDS );
		}

		$this->current_rule_index = $rule_index;

		$action   = $rule['action'] ?? '';
		$schedule = $rule['schedule'] ?? 'once';

		// "Once" runs may import an entire channel back-catalog in a single
		// execution — lift PHP limits so a large import completes instead of
		// timing out mid-run. Recurring runs stay within normal cron limits since
		// each tick only needs to catch up on what's new since the last run.
		if ( 'once' === $schedule ) {
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged -- Required so a large back-catalog import completes in one run instead of timing out.
			}
			wp_raise_memory_limit( 'admin' );
		}

		$this->mark_syncing( $source_type, $term_id, $rule_index );
		$this->write_progress( 0, 0 ); // Reset any stale progress from a previous run.

		$syncing_cleared = false;
		register_shutdown_function(
			function () use ( $source_type, $term_id, $rule_index, &$syncing_cleared ) {
				if ( $syncing_cleared ) {
					return;
				}
				$error = error_get_last();
				if ( $error && in_array( $error['type'], array( E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE ), true ) ) {
					$this->clear_syncing( $source_type, $term_id, $rule_index );
				}
			}
		);

		try {
			switch ( $action ) {
				// ---- Video sync ----
				case 'videos_sync_new':
					$this->handle_videos_sync_new( $source_type, $term_id, $rule );
					break;

				// ---- Channel ----
				case 'channel_sync_new':
					$this->handle_channel_sync_new( $term_id, $rule );
					break;

				// ---- Playlists from channel ----
				case 'playlists_sync_new':
					$this->handle_playlists_sync( $term_id, $rule, $action );
					break;

				default:
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( "Buoy Video Sync: unknown action '{$action}' (term {$term_id}, rule {$rule_index})." );
					return;
			}

			$this->record_success( $source_type, $term_id );

		} catch ( \Exception $e ) {
			$this->record_error( $source_type, $term_id, $e->getMessage(), 'exception' );
		} finally {
			$this->clear_syncing( $source_type, $term_id, $rule_index );

			// Auto-disable "once" rules here (inside finally) so it runs even on
			// fatal errors caught by the shutdown function — preventing the rule
			// from firing a second time if the cron event somehow re-queues.
			// Recurring rules stay enabled so the next scheduled tick still fires.
			if ( 'once' === ( $rule['schedule'] ?? 'once' ) ) {
				$this->disable_once_rule( $source_type, $term_id, $rule_index );
			}

			if ( null !== $lock_key ) {
				delete_transient( $lock_key );
			}

			$syncing_cleared = true;
		}
	}

	/**
	 * Execute a sync rule for an option-based channel (Channels Page / buoyvs_channel_config).
	 *
	 * Sets up the source data override so that get_channel_data() and
	 * save_source_data() transparently read/write wp_options instead of
	 * term meta, then delegates to the standard run() method with term_id = 0.
	 *
	 * Called by Sync_Scheduler::dispatch_config_sync() when a
	 * buoyvs_channel_config_sync_rule cron event fires.
	 *
	 * @param int $rule_index 0-based rule index within the channel's sync_rules.
	 * @return void
	 */
	public function run_config_channel( int $rule_index ): void {
		$ch_data = buoyvs_get_channel_config();

		if ( empty( $ch_data['youtube_id'] ) ) {
			return;
		}

		// Ensure channel_id is present (option stores as youtube_id).
		$ch_data['channel_id'] = $ch_data['youtube_id'];

		$this->is_option_channel    = true;
		$this->source_data_override = $ch_data;

		$this->run( 'channel', 0, $rule_index );

		$this->is_option_channel    = false;
		$this->source_data_override = null;
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
		$destination_post_type = $rule['destination_post_type'] ?? '';
		$post_status            = $rule['post_status'] ?? 'publish';

		if ( $this->invalid_destination_post_type( $destination_post_type ) ) {
			$this->record_error( $source_type, $term_id, $this->invalid_post_type_message( $destination_post_type ), 'invalid_post_type' );
			return;
		}

		if ( 'channel' === $source_type ) {
			// Channels: need to get the uploads playlist ID first.
			$meta = $this->get_channel_data();
			if ( ! $meta || empty( $meta['channel_id'] ) ) {
				throw new \RuntimeException( 'Channel ID not found in term meta.' );
			}

			$channel_data = $this->api->get_channel_data( $meta['channel_id'] );
			if ( is_wp_error( $channel_data ) ) {
				$this->record_error( $source_type, $term_id, $channel_data->get_error_message(), $channel_data->get_error_code() );
				return;
			}

			// Opportunistically refresh channel metadata (title, subscribers, images, etc.)
			// while we already have the API response in hand.
			$this->refresh_channel_meta( $term_id, $channel_data );

			$playlist_id = $channel_data['uploads_playlist_id'] ?? '';
			if ( empty( $playlist_id ) ) {
				throw new \RuntimeException( esc_html( "Could not retrieve uploads playlist ID for channel '{$meta['channel_id']}'." ) );
			}
		} else {
			// Playlists: playlist ID is stored directly in term meta.
			$meta = $this->get_channel_data();
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

		// Split into new vs already-imported. Dedup is scoped to the destination
		// post type so the same video can be imported into more than one post type.
		$existing_ids = $this->importer->get_imported_video_ids( $destination_post_type );
		$new_ids      = array_values( array_diff( $all_ids, $existing_ids ) );

		if ( empty( $new_ids ) ) {
			return; // All videos already imported.
		}

		$max = (int) ( $rule['max_videos'] ?? 0 );

		// Batch-fetch full video details and import.
		$imported                = $this->batch_fetch_and_import( $new_ids, $source_type, $term_id, $destination_post_type, $max, $post_status );
		$this->current_run_count = $imported;

		// Warn when candidates were available but nothing could be saved.
		if ( 0 === $imported ) {
			$candidate_count = count( $new_ids );
			$message         = sprintf(
				/* translators: %d: number of candidate videos */
				_n(
					'No videos were imported. %d candidate was fetched from the API but could not be saved.',
					'No videos were imported. %d candidates were fetched from the API but none could be saved.',
					$candidate_count,
					'buoy-video-sync'
				),
				$candidate_count
			);
			$this->accumulate_error( $source_type, $term_id, $message, 'no_results' );
		}
	}

	// -------------------------------------------------------------------------
	// Phase 2 handlers
	// -------------------------------------------------------------------------

	/**
	 * Handle the channel_sync_new action.
	 *
	 * Fetches fresh channel data from the API and creates a WordPress post for the channel.
	 * If a post for this channel already exists (deduped by _buoyvs_channel_post), nothing
	 * is done — channel update actions handle refreshing an existing post.
	 *
	 * @param int   $term_id Channel term ID (0 for option-based channels).
	 * @param array $rule    Sync rule array.
	 * @return void
	 * @throws \RuntimeException On missing channel ID.
	 */
	private function handle_channel_sync_new( int $term_id, array $rule ): void {
		$data = $this->get_channel_data();
		if ( ! $data || empty( $data['channel_id'] ) ) {
			throw new \RuntimeException( 'Channel ID not found in term meta.' );
		}

		$channel_id = $data['channel_id'];

		$destination_post_type = $rule['destination_post_type'] ?? '';
		$post_status            = $rule['post_status'] ?? 'publish';

		if ( $this->invalid_destination_post_type( $destination_post_type ) ) {
			$this->record_error( 'channel', $term_id, $this->invalid_post_type_message( $destination_post_type ), 'invalid_post_type' );
			return;
		}

		// Skip if this channel is already imported into this post type.
		$existing_post_id = $this->importer->find_post_by_channel_id( $channel_id, $destination_post_type );
		if ( $existing_post_id ) {
			return;
		}

		$fresh = $this->api->get_channel_data( $channel_id );
		if ( is_wp_error( $fresh ) ) {
			$this->record_error( 'channel', $term_id, $fresh->get_error_message(), $fresh->get_error_code() );
			return;
		}

		$this->write_progress( 0, 1 );
		$result = $this->importer->import_channel( $fresh, $channel_id, $destination_post_type, $post_status );

		if ( is_wp_error( $result ) ) {
			$this->accumulate_error( 'channel', $term_id, $result->get_error_message(), $result->get_error_code() );
		} else {
			$this->current_run_count = 1;
		}
		$this->write_progress( 1, 1 );
	}

	/**
	 * Handle the playlists_sync_new action.
	 *
	 * Creates a new post for each playlist not yet imported into the destination
	 * post type.
	 *
	 * @param int    $term_id Channel term ID (0 for option-based channels).
	 * @param array  $rule    Sync rule array.
	 * @param string $action  Action slug (always playlists_sync_new).
	 * @return void
	 * @throws \RuntimeException On missing channel ID.
	 */
	private function handle_playlists_sync( int $term_id, array $rule, string $action ): void {
		$destination_post_type = $rule['destination_post_type'] ?? '';
		$post_status            = $rule['post_status'] ?? 'publish';

		$data = $this->get_channel_data();
		if ( ! $data || empty( $data['channel_id'] ) ) {
			throw new \RuntimeException( 'Channel ID not found.' );
		}

		// Validate the destination post type before spending an API call.
		if ( $this->invalid_destination_post_type( $destination_post_type ) ) {
			$this->record_error( 'channel', $term_id, $this->invalid_post_type_message( $destination_post_type ), 'invalid_post_type' );
			return;
		}

		$channel_id = $data['channel_id'];
		$playlists  = $this->api->get_channel_playlists( $channel_id );

		if ( is_wp_error( $playlists ) ) {
			$this->record_error( 'channel', $term_id, $playlists->get_error_message(), $playlists->get_error_code() );
			return;
		}
		$max        = (int) ( $rule['max_videos'] ?? 0 ); // max_videos field doubles as max_playlists.
		$processed  = 0;
		$total_pl   = $max > 0 ? min( $max, count( $playlists ) ) : count( $playlists );
		$scanned_pl = 0;

		$this->write_progress( 0, $total_pl );

		foreach ( $playlists as $playlist_data ) {
			if ( $max > 0 && $processed >= $max ) {
				break;
			}

			if ( empty( $playlist_data['playlist_id'] ) ) {
				continue;
			}

			// Create if missing in this post type.
			$existing_post_id = $this->importer->find_post_by_playlist_id( $playlist_data['playlist_id'], $destination_post_type );
			if ( ! $existing_post_id ) {
				$this->importer->import_playlist( $playlist_data, $channel_id, $destination_post_type, $post_status );
				++$processed;
			}
			++$scanned_pl;
			$this->write_progress( min( $scanned_pl, $total_pl ), $total_pl );
		}

		$this->current_run_count = $processed;
	}

	// -------------------------------------------------------------------------
	// Batch fetch + import
	// -------------------------------------------------------------------------

	/**
	 * Fetch video details in batches of 50 and import them.
	 *
	 * @param string[] $video_ids   Video IDs to process.
	 * @param string   $source_type 'channel' or 'playlist'.
	 * @param int      $term_id     Source term ID.
	 * @return int Number of videos successfully imported.
	 */
	private function batch_fetch_and_import(
		array $video_ids,
		string $source_type,
		int $term_id,
		string $destination_post_type = '',
		int $max = 0,
		string $post_status = 'publish'
	): int {
		$cap     = $max > 0 ? $max : PHP_INT_MAX;
		$chunks  = array_chunk( $video_ids, 50 );
		$total   = min( $cap, count( $video_ids ) );
		$scanned = 0;
		$imported = 0;

		$this->write_progress( 0, $total );

		foreach ( $chunks as $chunk ) {
			$videos = $this->api->get_videos_by_ids( $chunk );

			if ( is_wp_error( $videos ) ) {
				$this->accumulate_error( $source_type, $term_id, $videos->get_error_message(), $videos->get_error_code() );
				$scanned += count( $chunk );
				$this->write_progress( min( $scanned, $total ), $total );
				continue;
			}

			foreach ( $videos as $video_data ) {
				if ( $imported >= $cap ) {
					return $imported;
				}

				$result = $this->importer->import( $video_data, $source_type, $term_id, $destination_post_type, $post_status );

				if ( is_wp_error( $result ) ) {
					$this->accumulate_error( $source_type, $term_id, $result->get_error_message(), $result->get_error_code() );
				} else {
					++$imported;
				}
				++$scanned;
				$this->write_progress( min( $scanned, $total ), $total );
			}
		}

		return $imported;
	}

	// -------------------------------------------------------------------------
	// Lookup helpers
	// -------------------------------------------------------------------------

	/**
	 * Whether a rule's destination post type is unusable (empty or not registered).
	 *
	 * sync_new actions must refuse to run rather than let wp_insert_post() fall
	 * back to the default 'post' type or create posts under an orphaned type.
	 *
	 * @param string $post_type Destination post type from the rule.
	 * @return bool True when the post type cannot be used.
	 */
	private function invalid_destination_post_type( string $post_type ): bool {
		return '' === $post_type || ! post_type_exists( $post_type );
	}

	/**
	 * Build the user-facing error for an invalid destination post type.
	 *
	 * @param string $post_type The offending post type value.
	 * @return string
	 */
	private function invalid_post_type_message( string $post_type ): string {
		if ( '' === $post_type ) {
			return __( 'No destination post type is set for this rule. Choose a post type so synced items have somewhere to be saved.', 'buoy-video-sync' );
		}
		return sprintf(
			/* translators: %s: post type slug */
			__( 'The destination post type "%s" is no longer registered. Choose a valid post type for this rule.', 'buoy-video-sync' ),
			$post_type
		);
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
		$data = $this->get_channel_data();

		if ( ! $data ) {
			return null;
		}

		return $data['sync_rules'][ $rule_index ] ?? null;
	}

	/**
	 * Merge fresh channel API data into the channel's term meta.
	 *
	 * Called as a side effect of handle_videos_sync_new() so that channel
	 * metadata (title, subscribers, profile picture, banner) stays up to date
	 * even when no explicit channel_update rule has been configured.
	 *
	 * @param int   $term_id      Channel term ID.
	 * @param array $channel_data Data returned by YouTube_API::get_channel_data().
	 * @return void
	 */
	private function refresh_channel_meta( int $term_id, array $channel_data ): void {
		$data = $this->get_channel_data();
		if ( ! $data ) {
			return;
		}

		$fields = array( 'channel_title', 'channel_description', 'subscriber_count', 'video_count', 'profile_picture', 'banner_image' );
		foreach ( $fields as $field ) {
			if ( isset( $channel_data[ $field ] ) ) {
				$data[ $field ] = $channel_data[ $field ];
			}
		}

		$data['etag'] = $channel_data['etag'] ?? ( $data['etag'] ?? '' );
		$this->save_source_data( 'channel', $term_id, $data );
	}

	/**
	 * Return the current channel's source data.
	 *
	 * The runner syncs the single option-based channel, so this returns the
	 * in-memory source_data_override that run_config_channel() loaded from
	 * buoyvs_channel_config. (Params retained for the call signature.)
	 *
	 * @return array|null Channel data array, or null if not set.
	 */
	private function get_channel_data(): ?array {
		return $this->source_data_override;
	}

	/**
	 * Persist the channel's source data to buoyvs_channel_config and refresh the
	 * in-memory override so later reads in the same run see the update.
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $term_id     Term ID (unused; retained for the call signature).
	 * @param array  $data        Decoded data to persist.
	 * @return void
	 */
	private function save_source_data( string $source_type, int $term_id, array $data ): void {
		$this->update_option_channel( $data );
		$this->source_data_override = $data;
	}

	/**
	 * Write the updated single channel back to buoyvs_channel_config (flat).
	 *
	 * Preserves the youtube_id key (the option stores youtube_id; the runner
	 * temporarily adds channel_id as an alias — we don't store it).
	 *
	 * @param array $data Updated channel data.
	 * @return void
	 */
	private function update_option_channel( array $data ): void {
		if ( ! isset( $data['youtube_id'] ) && isset( $data['channel_id'] ) ) {
			$data['youtube_id'] = $data['channel_id'];
		}

		update_option( 'buoyvs_channel_config', $data );
	}

	// -------------------------------------------------------------------------
	// Status recording
	// -------------------------------------------------------------------------

	/**
	 * Append a run entry to the channel's per-channel sync history.
	 *
	 * Writes ONE entry per run — called only from record_success() and terminal
	 * record_error() calls. All mid-run errors are already in $this->run_errors.
	 * Only runs for option-based channels (source_data_override is set).
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $term_id     Term ID.
	 * @param bool   $has_error   Whether the run produced any error (terminal or accumulated).
	 * @return void
	 */
	private function append_history( string $source_type, int $term_id, bool $has_error ): void {
		if ( null === $this->source_data_override ) {
			return;
		}
		$youtube_id = $this->source_data_override['youtube_id'] ?? '';
		if ( ! $youtube_id ) {
			return;
		}

		$data = $this->get_channel_data();
		$rule = $data['sync_rules'][ $this->current_rule_index ] ?? array();

		Sync_History::append( $youtube_id, array(
			'timestamp'             => time(),
			'rule_action'           => $rule['action'] ?? '',
			'rule_index'            => $this->current_rule_index,
			'duration'              => max( 0, time() - $this->run_started_at ),
			'has_error'             => $has_error,
			'errors'                => $this->run_errors,
			'items_count'           => $this->current_run_count,
			'destination_post_type' => $rule['destination_post_type'] ?? '',
		) );
	}

	/**
	 * Accumulate a non-terminal error during the current sync run.
	 *
	 * Pushes the error to $run_errors (flushed into one history entry at the end)
	 * and updates the rule's sync_errors meta for quick UI display.
	 * Use this for errors inside batch loops where the run continues after the error.
	 * For terminal errors where the run stops, use record_error() instead.
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $term_id     Term ID.
	 * @param string $error       Human-readable error message.
	 * @param string $code        Error code string.
	 * @return void
	 */
	private function accumulate_error( string $source_type, int $term_id, string $error, string $code = '' ): void {
		$this->run_errors[] = array(
			'timestamp' => time(),
			'error'     => $error,
			'code'      => $code,
		);

		$data = $this->get_channel_data();
		if ( ! $data || ! isset( $data['sync_rules'][ $this->current_rule_index ] ) ) {
			return;
		}

		$rule                = &$data['sync_rules'][ $this->current_rule_index ];
		$errors              = $rule['sync_errors'] ?? array();
		$errors[]            = array( 'timestamp' => time(), 'error' => $error, 'code' => $code );
		$rule['sync_errors'] = array_slice( $errors, -5 );
		$rule['sync_status'] = 'failed';
		unset( $rule );

		$this->save_source_data( $source_type, $term_id, $data );
	}

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
		$data = $this->get_channel_data();
		if ( ! $data || ! isset( $data['sync_rules'][ $this->current_rule_index ] ) ) {
			return;
		}

		$rule                = &$data['sync_rules'][ $this->current_rule_index ];
		$rule['last_synced'] = time();
		$rule['sync_count']  = (int) ( $rule['sync_count'] ?? 0 ) + 1;
		$rule['sync_status'] = 'success';
		$rule['sync_errors'] = array();
		unset( $rule );

		$this->save_source_data( $source_type, $term_id, $data );
		$this->append_history( $source_type, $term_id, ! empty( $this->run_errors ) );
	}

	/**
	 * Record a terminal sync error — the run stops here.
	 *
	 * Accumulates the error (updates rule meta + run_errors buffer), then
	 * writes the single history entry for this run.
	 * For non-terminal errors inside batch loops, use accumulate_error() instead.
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $term_id     Term ID.
	 * @param string $error       Human-readable error message.
	 * @param string $code        Error code string.
	 * @return void
	 */
	private function record_error( string $source_type, int $term_id, string $error, string $code = '' ): void {
		$this->accumulate_error( $source_type, $term_id, $error, $code );
		$this->append_history( $source_type, $term_id, true );
	}

	/**
	 * Mark a sync rule as actively syncing.
	 *
	 * Sets sync_status = 'syncing' and records sync_started_at timestamp.
	 * The timestamp is used to detect stale locks if the process is killed
	 * at the OS level (beyond PHP's shutdown handler reach).
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $term_id     Term ID.
	 * @param int    $rule_index  Index of the rule being executed.
	 * @return void
	 */
	private function mark_syncing( string $source_type, int $term_id, int $rule_index ): void {
		$data = $this->get_channel_data();
		if ( ! $data || ! isset( $data['sync_rules'][ $rule_index ] ) ) {
			return;
		}

		$rule                    = &$data['sync_rules'][ $rule_index ];
		$rule['sync_status']     = 'syncing';
		$rule['sync_started_at'] = time();
		unset( $rule );

		$this->run_started_at    = time();
		$this->run_errors        = array();
		$this->current_run_count = 0;

		$this->save_source_data( $source_type, $term_id, $data );
	}

	/**
	 * Clear the syncing lock from a sync rule.
	 *
	 * Called in the finally block of run() and by the register_shutdown_function
	 * callback on PHP fatal errors. Unsets sync_started_at and resets
	 * sync_status to '' if it is still 'syncing' (i.e. neither record_success()
	 * nor record_error() ran to completion).
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $term_id     Term ID.
	 * @param int    $rule_index  Index of the rule being executed.
	 * @return void
	 */
	private function clear_syncing( string $source_type, int $term_id, int $rule_index ): void {
		$data = $this->get_channel_data();
		if ( ! $data || ! isset( $data['sync_rules'][ $rule_index ] ) ) {
			return;
		}

		$rule = &$data['sync_rules'][ $rule_index ];
		unset( $rule['sync_started_at'] );
		if ( 'syncing' === ( $rule['sync_status'] ?? '' ) ) {
			$rule['sync_status'] = '';
		}
		unset( $rule );

		$this->save_source_data( $source_type, $term_id, $data );
	}

	/**
	 * Set a "once" rule's enabled flag to false after it fires.
	 *
	 * "Once" rules run a single time; the rule remains visible in the UI with
	 * the toggle off so the user can see it ran and re-enable it to run again.
	 * Recurring rules never reach this method — they stay enabled between ticks.
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $term_id     Term ID.
	 * @param int    $rule_index  Index of the rule to disable.
	 * @return void
	 */
	private function disable_once_rule( string $source_type, int $term_id, int $rule_index ): void {
		$data = $this->get_channel_data();
		if ( ! $data || ! isset( $data['sync_rules'][ $rule_index ] ) ) {
			return;
		}

		$data['sync_rules'][ $rule_index ]['enabled'] = false;
		$this->save_source_data( $source_type, $term_id, $data );
		// Progress transient is intentionally left to expire — the UI reads it
		// in the onSyncDone response to briefly show the final count.
	}

	// -------------------------------------------------------------------------
	// Progress tracking
	// -------------------------------------------------------------------------

	/**
	 * Write sync progress to a transient for UI polling.
	 *
	 * Only tracked for the option-based channel (is_option_channel is true).
	 * The transient expires after 30 minutes — a stale lock guard.
	 *
	 * @param int $current Items scanned so far.
	 * @param int $total   Total items to scan.
	 * @return void
	 */
	private function write_progress( int $current, int $total ): void {
		if ( ! $this->is_option_channel ) {
			return;
		}
		set_transient(
			'buoyvs_prog_' . $this->current_rule_index,
			array( 'current' => $current, 'total' => $total ),
			1800
		);
	}

}
