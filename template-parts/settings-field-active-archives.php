<?php
/**
 * Template part for displaying active archives settings field
 *
 * @package YouSync
 * @var array $active_archives The current active archives configuration
 * @var array $custom_archives Array of available archive types
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="form-table permalink-structure">
	<fieldset class="structure-selection">
		<legend class="screen-reader-text"><?php esc_html_e( 'Enabled archives', 'yousync' ); ?></legend>
		<?php
		foreach ( $custom_archives as $archive ) {
			$slug        = $archive['slug'];
			$is_enabled  = isset( $active_archives[ $slug ]['enabled'] ) ? $active_archives[ $slug ]['enabled'] : false;
			$custom_slug = isset( $active_archives[ $slug ]['slug'] ) ? $active_archives[ $slug ]['slug'] : '';
			?>
			<div class="row">
				<input
					id="<?php echo esc_attr( $slug ); ?>-selection"
					name="yousync_active_archives[<?php echo esc_attr( $slug ); ?>][enabled]"
					type="checkbox"
					value="1"
					<?php checked( $is_enabled, true ); ?>
				>
				<div>
					<label for="<?php echo esc_attr( $slug ); ?>-selection">
						<?php echo esc_html( $archive['labels']['singular_name'] ); ?>
					</label>
					<p>
						<label for="<?php echo esc_attr( $slug ); ?>-slug" class="screen-reader-text">
							<?php
							/* translators: %s: Archive type singular name */
							printf( esc_html__( 'Customize %s post type slug', 'yousync' ), esc_html( $archive['labels']['singular_name'] ) );
							?>
						</label>
						<span class="code">
							<code id="<?php echo esc_attr( $slug ); ?>-slug"><?php echo esc_url( site_url() ); ?>/</code>
							<input
								aria-describedby="<?php echo esc_attr( $slug ); ?>-slug"
								class="regular-text code"
								id="<?php echo esc_attr( $slug ); ?>-slug-input"
								name="yousync_active_archives[<?php echo esc_attr( $slug ); ?>][slug]"
								placeholder="<?php echo esc_attr( $slug ); ?>"
								type="text"
								value="<?php echo esc_attr( $custom_slug ); ?>"
							>
						</span>
					</p>
				</div>
			</div>
		<?php } ?>
	</fieldset>
</div>
