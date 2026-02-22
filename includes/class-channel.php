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
		$channel_id = get_term_meta( $term->term_id, 'channel_id', true );
		$sync_rules = get_term_meta( $term->term_id, 'sync_rules', true ); ?>

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
			$html .= yousync_return_template_part('sync-rule', array(
				'channel_obj' => $this,
				'index' => $index,
				'rule' => $rule,
			));
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

		wp_enqueue_style('tom-select', 'https://cdnjs.cloudflare.com/ajax/libs/tom-select/2.4.3/css/tom-select.min.css', array(), '2.4.3');
		wp_enqueue_style('yousync-admin', YOUSYNC_PLUGIN_URL . 'assets/css/admin.css', array( 'tom-select' ), YOUSYNC_VERSION);
		wp_enqueue_script('tom-select', 'https://cdnjs.cloudflare.com/ajax/libs/tom-select/2.4.3/js/tom-select.complete.min.js', array(), '2.4.3', true);
		wp_enqueue_script('yousync-admin', YOUSYNC_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'tom-select' ), YOUSYNC_VERSION, true);
		wp_localize_script('yousync-admin', 'youSync', array(
			'channelFieldOptions' => yousync_return_template_part('options', 'channel-fields'),
			'operators' => [
				'text' => yousync_return_template_part('options', 'text-operators', ['operator' => '']),
				'number' => yousync_return_template_part('options', 'number-operators', ['operator' => '']),
				'date' => yousync_return_template_part('options', 'date-operators', ['operator' => ''])
			],
			'values' => [
				'text' => yousync_return_template_part('input', 'text'),
				'number' => yousync_return_template_part('input', 'number'),
				'date' => yousync_return_template_part('input', 'date')
			],
			'syncRule' => [
				'channel' => [
					'fieldOptions' => yousync_return_template_part('options', 'channel-fields')
				],
				'video' => [
					'fieldOptions' => yousync_return_template_part('options', 'video-fields')
				],
				'playlist' => [
					'fieldOptions' => yousync_return_template_part('options', 'playlist-fields')
				],
				'condition' => yousync_return_template_part('sync-rule', 'condition'),
				'rule' => yousync_return_template_part('sync-rule')
			]
		));
	}

	/**
	 * Save channel meta.
	 *
	 * @param int $term_id Term ID.
	 * @param int $tt_id Term taxonomy ID.
	 * @return void
	 */
	public function save_channel_meta( $term_id, $tt_id ) {
		// Save channel ID.
		if ( isset( $_POST['channel_id'] ) ) {
			update_term_meta( $term_id, 'channel_id', sanitize_text_field( wp_unslash( $_POST['channel_id'] ) ) );
		}

		// Save sync rules.
		if ( isset( $_POST['sync_rules'] ) && is_array( $_POST['sync_rules'] ) ) {
			$sync_rules = array_map( array( $this, 'sanitize_sync_rule' ), $_POST['sync_rules'] );
			update_term_meta( $term_id, 'sync_rules', $sync_rules );
		}
	}

	/**
	 * Sanitize sync rule.
	 *
	 * @param array $rule Rule data.
	 * @return array Sanitized rule.
	 */
	private function sanitize_sync_rule( $rule ) {
		$sanitized = array(
			'enabled'      => isset( $rule['enabled'] ) ? (bool) $rule['enabled'] : false,
			'schedule'     => isset( $rule['schedule'] ) ? sanitize_text_field( $rule['schedule'] ) : 'daily',
			'custom_hours' => isset( $rule['custom_hours'] ) ? absint( $rule['custom_hours'] ) : 24,
			'what_to_sync' => isset( $rule['what_to_sync'] ) ? sanitize_text_field( $rule['what_to_sync'] ) : 'videos',
			'strategy'     => isset( $rule['strategy'] ) ? sanitize_text_field( $rule['strategy'] ) : '',
		);

		// Sanitize update_specific_fields for specific_fields strategy.
		if ( isset( $rule['update_specific_fields'] ) && is_array( $rule['update_specific_fields'] ) ) {
			$sanitized['update_specific_fields'] = array_map( 'sanitize_text_field', $rule['update_specific_fields'] );
		}

		// Sanitize condition groups.
		if ( isset( $rule['condition_groups'] ) && is_array( $rule['condition_groups'] ) ) {
			$sanitized['condition_groups'] = array_map( array( $this, 'sanitize_condition_group' ), $rule['condition_groups'] );
		}

		// Sanitize contextual fields based on what_to_sync.
		$what_to_sync = isset( $rule['what_to_sync'] ) ? $rule['what_to_sync'] : 'videos';

		if ( 'videos' === $what_to_sync ) {
			// Videos contextual fields.
			if ( isset( $rule['post_status'] ) ) {
				$sanitized['post_status'] = sanitize_text_field( $rule['post_status'] );
			}
			if ( isset( $rule['post_author'] ) ) {
				$sanitized['post_author'] = absint( $rule['post_author'] );
			}
			if ( isset( $rule['post_date'] ) ) {
				$sanitized['post_date'] = sanitize_text_field( $rule['post_date'] );
			}
			if ( isset( $rule['featured_image'] ) ) {
				$sanitized['featured_image'] = sanitize_text_field( $rule['featured_image'] );
			}
			if ( isset( $rule['thumbnail_size_priority'] ) && is_array( $rule['thumbnail_size_priority'] ) ) {
				$sanitized['thumbnail_size_priority'] = array_map( 'sanitize_text_field', $rule['thumbnail_size_priority'] );
			}
			if ( isset( $rule['default_category'] ) ) {
				$sanitized['default_category'] = absint( $rule['default_category'] );
			}
			if ( isset( $rule['tag_handling'] ) ) {
				$sanitized['tag_handling'] = sanitize_text_field( $rule['tag_handling'] );
			}
			if ( isset( $rule['comments'] ) ) {
				$sanitized['comments'] = sanitize_text_field( $rule['comments'] );
			}
			if ( isset( $rule['sync_limit'] ) ) {
				$sanitized['sync_limit'] = absint( $rule['sync_limit'] );
			}
			if ( isset( $rule['priority_order'] ) ) {
				$sanitized['priority_order'] = sanitize_text_field( $rule['priority_order'] );
			}
			if ( isset( $rule['custom_post_meta'] ) && is_array( $rule['custom_post_meta'] ) ) {
				$sanitized['custom_post_meta'] = array_map( function( $meta ) {
					return array(
						'key'   => isset( $meta['key'] ) ? sanitize_text_field( $meta['key'] ) : '',
						'value' => isset( $meta['value'] ) ? sanitize_text_field( $meta['value'] ) : '',
					);
				}, $rule['custom_post_meta'] );
			}
		} elseif ( 'channel_metadata' === $what_to_sync ) {
			// Channel metadata contextual fields.
			if ( isset( $rule['branding_assets'] ) && is_array( $rule['branding_assets'] ) ) {
				$sanitized['branding_assets'] = array_map( 'sanitize_text_field', $rule['branding_assets'] );
			}
			if ( isset( $rule['custom_term_meta'] ) && is_array( $rule['custom_term_meta'] ) ) {
				$sanitized['custom_term_meta'] = array_map( function( $meta ) {
					return array(
						'key'   => isset( $meta['key'] ) ? sanitize_text_field( $meta['key'] ) : '',
						'value' => isset( $meta['value'] ) ? sanitize_text_field( $meta['value'] ) : '',
					);
				}, $rule['custom_term_meta'] );
			}
		}

		// Universal fields.
		if ( isset( $rule['error_handling'] ) ) {
			$sanitized['error_handling'] = sanitize_text_field( $rule['error_handling'] );
		}
		if ( isset( $rule['conflict_resolution'] ) ) {
			$sanitized['conflict_resolution'] = sanitize_text_field( $rule['conflict_resolution'] );
		}
		if ( isset( $rule['notifications'] ) && is_array( $rule['notifications'] ) ) {
			$sanitized['notifications'] = array_map( 'sanitize_text_field', $rule['notifications'] );
		}

		return $sanitized;
	}

	/**
	 * Sanitize condition group.
	 *
	 * @param array $group Group data.
	 * @return array Sanitized group.
	 */
	private function sanitize_condition_group( $group ) {
		$sanitized = array();

		if ( isset( $group['conditions'] ) && is_array( $group['conditions'] ) ) {
			$sanitized['conditions'] = array_map( array( $this, 'sanitize_condition' ), $group['conditions'] );
		}

		return $sanitized;
	}

	/**
	 * Sanitize condition.
	 *
	 * @param array $condition Condition data.
	 * @return array Sanitized condition.
	 */
	private function sanitize_condition( $condition ) {
		return array(
			'field'    => isset( $condition['field'] ) ? sanitize_text_field( $condition['field'] ) : '',
			'operator' => isset( $condition['operator'] ) ? sanitize_text_field( $condition['operator'] ) : '',
			'value'    => isset( $condition['value'] ) ? sanitize_text_field( $condition['value'] ) : '',
			'value2'   => isset( $condition['value2'] ) ? sanitize_text_field( $condition['value2'] ) : '',
		);
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
				$channel_id = get_term_meta( $term_id, 'channel_id', true );
				$content    = $channel_id ? esc_html( $channel_id ) : '—';
				break;

			case 'sync_rules':
				$sync_rules = get_term_meta( $term_id, 'sync_rules', true );
				if ( ! empty( $sync_rules ) && is_array( $sync_rules ) ) {
					$enabled_count = count( array_filter( $sync_rules, function( $rule ) {
						return isset( $rule['enabled'] ) && $rule['enabled'];
					}));
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
