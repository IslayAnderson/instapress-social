<?php
/**
 * One tile in the profile grid. Carries the post's data for the modal.
 * Expects to run inside The Loop.
 */

defined( 'ABSPATH' ) || exit;

$media = iar_get_media();
if ( ! $media ) {
	return;
}

$first    = $media[0];
$thumb    = $first['thumb'] ?: ( $first['poster'] ?? $first['src'] );
$likes    = (int) iar_field( 'likes' );
$comments = (int) iar_field( 'comments' );
$caption  = iar_get_caption();

$modal_media = array_map( function ( $m ) {
	return array_filter( array(
		'type'   => $m['type'],
		'src'    => $m['src'],
		'poster' => $m['poster'] ?? '',
	) );
}, $media );
?>
<a class="grid__item" href="<?php the_permalink(); ?>"
	data-media="<?php echo esc_attr( wp_json_encode( $modal_media ) ); ?>"
	data-caption="<?php echo esc_attr( $caption ); ?>"
	data-date="<?php echo esc_attr( get_post_time( 'c', true ) ); ?>"
	data-likes="<?php echo esc_attr( $likes ); ?>"
	data-comments="<?php echo esc_attr( $comments ); ?>"
	data-location="<?php echo esc_attr( iar_field( 'location' ) ); ?>"
	data-ratio="<?php echo esc_attr( iar_aspect_ratio() ); ?>">
	<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( wp_html_excerpt( $caption, 100, '…' ) ); ?>" loading="lazy">
	<?php if ( count( $media ) > 1 ) : ?>
		<span class="grid__badge" aria-label="<?php esc_attr_e( 'Carousel', 'instapress-social' ); ?>"><?php echo iar_icon( 'carousel' ); // phpcs:ignore ?></span>
	<?php elseif ( 'video' === $first['type'] ) : ?>
		<span class="grid__badge" aria-label="<?php esc_attr_e( 'Video', 'instapress-social' ); ?>"><?php echo iar_icon( 'reels' ); // phpcs:ignore ?></span>
	<?php endif; ?>
	<span class="grid__overlay">
		<span><?php echo iar_icon( 'heart-filled' ); // phpcs:ignore ?> <?php echo esc_html( iar_count( $likes ) ); ?></span>
		<span><?php echo iar_icon( 'comment-filled' ); // phpcs:ignore ?> <?php echo esc_html( iar_count( $comments ) ); ?></span>
	</span>
</a>
