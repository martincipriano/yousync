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
		'video_id'             => 'text',
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

// Load sync engine.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-youtube-api.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-condition-evaluator.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-video-importer.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-sync-logger.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-sync-runner.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-sync-scheduler.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/admin-logs.php';

/**
 * Instantiate the sync engine.
 *
 * Priority 5 — before Channel and Playlist constructors which hook into
 * 'init' at default priority 10. This ensures the scheduler is ready to
 * attach its priority-20 hooks when the taxonomy save hooks fire.
 */
add_action(
	'init',
	function () {
		$api       = new \YouSync\YouTube_API( get_option( 'yousync_api_key', '' ) );
		$evaluator = new \YouSync\Condition_Evaluator();
		$importer  = new \YouSync\Video_Importer();
		$runner    = new \YouSync\Sync_Runner( $api, $evaluator, $importer );
		new \YouSync\Sync_Scheduler( $runner );
	},
	5
);

/**
 * Show a notice on YouSync admin pages when WP Cron may be unreliable.
 *
 * Hidden when DISABLE_WP_CRON is defined, which means the site owner
 * has already set up a real server-side scheduled task. Dismissible
 * per-user; dismissal is stored in user meta.
 */
add_action( 'admin_notices', function () {
	if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
		return;
	}

	if ( get_user_meta( get_current_user_id(), 'yousync_cron_notice_dismissed', true ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	$yousync_screens = array( 'edit-yousync_channel', 'edit-yousync_playlist' );
	if ( ! in_array( $screen->id, $yousync_screens, true ) && false === strpos( $screen->id, 'yousync' ) ) {
		return;
	}
	?>
	<div class="notice notice-warning is-dismissible yousync-cron-notice" data-nonce="<?php echo esc_attr( wp_create_nonce( 'yousync_dismiss_cron_notice' ) ); ?>">
		<p>
			<strong><?php esc_html_e( 'YouSync — Sync Scheduling Notice', 'yousync' ); ?></strong><br>
			<?php esc_html_e( 'YouSync uses WordPress\'s built-in scheduler to run your sync rules. This only works when someone visits your site, so syncs may run late or be skipped on low-traffic sites. For reliable scheduling, ask your hosting provider to set up an automatic server task (cron job) that runs every few minutes.', 'yousync' ); ?>
		</p>
	</div>
	<script>
	( function () {
		var notice = document.querySelector( '.yousync-cron-notice' );
		if ( ! notice ) return;
		notice.addEventListener( 'click', function ( e ) {
			if ( ! e.target.classList.contains( 'notice-dismiss' ) ) return;
			fetch( ajaxurl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: 'action=yousync_dismiss_cron_notice&nonce=' + notice.dataset.nonce,
			} );
		} );
	} )();
	</script>
	<?php
} );

/**
 * AJAX handler to persist the cron notice dismissal for the current user.
 */
add_action( 'wp_ajax_yousync_dismiss_cron_notice', function () {
	check_ajax_referer( 'yousync_dismiss_cron_notice', 'nonce' );
	update_user_meta( get_current_user_id(), 'yousync_cron_notice_dismissed', 1 );
	wp_die();
} );

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
				'add_new'       => __( 'Add New Video', 'yousync' ),
				'add_new_item'  => __( 'Add New Video', 'yousync' ),
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

	// Register video_tag taxonomy.
	register_taxonomy(
		'video_tag',
		'yousync_videos',
		array(
			'labels'            => array(
				'name'          => __( 'Video Tags', 'yousync' ),
				'singular_name' => __( 'Video Tag', 'yousync' ),
				'menu_name'     => __( 'Tags', 'yousync' ),
				'search_items'  => __( 'Search Video Tags', 'yousync' ),
				'all_items'     => __( 'All Video Tags', 'yousync' ),
				'edit_item'     => __( 'Edit Video Tag', 'yousync' ),
				'add_new_item'  => __( 'Add New Video Tag', 'yousync' ),
			),
			'hierarchical'      => false,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => false,
			'rewrite'           => array( 'slug' => 'video-tag' ),
		)
	);

	// Register video_category taxonomy.
	register_taxonomy(
		'video_category',
		'yousync_videos',
		array(
			'labels'            => array(
				'name'          => __( 'Video Categories', 'yousync' ),
				'singular_name' => __( 'Video Category', 'yousync' ),
				'menu_name'     => __( 'Categories', 'yousync' ),
				'search_items'  => __( 'Search Video Categories', 'yousync' ),
				'all_items'     => __( 'All Video Categories', 'yousync' ),
				'edit_item'     => __( 'Edit Video Category', 'yousync' ),
				'add_new_item'  => __( 'Add New Video Category', 'yousync' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => false,
			'rewrite'           => array( 'slug' => 'video-category' ),
		)
	);

	// Note: Channel and Playlist taxonomies are registered in their respective class files.
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
 * Render the video metabox with a tabbed interface.
 *
 * Tabs: Details | YouTube Data | Thumbnails | Sync Status
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

	$video_id             = $data['video_id'] ?? '';
	$video_url            = $data['video_url'] ?? '';
	$channel_id           = $data['channel_id'] ?? '';
	$manual_edits         = (bool) ( $data['manual_edits'] ?? false );
	$original_title       = $data['original_title'] ?? '';
	$original_description = $data['original_description'] ?? '';
	$channel_title        = $data['channel_title'] ?? '';
	$published_date       = $data['published_date'] ?? '';
	$duration_seconds     = $data['duration_seconds'] ?? '';
	$view_count           = $data['view_count'] ?? '';
	$like_count           = $data['like_count'] ?? '';
	$comment_count        = $data['comment_count'] ?? '';
	$sync_source_type     = $data['sync_source_type'] ?? '';
	$last_synced          = $data['last_synced'] ?? '';
	$sync_count           = $data['sync_count'] ?? 0;
	$sync_errors          = is_array( $data['sync_errors'] ?? null ) ? $data['sync_errors'] : array();
	$thumbnails           = is_array( $data['thumbnails'] ?? null ) ? $data['thumbnails'] : array();

	$thumbnail_size_labels = array(
		'maxres'   => 'Max Res (1280×720)',
		'standard' => 'Standard (640×480)',
		'high'     => 'High (480×360)',
		'medium'   => 'Medium (320×180)',
		'default'  => 'Default (120×90)',
	);
	?>

	<div class="yousync-metabox">
		<nav class="nav-tab-wrapper" style="margin-bottom:0; padding-bottom:0;">
			<a href="#" class="nav-tab nav-tab-active yousync-mb-tab" data-tab="details"><?php esc_html_e( 'Details', 'yousync' ); ?></a>
			<a href="#" class="nav-tab yousync-mb-tab" data-tab="yt-data"><?php esc_html_e( 'YouTube Data', 'yousync' ); ?></a>
			<?php if ( ! empty( $thumbnails ) ) : ?>
			<a href="#" class="nav-tab yousync-mb-tab" data-tab="thumbnails"><?php esc_html_e( 'Thumbnails', 'yousync' ); ?></a>
			<?php endif; ?>
			<a href="#" class="nav-tab yousync-mb-tab" data-tab="sync-status"><?php esc_html_e( 'Sync Status', 'yousync' ); ?></a>
		</nav>

		<!-- Details -->
		<div id="yousync-panel-details" class="yousync-mb-panel" style="padding-top:12px;">
			<table class="form-table">
				<?php if ( $video_id ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Video ID', 'yousync' ); ?></th>
					<td><code><?php echo esc_html( $video_id ); ?></code></td>
				</tr>
				<?php endif; ?>

				<?php if ( $video_url ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Video URL', 'yousync' ); ?></th>
					<td><a href="<?php echo esc_url( $video_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $video_url ); ?></a></td>
				</tr>
				<?php endif; ?>

				<?php if ( $channel_id ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Channel ID', 'yousync' ); ?></th>
					<td><code><?php echo esc_html( $channel_id ); ?></code></td>
				</tr>
				<?php endif; ?>

				<tr>
					<th scope="row">
						<label for="yousync_manual_edits"><?php esc_html_e( 'Protected from Sync Rules', 'yousync' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="yousync_manual_edits" id="yousync_manual_edits" value="1" <?php checked( $manual_edits ); ?>>
							<?php esc_html_e( 'Prevent sync rules from overwriting this video', 'yousync' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>

		<!-- YouTube Data -->
		<div id="yousync-panel-yt-data" class="yousync-mb-panel" style="display:none; padding-top:12px;">
			<table class="form-table">
				<?php if ( $original_title ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Original Title', 'yousync' ); ?></th>
					<td><?php echo esc_html( $original_title ); ?></td>
				</tr>
				<?php endif; ?>

				<?php if ( $original_description ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Description', 'yousync' ); ?></th>
					<td>
						<p style="margin:0; white-space:pre-wrap; max-height:7em; overflow-y:auto;"><?php echo esc_html( $original_description ); ?></p>
					</td>
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
						echo esc_html(
							$hours > 0
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

				<?php if ( ! $original_title && ! $channel_title && $duration_seconds === '' && $view_count === '' ) : ?>
				<tr>
					<td colspan="2"><p style="margin:0; color:#757575;"><?php esc_html_e( 'No YouTube data available yet.', 'yousync' ); ?></p></td>
				</tr>
				<?php endif; ?>
			</table>
		</div>

		<!-- Thumbnails -->
		<?php if ( ! empty( $thumbnails ) ) : ?>
		<div id="yousync-panel-thumbnails" class="yousync-mb-panel" style="display:none; padding-top:16px;">
			<?php
			$preview_thumb = \YouSync\Video_Importer::get_best_thumbnail( $thumbnails );
			if ( $preview_thumb ) :
			?>
			<img src="<?php echo esc_url( $preview_thumb['url'] ); ?>" style="max-width:768px; width:100%; height:auto; display:block; margin-bottom:16px; border:1px solid #ddd;" alt="">
			<?php endif; ?>

			<table class="wp-list-table widefat fixed striped" style="max-width:768px;">
				<thead>
					<tr>
						<th style="width:140px;"><?php esc_html_e( 'Size', 'yousync' ); ?></th>
						<th><?php esc_html_e( 'URL', 'yousync' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array( 'maxres', 'standard', 'high', 'medium', 'default' ) as $size ) : ?>
						<?php if ( empty( $thumbnails[ $size ]['url'] ) ) : ?>
							<?php continue; ?>
						<?php endif; ?>
						<tr>
							<td><?php echo esc_html( $thumbnail_size_labels[ $size ] ); ?></td>
							<td><input type="text" value="<?php echo esc_attr( $thumbnails[ $size ]['url'] ); ?>" readonly style="width:100%; font-family:monospace; font-size:11px;" onclick="this.select()"></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>

		<!-- Sync Status -->
		<div id="yousync-panel-sync-status" class="yousync-mb-panel" style="display:none; padding-top:12px;">
			<table class="form-table">
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

				<?php if ( ! empty( $sync_errors ) ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Sync Errors', 'yousync' ); ?></th>
					<td>
						<?php foreach ( $sync_errors as $sync_error ) : ?>
						<p style="margin:0 0 4px; color:#d63638;">
							<?php
							if ( ! empty( $sync_error['timestamp'] ) ) {
								echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $sync_error['timestamp'] ) ) . ' &mdash; ';
							}
							echo esc_html( $sync_error['error'] ?? '' );
							if ( ! empty( $sync_error['code'] ) ) {
								echo ' <code>' . esc_html( $sync_error['code'] ) . '</code>';
							}
							?>
						</p>
						<?php endforeach; ?>
					</td>
				</tr>
				<?php endif; ?>

				<?php if ( ! $sync_source_type && ! $last_synced && ! $sync_count && empty( $sync_errors ) ) : ?>
				<tr>
					<td colspan="2"><p style="margin:0; color:#757575;"><?php esc_html_e( 'This video has not been synced yet.', 'yousync' ); ?></p></td>
				</tr>
				<?php endif; ?>
			</table>
		</div>
	</div>

	<script>
	(function () {
		var tabs   = document.querySelectorAll( '.yousync-mb-tab' );
		var panels = document.querySelectorAll( '.yousync-mb-panel' );
		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var target = this.dataset.tab;
				tabs.forEach( function ( t ) { t.classList.remove( 'nav-tab-active' ); } );
				panels.forEach( function ( p ) { p.style.display = 'none'; } );
				this.classList.add( 'nav-tab-active' );
				document.getElementById( 'yousync-panel-' + target ).style.display = 'block';
			} );
		} );
	}() );
	</script>
	<?php
}

/**
 * Save video metabox data.
 *
 * Merges the manual_edits flag into the existing JSON meta, preserving all
 * YouTube API data previously synced. All other fields are read-only and
 * written only by the sync engine.
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

	// The only user-controlled field is the protect-from-sync flag.
	$data['manual_edits'] = isset( $_POST['yousync_manual_edits'] ) && '1' === $_POST['yousync_manual_edits'];

	update_post_meta( $post_id, '_yousync_video', wp_slash( wp_json_encode( $data ) ) );
}
add_action( 'save_post_yousync_videos', 'yousync_save_video_meta' );

/**
 * Fall back to the YouTube thumbnail URL when no featured image is set.
 *
 * Only applies to yousync_videos posts. If the user has explicitly set a
 * featured image, that takes precedence and this filter is a no-op.
 *
 * @param string     $html             Current featured image HTML.
 * @param int        $post_id          Post ID.
 * @param int        $post_thumbnail_id Attachment ID of the set thumbnail (0 if none).
 * @param string|int[] $size           Requested image size.
 * @param string|array $attr           Additional HTML attributes.
 * @return string HTML img tag, or original $html.
 */
function yousync_post_thumbnail_html( string $html, int $post_id, int $post_thumbnail_id, $size, $attr ): string {
	if ( 'yousync_videos' !== get_post_type( $post_id ) ) {
		return $html;
	}

	// User explicitly set a featured image — respect it.
	if ( $post_thumbnail_id ) {
		return $html;
	}

	$raw  = get_post_meta( $post_id, '_yousync_video', true );
	$data = $raw ? json_decode( $raw, true ) : array();

	if ( empty( $data['thumbnails'] ) || ! is_array( $data['thumbnails'] ) ) {
		return $html;
	}

	$thumb = \YouSync\Video_Importer::get_best_thumbnail( $data['thumbnails'] );

	if ( ! $thumb ) {
		return $html;
	}

	$width  = ! empty( $thumb['width'] ) ? ' width="' . (int) $thumb['width'] . '"' : '';
	$height = ! empty( $thumb['height'] ) ? ' height="' . (int) $thumb['height'] . '"' : '';
	$alt    = esc_attr( get_the_title( $post_id ) );

	return '<img src="' . esc_url( $thumb['url'] ) . '"' . $width . $height . ' alt="' . $alt . '" class="attachment-post-thumbnail size-post-thumbnail wp-post-image">';
}
add_filter( 'post_thumbnail_html', 'yousync_post_thumbnail_html', 10, 5 );

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
function yousync_admin_post_thumbnail_html( string $content, int $post_id, $thumbnail_id ): string {
	if ( 'yousync_videos' !== get_post_type( $post_id ) ) {
		return $content;
	}

	// A featured image is explicitly set — leave it alone.
	if ( $thumbnail_id ) {
		return $content;
	}

	$raw  = get_post_meta( $post_id, '_yousync_video', true );
	$data = $raw ? json_decode( $raw, true ) : array();

	if ( empty( $data['thumbnails'] ) || ! is_array( $data['thumbnails'] ) ) {
		return $content;
	}

	$thumb = \YouSync\Video_Importer::get_best_thumbnail( $data['thumbnails'] );

	if ( ! $thumb ) {
		return $content;
	}

	$preview = '<img'
		. ' src="' . esc_url( $thumb['url'] ) . '"'
		. ' onclick="document.getElementById(\'set-post-thumbnail\').click()"'
		. ' title="' . esc_attr__( 'Click to set a custom featured image', 'yousync' ) . '"'
		. ' alt=""'
		. '>';
	$label   = '<p class="hide-if-no-js howto" id="set-post-thumbnail-desc">'
		. esc_html__( 'Click the YouTube thumbnail to edit or update', 'yousync' )
		. '</p>';

	return $preview . $label . $content;
}
add_filter( 'admin_post_thumbnail_html', 'yousync_admin_post_thumbnail_html', 10, 3 );

/**
 * Reorder YouSync submenu items.
 *
 * Enforces the order: Videos, Add New Video, Categories, Tags, Channels, Playlists, Settings.
 *
 * Uses str_contains for taxonomy slugs because WordPress omits the &post_type=
 * suffix when a taxonomy uses a custom show_in_menu string.
 *
 * @return void
 */
function yousync_reorder_submenu(): void {
	global $submenu;

	$parent = 'edit.php?post_type=yousync_videos';

	if ( empty( $submenu[ $parent ] ) ) {
		return;
	}

	$position = static function ( string $slug ): int {
		if ( 'edit.php?post_type=yousync_videos' === $slug ) return 0;
		if ( 'post-new.php?post_type=yousync_videos' === $slug ) return 1;
		if ( str_contains( $slug, 'taxonomy=yousync_channel' ) ) return 2;
		if ( str_contains( $slug, 'taxonomy=yousync_playlist' ) ) return 3;
		if ( str_contains( $slug, 'taxonomy=video_category' ) ) return 4;
		if ( str_contains( $slug, 'taxonomy=video_tag' ) ) return 5;
		if ( 'yousync_logs' === $slug ) return 6;
		if ( 'yousync_settings' === $slug ) return 7;
		return PHP_INT_MAX;
	};

	$items = array_values( $submenu[ $parent ] );

	usort( $items, static function ( array $a, array $b ) use ( $position ): int {
		return $position( $a[2] ) - $position( $b[2] );
	} );

	$submenu[ $parent ] = $items;
}
add_action( 'admin_menu', 'yousync_reorder_submenu', 999 );

/**
 * Add thumbnail and protected columns to the yousync_videos post list.
 *
 * Thumbnail is inserted before the title; Protected is appended last.
 *
 * @param array $columns Existing columns.
 * @return array Modified columns.
 */
function yousync_add_video_columns( array $columns ): array {
	$new = array();
	foreach ( $columns as $key => $label ) {
		if ( 'title' === $key ) {
			$new['yousync_thumbnail'] = __( 'Thumbnail', 'yousync' );
		}
		$new[ $key ] = $label;
	}
	$new['yousync_protected'] = '<span style="display:block; text-align:right;">' . esc_html__( 'Protected from Sync Rules', 'yousync' ) . '</span>';
	return $new;
}
add_filter( 'manage_yousync_videos_posts_columns', 'yousync_add_video_columns' );

/**
 * Render the thumbnail and protected columns in the yousync_videos post list.
 *
 * @param string $column  Column slug.
 * @param int    $post_id Post ID.
 * @return void
 */
function yousync_render_video_columns( string $column, int $post_id ): void {
	$meta = get_post_meta( $post_id, '_yousync_video', true );
	$data = $meta ? json_decode( $meta, true ) : array();

	if ( 'yousync_thumbnail' === $column ) {
		$thumbnail = \YouSync\Video_Importer::get_best_thumbnail( $data['thumbnails'] ?? array() );
		$edit_url  = get_edit_post_link( $post_id );
		if ( $thumbnail ) {
			printf(
				'<a href="%s"><img src="%s" width="80" height="45" style="object-fit:cover;border-radius:3px;display:block;" alt="" loading="lazy"></a>',
				esc_url( $edit_url ),
				esc_url( $thumbnail['url'] )
			);
		} else {
			echo '<span style="color:#aaa;">—</span>';
		}
		return;
	}

	if ( 'yousync_protected' === $column ) {
		$is_protected = ! empty( $data['manual_edits'] );
		?>
		<label class="ys-toggle ys-protect-toggle" data-post-id="<?php echo esc_attr( $post_id ); ?>" title="<?php esc_attr_e( 'Protected from Sync Rules', 'yousync' ); ?>" style="display:flex; justify-content:flex-end;">
			<input type="checkbox" class="ys-protect-checkbox" value="1" <?php checked( $is_protected ); ?>>
			<span class="ys-toggle-slider"></span>
		</label>
		<?php
	}
}
add_action( 'manage_yousync_videos_posts_custom_column', 'yousync_render_video_columns', 10, 2 );

/**
 * Enqueue assets for the yousync_videos post list screen.
 *
 * Loads the admin CSS (for the toggle) and an inline JS handler that fires
 * an AJAX request when the protection toggle is clicked in the list column.
 *
 * @return void
 */
function yousync_enqueue_video_list_assets(): void {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-yousync_videos' !== $screen->id ) {
		return;
	}

	wp_enqueue_style(
		'yousync-admin',
		YOUSYNC_PLUGIN_URL . 'assets/css/admin.css',
		array(),
		filemtime( YOUSYNC_PLUGIN_DIR . 'assets/css/admin.css' )
	);

	$nonce = wp_create_nonce( 'yousync_toggle_protection' );
	wp_add_inline_script(
		'jquery',
		'var ysProtect = ' . wp_json_encode( array( 'nonce' => $nonce ) ) . ';
document.addEventListener( "change", function( e ) {
	var cb = e.target;
	if ( ! cb.classList.contains( "ys-protect-checkbox" ) ) return;
	var label  = cb.closest( ".ys-protect-toggle" );
	var postId = label.dataset.postId;
	var fd     = new FormData();
	fd.append( "action",    "yousync_toggle_protection" );
	fd.append( "post_id",   postId );
	fd.append( "protected", cb.checked ? "1" : "0" );
	fd.append( "nonce",     ysProtect.nonce );
	label.style.opacity = "0.5";
	fetch( ajaxurl, { method: "POST", body: fd } )
		.then( function( r ) { return r.json(); } )
		.then( function( res ) {
			label.style.opacity = "1";
			if ( ! res.success ) { cb.checked = ! cb.checked; }
		} )
		.catch( function() {
			label.style.opacity = "1";
			cb.checked = ! cb.checked;
		} );
} );'
	);
}
add_action( 'admin_enqueue_scripts', 'yousync_enqueue_video_list_assets' );

/**
 * AJAX handler — toggle Protect from Sync on a single video post.
 *
 * @return void
 */
function yousync_ajax_toggle_protection(): void {
	check_ajax_referer( 'yousync_toggle_protection', 'nonce' );

	$post_id = (int) ( $_POST['post_id'] ?? 0 );

	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( 'Unauthorized' );
	}

	$raw              = get_post_meta( $post_id, '_yousync_video', true );
	$data             = $raw ? json_decode( $raw, true ) : array();
	$data             = is_array( $data ) ? $data : array();
	$data['manual_edits'] = '1' === ( $_POST['protected'] ?? '0' );

	update_post_meta( $post_id, '_yousync_video', wp_slash( wp_json_encode( $data ) ) );
	wp_send_json_success();
}
add_action( 'wp_ajax_yousync_toggle_protection', 'yousync_ajax_toggle_protection' );