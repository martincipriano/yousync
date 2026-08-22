<?php
declare(strict_types=1);
/**
 * Video importer.
 *
 * Creates and updates synced video posts (in the per-rule destination post type) from normalised YouTube video data.
 * Video and playlist thumbnails are stored as the YouTube-served URLs the Data
 * API returns. Channel profile pictures and banners are downloaded into the
 * media library and attached to the channel post. The post_thumbnail_html
 * filter in the main plugin file serves the image when no featured image is
 * explicitly set by the user.
 *
 * @package Buoy_Video_Sync
 */

namespace Buoy_Video_Sync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Video_Importer
 *
 * Handles creation and updating of synced video posts.
 */
class Video_Importer {

	/**
	 * Thumbnail size preference order (largest first).
	 */
	private const THUMBNAIL_SIZE_PRIORITY = array( 'maxres', 'standard', 'high', 'medium', 'default' );

	// -------------------------------------------------------------------------
	// Public methods
	// -------------------------------------------------------------------------

	/**
	 * Import a YouTube video as a new WordPress post.
	 *
	 * Assumes the caller has already confirmed the video does not exist.
	 *
	 * @param array  $video_data                  Normalised video data from YouTube_API::get_videos_by_ids().
	 * @param string $source_type                 'channel' or 'playlist'.
	 * @param int    $source_term_id              WordPress term ID of the source channel/playlist.
	 * @param string $post_type                   Destination post type. Required and validated by the caller (Sync_Runner); an empty/invalid value is rejected before reaching here.
	 * @param string $post_status                 Post status to create the post as. Defaults to 'publish'.
	 * @return int|\WP_Error Post ID on success, WP_Error on failure.
	 */
	public function import( array $video_data, string $source_type, int $source_term_id, string $post_type = '', string $post_status = 'publish' ): int|\WP_Error {
		// 1. Create the post.
		$post_id = wp_insert_post(
			array(
				'post_title'  => sanitize_text_field( $video_data['title'] ?? '' ),
				'post_type'   => $post_type,
				'post_status' => $post_status,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// 2. Save all internal _buoyvs_* meta keys.
		$this->save_all_meta( $post_id, $video_data, $source_type, $source_term_id );

		return $post_id;
	}

	/**
	 * Return the YouTube video IDs already imported into a given post type.
	 *
	 * Used by videos_sync_new dedup. Scoping by post type lets the same video
	 * be imported into more than one post type by separate rules. Includes
	 * 'trash' so a deliberately trashed video is not silently re-imported into
	 * the same post type.
	 *
	 * One indexed SQL query — no per-post meta reads.
	 *
	 * @param string $post_type Destination post type. Empty = match any post type.
	 * @return string[] Imported YouTube video IDs.
	 */
	public function get_imported_video_ids( string $post_type = '' ): array {
		global $wpdb;
		$statuses     = array( 'publish', 'draft', 'private', 'trash' );
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		// $placeholders is a generated list of %s tokens (one per status) bound through
		// $wpdb->prepare() below; the values are a fixed, non-user list.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		if ( '' !== $post_type ) {
			$sql = $wpdb->prepare(
				"SELECT pm.meta_value
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = '_buoyvs_video_id'
				   AND p.post_type = %s
				   AND p.post_status IN ( $placeholders )",
				array_merge( array( $post_type ), $statuses )
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT pm.meta_value
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = '_buoyvs_video_id'
				   AND p.post_status IN ( $placeholders )",
				$statuses
			);
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		// $sql is built via $wpdb->prepare() in both branches above; a dedup lookup must
		// reflect just-imported posts in the same run, so caching is not applicable.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col( $sql );
		return array_values( array_filter( array_unique( $ids ) ) );
	}

	// -------------------------------------------------------------------------
	// Private methods
	// -------------------------------------------------------------------------

	/**
	 * Save all video data as individual post meta keys.
	 *
	 * Each field is stored as its own meta key for direct WP_Query filtering.
	 *
	 * @param int    $post_id     Post ID.
	 * @param array  $video_data  Normalised video data from YouTube_API::get_videos_by_ids().
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $source_id   WordPress term ID of the source.
	 * @return void
	 */
	private function save_all_meta( int $post_id, array $video_data, string $source_type, int $source_id ): void {
		$thumbnails = array();
		foreach ( $video_data['thumbnails'] ?? array() as $size => $thumb ) {
			$thumbnails[ $size ] = array(
				'url'    => $thumb['url'],
				'width'  => $thumb['width'],
				'height' => $thumb['height'],
			);
		}

		update_post_meta( $post_id, '_buoyvs_video_id',             $video_data['video_id'] );
		update_post_meta( $post_id, '_buoyvs_video_url',            'https://www.youtube.com/watch?v=' . $video_data['video_id'] );
		update_post_meta( $post_id, '_buoyvs_channel_id',           $video_data['channel_id'] ?? '' );
		update_post_meta( $post_id, '_buoyvs_channel_title',        $video_data['channel_title'] ?? '' );
		update_post_meta( $post_id, '_buoyvs_etag',                 $video_data['etag'] ?? '' );
		update_post_meta( $post_id, '_buoyvs_source_type',          $source_type );
		update_post_meta( $post_id, '_buoyvs_source_id',            $source_id );
		update_post_meta( $post_id, '_buoyvs_original_title',       $video_data['title'] ?? '' );
		update_post_meta( $post_id, '_buoyvs_original_description', $video_data['description'] ?? '' );
		update_post_meta( $post_id, '_buoyvs_published_at',         $video_data['published_at'] ?? '' );
		update_post_meta( $post_id, '_buoyvs_duration_seconds',     (int) ( $video_data['duration_seconds'] ?? 0 ) );
		update_post_meta( $post_id, '_buoyvs_view_count',           (int) ( $video_data['view_count'] ?? 0 ) );
		update_post_meta( $post_id, '_buoyvs_like_count',           (int) ( $video_data['like_count'] ?? 0 ) );
		update_post_meta( $post_id, '_buoyvs_comment_count',        (int) ( $video_data['comment_count'] ?? 0 ) );
		$best_thumb = static::get_best_thumbnail( $thumbnails );
		update_post_meta( $post_id, '_buoyvs_thumbnails',           $thumbnails );
		update_post_meta( $post_id, '_buoyvs_thumbnail_url',        $best_thumb['url'] ?? '' );
		update_post_meta( $post_id, '_buoyvs_last_synced',          time() );

		/**
		 * Fires after a video's metadata has been written during sync.
		 *
		 * Fires on both initial import and every re-sync, after all meta is set,
		 * so consumers have full context to identify or filter the synced video.
		 *
		 * @param int    $post_id     Video post ID.
		 * @param array  $video_data  Video data from the YouTube API.
		 * @param string $source_type Sync source type: 'channel', 'playlist', or 'video'.
		 * @param int    $source_id   Source term ID the video was synced from.
		 */
		do_action( 'buoyvs_video_synced', $post_id, $video_data, $source_type, $source_id );
	}

	// -------------------------------------------------------------------------
	// Playlist import methods
	// -------------------------------------------------------------------------

	/**
	 * Find an existing post by its YouTube playlist ID.
	 *
	 * @param string $playlist_id YouTube playlist ID.
	 * @return int Post ID, or 0 if not found.
	 */
	public function find_post_by_playlist_id( string $playlist_id, string $post_type = '' ): int {
		$query = new \WP_Query(
			array(
				'post_type'      => $post_type ?: 'any',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Lookup by the _buoyvs_* ID meta is required to match a YouTube item; there is no non-meta alternative.
				'meta_query'     => array(
					array(
						'key'   => '_buoyvs_playlist_id',
						'value' => $playlist_id,
					),
				),
			)
		);

		return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
	}

	/**
	 * Import a YouTube playlist as a new WordPress post.
	 *
	 * @param array  $playlist_data             Normalised playlist data from YouTube_API::get_channel_playlists().
	 * @param string $channel_id                YouTube channel ID.
	 * @param string $post_type                 Destination post type.
	 * @param string $post_status               Post status to create the post as. Defaults to 'publish'.
	 * @return int|\WP_Error Post ID on success, WP_Error on failure.
	 */
	public function import_playlist( array $playlist_data, string $channel_id, string $post_type, string $post_status = 'publish' ): int|\WP_Error {
		$post_id = wp_insert_post(
			array(
				'post_title'  => sanitize_text_field( $playlist_data['playlist_title'] ?: $playlist_data['playlist_id'] ),
				'post_type'   => $post_type,
				'post_status' => $post_status,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$this->save_playlist_meta( $post_id, $playlist_data, $channel_id );

		return $post_id;
	}

	/**
	 * Save playlist data as individual post meta keys.
	 *
	 * @param int    $post_id       Post ID.
	 * @param array  $playlist_data Normalised playlist data.
	 * @param string $channel_id    YouTube channel ID.
	 * @return void
	 */
	private function save_playlist_meta( int $post_id, array $playlist_data, string $channel_id ): void {
		update_post_meta( $post_id, '_buoyvs_playlist_id',          $playlist_data['playlist_id'] );
		update_post_meta( $post_id, '_buoyvs_channel_id',           $channel_id );
		update_post_meta( $post_id, '_buoyvs_playlist_title',       $playlist_data['playlist_title'] ?? '' );
		update_post_meta( $post_id, '_buoyvs_playlist_description', $playlist_data['playlist_description'] ?? '' );
		update_post_meta( $post_id, '_buoyvs_playlist_video_count', (int) ( $playlist_data['playlist_video_count'] ?? 0 ) );
		update_post_meta( $post_id, '_buoyvs_playlist_thumbnail',   $playlist_data['thumbnail_url'] ?? '' );
		update_post_meta( $post_id, '_buoyvs_etag',                 $playlist_data['etag'] ?? '' );
		update_post_meta( $post_id, '_buoyvs_last_synced',          time() );

		/**
		 * Fires after a playlist's metadata has been written during sync.
		 *
		 * Fires on both initial import and every re-sync, after all meta is set.
		 *
		 * @param int    $post_id       Playlist post ID.
		 * @param array  $playlist_data Playlist data from the YouTube API.
		 * @param string $channel_id    Source channel ID the playlist belongs to.
		 */
		do_action( 'buoyvs_playlist_synced', $post_id, $playlist_data, $channel_id );
	}

	// -------------------------------------------------------------------------
	// Channel import methods
	// -------------------------------------------------------------------------

	/**
	 * Find an existing post by its YouTube channel ID.
	 *
	 * Uses _buoyvs_channel_post as the dedup key (distinct from _buoyvs_channel_id,
	 * which stores the source channel ID on video/playlist posts).
	 *
	 * @param string $channel_id YouTube channel ID.
	 * @return int Post ID, or 0 if not found.
	 */
	public function find_post_by_channel_id( string $channel_id, string $post_type = '' ): int {
		$query = new \WP_Query(
			array(
				'post_type'      => $post_type ?: 'any',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Lookup by the _buoyvs_* ID meta is required to match a YouTube item; there is no non-meta alternative.
				'meta_query'     => array(
					array(
						'key'   => '_buoyvs_channel_post',
						'value' => $channel_id,
					),
				),
			)
		);

		return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
	}

	/**
	 * Import a YouTube channel as a new WordPress post.
	 *
	 * Deduplication key: _buoyvs_channel_post. The channel profile picture and
	 * banner are sideloaded into the media library, attached to the channel post,
	 * and their local URLs stored in meta. The profile picture is served as the
	 * featured image on the frontend via the post_thumbnail_html filter (a
	 * user-set featured image takes precedence).
	 *
	 * @param array  $channel_data              Channel data from YouTube_API::get_channel_data().
	 * @param string $channel_id                YouTube channel ID.
	 * @param string $post_type                 Destination post type.
	 * @param string $post_status               Post status to create the post as. Defaults to 'publish'.
	 * @return int|\WP_Error Post ID on success, WP_Error on failure.
	 */
	public function import_channel( array $channel_data, string $channel_id, string $post_type, string $post_status = 'publish' ): int|\WP_Error {
		$post_id = wp_insert_post(
			array(
				'post_title'  => sanitize_text_field( $channel_data['channel_title'] ?? $channel_id ),
				'post_type'   => $post_type,
				'post_status' => $post_status,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$this->save_channel_meta( $post_id, $channel_data, $channel_id );

		return $post_id;
	}

	/**
	 * Save channel data as individual post meta keys.
	 *
	 * @param int    $post_id      Post ID.
	 * @param array  $channel_data Channel data from YouTube_API::get_channel_data().
	 * @param string $channel_id   YouTube channel ID.
	 * @return void
	 */
	private function save_channel_meta( int $post_id, array $channel_data, string $channel_id ): void {
		update_post_meta( $post_id, '_buoyvs_channel_post',        $channel_id );
		update_post_meta( $post_id, '_buoyvs_channel_id',          $channel_id );
		update_post_meta( $post_id, '_buoyvs_channel_title',       $channel_data['channel_title'] ?? '' );
		update_post_meta( $post_id, '_buoyvs_channel_description', $channel_data['channel_description'] ?? '' );
		update_post_meta( $post_id, '_buoyvs_subscriber_count',    (int) ( $channel_data['subscriber_count'] ?? 0 ) );
		update_post_meta( $post_id, '_buoyvs_channel_video_count', (int) ( $channel_data['video_count'] ?? 0 ) );
		update_post_meta( $post_id, '_buoyvs_etag',                $channel_data['etag'] ?? '' );

		// Download the channel images into the media library and store their
		// local URLs. The channel post is created once per post type (deduped
		// upstream), so this runs a single time per channel post.
		$profile = $this->sideload_channel_image(
			$channel_data['profile_picture']['url'] ?? '',
			$post_id,
			/* translators: %s: channel title */
			sprintf( __( '%s profile picture', 'buoy-video-sync' ), $channel_data['channel_title'] ?? $channel_id )
		);
		$banner  = $this->sideload_channel_image(
			$channel_data['banner_image']['url'] ?? '',
			$post_id,
			/* translators: %s: channel title */
			sprintf( __( '%s banner', 'buoy-video-sync' ), $channel_data['channel_title'] ?? $channel_id )
		);

		update_post_meta( $post_id, '_buoyvs_profile_picture',    $profile['url'] );
		update_post_meta( $post_id, '_buoyvs_profile_picture_id', $profile['id'] );
		update_post_meta( $post_id, '_buoyvs_banner_image',       $banner['url'] );
		update_post_meta( $post_id, '_buoyvs_banner_image_id',    $banner['id'] );
		update_post_meta( $post_id, '_buoyvs_source_type',         'channel' );
		update_post_meta( $post_id, '_buoyvs_last_synced',         time() );

		/**
		 * Fires after a channel's metadata has been written during sync.
		 *
		 * Fires on both initial import and every re-sync, after all meta is set.
		 *
		 * @param int    $post_id      Channel post ID.
		 * @param array  $channel_data Channel data from the YouTube API.
		 * @param string $channel_id   YouTube channel ID.
		 */
		do_action( 'buoyvs_channel_synced', $post_id, $channel_data, $channel_id );
	}

	/**
	 * Download a channel image into the media library and attach it to a post.
	 *
	 * The YouTube Data API returns image URLs without a file extension, so the
	 * file is downloaded first and its type detected from the image data before
	 * it is registered as an attachment.
	 *
	 * @param string $url         Image URL returned by the YouTube Data API.
	 * @param int    $post_id     Post to attach the image to.
	 * @param string $description Attachment title / file name base.
	 * @return array { id: int, url: string } Attachment ID and local URL, or 0/'' on failure.
	 */
	private function sideload_channel_image( string $url, int $post_id, string $description ): array {
		$none = array(
			'id'  => 0,
			'url' => '',
		);

		if ( '' === $url ) {
			return $none;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			return $none;
		}

		$mime       = wp_get_image_mime( $tmp );
		$extensions = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
		);

		if ( ! $mime || ! isset( $extensions[ $mime ] ) ) {
			wp_delete_file( $tmp );
			return $none;
		}

		$file_array = array(
			'name'     => sanitize_file_name( $description . '.' . $extensions[ $mime ] ),
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file_array, $post_id, $description );

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $tmp );
			return $none;
		}

		return array(
			'id'  => (int) $attachment_id,
			'url' => (string) wp_get_attachment_url( $attachment_id ),
		);
	}

	/**
	 * Get the URL of the largest available thumbnail.
	 *
	 * Used by the post_thumbnail_html filter in buoyvs.php.
	 *
	 * @param array $thumbnails Thumbnails array from _buoyvs_video meta.
	 * @return array|null Thumbnail array (url, width, height) or null if none found.
	 */
	public static function get_best_thumbnail( array $thumbnails ): ?array {
		foreach ( self::THUMBNAIL_SIZE_PRIORITY as $size ) {
			if ( ! empty( $thumbnails[ $size ]['url'] ) ) {
				return $thumbnails[ $size ];
			}
		}
		return null;
	}
}
