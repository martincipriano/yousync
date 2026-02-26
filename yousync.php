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

/**
 * Get the field type for a sync rule condition field.
 *
 * Used to determine which operators and value input to render.
 *
 * @param string $field The condition field name.
 * @return string Field type: 'text', 'number', or 'date'. Empty string if unknown.
 */
function yousync_get_condition_field_type( $field ) {
	$map = array(
		// Channel fields
		'channel_title'        => 'text',
		'channel_description'  => 'text',
		'subscriber_count'     => 'number',
		'video_count'          => 'number',
		// Playlist fields
		'playlist_title'       => 'text',
		'playlist_description' => 'text',
		'playlist_video_count' => 'number',
		// Video fields
		'title'                => 'text',
		'description'          => 'text',
		'tags'                 => 'text',
		'duration'             => 'number',
		'published_date'       => 'date',
		'video_category'       => 'text',
		'view_count'           => 'number',
		'like_count'           => 'number',
		'comment_count'        => 'number',
	);
	return isset( $map[ $field ] ) ? $map[ $field ] : '';
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
	wp_nonce_field( 'yousync_save_video_meta', 'yousync_video_meta_nonce' );

	$meta = get_post_meta( $post->ID, '_yousync_video', true );
	$data = $meta ? json_decode( $meta, true ) : array();
	if ( ! is_array( $data ) ) {
		$data = array();
	}

	// Editable fields
	$video_id  = isset( $data['video_id'] ) ? $data['video_id'] : '';
	$video_url = isset( $data['video_url'] ) ? $data['video_url'] : '';

	// Read-only YouTube data
	$original_title   = isset( $data['original_title'] ) ? $data['original_title'] : '';
	$channel_title    = isset( $data['channel_title'] ) ? $data['channel_title'] : '';
	$published_date   = isset( $data['published_date'] ) ? $data['published_date'] : '';
	$duration_seconds = isset( $data['duration_seconds'] ) ? $data['duration_seconds'] : '';
	$view_count       = isset( $data['view_count'] ) ? $data['view_count'] : '';
	$like_count       = isset( $data['like_count'] ) ? $data['like_count'] : '';
	$comment_count    = isset( $data['comment_count'] ) ? $data['comment_count'] : '';
	$sync_source_type = isset( $data['sync_source_type'] ) ? $data['sync_source_type'] : '';
	$last_synced      = isset( $data['last_synced'] ) ? $data['last_synced'] : '';
	$sync_count       = isset( $data['sync_count'] ) ? $data['sync_count'] : 0;
	$manual_edits     = isset( $data['manual_edits'] ) ? $data['manual_edits'] : false;
	?>
	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="yousync_video_id"><?php esc_html_e( 'Video ID', 'yousync' ); ?></label>
			</th>
			<td>
				<input type="text" name="yousync_video_id" id="yousync_video_id" value="<?php echo esc_attr( $video_id ); ?>" class="regular-text">
				<p class="description"><?php esc_html_e( 'YouTube video ID (e.g., dQw4w9WgXcQ)', 'yousync' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="yousync_video_url"><?php esc_html_e( 'Video URL', 'yousync' ); ?></label>
			</th>
			<td>
				<input type="url" name="yousync_video_url" id="yousync_video_url" value="<?php echo esc_attr( $video_url ); ?>" class="regular-text">
			</td>
		</tr>

		<?php if ( $original_title ) : ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Original Title', 'yousync' ); ?></th>
			<td><?php echo esc_html( $original_title ); ?></td>
		</tr>
		<?php endif; ?>

		<?php if ( $channel_title ) : ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Channel', 'yousync' ); ?></th>
			<td><?php echo esc_html( $channel_title ); ?></td>
		</tr>
		<?php endif; ?>

		<?php if ( $published_date ) : ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Published Date', 'yousync' ); ?></th>
			<td><?php echo esc_html( $published_date ); ?></td>
		</tr>
		<?php endif; ?>

		<?php if ( $duration_seconds !== '' ) : ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Duration', 'yousync' ); ?></th>
			<td>
				<?php
				$hours = floor( (int) $duration_seconds / 3600 );
				$mins  = floor( ( (int) $duration_seconds % 3600 ) / 60 );
				$secs  = (int) $duration_seconds % 60;
				echo esc_html( $hours > 0
					? sprintf( '%d:%02d:%02d', $hours, $mins, $secs )
					: sprintf( '%d:%02d', $mins, $secs )
				);
				?>
			</td>
		</tr>
		<?php endif; ?>

		<?php if ( $view_count !== '' ) : ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'View Count', 'yousync' ); ?></th>
			<td><?php echo esc_html( number_format( (int) $view_count ) ); ?></td>
		</tr>
		<?php endif; ?>

		<?php if ( $like_count !== '' ) : ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Like Count', 'yousync' ); ?></th>
			<td><?php echo esc_html( number_format( (int) $like_count ) ); ?></td>
		</tr>
		<?php endif; ?>

		<?php if ( $comment_count !== '' ) : ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Comment Count', 'yousync' ); ?></th>
			<td><?php echo esc_html( number_format( (int) $comment_count ) ); ?></td>
		</tr>
		<?php endif; ?>

		<?php if ( $sync_source_type ) : ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Sync Source', 'yousync' ); ?></th>
			<td><?php echo esc_html( ucfirst( $sync_source_type ) ); ?></td>
		</tr>
		<?php endif; ?>

		<?php if ( $last_synced ) : ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Last Synced', 'yousync' ); ?></th>
			<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_synced ) ); ?></td>
		</tr>
		<?php endif; ?>

		<?php if ( $sync_count ) : ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Sync Count', 'yousync' ); ?></th>
			<td><?php echo esc_html( $sync_count ); ?></td>
		</tr>
		<?php endif; ?>

		<?php if ( $manual_edits ) : ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Manual Edits', 'yousync' ); ?></th>
			<td><?php esc_html_e( 'Yes — title or description has been edited since last sync', 'yousync' ); ?></td>
		</tr>
		<?php endif; ?>
	</table>
	<?php
}

/**
 * Save video metabox data.
 *
 * Merges editable fields (video_id, video_url) into the existing JSON meta,
 * preserving all YouTube API data that was previously synced.
 *
 * @param int $post_id The current post ID.
 * @return void
 */
function yousync_save_video_meta( $post_id ) {
	if ( ! isset( $_POST['yousync_video_meta_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['yousync_video_meta_nonce'] ) ), 'yousync_save_video_meta' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Read existing JSON meta to preserve YouTube API data.
	$existing_meta = get_post_meta( $post_id, '_yousync_video', true );
	$data          = $existing_meta ? json_decode( $existing_meta, true ) : array();
	if ( ! is_array( $data ) ) {
		$data = array();
	}

	// Update editable fields.
	if ( isset( $_POST['yousync_video_id'] ) ) {
		$data['video_id'] = sanitize_text_field( wp_unslash( $_POST['yousync_video_id'] ) );
	}
	if ( isset( $_POST['yousync_video_url'] ) ) {
		$data['video_url'] = esc_url_raw( wp_unslash( $_POST['yousync_video_url'] ) );
	}

	update_post_meta( $post_id, '_yousync_video', wp_json_encode( $data ) );
}
add_action( 'save_post_yousync_videos', 'yousync_save_video_meta' );