<?php
/**
 * Template part for displaying Playlist sync rule.
 *
 * @package YouSync
 *
 * Variables available in this template:
 * @var int|string $index Rule index.
 * @var array      $rule Rule data.
 * @var object     $playlist_obj Playlist class instance.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$enabled = isset( $rule['enabled'] ) ? $rule['enabled'] : true;
$schedule = isset( $rule['schedule'] ) ? $rule['schedule'] : 'daily';
$custom_hours = $rule['custom_hours'] ?? 1;
$strategy = isset( $rule['strategy'] ) ? $rule['strategy'] : '';
$index = esc_attr( $index ); ?>

<div class="ys-sync-rule" data-rule-index="<?php echo $index; ?>">

	<div class="ys-sync-rule-header">
		<label class="ys-toggle">
			<input <?php checked( $enabled, true ); ?> class="ys-rule-toggle" name="sync_rules[<?php echo $index; ?>][enabled]" type="checkbox" value="1">
			<span class="ys-toggle-slider"></span>
		</label>
		<button type="button" class="button ys-remove-rule">
			<?php esc_html_e( 'Remove', 'yousync' ); ?>
		</button>
	</div>

	<div class="ys-sync-rule-body">
		<div class="ys-2-columns">
			<div class="ys-form-group">
				<label for="ys-sync-schedule-<?php echo $index; ?>">Sync Schedule</label>
				<select class="ys-select ys-sync-schedule" id="ys-sync-schedule-<?php echo $index; ?>" name="sync_rules[<?php echo $index; ?>][schedule]" required>
					<option value="hourly" <?php selected( $schedule, 'hourly' ); ?>>Hourly</option>
					<option value="daily" <?php selected( $schedule, 'daily' ); ?>>Daily</option>
					<option value="weekly" <?php selected( $schedule, 'weekly' ); ?>>Weekly</option>
					<option value="monthly" <?php selected( $schedule, 'monthly' ); ?>>Monthly</option>
					<option value="custom" <?php selected( $schedule, 'custom' ); ?>>Custom</option>
				</select>
			</div>
			<div class="ys-form-group">
				<label for="ys-custom-hours-<?php echo $index; ?>">Custom (Hours)</label>
				<input class="ys-number ys-custom-sync-schedule" disabled id="ys-custom-hours-<?php echo $index; ?>" name="sync_rules[<?php echo $index; ?>][custom_schedule]" value="<?php echo esc_attr( $custom_hours ); ?>" min="1" placeholder="Eg. 24" type="number">
			</div>
		</div>

		<div class="ys-form-group">
			<label for="ys-action-<?php echo $index; ?>">Action</label>
			<select class="ys-select ys-action" id="ys-action-<?php echo $index; ?>" name="sync_rules[<?php echo $index; ?>][strategy]" required>
				<option disabled <?php selected( $strategy, '' ); ?> value="">Select action</option>
				<optgroup label="Videos">
					<option value="videos_sync_new" <?php selected( $strategy, 'videos_sync_new' ); ?>>Sync new videos</option>
					<option value="videos_update_all" <?php selected( $strategy, 'videos_update_all' ); ?>>Update metadata for all videos</option>
					<option value="videos_update_non_modified" <?php selected( $strategy, 'videos_update_non_modified' ); ?>>Update metadata for non modified videos</option>
					<option value="videos_update_specific_all" <?php selected( $strategy, 'videos_update_specific_all' ); ?>>Update specific metadata for all videos</option>
					<option value="videos_update_specific_non_modified" <?php selected( $strategy, 'videos_update_specific_non_modified' ); ?>>Update specific metadata for non modified videos</option>
				</optgroup>
			</select>
		</div>

		<div class="ys-form-group ys-hidden ys-action-metadata-wrapper">
			<label for="ys-action-metadata-<?php echo $index; ?>">Fields to Update</label>
			<select class="ys-select ys-action-metadata" id="ys-action-metadata-<?php echo $index; ?>" name="sync_rules[<?php echo $index; ?>][fields_to_update][]" multiple placeholder="Select fields to update..."></select>
		</div>

		<fieldset class="ys-fieldset">
			<legend>Conditions</legend>
			<div class="ys-3-columns">
				<div class="ys-form-group">
					<label for="ys-condition-field-<?php echo $index; ?>">Field</label>
					<select class="ys-select ys-condition-field" id="ys-condition-field-<?php echo $index; ?>" name="sync_rules[<?php echo $index; ?>][condition_field]">
						<option value="">Select field</option>
						<optgroup label="Video Fields" data-type="videos">
							<option value="title" data-type="text">Title</option>
							<option value="description" data-type="text">Description</option>
							<option value="tags" data-type="text">Tags</option>
							<option value="duration" data-type="number">Duration</option>
							<option value="published_date" data-type="date">Published Date</option>
							<option value="video_category" data-type="text">Video Category</option>
							<option value="view_count" data-type="number">View Count</option>
							<option value="like_count" data-type="number">Like Count</option>
							<option value="comment_count" data-type="number">Comment Count</option>
						</optgroup>
					</select>
				</div>
				<div class="ys-form-group">
					<label for="ys-condition-operator-<?php echo $index; ?>">Comparison Operator</label>
					<select class="ys-select ys-condition-operator" id="ys-condition-operator-<?php echo $index; ?>" name="sync_rules[<?php echo $index; ?>][condition_operator]">
						<option value="">Select operator</option>
						<optgroup data-type="text" label="Text Operators">
							<option value="contains">Contains</option>
							<option value="not_contains">Does not contain</option>
							<option value="equals">Matches</option>
							<option value="not_equals">Does not match</option>
							<option value="starts_with">Starts with</option>
							<option value="ends_with">Ends with</option>
						</optgroup>
						<optgroup data-type="number" label="Number Operators">
							<option value="greater_than">Greater than</option>
							<option value="less_than">Less than</option>
							<option value="equal_to">Equal to</option>
							<option value="between">Between</option>
						</optgroup>
						<optgroup data-type="date" label="Date Operators">
							<option value="before">Before</option>
							<option value="after">After</option>
							<option value="within">Within (last X days)</option>
							<option value="between_dates">Between (date range)</option>
						</optgroup>
					</select>
				</div>
				<div class="ys-form-group">
					<label for="ys-condition-value-<?php echo $index; ?>">Value</label>
					<input class="ys-text ys-condition-value" id="ys-condition-value-<?php echo $index; ?>" name="sync_rules[<?php echo $index; ?>][condition_value]" type="text" placeholder="Enter value...">
				</div>
			</div>
		</fieldset>

	</div>
</div>
