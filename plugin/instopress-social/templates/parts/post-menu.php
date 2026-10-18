<?php
/**
 * The ⋯ button on a post. For someone who can edit the post it opens a menu
 * (status, editor link, original post, bin); for everyone else it's a plain
 * link to $args['fallback'] (or nothing, without one).
 *
 * @var array $args { @type int $post_id, @type string $fallback, @type string $fallback_label, @type string $original }
 */

defined( 'ABSPATH' ) || exit;

$post_id  = (int) ( $args['post_id'] ?? 0 );
$fallback = (string) ( $args['fallback'] ?? '' );
$original = (string) ( $args['original'] ?? '' );

if ( ! $post_id || ! iar_can_manage_status( $post_id ) ) {
	if ( $fallback ) {
		?>
		<a class="post__more icon-btn" href="<?php echo esc_url( $fallback ); ?>" rel="noopener" aria-label="<?php echo esc_attr( $args['fallback_label'] ?? __( 'Open post', 'instopress-social' ) ); ?>"><?php echo iar_icon( 'more' ); // phpcs:ignore ?></a>
		<?php
	}
	return;
}

$status = get_post_status( $post_id );
$menu   = 'iar-menu-' . $post_id . '-' . wp_rand( 100, 99999 );
?>
<div class="post-menu" data-post-menu data-post-id="<?php echo (int) $post_id; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'iar_status' ) ); ?>">
	<button type="button" class="post__more icon-btn" data-post-menu-toggle aria-haspopup="menu" aria-expanded="false" aria-controls="<?php echo esc_attr( $menu ); ?>" aria-label="<?php esc_attr_e( 'Post options', 'instopress-social' ); ?>"><?php echo iar_icon( 'more' ); // phpcs:ignore ?></button>
	<div class="post-menu__panel" id="<?php echo esc_attr( $menu ); ?>" role="menu" hidden>
		<p class="post-menu__heading"><?php esc_html_e( 'Who can see this', 'instopress-social' ); ?></p>
		<?php foreach ( iar_status_choices() as $value => $label ) : ?>
			<button type="button" role="menuitemradio" data-set-status="<?php echo esc_attr( $value ); ?>" aria-checked="<?php echo $value === $status ? 'true' : 'false'; ?>"><?php echo esc_html( $label ); ?></button>
		<?php endforeach; ?>
		<hr>
		<a role="menuitem" href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>"><?php esc_html_e( 'Edit in WordPress', 'instopress-social' ); ?></a>
		<?php if ( $original ) : ?>
			<a role="menuitem" href="<?php echo esc_url( $original ); ?>" rel="noopener"><?php esc_html_e( 'View original post', 'instopress-social' ); ?></a>
		<?php endif; ?>
		<?php if ( current_user_can( 'delete_post', $post_id ) ) : ?>
			<button type="button" role="menuitem" class="post-menu__danger" data-set-status="trash" data-confirm="<?php esc_attr_e( 'Move this post to the bin? You can restore it from wp-admin.', 'instopress-social' ); ?>"><?php esc_html_e( 'Move to bin', 'instopress-social' ); ?></button>
		<?php endif; ?>
		<p class="post-menu__error" data-post-menu-error hidden></p>
	</div>
</div>
