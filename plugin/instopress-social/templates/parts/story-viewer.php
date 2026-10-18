<?php
/**
 * Full-screen story viewer: the data for every story group on this page,
 * plus the empty overlay that assets/js/stories.js fills in.
 *
 * Profile and single pages carry your own stories; the home feed adds the
 * people you follow.
 */

defined( 'ABSPATH' ) || exit;

$groups = iar_story_groups( iar_is_home() );
if ( ! $groups ) {
	return;
}
?>
<script type="application/json" id="iar-stories-data"><?php echo wp_json_encode( $groups, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES ); ?></script>
<div class="story-viewer" data-story-viewer hidden role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Stories', 'instopress-social' ); ?>">
	<div class="story-viewer__frame">
		<div class="story-viewer__bars" data-story-bars></div>
		<header class="story-viewer__head">
			<a class="story-viewer__user" data-story-user href="#">
				<img class="avatar" alt="">
				<span class="story-viewer__name"></span>
				<span class="story-viewer__time"></span>
			</a>
			<button type="button" class="story-viewer__btn" data-story-mute hidden aria-label="<?php esc_attr_e( 'Sound on/off', 'instopress-social' ); ?>">🔇</button>
			<button type="button" class="story-viewer__btn" data-story-pause aria-label="<?php esc_attr_e( 'Pause', 'instopress-social' ); ?>">❚❚</button>
			<button type="button" class="story-viewer__btn" data-story-close aria-label="<?php esc_attr_e( 'Close', 'instopress-social' ); ?>"><?php echo iar_icon( 'close' ); // phpcs:ignore ?></button>
		</header>
		<div class="story-viewer__media" data-story-media></div>
		<p class="story-viewer__text" data-story-text hidden></p>
		<a class="story-viewer__link" data-story-link hidden target="_blank" rel="noopener"><?php echo iar_icon( 'link' ); // phpcs:ignore ?> <span></span></a>
		<button type="button" class="story-viewer__tap story-viewer__tap--prev" data-story-prev aria-label="<?php esc_attr_e( 'Previous', 'instopress-social' ); ?>"></button>
		<button type="button" class="story-viewer__tap story-viewer__tap--next" data-story-next aria-label="<?php esc_attr_e( 'Next', 'instopress-social' ); ?>"></button>
	</div>
</div>
