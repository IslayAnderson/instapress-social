<?php
/**
 * Profile page: header, tabs, grid of posts, "load more".
 *
 * Override by copying to <theme>/instapress-social/archive.php.
 */

defined( 'ABSPATH' ) || exit;

$iar         = iar_settings();
$profile_url = iar_profile_url();
$tab         = iar_current_tab();
$total       = (int) wp_count_posts( IAR_POST_TYPE )->publish;
$website     = $iar['website'] ? preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( $iar['website'] ) ) : '';

$stats = array( array( iar_count( $total ), _n( 'post', 'posts', $total, 'instapress-social' ) ) );
if ( '' !== $iar['followers'] && iar_show_follow_counts() ) {
	$stats[] = array( $iar['followers'], __( 'followers', 'instapress-social' ) );
}
$following_n = count( iar_following() );
if ( ( $following_n || '' !== $iar['following'] ) && iar_show_follow_counts() ) {
	$stats[] = array( $following_n ? iar_count( $following_n ) : $iar['following'], __( 'following', 'instapress-social' ) );
}

iar_template_part( 'header' );
?>
		<div class="container">

			<header class="profile" data-profile data-username="<?php echo esc_attr( $iar['username'] ); ?>" data-avatar="<?php echo esc_url( iar_avatar_url() ); ?>" data-profile-url="<?php echo esc_url( $profile_url ); ?>">
				<div class="profile__avatar">
					<?php $own_stories = iar_own_story_items(); ?>
					<?php if ( $own_stories ) : ?>
						<button type="button" class="avatar-ring has-story" data-story-open="me" data-story-ids="<?php echo esc_attr( implode( ' ', wp_list_pluck( $own_stories, 'id' ) ) ); ?>" aria-label="<?php esc_attr_e( 'View story', 'instapress-social' ); ?>"><img class="avatar" src="<?php echo esc_url( iar_avatar_url( 'medium' ) ); ?>" alt="<?php echo esc_attr( $iar['name'] ); ?>"></button>
					<?php else : ?>
						<span class="avatar-ring no-story"><img class="avatar" src="<?php echo esc_url( iar_avatar_url( 'medium' ) ); ?>" alt="<?php echo esc_attr( $iar['name'] ); ?>"></span>
					<?php endif; ?>
				</div>
				<section class="profile__info">
					<div class="profile__row">
						<h1 class="profile__username"><?php echo esc_html( $iar['username'] ); ?></h1>
						<div class="profile__actions">
							<button type="button" class="btn btn--primary" data-follow="<?php echo esc_url( iar_feed_url() ); ?>" data-copied="<?php esc_attr_e( 'Link copied — paste it into instapress-social → Following on your WordPress site.', 'instapress-social' ); ?>"><?php esc_html_e( 'Follow', 'instapress-social' ); ?></button>
							<?php if ( $iar['website'] ) : ?>
								<a class="btn" href="<?php echo esc_url( $iar['website'] ); ?>"><?php esc_html_e( 'Website', 'instapress-social' ); ?></a>
							<?php endif; ?>
							<?php if ( iar_can_compose() ) : ?>
								<a class="btn" href="<?php echo esc_url( iar_compose_url() ); ?>"><?php esc_html_e( 'New post', 'instapress-social' ); ?></a>
							<?php endif; ?>
							<?php if ( iar_download_enabled() ) : ?>
								<a class="btn" href="<?php echo esc_url( iar_download_url() ); ?>" download title="<?php echo esc_attr( sprintf( __( 'Download the instapress-social WordPress plugin (version %s) to follow people from your own site', 'instapress-social' ), IAR_VERSION ) ); ?>"><?php esc_html_e( 'Get instapress-social', 'instapress-social' ); ?></a>
							<?php endif; ?>
						</div>
					</div>
					<ul class="profile__stats">
						<?php foreach ( $stats as $stat ) : ?>
							<li><b><?php echo esc_html( $stat[0] ); ?></b> <?php echo esc_html( $stat[1] ); ?></li>
						<?php endforeach; ?>
					</ul>
					<div class="profile__about">
						<?php if ( $iar['name'] ) : ?>
							<p class="profile__name"><?php echo esc_html( $iar['name'] ); ?></p>
						<?php endif; ?>
						<?php if ( $iar['bio'] ) : ?>
							<p class="profile__bio"><?php echo iar_format_caption( $iar['bio'] ); // phpcs:ignore -- escaped inside ?></p>
						<?php endif; ?>
						<?php if ( $website ) : ?>
							<a class="profile__link" href="<?php echo esc_url( $iar['website'] ); ?>"><?php echo iar_icon( 'link' ); // phpcs:ignore ?><?php echo esc_html( $website ); ?></a>
						<?php endif; ?>
					</div>
				</section>
			</header>

			<ul class="stats-bar">
				<?php foreach ( $stats as $stat ) : ?>
					<li><b><?php echo esc_html( $stat[0] ); ?></b> <?php echo esc_html( $stat[1] ); ?></li>
				<?php endforeach; ?>
			</ul>

			<nav class="tabs" aria-label="<?php esc_attr_e( 'Profile sections', 'instapress-social' ); ?>">
				<a class="tabs__tab<?php echo 'posts' === $tab ? ' is-active' : ''; ?>" href="<?php echo esc_url( $profile_url ); ?>"<?php echo 'posts' === $tab ? ' aria-current="page"' : ''; ?>><?php echo iar_icon( 'grid' ); // phpcs:ignore ?><span><?php esc_html_e( 'Posts', 'instapress-social' ); ?></span></a>
				<a class="tabs__tab<?php echo 'reels' === $tab ? ' is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'view', 'reels', $profile_url ) ); ?>"<?php echo 'reels' === $tab ? ' aria-current="page"' : ''; ?>><?php echo iar_icon( 'reels' ); // phpcs:ignore ?><span><?php esc_html_e( 'Reels', 'instapress-social' ); ?></span></a>
				<?php if ( iar_can_view_home() ) : ?>
				<a class="tabs__tab tabs__tab--home" href="<?php echo esc_url( iar_home_url() ); ?>"><?php echo iar_icon( 'home' ); // phpcs:ignore ?><span><?php esc_html_e( 'Home', 'instapress-social' ); ?></span></a>
				<?php endif; ?>
			</nav>

			<?php $hashtag = iar_current_hashtag(); ?>
			<?php if ( '' !== $hashtag ) : ?>
				<div class="hashtag-bar">
					<h2>#<?php echo esc_html( $hashtag ); ?></h2>
					<span><?php echo esc_html( sprintf( _n( '%s post', '%s posts', (int) $GLOBALS['wp_query']->found_posts, 'instapress-social' ), iar_count( $GLOBALS['wp_query']->found_posts ) ) ); ?></span>
					<a href="<?php echo esc_url( $profile_url ); ?>"><?php esc_html_e( 'Show all', 'instapress-social' ); ?></a>
				</div>
			<?php endif; ?>

			<?php if ( have_posts() ) : ?>
				<div class="grid" data-grid style="--tile-ar: <?php echo esc_attr( iar_grid_ratio() ); ?>">
					<?php
					while ( have_posts() ) :
						the_post();
						iar_template_part( 'grid-item', is_paged() ? array() : array( 'index' => $GLOBALS['wp_query']->current_post ) );
					endwhile;
					?>
				</div>

				<?php $next = get_next_posts_page_link(); ?>
				<?php if ( $next && get_query_var( 'paged', 1 ) < $GLOBALS['wp_query']->max_num_pages ) : ?>
					<div class="load-more">
						<a class="btn" href="<?php echo esc_url( $next ); ?>" data-load-more><?php esc_html_e( 'Load more', 'instapress-social' ); ?></a>
					</div>
				<?php endif; ?>
			<?php else : ?>
				<p class="empty"><?php esc_html_e( 'No posts yet.', 'instapress-social' ); ?></p>
			<?php endif; ?>
		</div>
<?php
iar_template_part( 'footer' );
