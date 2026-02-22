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
		$playlist_id = get_term_meta( $term->term_id, 'playlist_id', true );
		$sync_rules = get_term_meta( $term->term_id, 'sync_rules', true );
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

		// Ensure at least one empty rule is always shown
		if ( empty( $sync_rules ) ) {
			$sync_rules = array( array() );
		} ?>

		<div class="ys-sync-rules-header">
			<h2>Sync Rules</h2>
			<button type="button" class="button ys-add-rule" id="ys-add-rule">
				<?php esc_html_e( 'Add Sync Rule', 'yousync' ); ?>
			</button>
		</div>
		<div class="ys-sync-rules" id="ys-sync-rules">
			<?php
				foreach ( $sync_rules as $index => $rule ) {
					yousync_get_template_part('sync-rule', 'playlist', array(
						'playlist_obj' => $this,
						'index' => $index,
						'rule' => $rule,
					));
				}
			?>
		</div>
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

		wp_enqueue_style('tom-select', 'https://cdnjs.cloudflare.com/ajax/libs/tom-select/2.4.3/css/tom-select.min.css', array(), '2.4.3');
		wp_enqueue_style('yousync-admin', YOUSYNC_PLUGIN_URL . 'assets/css/admin.css', array( 'tom-select' ), YOUSYNC_VERSION);

		wp_enqueue_script('tom-select', 'https://cdnjs.cloudflare.com/ajax/libs/tom-select/2.4.3/js/tom-select.complete.min.js', array(), '2.4.3', true);
		wp_enqueue_script('yousync-admin', YOUSYNC_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'tom-select' ), YOUSYNC_VERSION, true);

		wp_localize_script('yousync-admin', 'youSync', array(
			'optionsChannelMetadata' => yousync_return_template_part('options', 'channel-metadata'),
			'optionsVideoMetadata'   => yousync_return_template_part('options', 'video-metadata')
		));
	}

	/**
	 * Save playlist meta.
	 *
	 * @param int $term_id Term ID.
	 * @param int $tt_id Term taxonomy ID.
	 * @return void
	 */
	public function save_playlist_meta( $term_id, $tt_id ) {
		// Save playlist ID.
		if ( isset( $_POST['playlist_id'] ) ) {
			update_term_meta( $term_id, 'playlist_id', sanitize_text_field( wp_unslash( $_POST['playlist_id'] ) ) );
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
			'strategy'     => isset( $rule['strategy'] ) ? sanitize_text_field( $rule['strategy'] ) : '',
		);

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
				$playlist_id = get_term_meta( $term_id, '_playlist_id', true );
				$content     = $playlist_id ? esc_html( $playlist_id ) : '—';
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

	/**
	 * Get sync rule template HTML for JavaScript localization.
	 *
	 * @return string Template HTML.
	 */
	public function get_sync_rule_template_html() {
		ob_start();
		yousync_get_template_part(
			'sync-rule-playlist',
			null,
			array(
				'index'        => '{{INDEX}}',
				'rule'         => array(),
				'playlist_obj' => $this,
			)
		);
		return ob_get_clean();
	}
}

new Playlist();
