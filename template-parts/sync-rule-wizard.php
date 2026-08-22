<?php
declare(strict_types=1);
/**
 * Template part: 3-step sync rule wizard (Action → Schedule → Destination).
 *
 * Rendered once per channel group, hidden by default. JavaScript reveals it
 * when the user clicks "Add sync rule" and hides it on cancel/completion.
 *
 * @package Buoy_Video_Sync
 *
 * Variables available in this template:
 * @var string $default_post_type Default destination post type for the channel.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to buoyvs_get_template_part()'s extract()/include scope, not globals.

$post_types         = get_post_types( array( 'public' => true ), 'objects' );
$default_post_type  = $default_post_type ?? '';
$_public_taxonomies = get_taxonomies( array( 'public' => true ) );
?>
<div class="buoyvs-wizard buoyvs-hidden" data-channel-index="0" data-default-post-type="<?php echo esc_attr( $default_post_type ); ?>">

	<div class="buoyvs-wizard-progress" role="progressbar" aria-label="<?php esc_attr_e( 'Wizard progress', 'buoy-video-sync' ); ?>">
		<span class="buoyvs-wizard-step-indicator buoyvs-wizard-step-indicator--active" data-step="1">1</span>
		<span class="buoyvs-wizard-progress-line"></span>
		<span class="buoyvs-wizard-step-indicator" data-step="2">2</span>
		<span class="buoyvs-wizard-progress-line"></span>
		<span class="buoyvs-wizard-step-indicator" data-step="3">3</span>
	</div>

	<div class="buoyvs-wizard-panels">

	<?php /* Step 1 — Action */ ?>
	<div class="buoyvs-wizard-panel" data-step="1">
		<div class="buoyvs-wizard-step-header">
			<h3><?php esc_html_e( 'What should this rule do?', 'buoy-video-sync' ); ?></h3>
		</div>
		<div class="buoyvs-2-columns buoyvs-cols-3-1">
			<div class="buoyvs-form-group">
				<label for="buoyvs-wizard-action"><?php esc_html_e( 'Action', 'buoy-video-sync' ); ?></label>
				<select id="buoyvs-wizard-action" class="buoyvs-select buoyvs-wizard-action-select">
					<option value=""><?php esc_html_e( '— Select action —', 'buoy-video-sync' ); ?></option>
					<optgroup label="<?php esc_attr_e( 'Videos', 'buoy-video-sync' ); ?>">
						<option data-resource="video" value="videos_sync_new"><?php esc_html_e( 'Sync new videos', 'buoy-video-sync' ); ?></option>
						<option disabled><?php esc_html_e( 'Update all video details (Pro)', 'buoy-video-sync' ); ?></option>
						<option disabled><?php esc_html_e( 'Update specific video details (Pro)', 'buoy-video-sync' ); ?></option>
					</optgroup>
					<optgroup label="<?php esc_attr_e( 'Playlists', 'buoy-video-sync' ); ?>">
						<option data-resource="playlist" value="playlists_sync_new"><?php esc_html_e( 'Sync new playlists', 'buoy-video-sync' ); ?></option>
						<option disabled><?php esc_html_e( 'Update all playlist details (Pro)', 'buoy-video-sync' ); ?></option>
						<option disabled><?php esc_html_e( 'Update specific playlist details (Pro)', 'buoy-video-sync' ); ?></option>
					</optgroup>
					<optgroup label="<?php esc_attr_e( 'Channel', 'buoy-video-sync' ); ?>">
						<option data-resource="channel" value="channel_sync_new"><?php esc_html_e( 'Sync this channel', 'buoy-video-sync' ); ?></option>
						<option disabled><?php esc_html_e( "Update this channel's details (Pro)", 'buoy-video-sync' ); ?></option>
						<option disabled><?php esc_html_e( 'Update specific details of this channel (Pro)', 'buoy-video-sync' ); ?></option>
					</optgroup>
				</select>
			</div>
			<div class="buoyvs-form-group buoyvs-items-per-run-wrapper">
				<label for="buoyvs-wizard-max-videos" class="buoyvs-max-items-label"><?php esc_html_e( 'Items per run', 'buoy-video-sync' ); ?></label>
				<div class="buoyvs-limit-wrap">
					<input id="buoyvs-wizard-max-videos" class="buoyvs-number buoyvs-wizard-max-videos buoyvs-max-videos-input" type="number" value="50" min="0">
					<span class="buoyvs-unlimited-icon buoyvs-hidden" title="<?php esc_attr_e( 'Unlimited — click to set a limit', 'buoy-video-sync' ); ?>">∞</span>
				</div>
			</div>
		</div>
		<div class="buoyvs-wizard-nav">
			<button type="button" class="button buoyvs-wizard-cancel"><?php esc_html_e( 'Cancel', 'buoy-video-sync' ); ?></button>
			<button type="button" class="button button-primary buoyvs-wizard-next" data-step="1"><?php esc_html_e( 'Next →', 'buoy-video-sync' ); ?></button>
		</div>
	</div>

	<?php /* Step 2 — Schedule */ ?>
	<div class="buoyvs-wizard-panel buoyvs-hidden" data-step="2">
		<div class="buoyvs-wizard-step-header">
			<h3><?php esc_html_e( 'How often should this rule run?', 'buoy-video-sync' ); ?></h3>
		</div>
		<div class="buoyvs-2-columns buoyvs-cols-3-1">
			<div class="buoyvs-form-group">
				<label for="buoyvs-wizard-schedule"><?php esc_html_e( 'Sync schedule', 'buoy-video-sync' ); ?></label>
				<select id="buoyvs-wizard-schedule" class="buoyvs-select buoyvs-wizard-schedule-select">
					<?php buoyvs_get_template_part( 'options', 'schedule', array( 'selected' => 'daily' ) ); ?>
				</select>
			</div>
			<div class="buoyvs-form-group buoyvs-wizard-custom-schedule-wrapper buoyvs-hidden">
				<label for="buoyvs-wizard-custom-schedule"><?php esc_html_e( 'Custom (Hours)', 'buoy-video-sync' ); ?></label>
				<input id="buoyvs-wizard-custom-schedule" class="buoyvs-number buoyvs-wizard-custom-schedule" type="number" value="24" min="1" placeholder="24">
			</div>
		</div>
		<div class="buoyvs-wizard-nav">
			<button type="button" class="button buoyvs-wizard-back" data-step="2"><?php esc_html_e( '← Back', 'buoy-video-sync' ); ?></button>
			<button type="button" class="button button-primary buoyvs-wizard-next" data-step="2"><?php esc_html_e( 'Next →', 'buoy-video-sync' ); ?></button>
		</div>
	</div>

	<?php /* Step 3 — Destination */ ?>
	<div class="buoyvs-wizard-panel buoyvs-hidden" data-step="3">
		<div class="buoyvs-wizard-step-header">
			<h3><?php esc_html_e( 'Where should synced items be saved?', 'buoy-video-sync' ); ?></h3>
		</div>
		<div class="buoyvs-2-columns buoyvs-cols-3-1">
			<div class="buoyvs-form-group buoyvs-wizard-post-type-wrapper">
				<label for="buoyvs-wizard-post-type"><?php esc_html_e( 'Post Type', 'buoy-video-sync' ); ?></label>
				<select id="buoyvs-wizard-post-type" class="buoyvs-select buoyvs-wizard-post-type">
					<option value=""><?php esc_html_e( '— Select post type —', 'buoy-video-sync' ); ?></option>
					<?php foreach ( $post_types as $pt ) : ?>
					<option value="<?php echo esc_attr( $pt->name ); ?>" data-has-taxonomy="<?php echo array_intersect( get_object_taxonomies( $pt->name ), $_public_taxonomies ) ? '1' : '0'; ?>"<?php selected( $default_post_type, $pt->name ); ?>>
						<?php echo esc_html( $pt->labels->singular_name ); ?>
					</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="buoyvs-form-group">
				<label for="buoyvs-wizard-post-status"><?php esc_html_e( 'Post status', 'buoy-video-sync' ); ?></label>
				<select id="buoyvs-wizard-post-status" class="buoyvs-select buoyvs-wizard-post-status">
					<option value="publish"><?php esc_html_e( 'Published', 'buoy-video-sync' ); ?></option>
					<option value="draft"><?php esc_html_e( 'Draft', 'buoy-video-sync' ); ?></option>
					<option value="private"><?php esc_html_e( 'Private', 'buoy-video-sync' ); ?></option>
				</select>
			</div>
		</div>

		<?php
		$_wiz_show_tax = $default_post_type && array_intersect( get_object_taxonomies( $default_post_type ), $_public_taxonomies );
		?>
		<div class="buoyvs-form-group buoyvs-taxonomy-terms-wrapper<?php echo $_wiz_show_tax ? '' : ' buoyvs-hidden'; ?>">
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

		<div class="buoyvs-wizard-error buoyvs-hidden"></div>
		<div class="buoyvs-wizard-nav">
			<button type="button" class="button buoyvs-wizard-back" data-step="3"><?php esc_html_e( '← Back', 'buoy-video-sync' ); ?></button>
			<button type="button" class="button button-primary buoyvs-wizard-finish"><?php esc_html_e( 'Add Rule', 'buoy-video-sync' ); ?></button>
		</div>
	</div>

	</div><?php /* .buoyvs-wizard-panels */ ?>

</div>
