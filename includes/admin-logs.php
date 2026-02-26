<?php
/**
 * Logs admin page.
 *
 * Registers the "Logs" submenu under the YouSync Videos post type and
 * renders a table of error log entries stored in the yousync_error_log option.
 *
 * @package YouSync
 */

namespace YouSync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Logs submenu page.
 *
 * @return void
 */
function yousync_add_logs_submenu(): void {
	add_submenu_page(
		'edit.php?post_type=yousync_videos',
		__( 'YouSync Logs', 'yousync' ),
		__( 'Logs', 'yousync' ),
		'manage_options',
		'yousync_logs',
		__NAMESPACE__ . '\yousync_render_logs_page'
	);
}
add_action( 'admin_menu', __NAMESPACE__ . '\yousync_add_logs_submenu', 20 );

/**
 * Render the Logs admin page.
 *
 * Handles the "Clear All Logs" POST action and displays the error log table
 * with an optional source-type filter.
 *
 * @return void
 */
function yousync_render_logs_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Handle clear action.
	$cleared = false;
	if (
		isset( $_POST['yousync_clear_logs_nonce'] ) &&
		wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['yousync_clear_logs_nonce'] ) ),
			'yousync_clear_logs'
		)
	) {
		Sync_Logger::clear();
		$cleared = true;
	}

	// Retrieve and optionally filter the log.
	$log         = Sync_Logger::get_log();
	$filter_type = isset( $_GET['source_type'] ) ? sanitize_key( $_GET['source_type'] ) : '';

	if ( $filter_type && in_array( $filter_type, array( 'channel', 'playlist' ), true ) ) {
		$log = array_values(
			array_filter(
				$log,
				static fn( array $e ) => ( $e['source_type'] ?? '' ) === $filter_type
			)
		);
	}

	$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'YouSync Error Logs', 'yousync' ); ?></h1>

		<?php if ( $cleared ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Error log cleared.', 'yousync' ); ?></p>
		</div>
		<?php endif; ?>

		<div style="display:flex; align-items:center; justify-content:space-between; margin:16px 0 12px;">
			<form method="get" style="margin:0;">
				<input type="hidden" name="page" value="yousync_logs">
				<label for="yousync_logs_filter" class="screen-reader-text"><?php esc_html_e( 'Filter by Source', 'yousync' ); ?></label>
				<select id="yousync_logs_filter" name="source_type">
					<option value=""><?php esc_html_e( 'All Sources', 'yousync' ); ?></option>
					<option value="channel" <?php selected( $filter_type, 'channel' ); ?>><?php esc_html_e( 'Channels', 'yousync' ); ?></option>
					<option value="playlist" <?php selected( $filter_type, 'playlist' ); ?>><?php esc_html_e( 'Playlists', 'yousync' ); ?></option>
				</select>
				<?php submit_button( __( 'Filter', 'yousync' ), 'secondary', '', false ); ?>
			</form>

			<?php if ( ! empty( Sync_Logger::get_log() ) ) : ?>
			<form method="post" style="margin:0;">
				<?php wp_nonce_field( 'yousync_clear_logs', 'yousync_clear_logs_nonce' ); ?>
				<?php submit_button( __( 'Clear All Logs', 'yousync' ), 'delete', '', false ); ?>
			</form>
			<?php endif; ?>
		</div>

		<?php if ( empty( $log ) ) : ?>
		<p><?php esc_html_e( 'No errors logged.', 'yousync' ); ?></p>
		<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width:160px;"><?php esc_html_e( 'Time', 'yousync' ); ?></th>
					<th style="width:90px;"><?php esc_html_e( 'Source', 'yousync' ); ?></th>
					<th style="width:160px;"><?php esc_html_e( 'Name', 'yousync' ); ?></th>
					<th><?php esc_html_e( 'Message', 'yousync' ); ?></th>
					<th style="width:120px;"><?php esc_html_e( 'Code', 'yousync' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $log as $entry ) : ?>
				<tr>
					<td><?php echo esc_html( wp_date( $date_format, $entry['timestamp'] ?? 0 ) ); ?></td>
					<td><?php echo esc_html( ucfirst( $entry['source_type'] ?? '' ) ); ?></td>
					<td>
						<?php
						$term_id = (int) ( $entry['source_term_id'] ?? 0 );
						$name    = $entry['source_name'] ?? '';
						if ( $term_id ) {
							$taxonomy = 'channel' === ( $entry['source_type'] ?? '' ) ? 'yousync_channel' : 'yousync_playlist';
							$edit_url = get_edit_term_link( $term_id, $taxonomy );
							echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html( $name ?: '#' . $term_id ) . '</a>';
						} else {
							echo esc_html( $name );
						}
						?>
					</td>
					<td><?php echo esc_html( $entry['message'] ?? '' ); ?></td>
					<td>
						<?php if ( ! empty( $entry['code'] ) ) : ?>
						<code><?php echo esc_html( $entry['code'] ); ?></code>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
	</div>
	<?php
}
