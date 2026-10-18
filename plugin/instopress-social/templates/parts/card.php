<?php
/**
 * A post in the home feed — yours or someone you follow.
 *
 * @var array $args { @type array $item, @type array $profile }
 */

defined( 'ABSPATH' ) || exit;

$item    = $args['item'];
$profile = $args['profile'];
$media   = $item['media'];
$likes   = (int) $item['likes'];
$own     = ! empty( $profile['own'] );
$caption = (string) $item['caption'];
$long    = mb_strlen( $caption ) > 125 || substr_count( $caption, "\n" ) > 1;
?>
<article class="card" data-feed-item>
	<header class="card__header">
		<a href="<?php echo esc_url( $profile['profile'] ); ?>" class="card__avatar"<?php echo $own ? '' : ' rel="noopener"'; ?>>
			<img class="avatar" src="<?php echo esc_url( $profile['avatar'] ); ?>" alt="">
		</a>
		<div class="card__who">
			<a class="post__user" href="<?php echo esc_url( $profile['profile'] ); ?>"<?php echo $own ? '' : ' rel="noopener"'; ?>><?php echo esc_html( $profile['username'] ); ?></a>
			<span class="card__dot">•</span>
			<a class="card__time" href="<?php echo esc_url( $item['url'] ); ?>"><time datetime="<?php echo esc_attr( $item['date'] ); ?>"><?php echo esc_html( iar_time_ago( $item['date'] ) ); ?></time></a>
			<?php if ( ! empty( $item['location'] ) ) : ?>
				<span class="post__location"><?php echo esc_html( $item['location'] ); ?></span>
			<?php endif; ?>
		</div>
		<?php
		iar_template_part( 'post-menu', array(
			'post_id'  => $own ? (int) ( $item['post_id'] ?? 0 ) : 0,
			'fallback' => $item['url'],
		) );
		?>
	</header>

	<div class="card__media" style="--ar: <?php echo esc_attr( (float) $item['ratio'] ?: 1 ); ?>">
		<div class="carousel">
			<div class="carousel__track">
				<?php foreach ( $media as $m ) : ?>
					<div class="carousel__slide">
						<?php if ( 'text' === $m['type'] && $own && ! empty( $item['post_id'] ) ) : ?>
							<?php echo iar_text_svg( (int) $item['post_id'], 'full' ); // phpcs:ignore -- built from escaped/sanitised parts ?>
						<?php elseif ( 'video' === $m['type'] ) : ?>
							<video src="<?php echo esc_url( $m['src'] ); ?>"<?php echo ! empty( $m['poster'] ) ? ' poster="' . esc_url( $m['poster'] ) . '"' : ''; ?> controls playsinline preload="metadata"></video>
						<?php else : ?>
							<img src="<?php echo esc_url( $m['src'] ); ?>" alt="" loading="lazy"<?php echo ! empty( $m['width'] ) ? ' width="' . (int) $m['width'] . '" height="' . (int) $m['height'] . '"' : ''; ?>>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
			<?php if ( count( $media ) > 1 ) : ?>
				<button class="carousel__nav carousel__nav--prev" aria-label="<?php esc_attr_e( 'Previous', 'instopress-social' ); ?>"><?php echo iar_icon( 'chevron-left' ); // phpcs:ignore ?></button>
				<button class="carousel__nav carousel__nav--next" aria-label="<?php esc_attr_e( 'Next', 'instopress-social' ); ?>"><?php echo iar_icon( 'chevron-right' ); // phpcs:ignore ?></button>
				<div class="carousel__dots"><?php echo str_repeat( '<span class="carousel__dot"></span>', count( $media ) ); // phpcs:ignore ?></div>
			<?php endif; ?>
		</div>
	</div>

	<div class="card__body">
		<div class="post__actions">
			<?php if ( $own && ! empty( $item['post_id'] ) && iar_likes_enabled() ) : ?>
				<button class="icon-btn" data-action="like" data-post-id="<?php echo (int) $item['post_id']; ?>" aria-label="<?php esc_attr_e( 'Like', 'instopress-social' ); ?>"><?php echo iar_icon( 'heart' ); // phpcs:ignore ?></button>
			<?php elseif ( ! $own ) : ?>
				<a class="icon-btn" href="<?php echo esc_url( $item['url'] ); ?>" rel="noopener" aria-label="<?php esc_attr_e( 'Like on their site', 'instopress-social' ); ?>"><?php echo iar_icon( 'heart' ); // phpcs:ignore ?></a>
			<?php endif; ?>
			<a class="icon-btn" href="<?php echo esc_url( $item['url'] ); ?>" aria-label="<?php esc_attr_e( 'Comment', 'instopress-social' ); ?>"><?php echo iar_icon( 'comment' ); // phpcs:ignore ?></a>
			<button class="icon-btn" data-action="share" data-url="<?php echo esc_url( $item['url'] ); ?>" aria-label="<?php esc_attr_e( 'Share', 'instopress-social' ); ?>"><?php echo iar_icon( 'share' ); // phpcs:ignore ?></button>
			<button class="icon-btn" data-action="save" aria-label="<?php esc_attr_e( 'Save', 'instopress-social' ); ?>"><?php echo iar_icon( 'bookmark' ); // phpcs:ignore ?></button>
		</div>
		<p class="post__likes" data-likes-count="<?php echo esc_attr( $likes ); ?>"><?php echo esc_html( sprintf( /* translators: %s: number of likes */ _n( '%s like', '%s likes', $likes, 'instopress-social' ), iar_count( $likes ) ) ); ?></p>
		<?php if ( '' !== trim( $caption ) ) : ?>
			<p class="card__caption<?php echo $long ? ' is-clamped' : ''; ?>"><a class="post__user" href="<?php echo esc_url( $profile['profile'] ); ?>"><?php echo esc_html( $profile['username'] ); ?></a> <?php echo iar_format_caption( $caption ); // phpcs:ignore -- escaped inside ?></p>
			<?php if ( $long ) : ?>
				<button class="card__more" data-action="expand"><?php esc_html_e( 'more', 'instopress-social' ); ?></button>
			<?php endif; ?>
		<?php endif; ?>
		<?php if ( ! empty( $item['comments'] ) ) : ?>
			<a class="card__comments" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( sprintf( /* translators: %s: number of comments */ _n( 'View %s comment', 'View all %s comments', (int) $item['comments'], 'instopress-social' ), iar_count( $item['comments'] ) ) ); ?></a>
		<?php endif; ?>
	</div>
</article>
