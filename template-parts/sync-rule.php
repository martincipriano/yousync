<?php
declare(strict_types=1);
/**
 * Template part for displaying a Channel sync rule.
 *
 * @package Buoy_Video_Sync
 *
 * Variables available in this template:
 * @var int|string $index Rule index.
 * @var array      $rule Rule data.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to buoyvs_get_template_part()'s extract()/include scope, not globals.

$term_id     = isset( $term_id ) ? (int) $term_id : 0;
$source_type = isset( $source_type ) ? $source_type : 'channel';
$name_prefix = isset( $name_prefix ) ? $name_prefix : 'sync_rules';
$enabled         = isset( $rule['enabled'] ) ? $rule['enabled'] : true;
$schedule        = isset( $rule['schedule'] ) ? $rule['schedule'] : 'once';
$custom_schedule = isset( $rule['custom_schedule'] ) ? $rule['custom_schedule'] : 24;
$post_status     = isset( $rule['post_status'] ) ? $rule['post_status'] : 'publish';
$action      = isset( $rule['action'] ) ? $rule['action'] : '';

$max_videos            = isset( $rule['max_videos'] ) ? (int) $rule['max_videos'] : 50;
$destination_post_type = $rule['destination_post_type'] ?? '';

$post_types         = get_post_types( array( 'public' => true ), 'objects' );
$_public_taxonomies = get_taxonomies( array( 'public' => true ) );

// Dual-mode: Use provided $index or fall back to the {{INDEX}} placeholder for JavaScript.
$rule_index = isset( $index ) ? $index : '{{INDEX}}';

// Auto-generated rule label from the action + schedule.
$_action_labels = array(
	'channel_sync_new'   => __( 'Sync this channel', 'buoy-video-sync' ),
	'playlists_sync_new' => __( 'Sync new playlists', 'buoy-video-sync' ),
	'videos_sync_new'    => __( 'Sync new videos', 'buoy-video-sync' ),
);
$_schedule_suffixes = array(
	'once'    => __( 'immediately after enabling and saving', 'buoy-video-sync' ),
	'hourly'  => __( 'every hour', 'buoy-video-sync' ),
	'daily'   => __( 'every day', 'buoy-video-sync' ),
	'weekly'  => __( 'every week', 'buoy-video-sync' ),
	'monthly' => __( 'every month', 'buoy-video-sync' ),
	/* translators: %d: number of hours */
	'custom'  => sprintf( __( 'every %d hours', 'buoy-video-sync' ), $custom_schedule ),
);
$rule_action_label    = $action ? ( $_action_labels[ $action ] ?? $action ) : __( 'New rule', 'buoy-video-sync' );
$rule_label_suffix = $_schedule_suffixes[ $schedule ] ?? $schedule;

$sync_started_at = isset( $rule['sync_started_at'] ) ? (int) $rule['sync_started_at'] : 0;
$is_syncing      = 'syncing' === ( $rule['sync_status'] ?? '' )
	&& $sync_started_at
	&& ( time() - $sync_started_at ) < 1800;

// Determine the resource type from the action (used for the dynamic labels).
$resource = '';
if ( strpos( $action, 'channel' ) === 0 ) {
	$resource = 'channel';
} elseif ( strpos( $action, 'playlists' ) === 0 ) {
	$resource = 'playlist';
} elseif ( strpos( $action, 'videos' ) === 0 ) {
	$resource = 'video';
}

// Sync status line.
$rule_last_synced = (int) ( $rule['last_synced'] ?? 0 );
$rule_sync_errors = is_array( $rule['sync_errors'] ?? null ) ? array_values( $rule['sync_errors'] ) : array();
$is_option_channel  = isset( $is_option_channel ) ? (bool) $is_option_channel : false;

$_df                 = 'F j, Y g:i A';
$rule_stat_created   = ! empty( $rule['created_at'] ) ? wp_date( $_df, (int) $rule['created_at'] ) : '—';
$rule_stat_last_sync = $rule_last_synced ? wp_date( $_df, $rule_last_synced ) : '—';

// Dynamic labels.
$_max_items_label = $resource === 'video'
	? __( 'Videos per run', 'buoy-video-sync' )
	: ( $resource === 'playlist'
		? __( 'Playlists per run', 'buoy-video-sync' )
		: __( 'Items per run', 'buoy-video-sync' ) );

$_post_type_label = 'playlists_sync_new' === $action
	? __( 'Save synced playlists as post type', 'buoy-video-sync' )
	: ( 'channel_sync_new' === $action
		? __( 'Save synced channel as post type', 'buoy-video-sync' )
		: __( 'Save synced videos as post type', 'buoy-video-sync' )
	);
?>

<div class="buoyvs-rule<?php echo $is_syncing ? ' buoyvs-rule--syncing' : ''; ?>" data-rule-index="<?php echo esc_attr( $rule_index ); ?>">

	<?php if ( $is_syncing ) : ?>
	<div class="buoyvs-syncing-overlay" aria-hidden="true">
		<span class="buoyvs-syncing-badge"><?php esc_html_e( 'Syncing...', 'buoy-video-sync' ); ?><span class="buoyvs-syncing-progress"></span></span>
	</div>
	<?php endif; ?>

	<div class="buoyvs-rule-header" role="button" tabindex="0" aria-expanded="true">
		<div class="buoyvs-rule-heading-wrap">
			<div class="buoyvs-rule-heading"><?php echo esc_html( $rule_action_label . ' ' . $rule_label_suffix . '.' ); ?></div>
			<?php if ( ! $enabled ) : ?>
			<p class="buoyvs-rule-disabled-notice"><?php esc_html_e( 'This rule is disabled and won\'t run until re-enabled.', 'buoy-video-sync' ); ?></p>
			<?php endif; ?>
		</div>
		<div class="buoyvs-rule-actions">
			<label class="buoyvs-toggle">
				<input <?php checked( $enabled, true ); ?> class="buoyvs-rule-toggle" name="<?php echo esc_attr( $name_prefix ); ?>[<?php echo esc_attr( $rule_index ); ?>][enabled]" type="checkbox" value="1">
				<span class="buoyvs-toggle-slider"></span>
			</label>
			<?php $has_stat = ! empty( $rule['created_at'] ) || $rule_last_synced; ?>
			<?php if ( $has_stat ) : ?>
				<button type="button" class="buoyvs-sync-info" aria-label="<?php esc_attr_e( 'Sync info', 'buoy-video-sync' ); ?>">
					<div class="buoyvs-sync-info-tooltip" hidden>
						<?php if ( ! empty( $rule['created_at'] ) && ! $rule_last_synced ) : ?><span class="buoyvs-sync-info-item"><span><?php esc_html_e( 'Created:', 'buoy-video-sync' ); ?></span><span><?php echo esc_html( $rule_stat_created ); ?></span></span><?php endif; ?>
						<?php if ( $rule_last_synced ) : ?><span class="buoyvs-sync-info-item"><span><?php esc_html_e( 'Last Sync:', 'buoy-video-sync' ); ?></span><span><?php echo esc_html( $rule_stat_last_sync ); ?></span></span><?php endif; ?>
					</div>
				</button>
			<?php endif; ?>
			<span class="dashicons dashicons-arrow-down-alt2 buoyvs-accordion-icon" aria-hidden="true"></span>
		</div>
	</div>

	<div class="buoyvs-rule-body">

		<div class="buoyvs-2-columns buoyvs-cols-3-1">
			<div class="buoyvs-form-group">
				<label for="buoyvs-action-<?php echo esc_attr( $rule_index ); ?>">
					<?php esc_html_e( 'Action', 'buoy-video-sync' ); ?>
					<span class="buoyvs-help-wrap">
						<button type="button" class="buoyvs-help-btn" aria-label="<?php esc_attr_e( 'More info', 'buoy-video-sync' ); ?>">?</button>
						<span class="buoyvs-help-tooltip" role="tooltip"><?php esc_html_e( 'Choose what to sync from your YouTube channel.', 'buoy-video-sync' ); ?></span>
					</span>
				</label>
				<select class="buoyvs-select buoyvs-action" id="buoyvs-action-<?php echo esc_attr( $rule_index ); ?>" name="<?php echo esc_attr( $name_prefix ); ?>[<?php echo esc_attr( $rule_index ); ?>][action]" required>
					<option value=""><?php esc_html_e( '— Select action —', 'buoy-video-sync' ); ?></option>
					<optgroup label="<?php esc_attr_e( 'Videos', 'buoy-video-sync' ); ?>">
						<option data-resource="video" value="videos_sync_new" <?php selected( $action, 'videos_sync_new' ); ?>><?php esc_html_e( 'Sync new videos', 'buoy-video-sync' ); ?></option>
						<option disabled><?php esc_html_e( 'Update all video details (Pro)', 'buoy-video-sync' ); ?></option>
						<option disabled><?php esc_html_e( 'Update specific video details (Pro)', 'buoy-video-sync' ); ?></option>
					</optgroup>
					<optgroup label="<?php esc_attr_e( 'Playlists', 'buoy-video-sync' ); ?>">
						<option data-resource="playlist" value="playlists_sync_new" <?php selected( $action, 'playlists_sync_new' ); ?>><?php esc_html_e( 'Sync new playlists', 'buoy-video-sync' ); ?></option>
						<option disabled><?php esc_html_e( 'Update all playlist details (Pro)', 'buoy-video-sync' ); ?></option>
						<option disabled><?php esc_html_e( 'Update specific playlist details (Pro)', 'buoy-video-sync' ); ?></option>
					</optgroup>
					<optgroup label="<?php esc_attr_e( 'Channel', 'buoy-video-sync' ); ?>">
						<option data-resource="channel" value="channel_sync_new" <?php selected( $action, 'channel_sync_new' ); ?>><?php esc_html_e( 'Sync this channel', 'buoy-video-sync' ); ?></option>
						<option disabled><?php esc_html_e( "Update this channel's details (Pro)", 'buoy-video-sync' ); ?></option>
						<option disabled><?php esc_html_e( 'Update specific details of this channel (Pro)', 'buoy-video-sync' ); ?></option>
					</optgroup>
				</select>
				<p class="buoyvs-quota-estimate buoyvs-hidden"></p>
			</div>
			<div class="buoyvs-form-group buoyvs-items-per-run-wrapper<?php echo str_starts_with( $action, 'channel_' ) ? ' buoyvs-hidden' : ''; ?>">
				<label for="buoyvs-max-videos-<?php echo esc_attr( $rule_index ); ?>" class="buoyvs-max-items-label">
					<?php echo esc_html( $_max_items_label ); ?>
					<span class="buoyvs-help-wrap">
						<button type="button" class="buoyvs-help-btn" aria-label="<?php esc_attr_e( 'More info', 'buoy-video-sync' ); ?>">?</button>
						<span class="buoyvs-help-tooltip" role="tooltip"><?php esc_html_e( 'Maximum number of items to process per run. Set to 0 for unlimited.', 'buoy-video-sync' ); ?></span>
					</span>
				</label>
				<span class="buoyvs-limit-wrap">
					<input class="buoyvs-number buoyvs-max-videos-input" id="buoyvs-max-videos-<?php echo esc_attr( $rule_index ); ?>" name="<?php echo esc_attr( $name_prefix ); ?>[<?php echo esc_attr( $rule_index ); ?>][max_videos]" type="number" value="<?php echo esc_attr( $max_videos ); ?>" min="0">
					<span class="buoyvs-unlimited-icon<?php echo 0 === $max_videos ? '' : ' buoyvs-hidden'; ?>" title="<?php esc_attr_e( 'Unlimited — click to set a limit', 'buoy-video-sync' ); ?>">
						<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 0 24 24" width="20px" fill="#999" aria-hidden="true"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M18.6 6.62c-1.44 0-2.8.56-3.77 1.53L7.8 14.39c-.64.64-1.49.99-2.4.99-1.87 0-3.39-1.51-3.39-3.38S3.53 8.62 5.4 8.62c.91 0 1.76.35 2.44 1.03l1.13 1 1.51-1.34L9.22 8.2C8.2 7.18 6.84 6.62 5.4 6.62 2.42 6.62 0 9.04 0 12s2.42 5.38 5.4 5.38c1.44 0 2.8-.56 3.77-1.53l7.03-6.24c.64-.64 1.49-.99 2.4-.99 1.87 0 3.39 1.51 3.39 3.38s-1.52 3.38-3.39 3.38c-.9 0-1.76-.35-2.44-1.03l-1.14-1.01-1.51 1.34 1.27 1.12c1.02 1.01 2.37 1.57 3.82 1.57 2.98 0 5.4-2.41 5.4-5.38s-2.42-5.37-5.4-5.37z"/></svg>
					</span>
				</span>
			</div>
		</div>

		<div class="buoyvs-2-columns buoyvs-cols-3-1">
			<div class="buoyvs-form-group">
				<label for="buoyvs-sync-schedule-<?php echo esc_attr( $rule_index ); ?>"><?php esc_html_e( 'Sync schedule', 'buoy-video-sync' ); ?></label>
				<select class="buoyvs-select buoyvs-sync-schedule" id="buoyvs-sync-schedule-<?php echo esc_attr( $rule_index ); ?>" name="<?php echo esc_attr( $name_prefix ); ?>[<?php echo esc_attr( $rule_index ); ?>][schedule]" required>
					<?php buoyvs_get_template_part( 'options', 'schedule', array( 'selected' => $schedule ) ); ?>
				</select>
			</div>
			<div class="buoyvs-form-group buoyvs-custom-schedule-wrapper<?php echo 'custom' !== $schedule ? ' buoyvs-hidden' : ''; ?>">
				<label for="buoyvs-custom-schedule-<?php echo esc_attr( $rule_index ); ?>"><?php esc_html_e( 'Custom (Hours)', 'buoy-video-sync' ); ?></label>
				<input class="buoyvs-number buoyvs-custom-sync-schedule" id="buoyvs-custom-schedule-<?php echo esc_attr( $rule_index ); ?>" name="<?php echo esc_attr( $name_prefix ); ?>[<?php echo esc_attr( $rule_index ); ?>][custom_schedule]" value="<?php echo esc_attr( $custom_schedule ); ?>" min="1" placeholder="<?php esc_attr_e( 'Eg. 24', 'buoy-video-sync' ); ?>" type="number">
			</div>
		</div>

		<?php $_show_post_type = in_array( $action, array( 'videos_sync_new', 'playlists_sync_new', 'channel_sync_new' ), true ); ?>
		<div class="buoyvs-2-columns buoyvs-post-type-wrapper<?php echo $_show_post_type ? '' : ' buoyvs-hidden'; ?>">
			<div class="buoyvs-form-group">
				<label for="buoyvs-dest-post-type-<?php echo esc_attr( $rule_index ); ?>" class="buoyvs-post-type-label">
					<?php echo esc_html( $_post_type_label ); ?>
					<span class="buoyvs-help-wrap">
						<button type="button" class="buoyvs-help-btn" aria-label="<?php esc_attr_e( 'More info', 'buoy-video-sync' ); ?>">?</button>
						<span class="buoyvs-help-tooltip" role="tooltip"><?php esc_html_e( 'The WordPress post type that synced items will be created as.', 'buoy-video-sync' ); ?></span>
					</span>
				</label>
				<select class="buoyvs-select buoyvs-dest-post-type" id="buoyvs-dest-post-type-<?php echo esc_attr( $rule_index ); ?>" name="<?php echo esc_attr( $name_prefix ); ?>[<?php echo esc_attr( $rule_index ); ?>][destination_post_type]" required>
					<option value=""><?php esc_html_e( '— Select post type —', 'buoy-video-sync' ); ?></option>
					<?php foreach ( $post_types as $pt ) : ?>
					<option value="<?php echo esc_attr( $pt->name ); ?>" data-has-taxonomy="<?php echo array_intersect( get_object_taxonomies( $pt->name ), $_public_taxonomies ) ? '1' : '0'; ?>" <?php selected( $destination_post_type, $pt->name ); ?>><?php echo esc_html( $pt->labels->singular_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="buoyvs-form-group">
				<label for="buoyvs-post-status-<?php echo esc_attr( $rule_index ); ?>">
					<?php esc_html_e( 'Post status', 'buoy-video-sync' ); ?>
					<span class="buoyvs-help-wrap">
						<button type="button" class="buoyvs-help-btn" aria-label="<?php esc_attr_e( 'More info', 'buoy-video-sync' ); ?>">?</button>
						<span class="buoyvs-help-tooltip" role="tooltip"><?php esc_html_e( 'The status synced items are saved as. Choose Draft to review before publishing.', 'buoy-video-sync' ); ?></span>
					</span>
				</label>
				<select class="buoyvs-select" id="buoyvs-post-status-<?php echo esc_attr( $rule_index ); ?>" name="<?php echo esc_attr( $name_prefix ); ?>[<?php echo esc_attr( $rule_index ); ?>][post_status]">
					<?php buoyvs_get_template_part( 'options', 'post-status', array( 'selected' => $post_status ) ); ?>
				</select>
			</div>
		</div>

		<?php
		$_show_tax_terms = $destination_post_type && array_intersect( get_object_taxonomies( $destination_post_type ), $_public_taxonomies );
		?>
		<div class="buoyvs-form-group buoyvs-taxonomy-terms-wrapper<?php echo $_show_tax_terms ? '' : ' buoyvs-hidden'; ?>">
			<label>
				<?php esc_html_e( 'Assign to taxonomy terms', 'buoy-video-sync' ); ?>
				<span class="buoyvs-help-wrap">
					<button type="button" class="buoyvs-help-btn" aria-label="<?php esc_attr_e( 'More info', 'buoy-video-sync' ); ?>">?</button>
					<span class="buoyvs-help-tooltip" role="tooltip"><?php esc_html_e( 'Assign synced items to categories, tags, or other taxonomy terms. Available in Pro.', 'buoy-video-sync' ); ?></span>
				</span> <span class="buoyvs-pro-badge" role="img" aria-label="Pro"></span>
			</label>
			<div class="buoyvs-taxonomy-terms"></div>
			<button type="button" class="buoyvs-add-taxonomy-term-locked"><?php esc_html_e( 'Add taxonomy term', 'buoy-video-sync' ); ?></button>
		</div>

		<div class="buoyvs-form-group buoyvs-field-mapping-wrapper">
			<label class="buoyvs-fm-mapping-label">
				<?php esc_html_e( 'Map YouTube details to post metadata', 'buoy-video-sync' ); ?>
				<span class="buoyvs-help-wrap">
					<button type="button" class="buoyvs-help-btn" aria-label="<?php esc_attr_e( 'More info', 'buoy-video-sync' ); ?>">?</button>
					<span class="buoyvs-help-tooltip" role="tooltip"><?php esc_html_e( 'Copy YouTube details into your own post meta keys. Available in Pro.', 'buoy-video-sync' ); ?></span>
				</span> <span class="buoyvs-pro-badge" role="img" aria-label="Pro"></span>
			</label>
			<div class="buoyvs-field-mapping-rows"></div>
			<button type="button" class="buoyvs-add-field-mapping-row-locked"><?php esc_html_e( 'Add detail mapping', 'buoy-video-sync' ); ?></button>
		</div>

		<div class="buoyvs-form-group buoyvs-conditions-wrapper">
			<label class="buoyvs-conditions-label">
				<?php esc_html_e( 'Filter conditions', 'buoy-video-sync' ); ?>
				<span class="buoyvs-help-wrap">
					<button type="button" class="buoyvs-help-btn" aria-label="<?php esc_attr_e( 'More info', 'buoy-video-sync' ); ?>">?</button>
					<span class="buoyvs-help-tooltip" role="tooltip"><?php esc_html_e( 'Only sync items that match all of the following conditions. Available in Pro.', 'buoy-video-sync' ); ?></span>
				</span> <span class="buoyvs-pro-badge" role="img" aria-label="Pro"></span>
			</label>
			<div class="buoyvs-conditions"></div>
			<button type="button" class="buoyvs-add-condition-locked"><?php esc_html_e( 'Add condition', 'buoy-video-sync' ); ?></button>
		</div>

	<?php if ( ! empty( $rule_sync_errors ) ) : ?>
	<div class="buoyvs-rule-errors buoyvs-mt-3">
		<?php foreach ( $rule_sync_errors as $err ) : ?>
		<p class="buoyvs-mb-0 buoyvs-rule-error-msg">
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

	<div class="buoyvs-rule-footer">
		<button type="button" class="buoyvs-remove-rule">
			<?php esc_html_e( 'Remove sync rule', 'buoy-video-sync' ); ?>
		</button>
	</div>

</div>
