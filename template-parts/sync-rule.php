<?php
/**
 * Template part for displaying Channel sync rule.
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

$term_id    = isset( $term_id ) ? (int) $term_id : 0;
$source_type = isset( $source_type ) ? $source_type : 'channel';
$enabled         = isset( $rule['enabled'] ) ? $rule['enabled'] : true;
$schedule        = isset( $rule['schedule'] ) ? $rule['schedule'] : 'daily';
$custom_schedule = isset( $rule['custom_schedule'] ) ? $rule['custom_schedule'] : 1;
$action          = isset( $rule['action'] ) ? $rule['action'] : '';
$conditions      = isset( $rule['conditions'] ) ? $rule['conditions'] : array();
$specific_metadata = isset( $rule['specific_metadata'] ) ? $rule['specific_metadata'] : array();

// Dual-mode: Use provided $index or fallback to {{INDEX}} placeholder for JavaScript
$rule_index = isset( $index ) ? $index : '{{INDEX}}';

// Determine the resource type from the action (used for field options and metadata options)
$resource = '';
if ( strpos( $action, 'channel' ) === 0 ) {
	$resource = 'channel';
} elseif ( strpos( $action, 'playlists' ) === 0 ) {
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
				<optgroup label="Channel">
					<option data-resource="channel" value="channel_update_all" <?php selected( $action, 'channel_update_all' ); ?>>Update metadata for this channel</option>
					<option data-resource="channel" value="channel_update_specific" <?php selected( $action, 'channel_update_specific' ); ?>>Update specific metadata for this channel</option>
				</optgroup>
				<optgroup label="Playlists">
					<option data-resource="playlist" value="playlists_sync_new" <?php selected( $action, 'playlists_sync_new' ); ?>>Sync new playlists</option>
					<option data-resource="playlist" value="playlists_update_all" <?php selected( $action, 'playlists_update_all' ); ?>>Update metadata for all playlists</option>
					<option data-resource="playlist" value="playlists_update_non_modified" <?php selected( $action, 'playlists_update_non_modified' ); ?>>Update metadata for non modified playlists</option>
					<option data-resource="playlist" value="playlists_update_specific_all" <?php selected( $action, 'playlists_update_specific_all' ); ?>>Update specific metadata for all playlists</option>
					<option data-resource="playlist" value="playlists_update_specific_non_modified" <?php selected( $action, 'playlists_update_specific_non_modified' ); ?>>Update specific metadata for non modified playlists</option>
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
							'rule_index'        => $rule_index,
							'condition_index'   => $condition_index,
							'condition'         => $condition,
							'field_options_html' => $cond_field_options_html,
							'operator_html'     => $cond_operator_html,
							'value_html'        => $cond_value_html,
						) );
					}
				}
				?>
			</div>
		</fieldset>

	<?php
	$rule_sync_status = $rule['sync_status'] ?? '';
	$rule_last_synced = (int) ( $rule['last_synced'] ?? 0 );
	$rule_sync_errors = is_array( $rule['sync_errors'] ?? null ) ? $rule['sync_errors'] : array();
	$rule_next_run    = ( $term_id && '{{INDEX}}' !== $rule_index )
		? wp_next_scheduled( 'yousync_sync_rule', array( $source_type, $term_id, (int) $rule_index ) )
		: false;
	if ( $rule_sync_status || $rule_last_synced || $rule_next_run ) :
		$status_colors = array(
			'success' => '#00a32a',
			'failed'  => '#d63638',
		);
	?>
	<div class="ys-rule-history ys-mt-3">
		<?php if ( $rule_sync_status ) : ?>
		<p class="ys-mb-0">
			<strong><?php esc_html_e( 'Status:', 'yousync' ); ?></strong>
			<span style="color:<?php echo esc_attr( $status_colors[ $rule_sync_status ] ?? '#757575' ); ?>; font-weight:600;">
				<?php echo esc_html( ucfirst( str_replace( '_', ' ', $rule_sync_status ) ) ); ?>
			</span>
		</p>
		<?php endif; ?>
		<?php if ( $rule_last_synced ) : ?>
		<p class="ys-mb-0">
			<strong><?php esc_html_e( 'Last Synced:', 'yousync' ); ?></strong>
			<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $rule_last_synced ) ); ?>
		</p>
		<?php endif; ?>
		<?php if ( $rule_next_run ) : ?>
		<p class="ys-mb-0">
			<strong><?php esc_html_e( 'Next Run:', 'yousync' ); ?></strong>
			<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $rule_next_run ) ); ?>
		</p>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<p class="ys-quota-estimate description ys-mb-0"><strong>Estimated Quota:</strong> <span class="ys-quota-value"></span></p>

	<?php if ( ! empty( $rule_sync_errors ) ) : ?>
	<div class="ys-rule-errors ys-mt-3" style="font-size:12px;">
		<?php foreach ( $rule_sync_errors as $err ) : ?>
		<p class="ys-mb-0" style="color:#d63638;">
			<?php
			if ( ! empty( $err['timestamp'] ) ) {
				echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $err['timestamp'] ) ) . ' &mdash; ';
			}
			echo esc_html( $err['error'] ?? '' );
			if ( ! empty( $err['code'] ) ) {
				echo ' <code>' . esc_html( $err['code'] ) . '</code>';
			}
			?>
		</p>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	</div>
</div>
