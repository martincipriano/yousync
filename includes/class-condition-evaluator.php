<?php
/**
 * Sync rule condition evaluator.
 *
 * Stateless class that evaluates a video data array against a conditions
 * array. No WordPress dependencies — pure logic.
 *
 * @package YouSync
 */

namespace YouSync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Condition_Evaluator
 *
 * Evaluates sync rule conditions against a normalised video data array.
 */
class Condition_Evaluator {

	/**
	 * Evaluate all conditions in a rule against a video data array.
	 *
	 * Uses AND logic: every condition must pass. An empty conditions array
	 * means no filtering — all videos pass.
	 *
	 * @param array $video_data  Normalised video data (from YouTube_API::get_videos_by_ids()).
	 * @param array $conditions  Array of condition arrays, each with keys: field, operator, value.
	 * @return bool True if the video passes all conditions.
	 */
	public function evaluate_all( array $video_data, array $conditions ): bool {
		foreach ( $conditions as $condition ) {
			if ( ! $this->evaluate( $video_data, $condition ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Evaluate a single condition against a video data array.
	 *
	 * Routes to the correct type-specific evaluator using
	 * yousync_get_condition_field_type() from yousync.php.
	 *
	 * Returns true for unknown fields (fail-open) to avoid silently
	 * dropping videos when a new field hasn't been wired up yet.
	 *
	 * @param array $video_data Normalised video data.
	 * @param array $condition  Condition array with keys: field, operator, value.
	 * @return bool True if the condition passes.
	 */
	public function evaluate( array $video_data, array $condition ): bool {
		$field    = $condition['field'] ?? '';
		$operator = $condition['operator'] ?? '';
		$value    = $condition['value'] ?? '';

		$field_value = $this->get_field_value( $video_data, $field );

		// Unknown field — fail-open.
		if ( null === $field_value ) {
			return true;
		}

		$type = function_exists( 'yousync_get_condition_field_type' )
			? yousync_get_condition_field_type( $field )
			: 'text';

		switch ( $type ) {
			case 'number':
				return $this->evaluate_number( (float) $field_value, $operator, $value );

			case 'date':
				return $this->evaluate_date( (string) $field_value, $operator, $value );

			default:
				// Special handling for tags: evaluate each tag individually.
				if ( 'tags' === $field && in_array( $operator, array( 'contains', 'not_contains' ), true ) ) {
					return $this->evaluate_tags( $video_data['tags'] ?? array(), $operator, $value );
				}
				return $this->evaluate_text( (string) $field_value, $operator, $value );
		}
	}

	// -------------------------------------------------------------------------
	// Field value extraction
	// -------------------------------------------------------------------------

	/**
	 * Extract the condition field's value from the video data array.
	 *
	 * @param array  $video_data Normalised video data.
	 * @param string $field      Condition field name.
	 * @return mixed Field value, or null if the field is unknown.
	 */
	private function get_field_value( array $video_data, string $field ): mixed {
		switch ( $field ) {
			case 'video_id':
				return $video_data['video_id'] ?? null;
			case 'title':
				return $video_data['title'] ?? null;
			case 'description':
				return $video_data['description'] ?? null;
			case 'tags':
				// Joined string used for all operators except contains/not_contains
				// (those call evaluate_tags() with the raw array instead).
				return implode( ',', $video_data['tags'] ?? array() );
			case 'duration':
				return isset( $video_data['duration_seconds'] ) ? (int) $video_data['duration_seconds'] : null;
			case 'published_date':
				return $video_data['published_at'] ?? null;
			case 'video_category':
				return $video_data['category_id'] ?? null;
			case 'view_count':
				return isset( $video_data['view_count'] ) ? (int) $video_data['view_count'] : null;
			case 'like_count':
				return isset( $video_data['like_count'] ) ? (int) $video_data['like_count'] : null;
			case 'comment_count':
				return isset( $video_data['comment_count'] ) ? (int) $video_data['comment_count'] : null;
			case 'channel_title':
				return $video_data['channel_title'] ?? null;
			default:
				return null;
		}
	}

	// -------------------------------------------------------------------------
	// Type-specific evaluators
	// -------------------------------------------------------------------------

	/**
	 * Evaluate a text condition.
	 *
	 * All comparisons are case-insensitive (mb_strtolower).
	 *
	 * @param string $field_value Actual value from the video.
	 * @param string $operator    Operator slug.
	 * @param string $cond_value  Value from the condition rule.
	 * @return bool
	 */
	private function evaluate_text( string $field_value, string $operator, string $cond_value ): bool {
		$haystack = mb_strtolower( $field_value );
		$needle   = mb_strtolower( $cond_value );

		switch ( $operator ) {
			case 'contains':
				return mb_strpos( $haystack, $needle ) !== false;
			case 'not_contains':
				return mb_strpos( $haystack, $needle ) === false;
			case 'equals':
				return $haystack === $needle;
			case 'not_equals':
				return $haystack !== $needle;
			case 'starts_with':
				return mb_strpos( $haystack, $needle ) === 0;
			case 'ends_with':
				return mb_substr( $haystack, -mb_strlen( $needle ) ) === $needle;
			default:
				return true;
		}
	}

	/**
	 * Evaluate tags individually against a contains/not_contains check.
	 *
	 * Prevents "php" from matching "phpstorm" through substring matching on
	 * a joined string. Each tag is compared independently.
	 *
	 * @param string[] $tags      Raw tags array from video data.
	 * @param string   $operator  'contains' or 'not_contains'.
	 * @param string   $cond_value Condition value to look for.
	 * @return bool
	 */
	private function evaluate_tags( array $tags, string $operator, string $cond_value ): bool {
		$needle = mb_strtolower( $cond_value );

		foreach ( $tags as $tag ) {
			if ( mb_strtolower( $tag ) === $needle ) {
				return 'contains' === $operator;
			}
		}

		// No exact tag match found.
		return 'not_contains' === $operator;
	}

	/**
	 * Evaluate a numeric condition.
	 *
	 * Both sides are cast to float before comparison.
	 *
	 * @param float  $field_value Actual numeric value.
	 * @param string $operator    Operator slug.
	 * @param string $cond_value  Condition value (will be cast to float).
	 * @return bool
	 */
	private function evaluate_number( float $field_value, string $operator, string $cond_value ): bool {
		$cond_float = (float) $cond_value;

		switch ( $operator ) {
			case 'greater_than':
				return $field_value > $cond_float;
			case 'less_than':
				return $field_value < $cond_float;
			case 'equal_to':
				// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
				return $field_value == $cond_float;
			default:
				return true;
		}
	}

	/**
	 * Evaluate a date condition.
	 *
	 * $field_value is expected to be an ISO 8601 datetime string.
	 * $cond_value is expected to be a Y-m-d date string.
	 * Both are converted to Unix timestamps for comparison.
	 *
	 * @param string $field_value ISO 8601 datetime from video metadata.
	 * @param string $operator    Operator slug.
	 * @param string $cond_value  Date string from the condition rule (Y-m-d).
	 * @return bool
	 */
	private function evaluate_date( string $field_value, string $operator, string $cond_value ): bool {
		$field_ts = strtotime( $field_value );
		$cond_ts  = strtotime( $cond_value );

		if ( false === $field_ts || false === $cond_ts ) {
			return true; // Fail-open on unparseable dates.
		}

		switch ( $operator ) {
			case 'before':
				return $field_ts < $cond_ts;
			case 'after':
				return $field_ts > $cond_ts;
			case 'on':
				return gmdate( 'Y-m-d', $field_ts ) === gmdate( 'Y-m-d', $cond_ts );
			default:
				return true;
		}
	}
}
