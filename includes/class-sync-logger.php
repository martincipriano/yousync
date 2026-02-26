<?php
/**
 * Sync Logger.
 *
 * Manages a global error log stored in a single WordPress option.
 * Entries are prepended (newest first) and capped at MAX_ENTRIES.
 * Each entry captures the timestamp, source type, source term name,
 * error message, and optional error code.
 *
 * Usage:
 *   Sync_Logger::log_error( 'channel', 42, 'API key invalid.', 'forbidden' );
 *   Sync_Logger::get_log();
 *   Sync_Logger::clear();
 *
 * @package YouSync
 */

namespace YouSync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Sync_Logger
 */
class Sync_Logger {

	/**
	 * WordPress option key for the error log.
	 */
	const OPTION_KEY = 'yousync_error_log';

	/**
	 * Maximum number of log entries to retain.
	 */
	const MAX_ENTRIES = 50;

	/**
	 * Prepend an error entry to the log and trim to MAX_ENTRIES.
	 *
	 * @param string $source_type   'channel' or 'playlist'.
	 * @param int    $source_term_id WordPress term ID of the source.
	 * @param string $message       Human-readable error message.
	 * @param string $code          Optional error code (e.g. WP_Error code).
	 * @return void
	 */
	public static function log_error(
		string $source_type,
		int $source_term_id,
		string $message,
		string $code = ''
	): void {
		$term        = get_term( $source_term_id );
		$source_name = ( $term && ! is_wp_error( $term ) ) ? $term->name : '';

		$entry = array(
			'timestamp'      => time(),
			'source_type'    => $source_type,
			'source_name'    => $source_name,
			'source_term_id' => $source_term_id,
			'message'        => $message,
			'code'           => $code,
		);

		$log = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		array_unshift( $log, $entry );

		if ( count( $log ) > self::MAX_ENTRIES ) {
			$log = array_slice( $log, 0, self::MAX_ENTRIES );
		}

		// false = do not autoload — the log is only needed on the Logs admin page.
		update_option( self::OPTION_KEY, $log, false );
	}

	/**
	 * Retrieve all log entries (newest first).
	 *
	 * @return array Array of entry arrays.
	 */
	public static function get_log(): array {
		$log = get_option( self::OPTION_KEY, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * Delete all log entries.
	 *
	 * @return void
	 */
	public static function clear(): void {
		delete_option( self::OPTION_KEY );
	}
}
