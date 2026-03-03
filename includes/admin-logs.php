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
	$count      = count( Sync_Logger::get_log() );
	$menu_title = __( 'Logs', 'yousync' );

	if ( $count > 0 ) {
		$menu_title .= ' <span class="awaiting-mod"><span class="pending-count">' . $count . '</span></span>';
	}

	add_submenu_page(
		'edit.php?post_type=yousync_videos',
		__( 'YouSync Logs', 'yousync' ),
		$menu_title,
		'manage_options',
		'yousync_logs',
		__NAMESPACE__ . '\yousync_render_logs_page'
	);
}
add_action( 'admin_menu', __NAMESPACE__ . '\yousync_add_logs_submenu', 20 );

/**
 * Render the Logs admin page.
 *
 * Handles the "Clear All Logs" POST action and displays the error log table.
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

	$log         = Sync_Logger::get_log();
	$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'YouSync Error Logs', 'yousync' ); ?></h1>

		<?php if ( $cleared ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Error log cleared.', 'yousync' ); ?></p>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $log ) ) : ?>
		<div style="margin:16px 0 12px; text-align:right;">
			<form method="post" style="margin:0;">
				<?php wp_nonce_field( 'yousync_clear_logs', 'yousync_clear_logs_nonce' ); ?>
				<?php submit_button( __( 'Clear All Logs', 'yousync' ), 'delete', '', false ); ?>
			</form>
		</div>
		<?php endif; ?>

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
