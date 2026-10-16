<?php
/**
 * Site footer, mobile bottom bar, post modal, icon sprite, document end.
 */

defined( 'ABSPATH' ) || exit;

$profile_url = iar_profile_url();
?>
		<footer class="site-footer">
			<nav>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
			</nav>
			<p>&copy; <?php echo esc_html( wp_date( 'Y' ) . ' ' . iar_setting( 'name' ) ); ?></p>
		</footer>
	</main>

	<nav class="bottombar" aria-label="<?php esc_attr_e( 'Primary', 'instapress-social' ); ?>">
		<a href="<?php echo esc_url( iar_home_url() ); ?>" aria-label="<?php esc_attr_e( 'Home', 'instapress-social' ); ?>"><?php echo iar_icon( 'home' ); // phpcs:ignore ?></a>
		<a href="<?php echo esc_url( add_query_arg( 'view', 'reels', $profile_url ) ); ?>" aria-label="<?php esc_attr_e( 'Reels', 'instapress-social' ); ?>"><?php echo iar_icon( 'reels' ); // phpcs:ignore ?></a>
		<?php if ( function_exists( 'iar_can_compose' ) && iar_can_compose() ) : ?>
			<a href="<?php echo esc_url( iar_compose_url() ); ?>" aria-label="<?php esc_attr_e( 'New post', 'instapress-social' ); ?>"><?php echo iar_icon( 'create' ); // phpcs:ignore ?></a>
		<?php endif; ?>
		<a href="<?php echo esc_url( $profile_url ); ?>" aria-label="<?php esc_attr_e( 'Profile', 'instapress-social' ); ?>"><img class="avatar" src="<?php echo esc_url( iar_avatar_url() ); ?>" alt=""></a>
	</nav>
</div>

<div class="modal" data-modal role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Post', 'instapress-social' ); ?>">
	<button class="modal__close" aria-label="<?php esc_attr_e( 'Close', 'instapress-social' ); ?>"><?php echo iar_icon( 'close' ); // phpcs:ignore ?></button>
	<button class="modal__step modal__step--prev" aria-label="<?php esc_attr_e( 'Previous post', 'instapress-social' ); ?>"><?php echo iar_icon( 'chevron-left' ); // phpcs:ignore ?></button>
	<button class="modal__step modal__step--next" aria-label="<?php esc_attr_e( 'Next post', 'instapress-social' ); ?>"><?php echo iar_icon( 'chevron-right' ); // phpcs:ignore ?></button>
	<div data-modal-content style="display:contents"></div>
</div>

<?php iar_template_part( 'story-viewer' ); ?>
<?php iar_template_part( 'sprite' ); ?>
<?php wp_footer(); ?>
</body>
</html>
