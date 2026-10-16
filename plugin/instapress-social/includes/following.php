<?php
/**
 * Following other instapress-social sites.
 *
 * You paste someone's feed URL (or profile URL — the feed is discovered) on
 * instapress-social → Following. Their feed is fetched hourly by WP-Cron and the
 * latest posts are cached in an option; the home feed reads from that cache.
 * Media is shown from the other site (hotlinked), never copied.
 */

defined( 'ABSPATH' ) || exit;

const IAR_FOLLOWING_OPTION = 'iar_following';      // id => profile + status
const IAR_ITEMS_OPTION     = 'iar_following_items_'; // + id => cached items
const IAR_CRON_HOOK        = 'iar_refresh_following';

function iar_following() {
	return (array) get_option( IAR_FOLLOWING_OPTION, array() );
}

function iar_follow_id( $feed_url ) {
	return substr( md5( strtolower( untrailingslashit( $feed_url ) ) ), 0, 12 );
}

function iar_following_items( $id ) {
	return (array) get_option( IAR_ITEMS_OPTION . $id, array() );
}

/* -------------------------------------------------------------------------
 * Fetching
 * ---------------------------------------------------------------------- */

/**
 * HTTP GET that refuses private/internal addresses — except on local dev
 * sites, so two Local/.test sites can follow each other.
 */
function iar_http_get( $url ) {
	$local = in_array( wp_get_environment_type(), array( 'local', 'development' ), true );
	$allow = function () {
		return true;
	};
	$ports = function ( $p ) use ( $url ) {
		$p[] = (int) wp_parse_url( $url, PHP_URL_PORT );
		return $p;
	};
	if ( $local ) {
		add_filter( 'http_request_host_is_external', $allow );
		add_filter( 'http_allowed_safe_ports', $ports );
	}
	$res = wp_safe_remote_get( $url, array(
		'timeout'             => 10,
		'redirection'         => 3,
		'limit_response_size' => 3 * MB_IN_BYTES,
		'sslverify'           => ! $local,
		'user-agent'          => 'instapress-social/' . IAR_VERSION . '; ' . home_url( '/' ),
	) );
	if ( $local ) {
		remove_filter( 'http_request_host_is_external', $allow );
		remove_filter( 'http_allowed_safe_ports', $ports );
	}
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$code = wp_remote_retrieve_response_code( $res );
	if ( 200 !== $code ) {
		return new WP_Error( 'iar_http', sprintf( __( 'The site answered with HTTP %d.', 'instapress-social' ), $code ) );
	}
	return wp_remote_retrieve_body( $res );
}

function iar_clean_url( $url ) {
	$url = esc_url_raw( trim( (string) $url ), array( 'http', 'https' ) );
	return $url ?: '';
}

/**
 * Parse an instapress-social feed. Returns [ profile, items ] or WP_Error.
 */
function iar_parse_feed( $xml, $feed_url ) {
	if ( ! function_exists( 'simplexml_load_string' ) ) {
		return new WP_Error( 'iar_xml', __( 'This server is missing PHP\'s SimpleXML extension.', 'instapress-social' ) );
	}
	$prev = libxml_use_internal_errors( true );
	$doc  = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA );
	libxml_use_internal_errors( $prev );
	if ( ! $doc || ! isset( $doc->channel ) ) {
		return new WP_Error( 'iar_xml', __( 'That isn\'t an RSS feed.', 'instapress-social' ) );
	}

	$channel = $doc->channel;
	// Sites still on a pre-1.3.0 version publish under the old namespace.
	$uri     = in_array( IAR_OLD_NS, $doc->getDocNamespaces( true ), true ) && ! in_array( IAR_NS, $doc->getDocNamespaces( true ), true ) ? IAR_OLD_NS : IAR_NS;
	$ns      = $channel->children( $uri );
	if ( ! isset( $ns->username ) || '' === trim( (string) $ns->username ) ) {
		return new WP_Error( 'iar_not_insta', __( 'That feed isn\'t from an instapress-social site.', 'instapress-social' ) );
	}

	$profile = array(
		'username' => sanitize_user( (string) $ns->username, true ),
		'name'     => sanitize_text_field( (string) $ns->name ),
		'avatar'   => iar_clean_url( (string) $ns->avatar ),
		'profile'  => iar_clean_url( (string) $ns->profile ) ?: iar_clean_url( (string) $channel->link ),
		'feed'     => $feed_url,
		'count'    => (int) $ns->count,
		'stories'  => iar_parse_feed_stories( $ns ),
	);

	$items = array();
	foreach ( $channel->item as $item ) {
		$ins   = $item->children( $uri );
		$media = array();
		foreach ( $item->children( 'http://search.yahoo.com/mrss/' )->content as $c ) {
			$a   = $c->attributes();
			$src = iar_clean_url( (string) $a->url );
			if ( ! $src ) {
				continue;
			}
			$thumb   = $c->children( 'http://search.yahoo.com/mrss/' )->thumbnail;
			$media[] = array_filter( array(
				'type'   => 'video' === (string) $a->medium ? 'video' : 'image',
				'src'    => $src,
				'poster' => $thumb ? iar_clean_url( (string) $thumb->attributes()->url ) : '',
				'width'  => (int) $a->width,
				'height' => (int) $a->height,
			) );
		}
		$url = iar_clean_url( (string) $item->link );
		if ( ! $media || ! $url ) {
			continue;
		}
		$ratio   = (float) $ins->ratio;
		$items[] = array(
			'id'       => $url,
			'url'      => $url,
			'date'     => gmdate( 'c', strtotime( (string) $item->pubDate ) ?: time() ),
			'caption'  => sanitize_textarea_field( (string) $item->description ),
			'ratio'    => $ratio >= 0.5 && $ratio <= 2 ? $ratio : 1,
			'type'     => sanitize_key( (string) $ins->type ),
			'likes'    => (int) $ins->likes,
			'comments' => (int) $ins->comments,
			'location' => sanitize_text_field( (string) $ins->location ),
			'media'    => array_slice( $media, 0, 20 ),
		);
	}

	return array( $profile, array_slice( $items, 0, 50 ) );
}

/**
 * Turn whatever was pasted into a feed URL: a feed is used as-is, a page is
 * searched for the <link rel="alternate" … data-instapress-social> tag
 * (or data-insta-archive, from versions before 1.3.0).
 */
function iar_resolve_feed( $url ) {
	$url  = iar_clean_url( $url );
	if ( ! $url ) {
		return new WP_Error( 'iar_url', __( 'Paste a full link starting with http:// or https://.', 'instapress-social' ) );
	}
	$body = iar_http_get( $url );
	if ( is_wp_error( $body ) ) {
		return $body;
	}
	if ( false !== stripos( substr( $body, 0, 500 ), '<rss' ) ) {
		return array( $url, $body );
	}
	if ( preg_match_all( '/<link\b[^>]*>/i', $body, $tags ) ) {
		foreach ( $tags[0] as $tag ) {
			if ( preg_match( '/data-(instapress-social|insta-archive)\b/i', $tag ) && preg_match( '/href=["\']([^"\']+)["\']/i', $tag, $m ) ) {
				$feed = iar_clean_url( html_entity_decode( $m[1] ) );
				$xml  = $feed ? iar_http_get( $feed ) : new WP_Error( 'iar_url', '' );
				return is_wp_error( $xml ) ? $xml : array( $feed, $xml );
			}
		}
	}
	return new WP_Error( 'iar_no_feed', __( 'Couldn\'t find an instapress-social feed at that link.', 'instapress-social' ) );
}

/** Follow a feed (or profile) URL. Returns the follow record or WP_Error. */
function iar_follow( $url ) {
	$resolved = iar_resolve_feed( $url );
	if ( is_wp_error( $resolved ) ) {
		return $resolved;
	}
	list( $feed, $xml ) = $resolved;

	if ( iar_follow_id( $feed ) === iar_follow_id( iar_feed_url() ) ) {
		return new WP_Error( 'iar_self', __( 'That\'s your own feed — your posts are already in your home feed.', 'instapress-social' ) );
	}

	$parsed = iar_parse_feed( $xml, $feed );
	if ( is_wp_error( $parsed ) ) {
		return $parsed;
	}
	list( $profile, $items ) = $parsed;

	$id                = iar_follow_id( $feed );
	$all               = iar_following();
	$profile['added']  = $all[ $id ]['added'] ?? time();
	$profile['synced'] = time();
	$profile['error']  = '';
	$all[ $id ]        = $profile;

	update_option( IAR_FOLLOWING_OPTION, $all, false );
	update_option( IAR_ITEMS_OPTION . $id, $items, false );
	return $profile;
}

function iar_unfollow( $id ) {
	$all = iar_following();
	unset( $all[ $id ] );
	update_option( IAR_FOLLOWING_OPTION, $all, false );
	delete_option( IAR_ITEMS_OPTION . $id );
}

/** Re-fetch one followed feed; keeps the old posts if the fetch fails. */
function iar_refresh_one( $id ) {
	$all = iar_following();
	if ( empty( $all[ $id ] ) ) {
		return;
	}
	$xml    = iar_http_get( $all[ $id ]['feed'] );
	$parsed = is_wp_error( $xml ) ? $xml : iar_parse_feed( $xml, $all[ $id ]['feed'] );

	if ( is_wp_error( $parsed ) ) {
		$all[ $id ]['error'] = $parsed->get_error_message();
	} else {
		list( $profile, $items ) = $parsed;
		$all[ $id ] = array_merge( $all[ $id ], $profile, array( 'synced' => time(), 'error' => '' ) );
		update_option( IAR_ITEMS_OPTION . $id, $items, false );
	}
	update_option( IAR_FOLLOWING_OPTION, $all, false );
}

function iar_refresh_all() {
	foreach ( array_keys( iar_following() ) as $id ) {
		iar_refresh_one( $id );
	}
}
add_action( IAR_CRON_HOOK, 'iar_refresh_all' );

add_action( 'init', function () {
	if ( ! wp_next_scheduled( IAR_CRON_HOOK ) ) {
		wp_schedule_event( time() + 300, 'hourly', IAR_CRON_HOOK );
	}
} );

/* -------------------------------------------------------------------------
 * Admin: instapress-social → Following
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=' . IAR_POST_TYPE,
		__( 'Following', 'instapress-social' ),
		__( 'Following', 'instapress-social' ),
		'manage_options',
		'iar-following',
		'iar_render_following_page'
	);
}, 9 );

add_action( 'admin_post_iar_follow', function () {
	check_admin_referer( 'iar_follow' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you can\'t do that.', 'instapress-social' ) );
	}
	$result = iar_follow( wp_unslash( $_POST['feed'] ?? '' ) );
	$args   = is_wp_error( $result )
		? array( 'iar_error' => rawurlencode( $result->get_error_message() ) )
		: array( 'iar_followed' => rawurlencode( $result['username'] ) );
	wp_safe_redirect( add_query_arg( $args, iar_following_admin_url() ) );
	exit;
} );

add_action( 'admin_post_iar_following_action', function () {
	check_admin_referer( 'iar_following_action' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you can\'t do that.', 'instapress-social' ) );
	}
	$id = sanitize_key( $_POST['id'] ?? '' );
	switch ( sanitize_key( $_POST['do'] ?? '' ) ) {
		case 'unfollow':
			iar_unfollow( $id );
			break;
		case 'refresh':
			iar_refresh_one( $id );
			break;
		case 'refresh_all':
			iar_refresh_all();
			break;
	}
	wp_safe_redirect( add_query_arg( 'iar_done', 1, iar_following_admin_url() ) );
	exit;
} );

function iar_following_admin_url() {
	return admin_url( 'edit.php?post_type=' . IAR_POST_TYPE . '&page=iar-following' );
}

function iar_render_following_page() {
	$all = iar_following();
	uasort( $all, function ( $a, $b ) {
		return ( $b['added'] ?? 0 ) <=> ( $a['added'] ?? 0 );
	} );
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Following', 'instapress-social' ); ?></h1>
		<a class="page-title-action" href="<?php echo esc_url( iar_home_url() ); ?>"><?php esc_html_e( 'View home feed', 'instapress-social' ); ?></a>
		<hr class="wp-header-end">

		<?php if ( isset( $_GET['iar_error'] ) ) : // phpcs:ignore ?>
			<div class="notice notice-error is-dismissible"><p><?php echo esc_html( wp_unslash( rawurldecode( $_GET['iar_error'] ) ) ); // phpcs:ignore ?></p></div>
		<?php elseif ( isset( $_GET['iar_followed'] ) ) : // phpcs:ignore ?>
			<div class="notice notice-success is-dismissible"><p><?php printf( esc_html__( 'You\'re now following @%s.', 'instapress-social' ), esc_html( sanitize_user( rawurldecode( $_GET['iar_followed'] ), true ) ) ); // phpcs:ignore ?></p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:16px 0 24px;max-width:720px;">
			<?php wp_nonce_field( 'iar_follow' ); ?>
			<input type="hidden" name="action" value="iar_follow">
			<label for="iar-feed" style="display:block;font-weight:600;margin-bottom:6px;"><?php esc_html_e( 'Follow someone', 'instapress-social' ); ?></label>
			<div style="display:flex;gap:8px;">
				<input type="url" id="iar-feed" name="feed" class="regular-text" style="flex:1;" required placeholder="https://their-site.com/feed/insta/">
				<?php submit_button( __( 'Follow', 'instapress-social' ), 'primary', 'submit', false ); ?>
			</div>
			<p class="description"><?php esc_html_e( 'Paste the link copied from the Follow button on their profile — or just their profile link.', 'instapress-social' ); ?></p>
		</form>

		<h2><?php printf( esc_html__( 'Following %s', 'instapress-social' ), '<span class="count">(' . count( $all ) . ')</span>' ); // phpcs:ignore ?></h2>
		<?php if ( ! $all ) : ?>
			<p><?php esc_html_e( 'You\'re not following anyone yet.', 'instapress-social' ); ?></p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:960px;">
				<thead><tr>
					<th style="width:48px;"></th>
					<th><?php esc_html_e( 'Account', 'instapress-social' ); ?></th>
					<th><?php esc_html_e( 'Posts in feed', 'instapress-social' ); ?></th>
					<th><?php esc_html_e( 'Last updated', 'instapress-social' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $all as $id => $f ) : ?>
					<tr>
						<td><img src="<?php echo esc_url( $f['avatar'] ); ?>" alt="" style="width:40px;height:40px;border-radius:50%;object-fit:cover;background:#f0f0f1;"></td>
						<td>
							<strong><a href="<?php echo esc_url( $f['profile'] ); ?>" target="_blank" rel="noopener">@<?php echo esc_html( $f['username'] ); ?></a></strong>
							<?php if ( $f['name'] ) : ?><br><?php echo esc_html( $f['name'] ); ?><?php endif; ?>
							<br><code style="font-size:11px;"><?php echo esc_html( $f['feed'] ); ?></code>
							<?php if ( ! empty( $f['error'] ) ) : ?>
								<br><span style="color:#b32d2e;"><?php echo esc_html( $f['error'] ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo (int) count( iar_following_items( $id ) ); ?></td>
						<td><?php echo $f['synced'] ? esc_html( sprintf( __( '%s ago', 'instapress-social' ), human_time_diff( $f['synced'] ) ) ) : '—'; ?></td>
						<td style="text-align:right;white-space:nowrap;">
							<?php foreach ( array( 'refresh' => __( 'Refresh', 'instapress-social' ), 'unfollow' => __( 'Unfollow', 'instapress-social' ) ) as $do => $label ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<?php wp_nonce_field( 'iar_following_action' ); ?>
									<input type="hidden" name="action" value="iar_following_action">
									<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">
									<input type="hidden" name="do" value="<?php echo esc_attr( $do ); ?>">
									<button class="button<?php echo 'unfollow' === $do ? ' button-link-delete' : ''; ?>" <?php echo 'unfollow' === $do ? 'onclick="return confirm(\'' . esc_js( sprintf( __( 'Unfollow @%s?', 'instapress-social' ), $f['username'] ) ) . '\')"' : ''; ?>><?php echo esc_html( $label ); ?></button>
								</form>
							<?php endforeach; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;">
				<?php wp_nonce_field( 'iar_following_action' ); ?>
				<input type="hidden" name="action" value="iar_following_action">
				<input type="hidden" name="do" value="refresh_all">
				<button class="button"><?php esc_html_e( 'Refresh all now', 'instapress-social' ); ?></button>
				<span class="description"><?php esc_html_e( 'Feeds refresh automatically every hour.', 'instapress-social' ); ?></span>
			</form>
		<?php endif; ?>

		<h2 style="margin-top:32px;"><?php esc_html_e( 'Your feed', 'instapress-social' ); ?></h2>
		<p><?php esc_html_e( 'Others follow you with this link (the Follow button on your profile copies it):', 'instapress-social' ); ?></p>
		<p><code><?php echo esc_html( iar_feed_url() ); ?></code></p>
	</div>
	<?php
}
