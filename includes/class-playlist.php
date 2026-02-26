<?php
/**
 * Playlist Taxonomy Management
 *
 * @package YouSync
 */

namespace YouSync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Playlist Class
 */
class Playlist {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'yousync_playlist_add_form_fields', array( $this, 'add_playlist_fields' ) );
		add_action( 'yousync_playlist_edit_form_fields', array( $this, 'edit_playlist_fields' ), 10, 2 );
		add_action( 'created_yousync_playlist', array( $this, 'save_playlist_meta' ), 10, 2 );
		add_action( 'edited_yousync_playlist', array( $this, 'save_playlist_meta' ), 10, 2 );
		add_filter( 'manage_edit-yousync_playlist_columns', array( $this, 'add_playlist_columns' ) );
		add_filter( 'manage_yousync_playlist_custom_column', array( $this, 'playlist_column_content' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Register playlist taxonomy.
	 *
	 * @return void
	 */
	public function register_taxonomy() {
		$labels = array(
			'name'                       => __( 'Playlists', 'yousync' ),
			'singular_name'              => __( 'Playlist', 'yousync' ),
			'menu_name'                  => __( 'Playlists', 'yousync' ),
			'all_items'                  => __( 'All Playlists', 'yousync' ),
			'edit_item'                  => __( 'Edit Playlist', 'yousync' ),
			'view_item'                  => __( 'View Playlist', 'yousync' ),
			'update_item'                => __( 'Update Playlist', 'yousync' ),
			'add_new_item'               => __( 'Add New Playlist', 'yousync' ),
			'new_item_name'              => __( 'New Playlist Name', 'yousync' ),
			'search_items'               => __( 'Search Playlists', 'yousync' ),
			'popular_items'              => __( 'Popular Playlists', 'yousync' ),
			'separate_items_with_commas' => __( 'Separate playlists with commas', 'yousync' ),
			'add_or_remove_items'        => __( 'Add or remove playlists', 'yousync' ),
			'choose_from_most_used'      => __( 'Choose from most used playlists', 'yousync' ),
			'not_found'                  => __( 'No playlists found', 'yousync' ),
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

		register_taxonomy( 'yousync_playlist', 'yousync_videos', $args );
	}

	/**
	 * Add custom fields to playlist add form.
	 *
	 * @return void
	 */
	public function add_playlist_fields() { ?>
		<div class="form-field term-playlist-id-wrap">
			<label for="playlist-id"><?php esc_html_e( 'Playlist ID', 'yousync' ); ?></label>
			<input name="playlist_id" id="playlist-id" type="text" value="" aria-required="true" aria-describedby="playlist-id-description">
			<p class="description" id="playlist-id-description">
				<?php esc_html_e( 'Enter the YouTube playlist ID (e.g., PLrAXtmErZgOeiKm4sgNOknGvNjby9efdf)', 'yousync' ); ?>
			</p>
		</div>
		<?php
		$this->render_sync_rules_section();
	}

	/**
	 * Add custom fields to playlist edit form.
	 *
	 * @param WP_Term $term Current taxonomy term object.
	 * @param string  $taxonomy Current taxonomy slug.
	 * @return void
	 */
	public function edit_playlist_fields( $term, $taxonomy ) {
		$meta = get_term_meta( $term->term_id, 'yousync_playlist', true );
		$data = $meta ? json_decode( $meta, true ) : array();
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		$playlist_id          = isset( $data['playlist_id'] ) ? $data['playlist_id'] : '';
		$sync_rules           = isset( $data['sync_rules'] ) ? $data['sync_rules'] : array();
		$playlist_title       = isset( $data['playlist_title'] ) ? $data['playlist_title'] : '';
		$playlist_description = isset( $data['playlist_description'] ) ? $data['playlist_description'] : '';
		$playlist_video_count = isset( $data['playlist_video_count'] ) ? $data['playlist_video_count'] : '';
		$playlist_thumbnail   = isset( $data['playlist_thumbnail'] ) ? $data['playlist_thumbnail'] : array();
		$last_synced          = isset( $data['last_synced'] ) ? $data['last_synced'] : '';
		$sync_count           = isset( $data['sync_count'] ) ? $data['sync_count'] : 0;
		?>

		<tr class="form-field term-playlist-id-wrap">
			<th scope="row">
				<label for="playlist-id"><?php esc_html_e( 'Playlist ID', 'yousync' ); ?></label>
			</th>
			<td>
				<input name="playlist_id" id="playlist-id" type="text" value="<?php echo esc_attr( $playlist_id ); ?>" size="40" aria-describedby="playlist-id-description">
				<p class="description" id="playlist-id-description">
					<?php esc_html_e( 'YouTube playlist ID (e.g., PLrAXtmErZgOeiKm4sgNOknGvNjby9efdf)', 'yousync' ); ?>
				</p>
			</td>
		</tr>

		<?php if ( $playlist_title ) : ?>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Playlist Title', 'yousync' ); ?></th>
			<td><p><?php echo esc_html( $playlist_title ); ?></p></td>
		</tr>
		<?php endif; ?>

		<?php if ( $playlist_description ) : ?>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Description', 'yousync' ); ?></th>
			<td><p><?php echo esc_html( $playlist_description ); ?></p></td>
		</tr>
		<?php endif; ?>

		<?php if ( ! empty( $playlist_thumbnail ) ) : ?>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Thumbnail', 'yousync' ); ?></th>
			<td>
				<?php if ( ! empty( $playlist_thumbnail['attachment_id'] ) ) : ?>
					<?php echo wp_get_attachment_image( (int) $playlist_thumbnail['attachment_id'], array( 120, 90 ) ); ?>
				<?php elseif ( ! empty( $playlist_thumbnail['url'] ) ) : ?>
					<img src="<?php echo esc_url( $playlist_thumbnail['url'] ); ?>" style="max-width:160px;height:auto;" alt="">
				<?php endif; ?>
			</td>
		</tr>
		<?php endif; ?>

		<?php if ( $playlist_video_count !== '' ) : ?>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Videos', 'yousync' ); ?></th>
			<td><p><?php echo esc_html( number_format( (int) $playlist_video_count ) ); ?></p></td>
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
	private function render_sync_rules_section( $sync_rules = array() ) {
		$html = '';
		foreach ( $sync_rules as $index => $rule ) {
			$html .= yousync_return_template_part( 'sync-rule-playlist', null, array(
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

		// Only load on playlist taxonomy pages.
		if ( ! $screen || 'edit-yousync_playlist' !== $screen->id ) {
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
				'playlist' => array(
					'fieldOptions'    => yousync_return_template_part( 'options', 'playlist-fields' ),
					'metadataOptions' => yousync_return_template_part( 'options', 'playlist-metadata' ),
				),
				'video' => array(
					'fieldOptions'    => yousync_return_template_part( 'options', 'video-fields' ),
					'metadataOptions' => yousync_return_template_part( 'options', 'video-metadata' ),
				),
				'condition' => yousync_return_template_part( 'sync-rule', 'condition' ),
				'rule'      => yousync_return_template_part( 'sync-rule-playlist' ),
			),
		) );
	}

	/**
	 * Save playlist meta.
	 *
	 * Merges user-editable fields (playlist_id, sync_rules) into the existing JSON
	 * meta, preserving any YouTube API data that was previously synced.
	 *
	 * @param int $term_id Term ID.
	 * @param int $tt_id Term taxonomy ID.
	 * @return void
	 */
	public function save_playlist_meta( $term_id, $tt_id ) {
		// Read existing JSON meta to preserve YouTube API data.
		$existing_meta = get_term_meta( $term_id, 'yousync_playlist', true );
		$data          = $existing_meta ? json_decode( $existing_meta, true ) : array();
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		// Update editable fields.
		if ( isset( $_POST['playlist_id'] ) ) {
			$data['playlist_id'] = sanitize_text_field( wp_unslash( $_POST['playlist_id'] ) );
		}

		// Update sync rules (re-index to ensure sequential keys).
		if ( isset( $_POST['sync_rules'] ) && is_array( $_POST['sync_rules'] ) ) {
			$data['sync_rules'] = array_values( array_map( array( $this, 'sanitize_sync_rule' ), $_POST['sync_rules'] ) );
		} else {
			$data['sync_rules'] = array();
		}

		update_term_meta( $term_id, 'yousync_playlist', wp_json_encode( $data ) );
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
	 * Add custom columns to playlist list.
	 *
	 * @param array $columns Columns.
	 * @return array Modified columns.
	 */
	public function add_playlist_columns( $columns ) {
		$new_columns = array(
			'cb'          => $columns['cb'],
			'name'        => $columns['name'],
			'playlist_id' => __( 'Playlist ID', 'yousync' ),
			'sync_rules'  => __( 'Sync Rules', 'yousync' ),
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
	public function playlist_column_content( $content, $column_name, $term_id ) {
		switch ( $column_name ) {
			case 'playlist_id':
				$meta        = get_term_meta( $term_id, 'yousync_playlist', true );
				$data        = $meta ? json_decode( $meta, true ) : array();
				$playlist_id = isset( $data['playlist_id'] ) ? $data['playlist_id'] : '';
				$content     = $playlist_id ? esc_html( $playlist_id ) : '—';
				break;

			case 'sync_rules':
				$meta       = get_term_meta( $term_id, 'yousync_playlist', true );
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

new Playlist();
