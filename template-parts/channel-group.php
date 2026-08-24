<?php
declare(strict_types=1);
/**
 * Template part for a single channel group card.
 *
 * @package Buoy_Video_Sync
 *
 * Variables available in this template:
 * @var array $channel  Channel configuration data.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to buoyvs_get_template_part()'s extract()/include scope, not globals.

$youtube_id          = $channel['youtube_id'] ?? '';
$ch_errors           = isset( $ch_errors ) && is_array( $ch_errors ) ? $ch_errors : array();
$channel_error       = $channel['_api_error'] ?? $ch_errors[ $youtube_id ] ?? '';
$is_new_channel      = ! $youtube_id || ! empty( $channel_error );
$history             = \Buoy_Video_Sync\Sync_History::get( $youtube_id );
$error_count         = \Buoy_Video_Sync\Sync_History::unread_error_count( $youtube_id );
$has_errors          = $error_count > 0;
$channel_title       = $channel['channel_title'] ?? '';
$channel_description = $channel['channel_description'] ?? '';
$subscriber_count    = isset( $channel['subscriber_count'] ) ? $channel['subscriber_count'] : '';
$sync_rules          = $channel['sync_rules'] ?? array();
$video_count         = $channel['video_count'] ?? 0;

$post_types           = get_post_types( array( 'public' => true ), 'objects' );
$default_post_type    = $channel['default_post_type'] ?? '';
$default_post_status  = $channel['default_post_status'] ?? 'publish';
$_public_taxonomies   = get_taxonomies( array( 'public' => true ) );

$profile_picture  = $channel['profile_picture'] ?? array();
$profile_src      = '';
if ( ! empty( $profile_picture['attachment_id'] ) ) {
	$profile_src_data = wp_get_attachment_image_src( (int) $profile_picture['attachment_id'], 'thumbnail' );
	$profile_src      = $profile_src_data ? $profile_src_data[0] : '';
}
if ( ! $profile_src && ! empty( $profile_picture['url'] ) ) {
	$profile_src = $profile_picture['url'];
}

$name_prefix = 'channel[sync_rules]';

?>

<div class="buoyvs-channel<?php echo $is_new_channel ? ' buoyvs-channel--new' : ''; ?>" data-channel-index="0" data-youtube-id="<?php echo esc_attr( $youtube_id ); ?>">
	<div class="buoyvs-channel-header" role="button" tabindex="0" aria-expanded="true">
		<div class="buoyvs-channel-icon">
			<?php if ( $profile_src ) : ?>
				<img src="<?php echo esc_url( $profile_src ); ?>" alt="" width="48" height="48" referrerpolicy="no-referrer">
			<?php else : ?>
				<?php echo esc_html( $channel_title ? mb_strtoupper( mb_substr( $channel_title, 0, 1 ) ) : 'C' ); ?>
			<?php endif; ?>
		</div>
		<h2>
			<?php
			if ( $channel_title ) {
				echo esc_html( $channel_title );
			} else {
				esc_html_e( 'Channel', 'buoy-video-sync' );
			}
			?>
		</h2>
		<span class="dashicons dashicons-arrow-down-alt2 buoyvs-accordion-icon" aria-hidden="true"></span>
	</div>
	<div class="buoyvs-channel-body">

		<div class="buoyvs-channel-tabs-nav" role="tablist">
			<button type="button" class="buoyvs-channel-tab-btn buoyvs-channel-tab-btn--active" data-tab="info" role="tab" aria-selected="true">
				<?php esc_html_e( 'Info', 'buoy-video-sync' ); ?>
			</button>
			<button type="button" class="buoyvs-channel-tab-btn" data-tab="rules" role="tab" aria-selected="false">
				<?php esc_html_e( 'Sync', 'buoy-video-sync' ); ?>
			</button>
			<button type="button" class="buoyvs-channel-tab-btn" data-tab="settings" role="tab" aria-selected="false">
				<?php esc_html_e( 'Settings', 'buoy-video-sync' ); ?>
			</button>
			<button type="button" class="buoyvs-channel-tab-btn" data-tab="history" role="tab" aria-selected="false">
				<?php esc_html_e( 'History', 'buoy-video-sync' ); ?>
				<?php if ( $has_errors ) : ?>
				<span class="buoyvs-history-badge" aria-label="<?php
					/* translators: %d: number of sync errors */
					echo esc_attr( sprintf( _n( '%d sync error', '%d sync errors', $error_count, 'buoy-video-sync' ), $error_count ) );
				?>"><?php echo (int) $error_count; ?></span>
				<?php endif; ?>
			</button>
		</div>

		<div class="buoyvs-channel-tabs-content">

			<?php /* Info tab */ ?>
			<div class="buoyvs-channel-tab-panel" data-panel="info" role="tabpanel">

				<div class="buoyvs-mb-fields">
					<div class="buoyvs-mb-field<?php echo $channel_error ? ' buoyvs-form-group--error' : ''; ?>">
						<label class="buoyvs-mb-label" for="buoyvs-youtube-id"><?php esc_html_e( 'Channel (channel URL or ID)', 'buoy-video-sync' ); ?> <span class="buoyvs-required" aria-hidden="true">*</span></label>
						<input
							type="text"
							id="buoyvs-youtube-id"
							name="channel[youtube_id]"
							value="<?php echo esc_attr( $youtube_id ); ?>"
							class="buoyvs-text"
							placeholder="<?php esc_attr_e( 'e.g. youtube.com/@channel or UCuAXFkgsw1L7xaCfnd5JJOw', 'buoy-video-sync' ); ?>"
							required
						>
						<?php if ( $channel_error ) : ?>
						<p class="buoyvs-field-error"><?php echo esc_html( $channel_error ); ?></p>
						<?php endif; ?>
					</div>

					<?php if ( $channel_title ) : ?>
					<div class="buoyvs-mb-field">
						<p class="buoyvs-mb-label"><?php esc_html_e( 'Title', 'buoy-video-sync' ); ?></p>
						<input type="text" class="buoyvs-text" value="<?php echo esc_attr( $channel_title ); ?>" disabled readonly>
					</div>
					<?php endif; ?>

					<?php if ( '' !== $subscriber_count ) : ?>
					<div class="buoyvs-mb-field">
						<p class="buoyvs-mb-label"><?php esc_html_e( 'Subscribers', 'buoy-video-sync' ); ?></p>
						<input type="text" class="buoyvs-text" value="<?php echo esc_attr( number_format_i18n( (int) $subscriber_count ) ); ?>" disabled readonly>
					</div>
					<?php endif; ?>

					<?php if ( $video_count ) : ?>
					<div class="buoyvs-mb-field">
						<p class="buoyvs-mb-label"><?php esc_html_e( 'Videos', 'buoy-video-sync' ); ?></p>
						<input type="text" class="buoyvs-text" value="<?php echo esc_attr( number_format_i18n( (int) $video_count ) ); ?>" disabled readonly>
					</div>
					<?php endif; ?>

					<?php if ( $channel_description ) : ?>
					<div class="buoyvs-mb-field">
						<p class="buoyvs-mb-label"><?php esc_html_e( 'Description', 'buoy-video-sync' ); ?></p>
						<textarea class="buoyvs-text" rows="3" disabled readonly><?php echo esc_textarea( $channel_description ); ?></textarea>
					</div>
					<?php endif; ?>
				</div>

			</div>

			<?php /* Sync Automation tab */ ?>
			<div class="buoyvs-channel-tab-panel buoyvs-hidden" data-panel="rules" role="tabpanel">

				<button type="button" class="buoyvs-add-rule">
					<?php esc_html_e( 'Add sync rule', 'buoy-video-sync' ); ?>
				</button>
				<div class="buoyvs-rules buoyvs-rules--init" data-video-count="<?php echo (int) $video_count; ?>">
					<?php
					foreach ( $sync_rules as $index => $rule ) {
						buoyvs_get_template_part( 'sync-rule', null, array(
							'index'             => $index,
							'rule'              => $rule,
							'term_id'           => 0,
							'source_type'       => 'channel',
							'name_prefix'       => $name_prefix,
							'is_option_channel' => true,
						) );
					}
					?>
				</div>
				<?php
				buoyvs_get_template_part( 'sync-rule-wizard', null, array(
					'default_post_type'   => $default_post_type,
					'default_post_status' => $default_post_status,
				) );
				?>

			</div>

			<?php /* Settings tab */ ?>
			<div class="buoyvs-channel-tab-panel buoyvs-hidden" data-panel="settings" role="tabpanel">

				<div class="buoyvs-2-columns">
					<div class="buoyvs-form-group">
						<label for="buoyvs-default-post-type">
							<?php esc_html_e( 'Default Post Type', 'buoy-video-sync' ); ?>
							<span class="buoyvs-help-wrap">
								<button type="button" class="buoyvs-help-btn" aria-label="<?php esc_attr_e( 'More info', 'buoy-video-sync' ); ?>">?</button>
								<span class="buoyvs-help-tooltip" role="tooltip"><?php esc_html_e( 'Assign synced videos and playlists from this channel to this post type by default.', 'buoy-video-sync' ); ?></span>
							</span>
						</label>
						<select
							id="buoyvs-default-post-type"
							name="channel[default_post_type]"
							class="buoyvs-select buoyvs-channel-default-post-type"
						>
							<option value=""><?php esc_html_e( '— Select post type —', 'buoy-video-sync' ); ?></option>
							<?php foreach ( $post_types as $pt ) : ?>
							<option value="<?php echo esc_attr( $pt->name ); ?>" data-has-taxonomy="<?php echo array_intersect( get_object_taxonomies( $pt->name ), $_public_taxonomies ) ? '1' : '0'; ?>"<?php selected( $default_post_type, $pt->name ); ?>><?php echo esc_html( $pt->labels->singular_name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="buoyvs-form-group">
						<label for="buoyvs-default-post-status">
							<?php esc_html_e( 'Default Post Status', 'buoy-video-sync' ); ?>
							<span class="buoyvs-help-wrap">
								<button type="button" class="buoyvs-help-btn" aria-label="<?php esc_attr_e( 'More info', 'buoy-video-sync' ); ?>">?</button>
								<span class="buoyvs-help-tooltip" role="tooltip"><?php esc_html_e( 'Pre-select this status when adding new sync rules for this channel.', 'buoy-video-sync' ); ?></span>
							</span>
						</label>
						<select
							id="buoyvs-default-post-status"
							name="channel[default_post_status]"
							class="buoyvs-select buoyvs-channel-default-post-status"
						>
							<?php buoyvs_get_template_part( 'options', 'post-status', array( 'selected' => $default_post_status ) ); ?>
						</select>
					</div>
				</div>

				<?php
				$_ch_show_tax = $default_post_type && array_intersect( get_object_taxonomies( $default_post_type ), $_public_taxonomies );
				?>
				<div class="buoyvs-form-group buoyvs-taxonomy-terms-wrapper<?php echo $_ch_show_tax ? '' : ' buoyvs-hidden'; ?>">
					<label>
						<?php esc_html_e( 'Default Taxonomy Terms', 'buoy-video-sync' ); ?>
						<span class="buoyvs-help-wrap">
							<button type="button" class="buoyvs-help-btn" aria-label="<?php esc_attr_e( 'More info', 'buoy-video-sync' ); ?>">?</button>
							<span class="buoyvs-help-tooltip" role="tooltip"><?php esc_html_e( 'Automatically apply these taxonomy terms to posts created by sync automations in this channel. Available in Pro.', 'buoy-video-sync' ); ?></span>
						</span> <span class="buoyvs-pro-badge" role="img" aria-label="Pro"></span>
					</label>
					<div class="buoyvs-taxonomy-terms"></div>
					<button type="button" class="buoyvs-add-taxonomy-term-locked"><?php esc_html_e( 'Add taxonomy term', 'buoy-video-sync' ); ?></button>
				</div>

				<div class="buoyvs-form-group buoyvs-field-mapping-wrapper">
					<label class="buoyvs-fm-mapping-label">
						<?php esc_html_e( 'Map video details to post metadata', 'buoy-video-sync' ); ?>
						<span class="buoyvs-help-wrap">
							<button type="button" class="buoyvs-help-btn" aria-label="<?php esc_attr_e( 'More info', 'buoy-video-sync' ); ?>">?</button>
							<span class="buoyvs-help-tooltip" role="tooltip"><?php esc_html_e( 'Store YouTube video data in custom post meta fields for all automations in this channel. Available in Pro.', 'buoy-video-sync' ); ?></span>
						</span> <span class="buoyvs-pro-badge" role="img" aria-label="Pro"></span>
					</label>
					<div class="buoyvs-field-mapping-rows"></div>
					<button type="button" class="buoyvs-add-field-mapping-row-locked"><?php esc_html_e( 'Add video detail mapping', 'buoy-video-sync' ); ?></button>
				</div>

			</div>

		<?php /* History tab */ ?>
		<div class="buoyvs-channel-tab-panel buoyvs-hidden" data-panel="history" role="tabpanel">
			<?php if ( empty( $history ) ) : ?>
			<p class="buoyvs-history-empty"><?php esc_html_e( 'No sync history yet.', 'buoy-video-sync' ); ?></p>
			<?php else : ?>
			<ul class="buoyvs-history-list">
				<?php
				$action_labels = array(
					'videos_sync_new'               => __( 'Sync new videos', 'buoy-video-sync' ),
					'playlists_sync_new'            => __( 'Sync new playlists', 'buoy-video-sync' ),
					'channel_sync_new'              => __( 'Sync channel', 'buoy-video-sync' ),
				);
				?>
			<?php foreach ( $history as $entry ) :
					$entry_action   = $entry['rule_action'] ?? '';
					$entry_time     = $entry['timestamp'] ?? 0;
					$entry_duration = (int) ( $entry['duration'] ?? 0 );
					$entry_error    = $entry['has_error'] ?? false;
					$entry_errors   = $entry['errors'] ?? array();
					$entry_label    = $action_labels[ $entry_action ] ?? $entry_action;

					// Build conversational summary: "3 videos synced to Posts".
					$entry_count   = isset( $entry['items_count'] ) ? (int) $entry['items_count'] : null;
					$entry_dest_pt = $entry['destination_post_type'] ?? '';

					$count_part = '';
					if ( null !== $entry_count ) {
						$is_sync  = str_contains( $entry_action, 'sync' );
						$verb     = $is_sync ? __( 'synced', 'buoy-video-sync' ) : __( 'updated', 'buoy-video-sync' );
						if ( str_contains( $entry_action, 'playlist' ) ) {
							$resource = _n( 'playlist', 'playlists', $entry_count, 'buoy-video-sync' );
						} elseif ( str_contains( $entry_action, 'channel' ) ) {
							$resource = _n( 'channel', 'channels', $entry_count, 'buoy-video-sync' );
						} else {
							$resource = _n( 'video', 'videos', $entry_count, 'buoy-video-sync' );
						}
						$count_part = $entry_count . ' ' . $resource . ' ' . $verb;
					}

					$pt_part = '';
					if ( $entry_dest_pt ) {
						$pt_obj  = get_post_type_object( $entry_dest_pt );
						$pt_name = $pt_obj ? $pt_obj->labels->name : $entry_dest_pt;
						/* translators: %s: post type label, e.g. "Posts" */
						$pt_part = ' ' . sprintf( __( 'to %s', 'buoy-video-sync' ), $pt_name );
					}

					$entry_summary = $count_part . $pt_part;

					if ( $entry_error && ! empty( $entry_errors ) ) {
						$messages = array_values( array_filter( array_map( function ( $e ) {
							$msg = trim( $e['error'] ?? '' );
							return trim( preg_replace( '/\s*\(cURL error \d+:[^)]*\)/i', '', $msg ) );
						}, $entry_errors ) ) );
						if ( ! empty( $messages ) ) {
							$error_text    = implode( '. ', $messages );
							$entry_summary = $entry_summary ? $entry_summary . '. ' . $error_text : $error_text;
						}
					}
				?>
				<li class="buoyvs-history-entry<?php echo $entry_error ? ' buoyvs-history-entry--error' : ''; ?>">
					<div class="buoyvs-history-entry-header">
						<span class="buoyvs-history-entry-status" aria-hidden="true"></span>
						<span class="buoyvs-history-entry-action">
							<?php echo esc_html( $entry_label ); ?>
							<?php if ( $entry_summary ) : ?>
							<span class="buoyvs-history-entry-summary"><?php echo esc_html( $entry_summary ); ?></span>
							<?php endif; ?>
						</span>
						<span class="buoyvs-history-entry-time"><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry_time ) ); ?></span>
						<span class="buoyvs-history-entry-duration"><?php
							/* translators: %d: sync run duration in seconds */
							printf( esc_html__( '%ds', 'buoy-video-sync' ), absint( $entry_duration ) );
						?></span>
					</div>
				</li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
		</div>

		</div><!-- .buoyvs-channel-tabs-content -->
	</div><!-- .buoyvs-channel-body -->
	<?php if ( $youtube_id ) : ?>
	<div class="buoyvs-channel-footer">
		<button type="button" class="buoyvs-remove-channel">
			<?php esc_html_e( 'Delete channel', 'buoy-video-sync' ); ?>
		</button>
	</div>
	<?php endif; ?>
</div>
