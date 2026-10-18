<?php
/**
 * Home feed: stories row of people you follow, then their posts and yours,
 * newest first. Override by copying to <theme>/instopress-social/home.php.
 */

defined( 'ABSPATH' ) || exit;

$per_page  = (int) apply_filters( 'iar_home_per_page', 10 );
$page_num  = max( 1, absint( $_GET['pg'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
$entries   = iar_home_entries();
$shown     = array_slice( $entries, ( $page_num - 1 ) * $per_page, $per_page );
$has_more  = count( $entries ) > $page_num * $per_page;
$following = iar_following();
$me        = iar_own_profile();

iar_template_part( 'header', array( 'topbar_title' => $me['username'] ) );
?>
		<div class="container home" data-profile data-username="<?php echo esc_attr( $me['username'] ); ?>" data-avatar="<?php echo esc_url( $me['avatar'] ); ?>" data-profile-url="<?php echo esc_url( $me['profile'] ); ?>">
			<div class="home__feed">

				<?php
				// People with live stories first (they get the ring), then everyone else.
				$story_groups = iar_story_groups( true );
				$people       = $following;
				uksort( $people, function ( $a, $b ) use ( $story_groups ) {
					return (int) isset( $story_groups[ $b ] ) - (int) isset( $story_groups[ $a ] );
				} );
				?>
				<ul class="stories">
					<li class="story">
						<?php if ( isset( $story_groups['me'] ) ) : ?>
							<button type="button" data-story-open="me" data-story-ids="<?php echo esc_attr( implode( ' ', wp_list_pluck( $story_groups['me']['stories'], 'id' ) ) ); ?>">
								<span class="story__ring has-story"><img class="avatar" src="<?php echo esc_url( $me['avatar'] ); ?>" alt=""></span>
								<span class="story__name"><?php esc_html_e( 'Your story', 'instopress-social' ); ?></span>
							</button>
						<?php else : ?>
							<a href="<?php echo esc_url( $me['profile'] ); ?>">
								<span class="story__ring story__ring--own"><img class="avatar" src="<?php echo esc_url( $me['avatar'] ); ?>" alt=""></span>
								<span class="story__name"><?php esc_html_e( 'Your profile', 'instopress-social' ); ?></span>
							</a>
						<?php endif; ?>
						<?php if ( iar_can_compose() ) : ?>
							<a class="story__add" href="<?php echo esc_url( iar_compose_url( 'story' ) ); ?>" aria-label="<?php esc_attr_e( 'Add a story', 'instopress-social' ); ?>">+</a>
						<?php endif; ?>
					</li>
					<?php foreach ( $people as $id => $f ) : ?>
						<li class="story">
							<?php if ( isset( $story_groups[ $id ] ) ) : ?>
								<button type="button" data-story-open="<?php echo esc_attr( $id ); ?>" data-story-ids="<?php echo esc_attr( implode( ' ', wp_list_pluck( $story_groups[ $id ]['stories'], 'id' ) ) ); ?>">
									<span class="story__ring has-story"><img class="avatar" src="<?php echo esc_url( $f['avatar'] ); ?>" alt=""></span>
									<span class="story__name"><?php echo esc_html( $f['username'] ); ?></span>
								</button>
							<?php else : ?>
								<a href="<?php echo esc_url( $f['profile'] ); ?>" rel="noopener">
									<span class="story__ring story__ring--own"><img class="avatar" src="<?php echo esc_url( $f['avatar'] ); ?>" alt=""></span>
									<span class="story__name"><?php echo esc_html( $f['username'] ); ?></span>
								</a>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>

				<?php if ( ! $following && current_user_can( 'manage_options' ) ) : ?>
					<div class="home__empty">
						<p><strong><?php esc_html_e( 'Follow people to see their posts here.', 'instopress-social' ); ?></strong></p>
						<p><?php esc_html_e( 'Press Follow on another instopress-social profile, then paste the link into Following.', 'instopress-social' ); ?></p>
						<a class="btn btn--primary" href="<?php echo esc_url( iar_following_admin_url() ); ?>"><?php esc_html_e( 'Go to Following', 'instopress-social' ); ?></a>
					</div>
				<?php endif; ?>

				<div class="cards" data-feed>
					<?php
					foreach ( $shown as $entry ) {
						iar_template_part( 'card', $entry );
					}
					?>
				</div>

				<?php if ( $has_more ) : ?>
					<div class="load-more">
						<a class="btn" href="<?php echo esc_url( iar_home_url( $page_num + 1 ) ); ?>" data-load-more data-target="[data-feed]" data-items="[data-feed] [data-feed-item]"><?php esc_html_e( 'Load more', 'instopress-social' ); ?></a>
					</div>
				<?php elseif ( $entries ) : ?>
					<p class="home__end"><?php esc_html_e( 'You\'re all caught up.', 'instopress-social' ); ?></p>
				<?php endif; ?>
			</div>

			<aside class="home__side">
				<div class="side__me">
					<img class="avatar" src="<?php echo esc_url( $me['avatar'] ); ?>" alt="">
					<div>
						<a class="post__user" href="<?php echo esc_url( $me['profile'] ); ?>"><?php echo esc_html( $me['username'] ); ?></a>
						<span class="side__sub"><?php echo esc_html( $me['name'] ); ?></span>
					</div>
				</div>
				<?php if ( $following ) : ?>
					<h2 class="side__title"><?php esc_html_e( 'Following', 'instopress-social' ); ?></h2>
					<ul class="side__list">
						<?php foreach ( $following as $f ) : ?>
							<li>
								<img class="avatar" src="<?php echo esc_url( $f['avatar'] ); ?>" alt="">
								<div>
									<a class="post__user" href="<?php echo esc_url( $f['profile'] ); ?>" rel="noopener"><?php echo esc_html( $f['username'] ); ?></a>
									<span class="side__sub"><?php echo esc_html( $f['name'] ?: wp_parse_url( $f['profile'], PHP_URL_HOST ) ); ?></span>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<a class="side__manage" href="<?php echo esc_url( iar_following_admin_url() ); ?>"><?php esc_html_e( 'Manage following', 'instopress-social' ); ?></a>
				<?php endif; ?>
			</aside>
		</div>
<?php
iar_template_part( 'footer' );
