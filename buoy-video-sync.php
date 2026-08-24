<?php
declare(strict_types=1);
/**
 * Plugin Name: Buoy Video Sync
 * Plugin URI: https://wpbuoy.com/product/video-sync
 * Description: Sync YouTube videos, playlists, and channels from a single channel into WordPress posts with metadata and thumbnails.
 * Version: 3.2.1
 * Author: Martin Cipriano
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: buoy-video-sync
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Tested up to: 7.0
 *
 * @package Buoy_Video_Sync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// If Pro is active, stay dormant — Pro handles everything.
if ( defined( 'BUOYVS_PRO' ) || in_array( 'buoy-video-sync-pro/buoy-video-sync-pro.php', (array) get_option( 'active_plugins', array() ), true ) ) {
	return;
}

// Define plugin constants.
define( 'BUOYVS_VERSION', '3.2.1' );
define( 'BUOYVS_PLUGIN_FILE', __FILE__ );
define( 'BUOYVS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BUOYVS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BUOYVS', true );

/**
 * Load a template part into a plugin template.
 *
 * Makes it easy for a plugin to reuse sections of code and
 * an easy way to separate concerns.
 *
 * @param string $slug The slug name for the generic template.
 * @param string $name Optional. The name of the specialized template. Default null.
 * @param array  $args Optional. Additional arguments passed to the template. Default empty array.
 * @return string|bool The template path if found, false otherwise.
 */
function buoyvs_get_template_part( $slug, $name = null, $args = array() ) {
	$templates  = array();
	$plugin_dir = plugin_dir_path( __FILE__ );

	// Build template file names.
	if ( isset( $name ) ) {
		$templates[] = "{$slug}-{$name}.php";
	}
	$templates[] = "{$slug}.php";

	// Try to locate the template.
	$located = false;
	foreach ( $templates as $template ) {
		$template_path = $plugin_dir . 'template-parts/' . $template;

		if ( file_exists( $template_path ) ) {
			$located = $template_path;
			break;
		}
	}

	// Load the template if found.
	if ( $located ) {
		// Extract args to make them available as variables in the template.
		if ( is_array( $args ) && ! empty( $args ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Necessary for template variable extraction.
			extract( $args, EXTR_SKIP );
		}

		// Include the template file.
		include $located;
	}

	return $located;
}

/**
 * Load a template part and return its output as a string.
 *
 * Uses output buffering to capture the template output instead of echoing it directly.
 * Utilizes buoyvs_get_template_part() internally to avoid code duplication.
 *
 * @param string $slug The slug name for the generic template.
 * @param string $name Optional. The name of the specialized template. Default null.
 * @param array  $args Optional. Additional arguments passed to the template. Default empty array.
 * @return string The template output as a string, or empty string if template not found.
 */
function buoyvs_return_template_part( $slug, $name = null, $args = array() ) {
	// Start output buffering.
	ob_start();

	// Use the existing get_template_part function.
	buoyvs_get_template_part( $slug, $name, $args );

	// Get the buffered content and clean the buffer.
	return ob_get_clean();
}

/**
 * Plugin activation.
 */
register_activation_hook( BUOYVS_PLUGIN_FILE, function () {
	flush_rewrite_rules();
} );

/**
 * Plugin deactivation — clear any scheduled sync jobs.
 */
register_deactivation_hook( BUOYVS_PLUGIN_FILE, function () {
	wp_unschedule_hook( 'buoyvs_channel_config_sync_rule' );
} );

// Load plugin files.
require_once BUOYVS_PLUGIN_DIR . 'includes/functions.php';
require_once BUOYVS_PLUGIN_DIR . 'includes/settings.php';
// Load sync engine.
require_once BUOYVS_PLUGIN_DIR . 'includes/class-youtube-api.php';
require_once BUOYVS_PLUGIN_DIR . 'includes/class-video-importer.php';
require_once BUOYVS_PLUGIN_DIR . 'includes/class-sync-runner.php';
require_once BUOYVS_PLUGIN_DIR . 'includes/class-sync-history.php';
require_once BUOYVS_PLUGIN_DIR . 'includes/class-sync-scheduler.php';
require_once BUOYVS_PLUGIN_DIR . 'includes/class-channels-page.php';
require_once BUOYVS_PLUGIN_DIR . 'includes/class-blocks.php';

/**
 * Instantiate the sync engine.
 *
 * Priority 5 — before other constructors which hook into 'init' at default
 * priority 10, so the scheduler's cron listener is registered before anything
 * that might fire it.
 */
add_action(
	'init',
	function () {
		$api      = new \Buoy_Video_Sync\YouTube_API( get_option( 'buoyvs_api_key', '' ) );
		$importer = new \Buoy_Video_Sync\Video_Importer();
		$runner   = new \Buoy_Video_Sync\Sync_Runner( $api, $importer );
		new \Buoy_Video_Sync\Sync_Scheduler( $runner );
	},
	5
);

/**
 * Add video metabox to Buoy Video Sync Videos post type.
 *
 * @return void
 */
add_action( 'add_meta_boxes', function ( string $post_type, \WP_Post $post ): void {
	if ( ! get_post_meta( $post->ID, '_buoyvs_video_id', true ) ) {
		return;
	}
	add_meta_box(
		'buoyvs_video_details',
		__( 'Buoy Video Sync Video Details', 'buoy-video-sync' ),
		'buoyvs_render_video_metabox',
		$post_type,
		'normal',
		'high'
	);
}, 10, 2 );

/**
 * Render the video metabox with a tabbed interface.
 *
 * Tabs: Details | YouTube Data | Thumbnails | Sync Status
 *
 * @param WP_Post $post The current post object.
 * @return void
 */
function buoyvs_render_video_metabox( $post ) {
	$thumbnails = get_post_meta( $post->ID, '_buoyvs_thumbnails', true );
	$thumbnails = is_array( $thumbnails ) ? $thumbnails : array();

	buoyvs_get_template_part( 'metabox', 'video', array(
		'post_id'               => $post->ID,
		'video_id'              => (string) get_post_meta( $post->ID, '_buoyvs_video_id', true ),
		'video_url'             => (string) get_post_meta( $post->ID, '_buoyvs_video_url', true ),
		'channel_id'            => (string) get_post_meta( $post->ID, '_buoyvs_channel_id', true ),
		'original_title'        => (string) get_post_meta( $post->ID, '_buoyvs_original_title', true ),
		'original_description'  => (string) get_post_meta( $post->ID, '_buoyvs_original_description', true ),
		'channel_title'         => (string) get_post_meta( $post->ID, '_buoyvs_channel_title', true ),
		'published_date'        => (string) get_post_meta( $post->ID, '_buoyvs_published_at', true ),
		'duration_seconds'      => get_post_meta( $post->ID, '_buoyvs_duration_seconds', true ),
		'view_count'            => get_post_meta( $post->ID, '_buoyvs_view_count', true ),
		'like_count'            => get_post_meta( $post->ID, '_buoyvs_like_count', true ),
		'comment_count'         => get_post_meta( $post->ID, '_buoyvs_comment_count', true ),
		'sync_source_type'      => (string) get_post_meta( $post->ID, '_buoyvs_source_type', true ),
		'last_synced'           => (int) get_post_meta( $post->ID, '_buoyvs_last_synced', true ),
		'thumbnails'            => $thumbnails,
		'thumbnail_size_labels' => array(
			'maxres'   => 'Max Res (1280×720)',
			'standard' => 'Standard (640×480)',
			'high'     => 'High (480×360)',
			'medium'   => 'Medium (320×180)',
			'default'  => 'Default (120×90)',
		),
		'preview_thumb'         => \Buoy_Video_Sync\Video_Importer::get_best_thumbnail( $thumbnails ),
	) );
}

/**
 * Resolve the YouTube image to use as the featured-image fallback for a post.
 *
 * Video and playlist posts store a sized thumbnails array; channel posts store a
 * single profile-picture URL. Returns a normalised array with at least a 'url'
 * key (and optional 'width'/'height'), or null when the post has no YouTube image.
 *
 * @param int $post_id Post ID.
 * @return array|null { url, width?, height? } or null.
 */
function buoyvs_get_fallback_thumbnail( int $post_id ): ?array {
	$thumbnails = get_post_meta( $post_id, '_buoyvs_thumbnails', true );

	if ( is_array( $thumbnails ) && ! empty( $thumbnails ) ) {
		return \Buoy_Video_Sync\Video_Importer::get_best_thumbnail( $thumbnails );
	}

	$profile_picture = (string) get_post_meta( $post_id, '_buoyvs_profile_picture', true );

	if ( $profile_picture ) {
		return array( 'url' => $profile_picture );
	}

	return null;
}

/**
 * Fall back to the YouTube image when no featured image is set.
 *
 * Applies to synced video, playlist, and channel posts, and only when the
 * "Use the YouTube image as the featured image" setting is enabled. If the user
 * has explicitly set a featured image, that takes precedence and this is a no-op.
 *
 * @param string     $html             Current featured image HTML.
 * @param int        $post_id          Post ID.
 * @param int        $post_thumbnail_id Attachment ID of the set thumbnail (0 if none).
 * @param string|int[] $size           Requested image size.
 * @param string|array $attr           Additional HTML attributes.
 * @return string HTML img tag, or original $html.
 */
function buoyvs_post_thumbnail_html( string $html, int $post_id, int $post_thumbnail_id, $size, $attr ): string {
	if ( $post_thumbnail_id ) {
		return $html;
	}

	if ( ! get_option( 'buoyvs_youtube_image_as_featured', 1 ) ) {
		return $html;
	}

	$thumb = buoyvs_get_fallback_thumbnail( $post_id );

	if ( ! $thumb ) {
		return $html;
	}

	$width  = ! empty( $thumb['width'] ) ? ' width="' . (int) $thumb['width'] . '"' : '';
	$height = ! empty( $thumb['height'] ) ? ' height="' . (int) $thumb['height'] . '"' : '';
	$alt    = esc_attr( get_the_title( $post_id ) );

	return '<img src="' . esc_url( $thumb['url'] ) . '"' . $width . $height . ' alt="' . $alt . '" class="attachment-post-thumbnail size-post-thumbnail wp-post-image">';
}
add_filter( 'post_thumbnail_html', 'buoyvs_post_thumbnail_html', 10, 5 );

/**
 * Show the YouTube thumbnail in the featured image metabox when none is set.
 *
 * The preview image triggers the existing "Set featured image" link on click
 * so the user can still upload their own image. A small label clarifies it is
 * the YouTube thumbnail.
 *
 * @param string   $content      Current featured image metabox HTML.
 * @param int      $post_id      Post ID.
 * @param int|null $thumbnail_id Attachment ID of the set thumbnail, or null if none.
 * @return string Modified HTML.
 */
function buoyvs_admin_post_thumbnail_html( string $content, int $post_id, $thumbnail_id ): string {
	if ( $thumbnail_id ) {
		return $content;
	}

	if ( ! get_option( 'buoyvs_youtube_image_as_featured', 1 ) ) {
		return $content;
	}

	$thumb = buoyvs_get_fallback_thumbnail( $post_id );

	if ( ! $thumb ) {
		return $content;
	}

	$preview = '<img'
		. ' src="' . esc_url( $thumb['url'] ) . '"'
		. ' onclick="document.getElementById(\'set-post-thumbnail\').click()"'
		. ' title="' . esc_attr__( 'Click to set a custom featured image', 'buoy-video-sync') . '"'
		. ' alt=""'
		. '>';
	$label   = '<p class="hide-if-no-js howto" id="set-post-thumbnail-desc">'
		. esc_html__( 'Click the YouTube thumbnail to edit or update', 'buoy-video-sync')
		. '</p>';

	return $preview . $label . $content;
}
add_filter( 'admin_post_thumbnail_html', 'buoyvs_admin_post_thumbnail_html', 10, 3 );

/**
 * Add a "Sync Type" column before Title on post list screens, showing a
 * Video/Playlist/Channel badge for synced posts. Registered on every post
 * type's list table (destination post type is configurable per sync rule),
 * so the cell is simply empty for unrelated posts; users can hide the
 * column entirely via Screen Options.
 *
 * @param string[] $columns Existing column headings, keyed by slug.
 * @return string[] Modified column headings.
 */
function buoyvs_add_type_column( array $columns ): array {
	$new = array();
	foreach ( $columns as $key => $label ) {
		if ( 'title' === $key ) {
			$new['buoyvs_type'] = __( 'Sync Type', 'buoy-video-sync' );
		}
		$new[ $key ] = $label;
	}
	return $new;
}
add_filter( 'manage_posts_columns', 'buoyvs_add_type_column' );
add_filter( 'manage_pages_columns', 'buoyvs_add_type_column' );

/**
 * Render the "Sync Type" column content as a badge (see .buoyvs-type-badge
 * in admin.scss).
 *
 * @param string $column  Column key being rendered.
 * @param int    $post_id Current row's post ID.
 * @return void
 */
function buoyvs_render_type_column( string $column, int $post_id ): void {
	if ( 'buoyvs_type' !== $column ) {
		return;
	}

	if ( get_post_meta( $post_id, '_buoyvs_video_id', true ) ) {
		$type  = 'video';
		$label = __( 'Video', 'buoy-video-sync' );
	} elseif ( get_post_meta( $post_id, '_buoyvs_playlist_id', true ) ) {
		$type  = 'playlist';
		$label = __( 'Playlist', 'buoy-video-sync' );
	} elseif ( get_post_meta( $post_id, '_buoyvs_channel_post', true ) ) {
		$type  = 'channel';
		$label = __( 'Channel', 'buoy-video-sync' );
	} else {
		return;
	}

	printf(
		'<span class="buoyvs-type-badge buoyvs-type-badge--%1$s">%2$s</span>',
		esc_attr( $type ),
		esc_html( $label )
	);
}
add_action( 'manage_posts_custom_column', 'buoyvs_render_type_column', 10, 2 );
add_action( 'manage_pages_custom_column', 'buoyvs_render_type_column', 10, 2 );

/**
 * Add playlist metabox to posts that were synced from a YouTube playlist.
 *
 * @return void
 */
add_action( 'add_meta_boxes', function ( string $post_type, \WP_Post $post ): void {
	if ( ! get_post_meta( $post->ID, '_buoyvs_playlist_id', true ) ) {
		return;
	}
	add_meta_box(
		'buoyvs_playlist_details',
		__( 'Buoy Video Sync Playlist Details', 'buoy-video-sync' ),
		'buoyvs_render_playlist_metabox',
		$post_type,
		'normal',
		'high'
	);
}, 10, 2 );

/**
 * Render the playlist metabox.
 *
 * @param WP_Post $post The current post object.
 * @return void
 */
function buoyvs_render_playlist_metabox( $post ) {
	buoyvs_get_template_part( 'metabox', 'playlist', array(
		'post_id'               => $post->ID,
		'playlist_id'           => (string) get_post_meta( $post->ID, '_buoyvs_playlist_id', true ),
		'channel_id'            => (string) get_post_meta( $post->ID, '_buoyvs_channel_id', true ),
		'playlist_title'        => (string) get_post_meta( $post->ID, '_buoyvs_playlist_title', true ),
		'playlist_description'  => (string) get_post_meta( $post->ID, '_buoyvs_playlist_description', true ),
		'playlist_video_count'  => get_post_meta( $post->ID, '_buoyvs_playlist_video_count', true ),
		'playlist_thumbnail'    => (string) get_post_meta( $post->ID, '_buoyvs_playlist_thumbnail', true ),
		'last_synced'           => (int) get_post_meta( $post->ID, '_buoyvs_last_synced', true ),
	) );
}

/**
 * Add channel metabox to posts that were synced as a YouTube channel.
 *
 * Uses _buoyvs_channel_post (not _buoyvs_channel_id) so that video/playlist posts
 * that store the source channel ID do not get a Channel Details metabox.
 *
 * @return void
 */
add_action( 'add_meta_boxes', function ( string $post_type, \WP_Post $post ): void {
	if ( ! get_post_meta( $post->ID, '_buoyvs_channel_post', true ) ) {
		return;
	}
	add_meta_box(
		'buoyvs_channel_details',
		__( 'Buoy Video Sync Channel Details', 'buoy-video-sync' ),
		'buoyvs_render_channel_metabox',
		$post_type,
		'normal',
		'high'
	);
}, 10, 2 );

/**
 * Render the channel metabox.
 *
 * @param WP_Post $post The current post object.
 * @return void
 */
function buoyvs_render_channel_metabox( $post ) {
	buoyvs_get_template_part( 'metabox', 'channel', array(
		'post_id'               => $post->ID,
		'channel_id'            => (string) get_post_meta( $post->ID, '_buoyvs_channel_post', true ),
		'channel_title'         => (string) get_post_meta( $post->ID, '_buoyvs_channel_title', true ),
		'channel_description'   => (string) get_post_meta( $post->ID, '_buoyvs_channel_description', true ),
		'subscriber_count'      => get_post_meta( $post->ID, '_buoyvs_subscriber_count', true ),
		'video_count'           => get_post_meta( $post->ID, '_buoyvs_channel_video_count', true ),
		'last_synced'           => (int) get_post_meta( $post->ID, '_buoyvs_last_synced', true ),
		'profile_picture'       => (string) get_post_meta( $post->ID, '_buoyvs_profile_picture', true ),
		'banner_image'          => (string) get_post_meta( $post->ID, '_buoyvs_banner_image', true ),
	) );
}

/**
 * Enqueue assets on post edit screens where the post has Buoy Video Sync video meta,
 * and the stylesheet alone on post list screens (for the Sync Type column badge).
 *
 * @return void
 */
function buoyvs_enqueue_video_list_assets(): void {
	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	if ( 'edit' === $screen->base ) {
		wp_enqueue_style(
			'buoyvs-admin',
			BUOYVS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BUOYVS_VERSION
		);
		return;
	}

	if ( 'post' !== $screen->base ) {
		return;
	}

	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$has_buoyvs_meta = $post_id && (
		get_post_meta( $post_id, '_buoyvs_video_id', true ) ||
		get_post_meta( $post_id, '_buoyvs_playlist_id', true ) ||
		get_post_meta( $post_id, '_buoyvs_channel_post', true )
	);
	if ( ! $has_buoyvs_meta ) {
		return;
	}

	wp_enqueue_style(
		'buoyvs-admin',
		BUOYVS_PLUGIN_URL . 'assets/css/admin.css',
		array(),
		BUOYVS_VERSION
	);
	wp_enqueue_script(
		'buoyvs-metabox',
		BUOYVS_PLUGIN_URL . 'assets/js/metabox.js',
		array(),
		BUOYVS_VERSION,
		true
	);
}
add_action( 'admin_enqueue_scripts', 'buoyvs_enqueue_video_list_assets' );

