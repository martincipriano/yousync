<?php
/**
 * YouSync Settings Class
 *
 * @package YouSync
 */

namespace YouSync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * YouSync Settings Class
 */
class YouSyncSettings {

	/**
	 * Custom archives configuration.
	 *
	 * @var array
	 */
	private $custom_archives = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->custom_archives = array(
			array(
				'labels' => array(
					'name'          => __( 'Videos', 'yousync' ),
					'singular_name' => __( 'Video', 'yousync' ),
				),
				'slug'   => 'ys-video',
				'type'   => 'post_type',
			),
			array(
				'labels' => array(
					'name'          => __( 'Channels', 'yousync' ),
					'singular_name' => __( 'Channel', 'yousync' ),
				),
				'slug'   => 'ys-channel',
				'type'   => 'taxonomy',
			),
			array(
				'labels' => array(
					'name'          => __( 'Playlists', 'yousync' ),
					'singular_name' => __( 'Playlist', 'yousync' ),
				),
				'slug'   => 'ys-playlist',
				'type'   => 'taxonomy',
			),
		);

		add_action( 'admin_menu', array( $this, 'add_settings_submenu' ), 20 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ) );
	}

	/**
	 * Add settings submenu page.
	 *
	 * @return void
	 */
	public function add_settings_submenu() {
		add_submenu_page(
			'edit.php?post_type=yousync_videos',
			__( 'YouSync Settings', 'yousync' ),
			__( 'Settings', 'yousync' ),
			'manage_options',
			'yousync_settings',
			array( $this, 'settings_html' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'yousync_settings_group',
			'yousync_api_key',
			array(
				'sanitize_callback' => array( $this, 'validate_api_key' ),
			)
		);

		register_setting(
			'yousync_settings_group',
			'yousync_active_archives',
			array(
				'sanitize_callback' => array( $this, 'sanitize_active_archives' ),
				'default'           => array(),
			)
		);

		add_settings_section(
			'yousync_api_settings',
			__( 'API Settings', 'yousync' ),
			null,
			'yousync_settings'
		);

		add_settings_field(
			'yousync_api_key',
			__( 'Google API Key', 'yousync' ),
			array( $this, 'api_key_html' ),
			'yousync_settings',
			'yousync_api_settings'
		);

		add_settings_field(
			'yousync_playlist_archive',
			__( 'Enabled Archive Pages', 'yousync' ),
			array( $this, 'active_archive_html' ),
			'yousync_settings',
			'yousync_api_settings'
		);
	}

	/**
	 * Render API key field HTML.
	 *
	 * @return void
	 */
	public function api_key_html() {
		$value = get_option( 'yousync_api_key', '' );
		yousync_get_template_part( 'settings-field', 'api-key', compact( 'value' ) );
	}

	/**
	 * Render active archives field HTML.
	 *
	 * @return void
	 */
	public function active_archive_html() {
		$active_archives = get_option( 'yousync_active_archives', array() );
		$custom_archives = $this->custom_archives;
		yousync_get_template_part( 'settings-field', 'active-archives', compact( 'active_archives', 'custom_archives' ) );
	}

	/**
	 * Check if rewrite rules need to be flushed and flush them.
	 *
	 * @return void
	 */
	public function maybe_flush_rewrite_rules() {
		if ( get_transient( 'yousync_flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
			delete_transient( 'yousync_flush_rewrite_rules' );
		}
	}

	/**
	 * Sanitize active archives input.
	 *
	 * @param mixed $input The input to sanitize.
	 * @return array The sanitized input.
	 */
	public function sanitize_active_archives( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $input as $slug => $data ) {
			$sanitized_slug               = sanitize_key( $slug );
			$sanitized[ $sanitized_slug ] = array(
				'enabled' => isset( $data['enabled'] ) ? (bool) $data['enabled'] : false,
				'slug'    => isset( $data['slug'] ) ? sanitize_title( $data['slug'] ) : '',
			);
		}

		// Flush rewrite rules when archive settings change.
		$old_value = get_option( 'yousync_active_archives', array() );
		if ( $old_value !== $sanitized ) {
			// Set a flag to flush rewrite rules on next page load.
			set_transient( 'yousync_flush_rewrite_rules', true, 300 );
		}

		return $sanitized;
	}

	/**
	 * Validate and sanitize API key.
	 *
	 * @param string $input The API key to validate.
	 * @return string The validated API key.
	 */
	public function validate_api_key( $input ) {
		$input    = sanitize_text_field( $input );
		$response = wp_remote_get( "https://www.googleapis.com/youtube/v3/videos?part=id&id=dQw4w9WgXcQ&key={$input}" );

		if ( empty( $input ) ) {
			add_settings_error( 'yousync_api_key', 'yousync_api_key_empty', __( 'API key cannot be empty.', 'yousync' ) );
			return '';
		}

		if ( is_wp_error( $response ) ) {
			add_settings_error( 'yousync_api_key', 'yousync_api_key_request_error', __( 'Could not connect to YouTube API.', 'yousync' ) );
			return get_option( 'yousync_api_key', '' );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown error';
			add_settings_error(
				'yousync_api_key',
				'yousync_api_key_invalid',
				/* translators: %s: Error message from YouTube API */
				sprintf( __( 'YouTube API error: %s', 'yousync' ), esc_html( $error_msg ) )
			);
			return get_option( 'yousync_api_key', '' );
		}

		add_settings_error( 'yousync_api_key', 'valid_api_key', __( 'API key saved successfully!', 'yousync' ), 'updated' );

		return $input;
	}

	/**
	 * Render settings page HTML.
	 *
	 * @return void
	 */
	public function settings_html() {
		yousync_get_template_part( 'settings', 'page' );
	}
}

new YouSyncSettings();
