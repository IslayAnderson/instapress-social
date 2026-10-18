<?php
/**
 * Single post page: media carousel + caption panel, then more posts.
 *
 * Override by copying to <theme>/instopress-social/single.php.
 */

defined( 'ABSPATH' ) || exit;

$iar = iar_settings();

iar_template_part( 'header', array( 'topbar_title' => __( 'Post', 'instopress-social' ), 'back' => true ) );

while ( have_posts() ) :
	the_post();
	$media    = iar_get_media();
	$likes    = iar_like_total( get_the_ID() );
	$location = iar_field( 'location' );
	$caption  = iar_get_caption();
	$date_c   = get_post_time( 'c', true );
	$date     = get_the_date();
	$ig_url   = iar_field( 'url' );
	$current  = get_the_ID();
	?>
		<div class="container single" data-profile data-username="<?php echo esc_attr( $iar['username'] ); ?>" data-avatar="<?php echo esc_url( iar_avatar_url() ); ?>" data-profile-url="<?php echo esc_url( iar_profile_url() ); ?>">

			<article class="post" data-post-id="<?php the_ID(); ?>" style="--ar: <?php echo esc_attr( iar_aspect_ratio() ); ?>">
				<div class="post__media">
					<div class="carousel">
						<div class="carousel__track">
							<?php foreach ( $media as $m ) : ?>
								<div class="carousel__slide">
									<?php if ( 'text' === $m['type'] ) : ?>
										<?php echo iar_text_svg( get_the_ID(), 'full' ); // phpcs:ignore -- built from escaped/sanitised parts ?>
									<?php elseif ( 'video' === $m['type'] ) : ?>
										<video src="<?php echo esc_url( $m['src'] ); ?>"<?php echo $m['poster'] ? ' poster="' . esc_url( $m['poster'] ) . '"' : ''; ?> controls playsinline preload="metadata"></video>
									<?php else : ?>
										<?php echo wp_get_attachment_image( $m['id'], 'large', false, array( 'alt' => '' ) ); ?>
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
				<div class="post__panel">
					<header class="post__header">
						<img class="avatar" src="<?php echo esc_url( iar_avatar_url() ); ?>" alt="">
						<div>
							<a class="post__user" href="<?php echo esc_url( iar_profile_url() ); ?>"><?php echo esc_html( $iar['username'] ); ?></a>
							<?php $badge = iar_status_badge( get_post_status() ); ?>
							<span class="post__status" data-post-status-badge<?php echo $badge ? '' : ' hidden'; ?>><?php echo esc_html( $badge ); ?></span>
							<?php if ( $location ) : ?>
								<span class="post__location"><?php echo esc_html( $location ); ?></span>
							<?php endif; ?>
						</div>
						<?php
						iar_template_part( 'post-menu', array(
							'post_id'        => get_the_ID(),
							'fallback'       => $ig_url,
							'fallback_label' => __( 'View original post', 'instopress-social' ),
							'original'       => $ig_url,
						) );
						?>
					</header>
					<div class="post__body">
						<div class="comment">
							<img class="avatar" src="<?php echo esc_url( iar_avatar_url() ); ?>" alt="">
							<div>
								<p class="comment__text"><a class="post__user" href="<?php echo esc_url( iar_profile_url() ); ?>"><?php echo esc_html( $iar['username'] ); ?></a><?php echo iar_format_caption( $caption ); // phpcs:ignore -- escaped inside ?></p>
								<div class="comment__meta"><time datetime="<?php echo esc_attr( $date_c ); ?>"><?php echo esc_html( $date ); ?></time></div>
							</div>
						</div>
						<div class="comments" data-comments><?php echo iar_comments_html( get_the_ID() ); // phpcs:ignore -- escaped inside ?></div>
					</div>
					<footer class="post__footer">
						<div class="post__actions">
							<?php if ( iar_likes_enabled() ) : ?>
								<button class="icon-btn" data-action="like" data-post-id="<?php the_ID(); ?>" aria-label="<?php esc_attr_e( 'Like', 'instopress-social' ); ?>"><?php echo iar_icon( 'heart' ); // phpcs:ignore ?></button>
							<?php endif; ?>
							<button class="icon-btn" data-action="comment" aria-label="<?php esc_attr_e( 'Comment', 'instopress-social' ); ?>"><?php echo iar_icon( 'comment' ); // phpcs:ignore ?></button>
							<button class="icon-btn" aria-label="<?php esc_attr_e( 'Share', 'instopress-social' ); ?>" data-action="share"><?php echo iar_icon( 'share' ); // phpcs:ignore ?></button>
							<button class="icon-btn" data-action="save" aria-label="<?php esc_attr_e( 'Save', 'instopress-social' ); ?>"><?php echo iar_icon( 'bookmark' ); // phpcs:ignore ?></button>
						</div>
						<p class="post__likes" data-likes-count="<?php echo esc_attr( $likes ); ?>"><?php echo esc_html( sprintf( /* translators: %s: number of likes */ _n( '%s like', '%s likes', $likes, 'instopress-social' ), iar_count( $likes ) ) ); ?></p>
						<a class="post__date" href="<?php the_permalink(); ?>"><time datetime="<?php echo esc_attr( $date_c ); ?>"><?php echo esc_html( $date ); ?></time></a>
						<?php echo iar_comment_form_html( get_the_ID() ); // phpcs:ignore -- escaped inside ?>
					</footer>
				</div>
			</article>

			<?php
			$related = new WP_Query( array(
				'post_type'      => IAR_POST_TYPE,
				'posts_per_page' => 6,
				'post__not_in'   => array( $current ),
				'no_found_rows'  => true,
			) );
			if ( $related->have_posts() ) :
				?>
				<section class="single__more">
					<h2><?php
						printf(
							/* translators: %s: username */
							esc_html__( 'More posts from %s', 'instopress-social' ),
							'<a href="' . esc_url( iar_profile_url() ) . '">' . esc_html( $iar['username'] ) . '</a>'
						);
					?></h2>
					<div class="grid" style="--tile-ar: <?php echo esc_attr( iar_grid_ratio() ); ?>">
						<?php
						while ( $related->have_posts() ) :
							$related->the_post();
							iar_template_part( 'grid-item' );
						endwhile;
						wp_reset_postdata();
						?>
					</div>
				</section>
			<?php endif; ?>
		</div>
	<?php
endwhile;

iar_template_part( 'footer' );
