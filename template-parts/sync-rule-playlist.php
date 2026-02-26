<?php
/**
 * Template part for displaying Playlist sync rule.
 *
 * Mirrors sync-rule.php (Channel) in structure and attribute conventions.
 * The only difference is the action dropdown contains Playlist + Video options only.
 *
 * @package YouSync
 *
 * Variables available in this template:
 * @var int|string $index Rule index.
 * @var array      $rule Rule data.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$enabled           = isset( $rule['enabled'] ) ? $rule['enabled'] : true;
$schedule          = isset( $rule['schedule'] ) ? $rule['schedule'] : 'daily';
$custom_schedule   = isset( $rule['custom_schedule'] ) ? $rule['custom_schedule'] : 1;
$action            = isset( $rule['action'] ) ? $rule['action'] : '';
$conditions        = isset( $rule['conditions'] ) ? $rule['conditions'] : array();
$specific_metadata = isset( $rule['specific_metadata'] ) ? $rule['specific_metadata'] : array();

// Dual-mode: Use provided $index or fallback to {{INDEX}} placeholder for JavaScript
$rule_index = isset( $index ) ? esc_attr( $index ) : '{{INDEX}}';

// Determine the resource type from the action
$resource = '';
if ( strpos( $action, 'playlist' ) === 0 ) {
	$resource = 'playlist';
} elseif ( strpos( $action, 'videos' ) === 0 ) {
	$resource = 'video';
}

// Specific metadata — show wrapper when action contains update_specific
$show_specific_metadata = $action && strpos( $action, 'update_specific' ) !== false;
$metadata_options_html  = '';
if ( $show_specific_metadata && $resource ) {
	$metadata_options_html = yousync_return_template_part( 'options', $resource . '-metadata' );
	// Mark saved values as selected
	foreach ( $specific_metadata as $saved_value ) {
		$metadata_options_html = str_replace(
			'value="' . esc_attr( $saved_value ) . '"',
			'value="' . esc_attr( $saved_value ) . '" selected',
			$metadata_options_html
		);
	}
}

// Field options HTML for conditions (based on resource)
$field_options_tpl = $resource ? yousync_return_template_part( 'options', $resource . '-fields' ) : '';
?>

<div class="ys-sync-rule" data-rule-index="<?php echo $rule_index; ?>">

	<div class="ys-sync-rule-header">
		<label class="ys-toggle">
			<input <?php checked( $enabled, true ); ?> class="ys-rule-toggle" name="sync_rules[<?php echo $rule_index; ?>][enabled]" type="checkbox" value="1">
			<span class="ys-toggle-slider"></span>
		</label>
		<button type="button" class="button ys-remove-rule">
			<?php esc_html_e( 'Remove', 'yousync' ); ?>
		</button>
	</div>

	<div class="ys-sync-rule-body">
		<div class="ys-2-columns">
			<div class="ys-form-group">
				<label for="ys-sync-schedule-<?php echo $rule_index; ?>">Sync Schedule</label>
				<select class="ys-select ys-sync-schedule" id="ys-sync-schedule-<?php echo $rule_index; ?>" name="sync_rules[<?php echo $rule_index; ?>][schedule]" required>
					<?php yousync_get_template_part( 'options', 'schedule', array( 'selected' => $schedule ) ); ?>
				</select>
			</div>
			<div class="ys-form-group">
				<label for="ys-custom-schedule-<?php echo $rule_index; ?>">Custom (Hours)</label>
				<input class="ys-number ys-custom-sync-schedule" <?php echo 'custom' !== $schedule ? 'disabled' : ''; ?> id="ys-custom-schedule-<?php echo $rule_index; ?>" name="sync_rules[<?php echo $rule_index; ?>][custom_schedule]" value="<?php echo esc_attr( $custom_schedule ); ?>" min="1" placeholder="Eg. 24" type="number">
			</div>
		</div>

		<div class="ys-form-group">
			<label for="ys-action-<?php echo $rule_index; ?>">Action</label>
			<select class="ys-select ys-action" id="ys-action-<?php echo $rule_index; ?>" name="sync_rules[<?php echo $rule_index; ?>][action]" required>
				<option disabled <?php selected( $action, '' ); ?> value="">&mdash; Select action &mdash;</option>
				<optgroup label="Playlist">
					<option data-resource="playlist" value="playlist_update_all" <?php selected( $action, 'playlist_update_all' ); ?>>Update metadata for this playlist</option>
					<option data-resource="playlist" value="playlist_update_specific" <?php selected( $action, 'playlist_update_specific' ); ?>>Update specific metadata for this playlist</option>
				</optgroup>
				<optgroup label="Videos">
					<option data-resource="video" value="videos_sync_new" <?php selected( $action, 'videos_sync_new' ); ?>>Sync new videos</option>
					<option data-resource="video" value="videos_update_all" <?php selected( $action, 'videos_update_all' ); ?>>Update metadata for all videos</option>
					<option data-resource="video" value="videos_update_non_modified" <?php selected( $action, 'videos_update_non_modified' ); ?>>Update metadata for non modified videos</option>
					<option data-resource="video" value="videos_update_specific_all" <?php selected( $action, 'videos_update_specific_all' ); ?>>Update specific metadata for all videos</option>
					<option data-resource="video" value="videos_update_specific_non_modified" <?php selected( $action, 'videos_update_specific_non_modified' ); ?>>Update specific metadata for non modified videos</option>
				</optgroup>
			</select>
		</div>

		<div class="ys-form-group <?php echo $show_specific_metadata ? '' : 'ys-hidden'; ?> ys-specific-metadata-wrapper">
			<label for="ys-specific-metadata-<?php echo $rule_index; ?>">Fields to Update</label>
			<select class="ys-select ys-specific-metadata" id="ys-specific-metadata-<?php echo $rule_index; ?>" name="sync_rules[<?php echo $rule_index; ?>][specific_metadata][]" multiple placeholder="Select metadata to update...">
				<?php if ( $show_specific_metadata ) echo $metadata_options_html; ?>
			</select>
		</div>

		<fieldset class="ys-fieldset ys-conditions-wrapper">
			<legend class="ys-mt-4"><strong>Conditions</strong> &mdash; <a class="ys-add-condition" href="#">Add condition</a></legend>
			<p class="description ys-mb-3">Add conditions to filter which videos are synced. Videos must match <strong>all</strong> conditions (AND logic). <br> To sync videos matching <strong>any</strong> condition (OR logic), create multiple sync rules.</p>
			<div class="ys-conditions" data-rule-index="<?php echo $rule_index; ?>" data-resource="<?php echo esc_attr( $resource ); ?>">
				<?php
				// Render existing conditions with pre-populated field/operator/value
				if ( ! empty( $conditions ) && is_array( $conditions ) ) {
					foreach ( $conditions as $condition_index => $condition ) {
						$cond_field    = isset( $condition['field'] ) ? $condition['field'] : '';
						$cond_operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
						$cond_value    = isset( $condition['value'] ) ? $condition['value'] : '';

						// Build field options with saved field pre-selected
						$cond_field_options_html = $field_options_tpl ?: null;
						if ( $cond_field_options_html && $cond_field ) {
							// Remove selected from the blank placeholder
							$cond_field_options_html = str_replace(
								'<option disabled selected value="">',
								'<option disabled value="">',
								$cond_field_options_html
							);
							// Mark the saved field as selected
							$cond_field_options_html = str_replace(
								'value="' . esc_attr( $cond_field ) . '"',
								'value="' . esc_attr( $cond_field ) . '" selected',
								$cond_field_options_html
							);
						}

						// Build operator options and value input if a field is selected
						$cond_operator_html = null;
						$cond_value_html    = null;
						if ( $cond_field ) {
							$field_type = yousync_get_condition_field_type( $cond_field );
							if ( $field_type ) {
								$cond_operator_html = yousync_return_template_part(
									'options',
									$field_type . '-operators',
									array( 'operator' => $cond_operator )
								);
								$cond_value_html = yousync_return_template_part(
									'input',
									$field_type,
									array(
										'rule_index'      => $rule_index,
										'condition_index' => $condition_index,
										'value'           => $cond_value,
										'disabled'        => false,
									)
								);
							}
						}

						yousync_get_template_part( 'sync-rule', 'condition', array(
							'rule_index'         => $rule_index,
							'condition_index'    => $condition_index,
							'condition'          => $condition,
							'field_options_html' => $cond_field_options_html,
							'operator_html'      => $cond_operator_html,
							'value_html'         => $cond_value_html,
						) );
					}
				}
				?>
			</div>
		</fieldset>

	</div>
</div>
