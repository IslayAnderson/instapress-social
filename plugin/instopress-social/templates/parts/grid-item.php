<?php
/**
 * One tile in the profile grid; links to the post's own page.
 * Expects to run inside The Loop.
 */

defined( 'ABSPATH' ) || exit;

$media = iar_get_media();
if ( ! $media ) {
	return;
}

$first    = $media[0];
// A video without a poster shows its own first frame rather than a borrowed image.
$thumb    = $first['thumb'] ?: ( 'image' === $first['type'] ? $first['src'] : '' );
$likes    = iar_like_total( get_the_ID() );
$comments = iar_comment_total( get_the_ID() );
$caption  = iar_get_caption();

// The first rows are on screen straight away: load them now (the very first
// with high priority) and leave the rest to load as they scroll in.
// $args['index'] is the tile's position on the page, when the caller knows it's at the top.
$index = isset( $args['index'] ) ? (int) $args['index'] : 99;
$eager = $index < 6;
?>
<a class="grid__item" href="<?php the_permalink(); ?>">
	<?php if ( 'text' === $first['type'] ) : ?>
		<?php echo iar_text_svg( get_the_ID(), 'tile' ); // phpcs:ignore -- built from escaped/sanitised parts ?>
	<?php elseif ( $thumb ) : ?>
		<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( wp_html_excerpt( $caption, 100, '…' ) ); ?>" <?php echo $eager ? ( 0 === $index ? 'fetchpriority="high"' : 'loading="eager"' ) : 'loading="lazy"'; ?> decoding="async">
	<?php else : ?>
		<?php // No poster yet: show a frame of the video, fetched only once the tile scrolls into view (assets/js/app.js). ?>
		<video class="grid__video" data-src="<?php echo esc_url( $first['src'] ); ?>#t=0.5" muted playsinline preload="none" tabindex="-1" aria-label="<?php echo esc_attr( wp_html_excerpt( $caption, 100, '…' ) ); ?>"></video>
	<?php endif; ?>
	<?php if ( count( $media ) > 1 ) : ?>
		<span class="grid__badge" aria-label="<?php esc_attr_e( 'Carousel', 'instopress-social' ); ?>"><?php echo iar_icon( 'carousel' ); // phpcs:ignore ?></span>
	<?php elseif ( 'video' === $first['type'] ) : ?>
		<span class="grid__badge" aria-label="<?php esc_attr_e( 'Video', 'instopress-social' ); ?>"><?php echo iar_icon( 'reels' ); // phpcs:ignore ?></span>
	<?php endif; ?>
	<?php $badge = iar_status_badge( get_post_status() ); ?>
	<?php if ( $badge ) : ?>
		<span class="grid__status"><?php echo esc_html( $badge ); ?></span>
	<?php endif; ?>
	<span class="grid__overlay">
		<span><?php echo iar_icon( 'heart-filled' ); // phpcs:ignore ?> <?php echo esc_html( iar_count( $likes ) ); ?></span>
		<span><?php echo iar_icon( 'comment-filled' ); // phpcs:ignore ?> <?php echo esc_html( iar_count( $comments ) ); ?></span>
	</span>
</a>
