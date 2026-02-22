<?php
/**
 * Plugin Name: YouSync
 * Description: A plugin to sync and display YouSync videos.
 * Version: 1.0.0
 * Author: Martin Cipriano
 *
 * @package YouSync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'YOUSYNC_VERSION', '1.0.0' );
define( 'YOUSYNC_PLUGIN_FILE', __FILE__ );
define( 'YOUSYNC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'YOUSYNC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

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
function yousync_get_template_part( $slug, $name = null, $args = array() ) {
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
 * Utilizes yousync_get_template_part() internally to avoid code duplication.
 *
 * @param string $slug The slug name for the generic template.
 * @param string $name Optional. The name of the specialized template. Default null.
 * @param array  $args Optional. Additional arguments passed to the template. Default empty array.
 * @return string The template output as a string, or empty string if template not found.
 */
function yousync_return_template_part( $slug, $name = null, $args = array() ) {
	// Start output buffering.
	ob_start();

	// Use the existing get_template_part function.
	yousync_get_template_part( $slug, $name, $args );

	// Get the buffered content and clean the buffer.
	return ob_get_clean();
}

// Load plugin files.
require_once plugin_dir_path( __FILE__ ) . 'includes/settings.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-channel.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-playlist.php';

/**
 * Register YouSync Videos custom post type and taxonomies.
 *
 * @return void
 */
function yousync_init() {
	// Get active archives configuration.
	$active_archives = get_option( 'yousync_active_archives', array() );

	// Videos post type configuration.
	$video_enabled     = isset( $active_archives['ys-video']['enabled'] ) && $active_archives['ys-video']['enabled'];
	$video_slug        = ! empty( $active_archives['ys-video']['slug'] ) ? $active_archives['ys-video']['slug'] : 'ys-video';
	$video_has_archive = $video_enabled;
	$video_public      = $video_enabled;
	$video_rewrite     = $video_enabled ? array( 'slug' => $video_slug ) : false;

	register_post_type(
		'yousync_videos',
		array(
			'labels'              => array(
				'name'          => __( 'Videos', 'yousync' ),
				'singular_name' => __( 'Video', 'yousync' ),
			),
			'public'              => $video_public,
			'publicly_queryable'  => $video_enabled,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'has_archive'         => $video_has_archive,
			'rewrite'             => $video_rewrite,
			'supports'            => array(
				'title',
				'editor',
				'thumbnail',
			),
		)
	);

	// Note: Channel taxonomy is registered in includes/class-channel.php
}
add_action( 'init', 'yousync_init' );

/**
 * Add video metabox to YouSync Videos post type.
 *
 * @return void
 */
function yousync_add_video_metabox() {
	add_meta_box(
		'yousync_video_details',
		__( 'YouSync Video Details', 'yousync' ),
		'yousync_render_video_metabox',
		'yousync_videos',
		'normal',
		'default'
	);
}
add_action( 'add_meta_boxes', 'yousync_add_video_metabox' );

/**
 * Render the video metabox.
 *
 * @param WP_Post $post The current post object.
 * @return void
 */
function yousync_render_video_metabox( $post ) {
	// Add a nonce field for security.
	wp_nonce_field( 'yousync_save_video_meta', 'yousync_video_meta_nonce' );

	// Get existing values.
	$video_url = get_post_meta( $post->ID, '_yousync_video_url', true );
	$video_id  = get_post_meta( $post->ID, '_yousync_video_id', true );
	?>
	<p>
		<label for="yousync_video_url"><strong><?php esc_html_e( 'Video URL', 'yousync' ); ?></strong></label><br>
		<input type="text" name="yousync_video_url" id="yousync_video_url" value="<?php echo esc_attr( $video_url ); ?>" style="width:100%;" />
	</p>

	<p>
		<label for="yousync_video_id"><strong><?php esc_html_e( 'Video ID', 'yousync' ); ?></strong></label><br>
		<input type="text" name="yousync_video_id" id="yousync_video_id" value="<?php echo esc_attr( $video_id ); ?>" style="width:100%;" />
	</p>
	<?php
}

/**
 * Save video metabox data.
 *
 * @param int $post_id The current post ID.
 * @return void
 */
function yousync_save_video_meta( $post_id ) {
	// Verify nonce.
	if ( ! isset( $_POST['yousync_video_meta_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['yousync_video_meta_nonce'] ) ), 'yousync_save_video_meta' ) ) {
		return;
	}

	// Prevent autosave overwrite.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// Check user capability.
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Save video URL field.
	if ( isset( $_POST['yousync_video_url'] ) ) {
		update_post_meta(
			$post_id,
			'_yousync_video_url',
			sanitize_text_field( wp_unslash( $_POST['yousync_video_url'] ) )
		);
	}

	// Save video ID field.
	if ( isset( $_POST['yousync_video_id'] ) ) {
		update_post_meta(
			$post_id,
			'_yousync_video_id',
			sanitize_text_field( wp_unslash( $_POST['yousync_video_id'] ) )
		);
	}
}
add_action( 'save_post_yousync_videos', 'yousync_save_video_meta' );