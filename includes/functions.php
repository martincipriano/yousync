<?php
declare(strict_types=1);
/**
 * Buoy Video Sync — General helper functions.
 *
 * @package Buoy_Video_Sync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Valid sync rule action values ('' = a rule that has not been configured yet).
 *
 * @return string[]
 */
function buoyvs_valid_actions(): array {
	return array( '', 'videos_sync_new', 'playlists_sync_new', 'channel_sync_new' );
}

/**
 * Valid sync rule schedule values.
 *
 * @return string[]
 */
function buoyvs_valid_schedules(): array {
	return array( 'once', 'hourly', 'daily', 'weekly', 'monthly', 'custom' );
}

/**
 * Valid sync rule post_status values.
 *
 * @return string[]
 */
function buoyvs_valid_post_statuses(): array {
	return array( 'publish', 'draft', 'private' );
}

/**
 * Get the configured channel as a single flat object.
 *
 * The channel is stored as a flat object in the buoyvs_channel_config option.
 *
 * @return array The channel config, or an empty array if none.
 */
function buoyvs_get_channel_config(): array {
	$config = get_option( 'buoyvs_channel_config', array() );
	return is_array( $config ) ? $config : array();
}

/**
 * Sanitize a single sync rule from form input.
 *
 * Shared by Channel, Playlist, and Channels_Page save handlers.
 *
 * @param array $rule Raw rule data from $_POST.
 * @return array Sanitized rule.
 */
function buoyvs_sanitize_sync_rule( $rule ) {
	$action = isset( $rule['action'] ) ? sanitize_text_field( $rule['action'] ) : '';
	// Unknown values sanitize to '' so the rule renders as unconfigured.
	if ( ! in_array( $action, buoyvs_valid_actions(), true ) ) {
		$action = '';
	}

	$schedule = isset( $rule['schedule'] ) ? sanitize_text_field( $rule['schedule'] ) : 'once';
	if ( ! in_array( $schedule, buoyvs_valid_schedules(), true ) ) {
		$schedule = 'once';
	}

	$post_status = isset( $rule['post_status'] ) ? sanitize_text_field( $rule['post_status'] ) : 'publish';
	if ( ! in_array( $post_status, buoyvs_valid_post_statuses(), true ) ) {
		$post_status = 'publish';
	}

	$sanitized = array(
		'enabled'         => isset( $rule['enabled'] ) ? (bool) $rule['enabled'] : false,
		'title'           => isset( $rule['title'] ) ? sanitize_text_field( $rule['title'] ) : '',
		'max_videos'      => isset( $rule['max_videos'] ) ? absint( $rule['max_videos'] ) : 50,
		'schedule'        => $schedule,
		'custom_schedule' => isset( $rule['custom_schedule'] ) ? absint( $rule['custom_schedule'] ) : 24,
		'action'          => $action,
		'destination_post_type' => isset( $rule['destination_post_type'] ) ? sanitize_key( $rule['destination_post_type'] ) : '',
		'post_status'     => $post_status,
	);

	return $sanitized;
}

/**
 * Collect and validate extra metabox tabs registered by other plugins.
 *
 * @param string $type    Metabox type: 'video', 'playlist', or 'channel'.
 * @param int    $post_id Current post ID.
 * @return array<int,array> Validated tabs, each [ 'slug', 'label', 'render' ].
 */
function buoyvs_get_metabox_tabs( string $type, int $post_id ): array {
	/**
	 * Filter the extra tabs shown in the video/playlist/channel metabox.
	 *
	 * Each tab is an array:
	 *   [ 'slug' => string, 'label' => string, 'render' => callable( int $post_id ) ]
	 * The render callback echoes the tab panel's inner content.
	 *
	 * @param array  $tabs    List of tab definitions. Default empty.
	 * @param string $type    Metabox type: 'video', 'playlist', or 'channel'.
	 * @param int    $post_id Current post ID.
	 */
	$tabs = apply_filters( 'buoyvs_metabox_tabs', array(), $type, $post_id );

	$valid = array();
	if ( is_array( $tabs ) ) {
		foreach ( $tabs as $tab ) {
			if ( ! empty( $tab['slug'] ) && ! empty( $tab['label'] ) && isset( $tab['render'] ) && is_callable( $tab['render'] ) ) {
				$valid[] = $tab;
			}
		}
	}
	return $valid;
}

/**
 * Render extra metabox tab buttons (call inside .buoyvs-channel-tabs-nav).
 *
 * @param string $type    Metabox type: 'video', 'playlist', or 'channel'.
 * @param int    $post_id Current post ID.
 * @return void
 */
function buoyvs_render_extra_tab_nav( string $type, int $post_id ): void {
	foreach ( buoyvs_get_metabox_tabs( $type, $post_id ) as $tab ) {
		printf(
			'<button type="button" class="buoyvs-channel-tab-btn" data-tab="%1$s" role="tab" aria-selected="false">%2$s</button>',
			esc_attr( $tab['slug'] ),
			esc_html( $tab['label'] )
		);
	}
}

/**
 * Render extra metabox tab panels (call inside .buoyvs-channel-tabs-content).
 *
 * @param string $type    Metabox type: 'video', 'playlist', or 'channel'.
 * @param int    $post_id Current post ID.
 * @return void
 */
function buoyvs_render_extra_tab_panels( string $type, int $post_id ): void {
	foreach ( buoyvs_get_metabox_tabs( $type, $post_id ) as $tab ) {
		printf(
			'<div class="buoyvs-channel-tab-panel buoyvs-hidden" data-panel="%s" role="tabpanel">',
			esc_attr( $tab['slug'] )
		);
		call_user_func( $tab['render'], $post_id );
		echo '</div>';
	}
}
