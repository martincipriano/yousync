<?php
/**
 * Channel Taxonomy Management
 *
 * @package YouSync
 */

namespace YouSync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Channel Class
 */
class Channel {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'yousync_channel_add_form_fields', array( $this, 'add_channel_fields' ) );
		add_action( 'yousync_channel_pre_edit_form', array( $this, 'render_channel_hero' ), 10, 2 );
		add_action( 'yousync_channel_edit_form_fields', array( $this, 'edit_channel_fields' ), 10, 2 );
		add_action( 'created_yousync_channel', array( $this, 'save_channel_meta' ), 10, 2 );
		add_action( 'edited_yousync_channel', array( $this, 'save_channel_meta' ), 10, 2 );
		add_filter( 'manage_edit-yousync_channel_columns', array( $this, 'add_channel_columns' ) );
		add_filter( 'manage_yousync_channel_custom_column', array( $this, 'channel_column_content' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Register channel taxonomy.
	 *
	 * @return void
	 */
	public function register_taxonomy() {
		$labels = array(
			'name'                       => __( 'Channels', 'yousync' ),
			'singular_name'              => __( 'Channel', 'yousync' ),
			'menu_name'                  => __( 'Channels', 'yousync' ),
			'all_items'                  => __( 'All Channels', 'yousync' ),
			'edit_item'                  => __( 'Edit Channel', 'yousync' ),
			'view_item'                  => __( 'View Channel', 'yousync' ),
			'update_item'                => __( 'Update Channel', 'yousync' ),
			'add_new_item'               => __( 'Add New Channel', 'yousync' ),
			'new_item_name'              => __( 'New Channel Name', 'yousync' ),
			'search_items'               => __( 'Search Channels', 'yousync' ),
			'popular_items'              => __( 'Popular Channels', 'yousync' ),
			'separate_items_with_commas' => __( 'Separate channels with commas', 'yousync' ),
			'add_or_remove_items'        => __( 'Add or remove channels', 'yousync' ),
			'choose_from_most_used'      => __( 'Choose from most used channels', 'yousync' ),
			'not_found'                  => __( 'No channels found', 'yousync' ),
		);

		$args = array(
			'labels'            => $labels,
			'public'            => false,
			'show_ui'           => true,
			'show_in_menu'      => 'edit.php?post_type=yousync_videos',
			'show_in_nav_menus' => false,
			'show_admin_column' => false,
			'hierarchical'      => false,
			'query_var'         => false,
			'rewrite'           => false,
			'capabilities'      => array(
				'manage_terms' => 'manage_options',
				'edit_terms'   => 'manage_options',
				'delete_terms' => 'manage_options',
				'assign_terms' => 'edit_posts',
			),
		);

		register_taxonomy( 'yousync_channel', 'yousync_videos', $args );
	}

	/**
	 * Add custom fields to channel add form.
	 *
	 * @return void
	 */
	public function add_channel_fields() : void
	{ ?>
		<div class="form-field term-channel-id-wrap">
			<label for="channel-id"><?php esc_html_e( 'Channel ID', 'yousync' ); ?></label>
			<input name="channel_id" id="channel-id" type="text" value="" aria-required="true" aria-describedby="channel-id-description">
			<p class="description" id="channel-id-description">
				<?php esc_html_e( 'Enter the YouTube channel ID (e.g., UCuAXFkgsw1L7xaCfnd5JJOw)', 'yousync' ); ?>
			</p>
		</div>
		<?php
		$this->render_sync_rules_section();
	}

	/**
	 * Add custom fields to channel edit form.
	 *
	 * @param WP_Term $term Current taxonomy term object.
	 * @param string  $taxonomy Current taxonomy slug.
	 * @return void
	 */
	public function edit_channel_fields( $term, $taxonomy ) : void
	{
		$meta = get_term_meta( $term->term_id, 'yousync_channel', true );
		$data = $meta ? json_decode( $meta, true ) : array();
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		$channel_id       = isset( $data['channel_id'] ) ? $data['channel_id'] : '';
		$sync_rules       = isset( $data['sync_rules'] ) ? $data['sync_rules'] : array();
		$channel_title    = isset( $data['channel_title'] ) ? $data['channel_title'] : '';
		$subscriber_count = isset( $data['subscriber_count'] ) ? $data['subscriber_count'] : '';
		?>

		<tr class="form-field term-channel-id-wrap">
			<th scope="row">
				<label for="channel-id"><?php esc_html_e( 'Channel ID', 'yousync' ); ?></label>
			</th>
			<td>
				<input name="channel_id" id="channel-id" type="text" value="<?php echo esc_attr( $channel_id ); ?>" size="40" aria-describedby="channel-id-description">
				<p class="description" id="channel-id-description">
					<?php esc_html_e( 'YouTube channel ID (e.g., UCuAXFkgsw1L7xaCfnd5JJOw)', 'yousync' ); ?>
				</p>
			</td>
		</tr>

		<?php if ( $channel_title || $subscriber_count !== '' ) : ?>

		<?php if ( $channel_title ) : ?>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Channel Title', 'yousync' ); ?></th>
			<td><?php echo esc_html( $channel_title ); ?></td>
		</tr>
		<?php endif; ?>

		<?php if ( $subscriber_count !== '' ) : ?>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Subscribers', 'yousync' ); ?></th>
			<td><?php echo esc_html( number_format( (int) $subscriber_count ) ); ?></td>
		</tr>
		<?php endif; ?>
		<?php endif; ?>

		<tr class="form-field term-sync-rules-wrap">
			<th scope="row">
				<label><?php esc_html_e( 'Sync Rules', 'yousync' ); ?></label>
			</th>
			<td>
				<?php $this->render_sync_rules_section( $sync_rules, $data['video_count'] ?? 0, $term->term_id ); ?>
			</td>
		</tr>

		<?php
	}

	/**
	 * Render the channel hero (banner + profile picture) before the edit form.
	 *
	 * Outputs a hidden <div> that JavaScript moves to just after the page <h1>.
	 *
	 * @param WP_Term $term     Current taxonomy term object.
	 * @param string  $taxonomy Current taxonomy slug.
	 * @return void
	 */
	public function render_channel_hero( $term, $taxonomy ): void {
		$meta = get_term_meta( $term->term_id, 'yousync_channel', true );
		$data = $meta ? json_decode( $meta, true ) : array();
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		$profile_picture = isset( $data['profile_picture'] ) && is_array( $data['profile_picture'] ) ? $data['profile_picture'] : array();
		$banner_image    = isset( $data['banner_image'] ) && is_array( $data['banner_image'] ) ? $data['banner_image'] : array();

		$banner_src = '';
		if ( ! empty( $banner_image['attachment_id'] ) ) {
			$img        = wp_get_attachment_image_src( (int) $banner_image['attachment_id'], 'full' );
			$banner_src = $img ? $img[0] : '';
		} elseif ( ! empty( $banner_image['url'] ) ) {
			$banner_src = $banner_image['url'];
		}

		$profile_src = '';
		if ( ! empty( $profile_picture['attachment_id'] ) ) {
			$img         = wp_get_attachment_image_src( (int) $profile_picture['attachment_id'], 'thumbnail' );
			$profile_src = $img ? $img[0] : '';
		} elseif ( ! empty( $profile_picture['url'] ) ) {
			$profile_src = $profile_picture['url'];
		}

		if ( ! $banner_src && ! $profile_src ) {
			return;
		}
		?>
		<div id="ys-channel-hero">
			<div class="ys-hero-inner">
				<?php if ( $banner_src ) : ?>
				<img src="<?php echo esc_url( $banner_src ); ?>" class="ys-hero-banner" alt="">
				<?php else : ?>
				<div class="ys-hero-banner-placeholder"></div>
				<?php endif; ?>
				<?php if ( $profile_src ) : ?>
				<div class="ys-hero-profile">
					<img src="<?php echo esc_url( $profile_src ); ?>" width="110" height="110" alt="">
				</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render sync rules section.
	 *
	 * @param array $sync_rules Existing sync rules.
	 * @return void
	 */
	private function render_sync_rules_section( $sync_rules = array(), $video_count = 0, $term_id = 0 ) : void
	{
		$html = '';
		foreach ( $sync_rules as $index => $rule ) {
			$html .= yousync_return_template_part( 'sync-rule', null, array(
				'index'       => $index,
				'rule'        => $rule,
				'term_id'     => $term_id,
				'source_type' => 'channel',
			) );
		} ?>
		<p class="ys-mb-3"><strong>Sync Rules</strong> &mdash; <a class="ys-add-rule" href="#" id="ys-add-rule"><?php esc_html_e( 'Add sync rule', 'yousync' ); ?></a></p>
		<div class="ys-sync-rules" id="ys-sync-rules" data-video-count="<?php echo (int) $video_count; ?>"><?php echo $html; ?></div>
		<?php
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets() {
		$screen = get_current_screen();

		// Only load on channel taxonomy pages.
		if ( ! $screen || 'edit-yousync_channel' !== $screen->id ) {
			return;
		}

		wp_enqueue_style( 'tom-select', 'https://cdnjs.cloudflare.com/ajax/libs/tom-select/2.4.3/css/tom-select.min.css', array(), '2.4.3' );
		wp_enqueue_style( 'yousync-admin', YOUSYNC_PLUGIN_URL . 'assets/css/admin.css', array( 'tom-select' ), filemtime( YOUSYNC_PLUGIN_DIR . 'assets/css/admin.css' ) );
		wp_enqueue_script( 'tom-select', 'https://cdnjs.cloudflare.com/ajax/libs/tom-select/2.4.3/js/tom-select.complete.min.js', array(), '2.4.3', true );
		wp_enqueue_script( 'yousync-admin', YOUSYNC_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'tom-select' ), filemtime( YOUSYNC_PLUGIN_DIR . 'assets/js/admin.js' ), true );
		wp_localize_script( 'yousync-admin', 'youSync', array(
			'operators' => array(
				'text'   => yousync_return_template_part( 'options', 'text-operators', array( 'operator' => '' ) ),
				'number' => yousync_return_template_part( 'options', 'number-operators', array( 'operator' => '' ) ),
				'date'   => yousync_return_template_part( 'options', 'date-operators', array( 'operator' => '' ) ),
			),
			'values'   => array(
				'text'   => yousync_return_template_part( 'input', 'text' ),
				'number' => yousync_return_template_part( 'input', 'number' ),
				'date'   => yousync_return_template_part( 'input', 'date' ),
			),
			'syncRule' => array(
				'channel' => array(
					'fieldOptions'    => yousync_return_template_part( 'options', 'channel-fields' ),
					'metadataOptions' => yousync_return_template_part( 'options', 'channel-metadata' ),
				),
				'video' => array(
					'fieldOptions'    => yousync_return_template_part( 'options', 'video-fields' ),
					'metadataOptions' => yousync_return_template_part( 'options', 'video-metadata' ),
				),
				'playlist' => array(
					'fieldOptions'    => yousync_return_template_part( 'options', 'playlist-fields' ),
					'metadataOptions' => yousync_return_template_part( 'options', 'playlist-metadata' ),
				),
				'condition' => yousync_return_template_part( 'sync-rule', 'condition' ),
				'rule'      => yousync_return_template_part( 'sync-rule' ),
			),
		) );
		wp_add_inline_script(
			'yousync-admin',
			'document.addEventListener("DOMContentLoaded", function () {
				var hero = document.getElementById("ys-channel-hero");
				if ( hero ) { hero.style.opacity = "1"; }
			});'
		);
	}

	/**
	 * Save channel meta.
	 *
	 * Merges user-editable fields (channel_id, sync_rules) into the existing JSON
	 * meta, preserving any YouTube API data that was previously synced.
	 *
	 * @param int $term_id Term ID.
	 * @param int $tt_id Term taxonomy ID.
	 * @return void
	 */
	public function save_channel_meta( $term_id, $tt_id ) {
		// Read existing JSON meta to preserve YouTube API data.
		$existing_meta  = get_term_meta( $term_id, 'yousync_channel', true );
		$data           = $existing_meta ? json_decode( $existing_meta, true ) : array();
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		// Capture old channel ID before overwriting.
		$old_channel_id = $data['channel_id'] ?? '';

		// Update editable fields.
		$new_channel_id     = isset( $_POST['channel_id'] ) ? sanitize_text_field( wp_unslash( $_POST['channel_id'] ) ) : $old_channel_id;
		$data['channel_id'] = $new_channel_id;

		// Update sync rules (re-index to ensure sequential keys).
		$old_rules = $data['sync_rules'] ?? array();
		if ( isset( $_POST['sync_rules'] ) && is_array( $_POST['sync_rules'] ) ) {
			$new_rules = array_values( array_map( array( $this, 'sanitize_sync_rule' ), $_POST['sync_rules'] ) );
			// Restore per-rule status fields not submitted via the form.
			foreach ( $new_rules as $i => &$new_rule ) {
				if ( isset( $old_rules[ $i ] ) ) {
					$new_rule['sync_status'] = $old_rules[ $i ]['sync_status'] ?? '';
					$new_rule['last_synced'] = $old_rules[ $i ]['last_synced'] ?? 0;
					$new_rule['sync_count']  = $old_rules[ $i ]['sync_count'] ?? 0;
					$new_rule['sync_errors'] = $old_rules[ $i ]['sync_errors'] ?? array();
				}
			}
			unset( $new_rule );
			$data['sync_rules'] = $new_rules;
		} else {
			$data['sync_rules'] = array();
		}

		// Auto-fetch YouTube channel data when: the channel ID changes, the
		// title is missing, or image fields have never been stored as arrays
		// (covers the old string-format profile_picture from pre-fix syncs).
		$api_key = get_option( 'yousync_api_key', '' );
		if (
			$api_key &&
			$new_channel_id &&
			(
				$new_channel_id !== $old_channel_id ||
				empty( $data['channel_title'] ) ||
				! isset( $data['banner_image'] ) ||
				! is_array( $data['profile_picture'] ?? null )
			)
		) {
			$channel = ( new YouTube_API( $api_key ) )->get_channel_data( $new_channel_id );
			if ( ! is_wp_error( $channel ) ) {
				$data['channel_title']       = $channel['channel_title'];
				$data['channel_description'] = $channel['channel_description'];
				$data['subscriber_count']    = $channel['subscriber_count'];
				$data['video_count']         = $channel['video_count'];
				$data['profile_picture']     = $channel['profile_picture'];
				$data['banner_image']        = $channel['banner_image'];
				$data['etag']                = $channel['etag'];
			}
		}

		update_term_meta( $term_id, 'yousync_channel', wp_slash( wp_json_encode( $data ) ) );
		update_term_meta( $term_id, 'yousync_channel_id', $new_channel_id );
	}

	/**
	 * Sanitize a single sync rule.
	 *
	 * @param array $rule Raw rule data from $_POST.
	 * @return array Sanitized rule.
	 */
	private function sanitize_sync_rule( $rule ) {
		$sanitized = array(
			'enabled'           => isset( $rule['enabled'] ) ? (bool) $rule['enabled'] : false,
			'schedule'          => isset( $rule['schedule'] ) ? sanitize_text_field( $rule['schedule'] ) : 'daily',
			'custom_schedule'   => isset( $rule['custom_schedule'] ) ? absint( $rule['custom_schedule'] ) : 24,
			'action'            => isset( $rule['action'] ) ? sanitize_text_field( $rule['action'] ) : '',
			'specific_metadata' => isset( $rule['specific_metadata'] ) && is_array( $rule['specific_metadata'] )
				? array_map( 'sanitize_text_field', $rule['specific_metadata'] )
				: array(),
			'conditions'        => array(),
		);

		if ( isset( $rule['conditions'] ) && is_array( $rule['conditions'] ) ) {
			foreach ( $rule['conditions'] as $condition ) {
				if ( ! is_array( $condition ) ) {
					continue;
				}
				$sanitized['conditions'][] = array(
					'field'    => isset( $condition['field'] ) ? sanitize_text_field( $condition['field'] ) : '',
					'operator' => isset( $condition['operator'] ) ? sanitize_text_field( $condition['operator'] ) : '',
					'value'    => isset( $condition['value'] ) ? sanitize_text_field( $condition['value'] ) : '',
				);
			}
		}

		return $sanitized;
	}

	/**
	 * Add custom columns to channel list.
	 *
	 * @param array $columns Columns.
	 * @return array Modified columns.
	 */
	public function add_channel_columns( $columns ) {
		$new_columns = array(
			'cb'          => $columns['cb'],
			'name'        => $columns['name'],
			'channel_id'  => __( 'Channel ID', 'yousync' ),
			'sync_rules'  => __( 'Sync Rules', 'yousync' ),
			'last_synced' => __( 'Last Synced', 'yousync' ),
			'sync_count'  => __( 'Videos Synced', 'yousync' ),
			'posts'       => $columns['posts'],
		);

		return $new_columns;
	}

	/**
	 * Display custom column content.
	 *
	 * @param string $content Column content.
	 * @param string $column_name Column name.
	 * @param int    $term_id Term ID.
	 * @return string Column content.
	 */
	public function channel_column_content( $content, $column_name, $term_id ) {
		switch ( $column_name ) {
			case 'channel_id':
				$meta       = get_term_meta( $term_id, 'yousync_channel', true );
				$data       = $meta ? json_decode( $meta, true ) : array();
				$channel_id = isset( $data['channel_id'] ) ? $data['channel_id'] : '';
				$content    = $channel_id ? esc_html( $channel_id ) : '—';
				break;

			case 'sync_rules':
				$meta       = get_term_meta( $term_id, 'yousync_channel', true );
				$data       = $meta ? json_decode( $meta, true ) : array();
				$sync_rules = isset( $data['sync_rules'] ) ? $data['sync_rules'] : array();
				if ( ! empty( $sync_rules ) && is_array( $sync_rules ) ) {
					$enabled_count = count( array_filter( $sync_rules, function( $rule ) {
						return isset( $rule['enabled'] ) && $rule['enabled'];
					} ) );
					/* translators: 1: enabled rules count, 2: total rules count */
					$content = sprintf( __( '%1$d of %2$d enabled', 'yousync' ), $enabled_count, count( $sync_rules ) );
				} else {
					$content = __( 'No rules', 'yousync' );
				}
				break;

			case 'last_synced':
				$meta       = get_term_meta( $term_id, 'yousync_channel', true );
				$data       = $meta ? json_decode( $meta, true ) : array();
				$timestamps = array_filter( array_column( $data['sync_rules'] ?? array(), 'last_synced' ) );
				$last       = $timestamps ? max( $timestamps ) : 0;
				$content    = $last ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last ) ) : '—';
				break;

			case 'sync_count':
				$meta    = get_term_meta( $term_id, 'yousync_channel', true );
				$data    = $meta ? json_decode( $meta, true ) : array();
				$total   = (int) array_sum( array_column( $data['sync_rules'] ?? array(), 'sync_count' ) );
				$content = (string) $total;
				break;
		}

		return $content;
	}
}

new Channel();
