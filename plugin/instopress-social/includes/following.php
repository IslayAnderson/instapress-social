<?php
/**
 * Following other instopress-social sites.
 *
 * You paste someone's feed URL (or profile URL — the feed is discovered) on
 * instopress-social → Following. Their feed is fetched hourly by WP-Cron and the
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

/**
 * Who a follow is, whatever address their feed had: site + username. The same
 * person can turn up under two feed addresses (an old one and its successor
 * /social/feed/), which would otherwise be two separate follows.
 */
function iar_follow_identity( $f ) {
	$host = (string) wp_parse_url( ( $f['profile'] ?? '' ) ?: ( $f['feed'] ?? '' ), PHP_URL_HOST );
	return strtolower( preg_replace( '/^www\./i', '', $host ) . '|' . ( $f['username'] ?? '' ) );
}

/** Ids of every follow of the same person as $f. */
function iar_follow_ids_like( $all, $f ) {
	$who = iar_follow_identity( $f );
	return array_keys( array_filter( $all, function ( $other ) use ( $who ) {
		return iar_follow_identity( $other ) === $who;
	} ) );
}

/** Merge duplicate follows of one person: keep the most recently synced. Returns how many were removed. */
function iar_dedupe_following() {
	$all     = iar_following();
	$removed = 0;
	$seen    = array();
	uasort( $all, function ( $a, $b ) {
		return ( $b['synced'] ?? 0 ) <=> ( $a['synced'] ?? 0 );
	} );
	foreach ( $all as $id => $f ) {
		$who = iar_follow_identity( $f );
		if ( isset( $seen[ $who ] ) ) {
			$keep = $seen[ $who ];
			$all[ $keep ]['added'] = min( $all[ $keep ]['added'] ?? time(), $f['added'] ?? time() );
			unset( $all[ $id ] );
			delete_option( IAR_ITEMS_OPTION . $id );
			++$removed;
		} else {
			$seen[ $who ] = $id;
		}
	}
	if ( $removed ) {
		update_option( IAR_FOLLOWING_OPTION, $all, false );
	}
	return $removed;
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
		'user-agent'          => 'instopress-social/' . IAR_VERSION . '; ' . home_url( '/' ),
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
		return new WP_Error( 'iar_http', sprintf( /* translators: %d: HTTP status code */ __( 'The site answered with HTTP %d.', 'instopress-social' ), $code ) );
	}
	return wp_remote_retrieve_body( $res );
}

function iar_clean_url( $url ) {
	$url = esc_url_raw( trim( (string) $url ), array( 'http', 'https' ) );
	return $url ?: '';
}

/**
 * Parse an instopress-social feed. Returns [ profile, items ] or WP_Error.
 */
function iar_parse_feed( $xml, $feed_url ) {
	if ( ! function_exists( 'simplexml_load_string' ) ) {
		return new WP_Error( 'iar_xml', __( 'This server is missing PHP\'s SimpleXML extension.', 'instopress-social' ) );
	}
	$prev = libxml_use_internal_errors( true );
	$doc  = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA );
	libxml_use_internal_errors( $prev );
	if ( ! $doc || ! isset( $doc->channel ) ) {
		return new WP_Error( 'iar_xml', __( 'That isn\'t an RSS feed.', 'instopress-social' ) );
	}

	$channel = $doc->channel;
	// Sites still on a pre-1.3.0 version publish under the old namespace.
	// The namespace a feed uses: this version's, or an older version's (sites that haven't updated yet).
	$uri     = IAR_NS;
	foreach ( array_merge( array( IAR_NS ), IAR_LEGACY_NAMESPACES ) as $candidate ) {
		if ( in_array( $candidate, $doc->getDocNamespaces( true ), true ) ) {
			$uri = $candidate;
			break;
		}
	}
	$ns      = $channel->children( $uri );
	if ( ! isset( $ns->username ) || '' === trim( (string) $ns->username ) ) {
		return new WP_Error( 'iar_not_ours', __( 'That feed isn\'t from an instopress-social site.', 'instopress-social' ) );
	}

	$profile = array(
		'username' => sanitize_user( (string) $ns->username, true ),
		'name'     => sanitize_text_field( (string) $ns->name ),
		'avatar'   => iar_clean_url( (string) $ns->avatar ),
		'profile'  => iar_clean_url( (string) $ns->profile ) ?: iar_clean_url( (string) $channel->link ),
		// The address the feed gives for itself (on the same site), so a moved feed is followed at its new address.
		'feed'     => iar_feed_self_url( $channel, $feed_url ),
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
 * searched for the <link rel="alternate" … data-instopress-social> tag
 * (or an older version's attribute, see includes/legacy.php).
 */
/** A feed's own address (atom:link rel="self") when it's on the same site as where we fetched it; else where we fetched it. */
function iar_feed_self_url( $channel, $fetched ) {
	foreach ( $channel->children( 'http://www.w3.org/2005/Atom' )->link as $link ) {
		$a = $link->attributes();
		if ( 'self' === (string) $a['rel'] ) {
			$self = iar_clean_url( (string) $a['href'] );
			if ( $self && strtolower( (string) wp_parse_url( $self, PHP_URL_HOST ) ) === strtolower( (string) wp_parse_url( $fetched, PHP_URL_HOST ) ) ) {
				return $self;
			}
		}
	}
	return $fetched;
}

function iar_resolve_feed( $url ) {
	$url  = iar_clean_url( $url );
	if ( ! $url ) {
		return new WP_Error( 'iar_url', __( 'Paste a full link starting with http:// or https://.', 'instopress-social' ) );
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
			if ( preg_match( '/data-(' . implode( '|', array_map( 'preg_quote', array_merge( array( 'instopress-social' ), IAR_LEGACY_DISCOVERY ) ) ) . ')\b/i', $tag ) && preg_match( '/href=["\']([^"\']+)["\']/i', $tag, $m ) ) {
				$feed = iar_clean_url( html_entity_decode( $m[1] ) );
				$xml  = $feed ? iar_http_get( $feed ) : new WP_Error( 'iar_url', '' );
				return is_wp_error( $xml ) ? $xml : array( $feed, $xml );
			}
		}
	}
	return new WP_Error( 'iar_no_feed', __( 'Couldn\'t find an instopress-social feed at that link.', 'instopress-social' ) );
}

/** Follow a feed (or profile) URL. Returns the follow record or WP_Error. */
function iar_follow( $url ) {
	$resolved = iar_resolve_feed( $url );
	if ( is_wp_error( $resolved ) ) {
		return $resolved;
	}
	list( $feed, $xml ) = $resolved;

	if ( iar_follow_id( $feed ) === iar_follow_id( iar_feed_url() ) ) {
		return new WP_Error( 'iar_self', __( 'That\'s your own feed — your posts are already in your home feed.', 'instopress-social' ) );
	}

	$parsed = iar_parse_feed( $xml, $feed );
	if ( is_wp_error( $parsed ) ) {
		return $parsed;
	}
	list( $profile, $items ) = $parsed;

	$all  = iar_following();
	$same = iar_follow_ids_like( $all, $profile );
	// Already following this person (maybe at an older feed address): update that follow instead of adding another.
	$id   = $same ? $same[0] : iar_follow_id( $feed );
	foreach ( array_slice( $same, 1 ) as $dupe ) {
		unset( $all[ $dupe ] );
		delete_option( IAR_ITEMS_OPTION . $dupe );
	}
	$profile['added']  = $all[ $id ]['added'] ?? time();
	$profile['synced'] = time();
	$profile['error']  = '';
	$all[ $id ]        = $profile;

	update_option( IAR_FOLLOWING_OPTION, $all, false );
	update_option( IAR_ITEMS_OPTION . $id, $items, false );
	return $profile;
}

/** Stop following someone (every follow of that person, if they were followed twice). Returns their username, or ''. */
function iar_unfollow( $id ) {
	$all = iar_following();
	if ( empty( $all[ $id ] ) ) {
		return '';
	}
	$username = (string) $all[ $id ]['username'];
	foreach ( array_unique( array_merge( array( $id ), iar_follow_ids_like( $all, $all[ $id ] ) ) ) as $gone ) {
		unset( $all[ $gone ] );
		delete_option( IAR_ITEMS_OPTION . $gone );
	}
	update_option( IAR_FOLLOWING_OPTION, $all, false );
	return $username;
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
	iar_dedupe_following();
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
 * Admin: instopress-social → Following
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=' . IAR_POST_TYPE,
		__( 'Following', 'instopress-social' ),
		__( 'Following', 'instopress-social' ),
		'manage_options',
		'iar-following',
		'iar_render_following_page'
	);
}, 9 );

add_action( 'admin_post_iar_follow', function () {
	check_admin_referer( 'iar_follow' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you can\'t do that.', 'instopress-social' ) );
	}
	$result = iar_follow( esc_url_raw( wp_unslash( $_POST['feed'] ?? '' ) ) );
	$args   = is_wp_error( $result )
		? array( 'iar_error' => rawurlencode( $result->get_error_message() ) )
		: array( 'iar_followed' => rawurlencode( $result['username'] ) );
	wp_safe_redirect( add_query_arg( $args, iar_following_admin_url() ) );
	exit;
} );

add_action( 'admin_post_iar_following_action', function () {
	check_admin_referer( 'iar_following_action' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you can\'t do that.', 'instopress-social' ) );
	}
	$id   = sanitize_key( $_POST['id'] ?? '' );
	$args = array();
	switch ( sanitize_key( $_POST['do'] ?? '' ) ) {
		case 'unfollow':
			$who  = iar_unfollow( $id );
			$args = $who ? array( 'iar_unfollowed' => rawurlencode( $who ) ) : array( 'iar_error' => rawurlencode( __( 'That account wasn\'t in your list — it may already be unfollowed.', 'instopress-social' ) ) );
			break;
		case 'refresh':
			iar_refresh_one( $id );
			$f    = iar_following()[ $id ] ?? null;
			$args = ! $f ? array() : ( empty( $f['error'] ) ? array( 'iar_refreshed' => rawurlencode( $f['username'] ) ) : array( 'iar_error' => rawurlencode( sprintf( /* translators: %1$s: username, %2$s: error message */ __( 'Couldn\'t refresh @%1$s: %2$s', 'instopress-social' ), $f['username'], $f['error'] ) ) ) );
			break;
		case 'refresh_all':
			iar_refresh_all();
			$args = array( 'iar_refreshed' => '*' );
			break;
	}
	wp_safe_redirect( add_query_arg( $args, iar_following_admin_url() ) );
	exit;
} );

function iar_following_admin_url() {
	return admin_url( 'edit.php?post_type=' . IAR_POST_TYPE . '&page=iar-following' );
}

/*
 * The Follow button on someone else's profile opens <this site>/?iar_follow=<their feed>
 * in a new tab. Pass it on to the Following page (WordPress asks you to log in first if
 * needed), where it's filled in for you to confirm. Nothing is followed without that click.
 */
add_action( 'template_redirect', function () {
	if ( empty( $_GET['iar_follow'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$feed = iar_clean_url( wp_unslash( $_GET['iar_follow'] ) ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	wp_safe_redirect( $feed ? add_query_arg( 'follow', rawurlencode( $feed ), iar_following_admin_url() ) : iar_following_admin_url() );
	exit;
}, 0 );

function iar_render_following_page() {
	iar_dedupe_following();
	$all = iar_following();
	uasort( $all, function ( $a, $b ) {
		return ( $b['added'] ?? 0 ) <=> ( $a['added'] ?? 0 );
	} );
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Following', 'instopress-social' ); ?></h1>
		<a class="page-title-action" href="<?php echo esc_url( iar_home_url() ); ?>"><?php esc_html_e( 'View home feed', 'instopress-social' ); ?></a>
		<hr class="wp-header-end">

		<?php if ( isset( $_GET['iar_error'] ) ) : // phpcs:ignore ?>
			<div class="notice notice-error is-dismissible"><p><?php echo esc_html( wp_unslash( rawurldecode( $_GET['iar_error'] ) ) ); // phpcs:ignore ?></p></div>
		<?php elseif ( isset( $_GET['iar_unfollowed'] ) ) : // phpcs:ignore ?>
			<div class="notice notice-success is-dismissible"><p><?php printf( esc_html__( 'Unfollowed @%s.', 'instopress-social' ), esc_html( sanitize_user( rawurldecode( wp_unslash( $_GET['iar_unfollowed'] ) ), true ) ) ); // phpcs:ignore ?></p></div>
		<?php elseif ( isset( $_GET['iar_refreshed'] ) ) : // phpcs:ignore ?>
			<div class="notice notice-success is-dismissible"><p><?php echo '*' === $_GET['iar_refreshed'] ? esc_html__( 'Refreshed everyone you follow.', 'instopress-social' ) : esc_html( sprintf( __( 'Refreshed @%s.', 'instopress-social' ), sanitize_user( rawurldecode( wp_unslash( $_GET['iar_refreshed'] ) ), true ) ) ); // phpcs:ignore ?></p></div>
		<?php elseif ( isset( $_GET['iar_followed'] ) ) : // phpcs:ignore ?>
			<div class="notice notice-success is-dismissible"><p><?php printf( esc_html__( 'You\'re now following @%s.', 'instopress-social' ), esc_html( sanitize_user( rawurldecode( $_GET['iar_followed'] ), true ) ) ); // phpcs:ignore ?></p></div>
		<?php endif; ?>

		<?php
		// Arrived from a Follow button elsewhere (?follow=<feed>): show who it is, ready to confirm.
		$prefill = isset( $_GET['follow'] ) ? iar_clean_url( wp_unslash( rawurldecode( $_GET['follow'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
		if ( $prefill ) :
			$preview = iar_resolve_feed( $prefill );
			$preview = is_wp_error( $preview ) ? $preview : iar_parse_feed( $preview[1], $preview[0] );
			?>
			<?php if ( is_wp_error( $preview ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( sprintf( /* translators: %s: error message */ __( 'Couldn\'t load that profile: %s', 'instopress-social' ), $preview->get_error_message() ) ); ?></p></div>
			<?php else : ?>
				<?php
				$who     = $preview[0];
				$already = isset( $all[ iar_follow_id( $who['feed'] ) ] ) || isset( $all[ iar_follow_id( $prefill ) ] );
				?>
				<div class="notice <?php echo $already ? 'notice-success' : 'notice-info'; ?>" style="display:flex;align-items:center;gap:12px;padding:12px;">
					<?php if ( $who['avatar'] ) : ?>
						<img src="<?php echo esc_url( $who['avatar'] ); ?>" alt="" width="48" height="48" style="border-radius:50%;object-fit:cover;flex:none;">
					<?php endif; ?>
					<p style="margin:0;">
						<?php if ( $already ) : ?>
							<?php printf( /* translators: %s: username */ esc_html__( 'You already follow @%s.', 'instopress-social' ), esc_html( $who['username'] ) ); ?>
						<?php else : ?>
							<strong><?php printf( /* translators: %s: username */ esc_html__( 'Follow @%s?', 'instopress-social' ), esc_html( $who['username'] ) ); ?></strong>
							<?php echo $who['name'] ? ' ' . esc_html( $who['name'] ) . ' · ' : ' '; ?>
							<a href="<?php echo esc_url( $who['profile'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( $who['profile'], PHP_URL_HOST ) ); ?></a><br>
							<?php esc_html_e( 'Press Follow below to add them to your home feed.', 'instopress-social' ); ?>
						<?php endif; ?>
					</p>
				</div>
			<?php endif; ?>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:16px 0 24px;max-width:720px;"<?php echo $prefill ? ' id="iar-follow-confirm"' : ''; ?>>
			<?php wp_nonce_field( 'iar_follow' ); ?>
			<input type="hidden" name="action" value="iar_follow">
			<label for="iar-feed" style="display:block;font-weight:600;margin-bottom:6px;"><?php esc_html_e( 'Follow someone', 'instopress-social' ); ?></label>
			<div style="display:flex;gap:8px;">
				<input type="url" id="iar-feed" name="feed" class="regular-text" style="flex:1;" required placeholder="https://their-site.com/social/feed/" value="<?php echo esc_attr( $prefill ); ?>">
				<?php submit_button( __( 'Follow', 'instopress-social' ), 'primary', 'submit', false, $prefill ? array( 'autofocus' => 'autofocus' ) : null ); ?>
			</div>
			<p class="description"><?php esc_html_e( 'Paste the link copied from the Follow button on their profile — or just their profile link.', 'instopress-social' ); ?></p>
		</form>

		<h2><?php printf( esc_html__( 'Following %s', 'instopress-social' ), '<span class="count">(' . count( $all ) . ')</span>' ); // phpcs:ignore ?></h2>
		<?php if ( ! $all ) : ?>
			<p><?php esc_html_e( 'You\'re not following anyone yet.', 'instopress-social' ); ?></p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:960px;">
				<thead><tr>
					<th style="width:48px;"></th>
					<th><?php esc_html_e( 'Account', 'instopress-social' ); ?></th>
					<th><?php esc_html_e( 'Posts in feed', 'instopress-social' ); ?></th>
					<th><?php esc_html_e( 'Last updated', 'instopress-social' ); ?></th>
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
						<td><?php echo $f['synced'] ? esc_html( sprintf( /* translators: %s: time span, e.g. "5 mins" */ __( '%s ago', 'instopress-social' ), human_time_diff( $f['synced'] ) ) ) : '—'; ?></td>
						<td style="text-align:right;white-space:nowrap;">
							<?php foreach ( array( 'refresh' => __( 'Refresh', 'instopress-social' ), 'unfollow' => __( 'Unfollow', 'instopress-social' ) ) as $do => $label ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<?php wp_nonce_field( 'iar_following_action' ); ?>
									<input type="hidden" name="action" value="iar_following_action">
									<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">
									<input type="hidden" name="do" value="<?php echo esc_attr( $do ); ?>">
									<button class="button<?php echo 'unfollow' === $do ? ' button-link-delete' : ''; ?>" <?php echo 'unfollow' === $do ? 'onclick="return confirm(\'' . esc_js( sprintf( /* translators: %s: username */ __( 'Unfollow @%s?', 'instopress-social' ), $f['username'] ) ) . '\')"' : ''; ?>><?php echo esc_html( $label ); ?></button>
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
				<button class="button"><?php esc_html_e( 'Refresh all now', 'instopress-social' ); ?></button>
				<span class="description"><?php esc_html_e( 'Feeds refresh automatically every hour.', 'instopress-social' ); ?></span>
			</form>
		<?php endif; ?>

		<h2 style="margin-top:32px;"><?php esc_html_e( 'Your feed', 'instopress-social' ); ?></h2>
		<p><?php esc_html_e( 'Others follow you with this link (the Follow button on your profile copies it):', 'instopress-social' ); ?></p>
		<p><code><?php echo esc_html( iar_feed_url() ); ?></code></p>
	</div>
	<?php
}
