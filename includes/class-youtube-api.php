<?php
/**
 * YouTube Data API v3 wrapper.
 *
 * Fetches channel, playlist, and video data without using search.list
 * (which costs 100 quota units per call). Every method here costs 1 unit
 * per page of up to 50 items.
 *
 * @package YouSync
 */

namespace YouSync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class YouTube_API
 *
 * Wraps YouTube Data API v3 calls using wp_remote_get().
 */
class YouTube_API {

	/**
	 * YouTube Data API v3 base URL.
	 *
	 * @var string
	 */
	private const BASE_URL = 'https://www.googleapis.com/youtube/v3/';

	/**
	 * API key.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Constructor.
	 *
	 * @param string $api_key YouTube Data API v3 key.
	 */
	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	// -------------------------------------------------------------------------
	// Public API methods
	// -------------------------------------------------------------------------

	/**
	 * Get a channel's uploads playlist ID and basic metadata.
	 *
	 * Uses channels.list?part=snippet,contentDetails,statistics — 1 quota unit.
	 *
	 * @param string $channel_id YouTube channel ID (e.g. UCuAXFkgsw1L7xaCfnd5JJOw).
	 * @return array|WP_Error {
	 *     @type string $uploads_playlist_id
	 *     @type string $channel_title
	 *     @type string $channel_description
	 *     @type int    $subscriber_count
	 *     @type int    $video_count
	 *     @type string $etag
	 * }
	 */
	public function get_channel_data( string $channel_id ): array|\WP_Error {
		$url  = $this->api_url(
			'channels',
			array(
				'part' => 'snippet,contentDetails,statistics,brandingSettings',
				'id'   => $channel_id,
			)
		);
		$data = $this->request( $url );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( empty( $data['items'] ) ) {
			return new \WP_Error( 'channel_not_found', "Channel not found. Please verify the Channel ID ({$channel_id}) in your channel settings." );
		}

		$item = $data['items'][0];

		return array(
			'uploads_playlist_id' => $item['contentDetails']['relatedPlaylists']['uploads'] ?? '',
			'channel_title'       => $item['snippet']['title'] ?? '',
			'channel_description' => $item['snippet']['description'] ?? '',
			'subscriber_count'    => (int) ( $item['statistics']['subscriberCount'] ?? 0 ),
			'video_count'         => (int) ( $item['statistics']['videoCount'] ?? 0 ),
			'profile_picture'     => array( 'url' => $item['snippet']['thumbnails']['high']['url'] ?? '' ),
			'banner_image'        => array( 'url' => $this->banner_url( $item['brandingSettings']['image'] ?? array() ) ),
			'etag'                => $item['etag'] ?? '',
		);
	}

	/**
	 * Get all items from a playlist (paginated).
	 *
	 * Uses playlistItems.list?part=snippet — 1 quota unit per page of 50.
	 * Recursively follows nextPageToken until all items are collected.
	 *
	 * @param string      $playlist_id YouTube playlist ID.
	 * @param string|null $page_token  Pagination token for recursive calls.
	 * @return array|WP_Error Flat array of items, each: {
	 *     @type string $video_id
	 *     @type string $title
	 *     @type string $description
	 *     @type string $published_at  ISO 8601 datetime
	 *     @type int    $position      0-based position in playlist
	 *     @type string $channel_title Channel that owns the video
	 * }
	 */
	public function get_playlist_items( string $playlist_id, ?string $page_token = null ): array|\WP_Error {
		$params = array(
			'part'       => 'snippet',
			'playlistId' => $playlist_id,
			'maxResults' => 50,
		);

		if ( $page_token ) {
			$params['pageToken'] = $page_token;
		}

		$url  = $this->api_url( 'playlistItems', $params );
		$data = $this->request( $url );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$items = array();

		foreach ( $data['items'] ?? array() as $item ) {
			$snippet  = $item['snippet'] ?? array();
			$resource = $snippet['resourceId'] ?? array();

			// Skip items that are not videos (e.g. deleted/private placeholders).
			if ( ( $resource['kind'] ?? '' ) !== 'youtube#video' ) {
				continue;
			}

			$items[] = array(
				'video_id'      => $resource['videoId'] ?? '',
				'title'         => $snippet['title'] ?? '',
				'description'   => $snippet['description'] ?? '',
				'published_at'  => $snippet['publishedAt'] ?? '',
				'position'      => (int) ( $snippet['position'] ?? 0 ),
				'channel_title' => $snippet['videoOwnerChannelTitle'] ?? '',
			);
		}

		// Recurse if there are more pages.
		if ( ! empty( $data['nextPageToken'] ) ) {
			$next = $this->get_playlist_items( $playlist_id, $data['nextPageToken'] );

			if ( is_wp_error( $next ) ) {
				return $next;
			}

			$items = array_merge( $items, $next );
		}

		return $items;
	}

	/**
	 * Fetch full video details for up to 50 video IDs in a single request.
	 *
	 * Uses videos.list?part=snippet,contentDetails,statistics — 1 quota unit.
	 *
	 * @param string[] $video_ids Array of YouTube video IDs (max 50).
	 * @return array|WP_Error Keyed array [ video_id => video_data ]. video_data: {
	 *     @type string   $video_id
	 *     @type string   $title
	 *     @type string   $description
	 *     @type string   $channel_id
	 *     @type string   $channel_title
	 *     @type string   $published_at       ISO 8601
	 *     @type int      $duration_seconds
	 *     @type int      $view_count
	 *     @type int      $like_count
	 *     @type int      $comment_count
	 *     @type string[] $tags
	 *     @type string   $category_id
	 *     @type array    $thumbnails          { default, medium, high, standard, maxres }
	 *     @type string   $etag
	 * }
	 */
	public function get_videos_by_ids( array $video_ids ): array|\WP_Error {
		if ( empty( $video_ids ) ) {
			return array();
		}

		$url  = $this->api_url(
			'videos',
			array(
				'part' => 'snippet,contentDetails,statistics',
				'id'   => implode( ',', array_slice( $video_ids, 0, 50 ) ),
			)
		);
		$data = $this->request( $url );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$videos = array();

		foreach ( $data['items'] ?? array() as $item ) {
			$snippet    = $item['snippet'] ?? array();
			$details    = $item['contentDetails'] ?? array();
			$statistics = $item['statistics'] ?? array();
			$video_id   = $item['id'] ?? '';

			$thumbnails = array();
			foreach ( $snippet['thumbnails'] ?? array() as $size => $thumb ) {
				$thumbnails[ $size ] = array(
					'url'    => $thumb['url'] ?? '',
					'width'  => (int) ( $thumb['width'] ?? 0 ),
					'height' => (int) ( $thumb['height'] ?? 0 ),
				);
			}

			$videos[ $video_id ] = array(
				'video_id'         => $video_id,
				'title'            => $snippet['title'] ?? '',
				'description'      => $snippet['description'] ?? '',
				'channel_id'       => $snippet['channelId'] ?? '',
				'channel_title'    => $snippet['channelTitle'] ?? '',
				'published_at'     => $snippet['publishedAt'] ?? '',
				'duration_seconds' => $this->iso8601_to_seconds( $details['duration'] ?? 'PT0S' ),
				'view_count'       => (int) ( $statistics['viewCount'] ?? 0 ),
				'like_count'       => (int) ( $statistics['likeCount'] ?? 0 ),
				'comment_count'    => (int) ( $statistics['commentCount'] ?? 0 ),
				'tags'             => $snippet['tags'] ?? array(),
				'category_id'      => $snippet['categoryId'] ?? '',
				'thumbnails'       => $thumbnails,
				'etag'             => $item['etag'] ?? '',
			);
		}

		return $videos;
	}

	/**
	 * Fetch a single playlist's metadata.
	 *
	 * Uses playlists.list?part=snippet,contentDetails — 1 quota unit.
	 *
	 * @param string $playlist_id YouTube playlist ID.
	 * @return array|WP_Error {
	 *     @type string $playlist_id
	 *     @type string $playlist_title
	 *     @type string $playlist_description
	 *     @type int    $playlist_video_count
	 *     @type string $thumbnail_url
	 *     @type string $etag
	 * }
	 */
	public function get_playlist_data( string $playlist_id ): array|\WP_Error {
		$url  = $this->api_url(
			'playlists',
			array(
				'part' => 'snippet,contentDetails',
				'id'   => $playlist_id,
			)
		);
		$data = $this->request( $url );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( empty( $data['items'] ) ) {
			return new \WP_Error( 'playlist_not_found', "Playlist not found. Please verify the Playlist ID ({$playlist_id}) in your playlist settings." );
		}

		$item    = $data['items'][0];
		$snippet = $item['snippet'] ?? array();

		// Pick the highest-quality thumbnail available.
		$thumb_url = '';
		foreach ( array( 'maxres', 'standard', 'high', 'medium', 'default' ) as $size ) {
			if ( ! empty( $snippet['thumbnails'][ $size ]['url'] ) ) {
				$thumb_url = $snippet['thumbnails'][ $size ]['url'];
				break;
			}
		}

		return array(
			'playlist_id'          => $item['id'] ?? '',
			'playlist_title'       => $snippet['title'] ?? '',
			'playlist_description' => $snippet['description'] ?? '',
			'playlist_video_count' => (int) ( $item['contentDetails']['itemCount'] ?? 0 ),
			'thumbnail_url'        => $thumb_url,
			'channel_id'           => $snippet['channelId'] ?? '',
			'etag'                 => $item['etag'] ?? '',
		);
	}

	/**
	 * Fetch all playlists belonging to a channel (paginated).
	 *
	 * Uses playlists.list?part=snippet,contentDetails&channelId=... — 1 unit per page of 50.
	 * Recursively follows nextPageToken until all playlists are collected.
	 *
	 * @param string      $channel_id YouTube channel ID.
	 * @param string|null $page_token Pagination token for recursive calls.
	 * @return array|WP_Error Flat array of playlist data arrays, each matching the
	 *                        shape returned by get_playlist_data().
	 */
	public function get_channel_playlists( string $channel_id, ?string $page_token = null ): array|\WP_Error {
		$params = array(
			'part'       => 'snippet,contentDetails',
			'channelId'  => $channel_id,
			'maxResults' => 50,
		);

		if ( $page_token ) {
			$params['pageToken'] = $page_token;
		}

		$url  = $this->api_url( 'playlists', $params );
		$data = $this->request( $url );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$playlists = array();

		foreach ( $data['items'] ?? array() as $item ) {
			$snippet   = $item['snippet'] ?? array();
			$thumb_url = '';

			foreach ( array( 'maxres', 'standard', 'high', 'medium', 'default' ) as $size ) {
				if ( ! empty( $snippet['thumbnails'][ $size ]['url'] ) ) {
					$thumb_url = $snippet['thumbnails'][ $size ]['url'];
					break;
				}
			}

			$playlists[] = array(
				'playlist_id'          => $item['id'] ?? '',
				'playlist_title'       => $snippet['title'] ?? '',
				'playlist_description' => $snippet['description'] ?? '',
				'playlist_video_count' => (int) ( $item['contentDetails']['itemCount'] ?? 0 ),
				'thumbnail_url'        => $thumb_url,
				'etag'                 => $item['etag'] ?? '',
			);
		}

		// Recurse if there are more pages.
		if ( ! empty( $data['nextPageToken'] ) ) {
			$next = $this->get_channel_playlists( $channel_id, $data['nextPageToken'] );

			if ( is_wp_error( $next ) ) {
				return $next;
			}

			$playlists = array_merge( $playlists, $next );
		}

		return $playlists;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Resolve the best-quality banner URL from the brandingSettings image object.
	 *
	 * Google CDN URLs (yt3.googleusercontent.com) accept a size parameter appended
	 * after the path, e.g. =w2560. bannerExternalUrl is the raw original upload URL
	 * without any size constraint — appending =w2560 requests the full-width version.
	 *
	 * @param array $image brandingSettings.image object from the API.
	 * @return string Banner URL.
	 */
	private function banner_url( array $image ): string {
		$url = $image['bannerExternalUrl'] ?? $image['bannerTabletImageUrl'] ?? '';

		if ( $url && str_contains( $url, 'googleusercontent.com' ) && ! str_contains( $url, '=' ) ) {
			$url .= '=w2560';
		}

		return $url;
	}

	/**
	 * Execute a GET request and return the decoded JSON body.
	 *
	 * @param string $url Full URL to request.
	 * @return array|WP_Error Decoded response array or WP_Error on failure.
	 */
	private function request( string $url ): array|\WP_Error {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			// Network-level failure (DNS, timeout, SSL, etc.) — almost always transient.
			return new \WP_Error(
				'youtube_network_error',
				'Could not reach YouTube. This is usually temporary — the next scheduled sync will try again. (' . $response->get_error_message() . ')'
			);
		}

		$status  = wp_remote_retrieve_response_code( $response );
		$body    = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );

		if ( 200 !== $status ) {
			$api_message = $decoded['error']['message'] ?? '';
			$reason      = $decoded['error']['errors'][0]['reason'] ?? '';

			$message = $this->friendly_error_message( $status, $reason, $api_message );
			return new \WP_Error( 'youtube_api_error', $message, array( 'status' => $status, 'reason' => $reason ) );
		}

		if ( ! is_array( $decoded ) ) {
			return new \WP_Error(
				'youtube_api_json_error',
				'YouTube returned an unexpected response. This is usually temporary — the next scheduled sync will try again.'
			);
		}

		return $decoded;
	}

	/**
	 * Return a plain-language error message based on HTTP status and API reason code.
	 *
	 * Distinguishes between errors the user can fix (bad API key, wrong ID) and
	 * temporary ones that will likely resolve on the next sync attempt.
	 *
	 * @param int    $status     HTTP status code.
	 * @param string $reason     YouTube API reason code (e.g. 'quotaExceeded').
	 * @param string $api_message Raw message from the API response.
	 * @return string Human-readable error message.
	 */
	private function friendly_error_message( int $status, string $reason, string $api_message ): string {
		// Quota exceeded — permanent until midnight Pacific, but not a config error.
		if ( in_array( $reason, array( 'quotaExceeded', 'dailyLimitExceeded' ), true ) ) {
			return 'YouTube API daily quota exceeded. Syncing will automatically resume on the next scheduled run tomorrow.';
		}

		switch ( $status ) {
			case 400:
				return 'YouTube rejected the request. This may indicate an invalid Channel ID or Playlist ID — please double-check your settings.';

			case 401:
				return 'YouTube API authentication failed. Please check your API key in YouSync → Settings.';

			case 403:
				if ( in_array( $reason, array( 'keyInvalid', 'accessNotConfigured', 'forbidden' ), true ) ) {
					return 'YouTube API access denied. Your API key may be invalid or the YouTube Data API v3 may not be enabled for it. Check YouSync → Settings.';
				}
				return 'YouTube API returned a "Forbidden" error. Check your API key in YouSync → Settings.';

			case 404:
				return 'YouTube could not find the requested channel or playlist. Please verify your Channel ID or Playlist ID.';

			case 429:
				return 'Too many requests sent to YouTube. This is temporary — the next scheduled sync will try again.';

			case 500:
			case 502:
			case 503:
			case 504:
				return "YouTube's servers are temporarily unavailable (HTTP {$status}). This is temporary — the next scheduled sync will try again.";

			default:
				$detail = $api_message ?: "HTTP {$status}";
				return "YouTube API returned an unexpected error ({$detail}). The next scheduled sync will try again.";
		}
	}

	/**
	 * Convert an ISO 8601 duration string to total seconds.
	 *
	 * Examples: PT3M33S → 213, PT1H2M3S → 3723, PT30S → 30.
	 *
	 * @param string $duration ISO 8601 duration (e.g. PT1H2M3S).
	 * @return int Total seconds.
	 */
	private function iso8601_to_seconds( string $duration ): int {
		preg_match( '/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $duration, $matches );

		$hours   = isset( $matches[1] ) ? (int) $matches[1] : 0;
		$minutes = isset( $matches[2] ) ? (int) $matches[2] : 0;
		$seconds = isset( $matches[3] ) ? (int) $matches[3] : 0;

		return ( $hours * 3600 ) + ( $minutes * 60 ) + $seconds;
	}

	/**
	 * Build an API URL with the api_key appended.
	 *
	 * @param string $endpoint API endpoint name (e.g. 'videos', 'channels').
	 * @param array  $params   Query parameters (key => value).
	 * @return string Full URL.
	 */
	private function api_url( string $endpoint, array $params ): string {
		$params['key'] = $this->api_key;
		return self::BASE_URL . $endpoint . '?' . http_build_query( $params );
	}
}
