<?php
/**
 * One tile in the profile grid; links to the post's own page.
 * Expects to run inside The Loop.
 */

defined( 'ABSPATH' ) || exit;

// A tile shows the first slide only; the rest just count towards the carousel badge.
$media = iar_get_media( null, 1 );
if ( ! $media ) {
	return;
}
$slides = iar_media_count();

$first    = $media[0];
// A video without a poster shows its own first frame rather than a borrowed image.
$thumb    = $first['thumb'] ?: ( 'image' === $first['type'] ? $first['src'] : '' );
$likes    = iar_like_total( get_the_ID() );
$comments = iar_comment_total( get_the_ID() );
$caption  = iar_get_caption();

// The first three rows are on screen straight away: load them now and leave the rest
// to load as they scroll in. The first tile with a picture is usually the page's
// largest paint, so it goes first; $args['priority'] marks it (text and poster-less
// video tiles before it have no image to fetch).
// $args['index'] is the tile's position on the page, when the caller knows it's at the top.
$index    = isset( $args['index'] ) ? (int) $args['index'] : 99;
$eager    = $index < 9;
$priority = isset( $args['priority'] ) ? (bool) $args['priority'] : 0 === $index;

// Let phones pick a smaller copy: a tile is a third of the grid (the full screen on
// phones, at most the 935px container on desktop).
$image_id = 'video' === $first['type'] ? ( $first['poster_id'] ?? 0 ) : ( $first['id'] ?? 0 );
$srcset   = $image_id && 'text' !== $first['type'] ? wp_get_attachment_image_srcset( $image_id, 'medium_large' ) : '';
?>
<a class="grid__item" href="<?php the_permalink(); ?>">
	<?php if ( 'text' === $first['type'] ) : ?>
		<?php echo iar_text_svg( get_the_ID(), 'tile' ); // phpcs:ignore -- built from escaped/sanitised parts ?>
	<?php elseif ( $thumb ) : ?>
		<img src="<?php echo esc_url( $thumb ); ?>"<?php if ( $srcset ) : ?> srcset="<?php echo esc_attr( $srcset ); ?>" sizes="(max-width: 935px) 33vw, 300px"<?php endif; ?> alt="<?php echo esc_attr( wp_html_excerpt( $caption, 100, '…' ) ); ?>" <?php echo $eager || $priority ? 'loading="eager"' : 'loading="lazy"'; ?><?php echo $priority ? ' fetchpriority="high"' : ''; ?> decoding="async">
	<?php else : ?>
		<?php // No poster yet: show a frame of the video, fetched only once the tile scrolls into view (assets/js/app.js). ?>
		<video class="grid__video" data-src="<?php echo esc_url( $first['src'] ); ?>#t=0.5" muted playsinline preload="none" tabindex="-1" aria-label="<?php echo esc_attr( wp_html_excerpt( $caption, 100, '…' ) ); ?>"></video>
	<?php endif; ?>
	<?php if ( $slides > 1 ) : ?>
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
