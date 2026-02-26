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
			'show_admin_column' => true,
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

		$channel_id          = isset( $data['channel_id'] ) ? $data['channel_id'] : '';
		$sync_rules          = isset( $data['sync_rules'] ) ? $data['sync_rules'] : array();
		$channel_title       = isset( $data['channel_title'] ) ? $data['channel_title'] : '';
		$channel_description = isset( $data['channel_description'] ) ? $data['channel_description'] : '';
		$subscriber_count    = isset( $data['subscriber_count'] ) ? $data['subscriber_count'] : '';
		$video_count         = isset( $data['video_count'] ) ? $data['video_count'] : '';
		$profile_picture     = isset( $data['profile_picture'] ) ? $data['profile_picture'] : array();
		$banner_image        = isset( $data['banner_image'] ) ? $data['banner_image'] : array();
		$last_synced         = isset( $data['last_synced'] ) ? $data['last_synced'] : '';
		$sync_count          = isset( $data['sync_count'] ) ? $data['sync_count'] : 0;
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

		<?php if ( $channel_title ) : ?>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Channel Title', 'yousync' ); ?></th>
			<td><p><?php echo esc_html( $channel_title ); ?></p></td>
		</tr>
		<?php endif; ?>

		<?php if ( $channel_description ) : ?>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Description', 'yousync' ); ?></th>
			<td><p><?php echo esc_html( $channel_description ); ?></p></td>
		</tr>
		<?php endif; ?>

		<?php if ( ! empty( $profile_picture ) ) : ?>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Profile Picture', 'yousync' ); ?></th>
			<td>
				<?php if ( ! empty( $profile_picture['attachment_id'] ) ) : ?>
					<?php echo wp_get_attachment_image( (int) $profile_picture['attachment_id'], array( 96, 96 ) ); ?>
				<?php elseif ( ! empty( $profile_picture['url'] ) ) : ?>
					<img src="<?php echo esc_url( $profile_picture['url'] ); ?>" width="96" height="96" alt="">
				<?php endif; ?>
			</td>
		</tr>
		<?php endif; ?>

		<?php if ( ! empty( $banner_image ) ) : ?>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Banner Image', 'yousync' ); ?></th>
			<td>
				<?php if ( ! empty( $banner_image['attachment_id'] ) ) : ?>
					<?php echo wp_get_attachment_image( (int) $banner_image['attachment_id'], 'medium' ); ?>
				<?php elseif ( ! empty( $banner_image['url'] ) ) : ?>
					<img src="<?php echo esc_url( $banner_image['url'] ); ?>" style="max-width:300px;height:auto;" alt="">
				<?php endif; ?>
			</td>
		</tr>
		<?php endif; ?>

		<?php if ( $subscriber_count !== '' ) : ?>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Subscribers', 'yousync' ); ?></th>
			<td><p><?php echo esc_html( number_format( (int) $subscriber_count ) ); ?></p></td>
		</tr>
		<?php endif; ?>

		<?php if ( $video_count !== '' ) : ?>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Videos', 'yousync' ); ?></th>
			<td><p><?php echo esc_html( number_format( (int) $video_count ) ); ?></p></td>
		</tr>
		<?php endif; ?>

		<?php if ( $last_synced ) : ?>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Last Synced', 'yousync' ); ?></th>
			<td><p><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_synced ) ); ?></p></td>
		</tr>
		<?php endif; ?>

		<?php if ( $sync_count ) : ?>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Sync Count', 'yousync' ); ?></th>
			<td><p><?php echo esc_html( $sync_count ); ?></p></td>
		</tr>
		<?php endif; ?>

		<tr class="form-field term-sync-rules-wrap">
			<th scope="row">
				<label><?php esc_html_e( 'Sync Rules', 'yousync' ); ?></label>
			</th>
			<td>
				<?php $this->render_sync_rules_section( $sync_rules ); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render sync rules section.
	 *
	 * @param array $sync_rules Existing sync rules.
	 * @return void
	 */
	private function render_sync_rules_section( $sync_rules = array() ) : void
	{
		$html = '';
		foreach ( $sync_rules as $index => $rule ) {
			$html .= yousync_return_template_part( 'sync-rule', null, array(
				'index' => $index,
				'rule'  => $rule,
			) );
		} ?>
		<p class="ys-mb-3 ys-mt-4"><strong>Sync Rules</strong> &mdash; <a class="ys-add-rule" href="#" id="ys-add-rule"><?php esc_html_e( 'Add sync rule', 'yousync' ); ?></a></p>
		<div class="ys-sync-rules" id="ys-sync-rules"><?php echo $html; ?></div>
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
		$existing_meta = get_term_meta( $term_id, 'yousync_channel', true );
		$data          = $existing_meta ? json_decode( $existing_meta, true ) : array();
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		// Update editable fields.
		if ( isset( $_POST['channel_id'] ) ) {
			$data['channel_id'] = sanitize_text_field( wp_unslash( $_POST['channel_id'] ) );
		}

		// Update sync rules (re-index to ensure sequential keys).
		if ( isset( $_POST['sync_rules'] ) && is_array( $_POST['sync_rules'] ) ) {
			$data['sync_rules'] = array_values( array_map( array( $this, 'sanitize_sync_rule' ), $_POST['sync_rules'] ) );
		} else {
			$data['sync_rules'] = array();
		}

		update_term_meta( $term_id, 'yousync_channel', wp_json_encode( $data ) );
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
			'cb'         => $columns['cb'],
			'name'       => $columns['name'],
			'channel_id' => __( 'Channel ID', 'yousync' ),
			'sync_rules' => __( 'Sync Rules', 'yousync' ),
			'posts'      => $columns['posts'],
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
		}

		return $content;
	}
}

new Channel();
