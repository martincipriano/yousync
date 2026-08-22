<?php
declare(strict_types=1);
/**
 * Template part: <option> list for a sync rule's post status <select>.
 *
 * Pulls from get_post_stati() instead of a hardcoded list so any custom
 * status a theme or plugin registers (e.g. an editorial "Pending Review"
 * status) is available here too. Excludes internal-only statuses (trash,
 * auto-draft, inherit, the post-password-request statuses) and 'future' —
 * scheduling a future post_date isn't something a sync rule collects, so
 * offering it would create a status WordPress silently corrects back to
 * 'publish' the moment the post is saved without a future date.
 *
 * @package Buoy_Video_Sync
 *
 * Variables available in this template:
 * @var string $selected Currently selected post_status value.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$selected = isset( $selected ) ? $selected : 'publish';

$statuses = get_post_stati( array( 'internal' => false ), 'objects' );
unset( $statuses['future'] );

// Guarantee 'publish' is always present and first, even if a theme/plugin
// ever unregisters or reorders it.
if ( ! isset( $statuses['publish'] ) ) {
	$statuses = array( 'publish' => (object) array( 'name' => 'publish', 'label' => __( 'Published', 'buoy-video-sync' ) ) ) + $statuses;
}
?>
<?php foreach ( $statuses as $status ) : ?>
<option value="<?php echo esc_attr( $status->name ); ?>" <?php selected( $selected, $status->name ); ?>><?php echo esc_html( $status->label ); ?></option>
<?php endforeach; ?>
