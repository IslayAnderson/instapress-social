<?php
/**
 * The feed other instopress-social sites follow: /social/feed/ (inside the
 * profile folder). Older addresses (includes/legacy.php) redirect there.
 *
 * Plain RSS 2.0 so any reader can use it, plus Media RSS for the images/videos
 * and an insto: namespace for the profile and the bits the cards need
 * (aspect ratio, likes, carousel order).
 */

defined( 'ABSPATH' ) || exit;

const IAR_NS   = 'urn:instopress-social:1';
const IAR_FEED = 'insto'; // the feed's name in WordPress (?feed=insto); its address is <profile folder>/feed/

function iar_feed_url() {
	// Without pretty permalinks there are no folders: ?feed=insto.
	return get_option( 'permalink_structure' ) ? home_url( user_trailingslashit( iar_archive_slug() . '/feed' ) ) : get_feed_link( IAR_FEED );
}

add_action( 'init', function () {
	add_feed( IAR_FEED, 'iar_render_feed' );
	add_feed( IAR_LEGACY_FEED, 'iar_render_feed' ); // keeps the old address resolving, for the redirect below
	// Before the post type registers its archive feeds (init 10), whose …/feed/ rule would otherwise win.
	add_rewrite_rule( '^' . preg_quote( iar_archive_slug(), '/' ) . '/feed/?$', 'index.php?feed=' . IAR_FEED, 'top' );
}, 5 );

// Old feed addresses (the feed's old name, or /feed/insto/): send readers and following sites to the current one.
add_action( 'template_redirect', function () {
	if ( is_feed( IAR_LEGACY_FEED ) ) {
		wp_safe_redirect( iar_feed_url(), 301 );
		exit;
	}
	if ( ! is_feed( IAR_FEED ) || ! get_option( 'permalink_structure' ) ) {
		return;
	}
	$here = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$feed = trim( (string) wp_parse_url( iar_feed_url(), PHP_URL_PATH ), '/' );
	if ( $here !== $feed ) {
		wp_safe_redirect( iar_feed_url(), 301 );
		exit;
	}
}, 1 );

/**
 * When the feed last changed (GMT, 'Y-m-d H:i:s'). WordPress would only look
 * at the newest post's edit time, so readers asking "anything new?" got a 304
 * after a new story, a like or comment, a story expiring, or a deletion.
 */
function iar_feed_last_modified() {
	global $wpdb;
	$times = array(
		// Posts and stories, including stories that have since expired.
		$wpdb->get_var( $wpdb->prepare(
			"SELECT MAX(post_modified_gmt) FROM {$wpdb->posts} WHERE (post_type = %s AND post_status = 'publish') OR (post_type = %s AND post_status IN ('publish', %s))",
			IAR_POST_TYPE,
			IAR_STORY_TYPE,
			IAR_STORY_STATUS
		) ),
		// Likes and comments change the counts in the feed.
		$wpdb->get_var( $wpdb->prepare(
			"SELECT MAX(c.comment_date_gmt) FROM {$wpdb->comments} c JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID WHERE p.post_type = %s AND c.comment_approved = '1'",
			IAR_POST_TYPE
		) ),
	);
	// A story drops out of the feed when its time is up, before the hourly expiry job touches it.
	$lifetime = iar_story_lifetime();
	$ended    = $wpdb->get_var( $wpdb->prepare(
		"SELECT MAX(post_date_gmt) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish', %s) AND post_date_gmt <= %s",
		IAR_STORY_TYPE,
		IAR_STORY_STATUS,
		gmdate( 'Y-m-d H:i:s', time() - $lifetime )
	) );
	if ( $ended ) {
		$times[] = gmdate( 'Y-m-d H:i:s', strtotime( $ended . ' UTC' ) + $lifetime );
	}
	// Deletions, unapproved comments and profile edits leave no date behind; they're recorded here.
	$changed = (int) get_option( 'iar_feed_changed' );
	if ( $changed ) {
		$times[] = gmdate( 'Y-m-d H:i:s', $changed );
	}
	$times = array_filter( $times );
	return $times ? max( $times ) : gmdate( 'Y-m-d H:i:s' );
}

function iar_feed_touch() {
	update_option( 'iar_feed_changed', time(), false );
}

foreach ( array( 'deleted_post', 'trashed_post', 'untrashed_post' ) as $hook ) {
	add_action( $hook, function ( $post_id ) {
		if ( in_array( get_post_type( $post_id ), array( IAR_POST_TYPE, IAR_STORY_TYPE ), true ) ) {
			iar_feed_touch();
		}
	} );
}
add_action( 'transition_comment_status', function ( $new, $old, $comment ) {
	if ( IAR_POST_TYPE === get_post_type( $comment->comment_post_ID ) ) {
		iar_feed_touch();
	}
}, 10, 3 );
add_action( 'deleted_comment', function ( $id, $comment ) {
	if ( $comment && IAR_POST_TYPE === get_post_type( $comment->comment_post_ID ) ) {
		iar_feed_touch();
	}
}, 10, 2 );
add_action( 'update_option_' . IAR_OPTION, 'iar_feed_touch' );

// The Last-Modified (and ETag) WordPress sends for this feed, and answers 304s against.
add_filter( 'get_lastpostmodified', function ( $date, $timezone, $post_type ) {
	global $wp;
	if ( 'any' === $post_type && 'gmt' === strtolower( $timezone ) && isset( $wp->query_vars['feed'] ) && IAR_FEED === $wp->query_vars['feed'] ) {
		return iar_feed_last_modified();
	}
	return $date;
}, 10, 3 );

/**
 * A post in the shape the feed and the home feed cards use.
 */
function iar_post_to_item( $post ) {
	$post  = get_post( $post );
	$media = array();
	foreach ( iar_get_media( $post->ID ) as $m ) {
		$meta = 'text' === $m['type']
			? array( 'width' => 1080, 'height' => (int) round( 1080 / iar_aspect_ratio( $post->ID ) ) )
			: wp_get_attachment_metadata( $m['id'] );
		$media[] = array_filter( array(
			'type'   => $m['type'],
			'src'    => $m['src'],
			'poster' => $m['poster'] ?? '',
			'width'  => (int) ( $meta['width'] ?? 0 ),
			'height' => (int) ( $meta['height'] ?? 0 ),
		) );
	}
	return array(
		'id'       => get_permalink( $post ),
		'url'      => get_permalink( $post ),
		'date'     => get_post_time( 'c', true, $post ),
		'caption'  => iar_get_caption( $post->ID ),
		'text'     => iar_is_text_post( $post->ID ) ? iar_text_plain( $post->ID ) : '',
		'ratio'    => iar_aspect_ratio( $post->ID ),
		'type'     => (string) iar_field( 'media_type', $post->ID ) ?: ( count( $media ) > 1 ? 'carousel' : 'image' ),
		'likes'    => iar_like_total( $post->ID ),
		'comments' => iar_comment_total( $post->ID ),
		'post_id'  => $post->ID,
		'location' => (string) iar_field( 'location', $post->ID ),
		'media'    => $media,
	);
}

/** This site's profile in the shape followed profiles are stored in. */
function iar_own_profile() {
	return array(
		'username' => (string) iar_setting( 'username' ),
		'name'     => (string) iar_setting( 'name' ),
		'avatar'   => iar_avatar_url( 'thumbnail' ),
		'profile'  => iar_profile_url(),
		'feed'     => iar_feed_url(),
		'own'      => true,
	);
}

function iar_render_feed() {
	$posts = get_posts( array(
		'post_type'      => IAR_POST_TYPE,
		'posts_per_page' => (int) apply_filters( 'iar_feed_size', 30 ),
		'post_status'    => 'publish',
	) );
	$me = iar_own_profile();

	header( 'Content-Type: application/rss+xml; charset=' . get_option( 'blog_charset' ), true );
	echo '<?xml version="1.0" encoding="' . esc_attr( get_option( 'blog_charset' ) ) . '"?>' . "\n";
	?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/" xmlns:insto="<?php echo esc_xml( (string) IAR_NS ); ?>">
<channel>
	<title><?php echo esc_xml( (string) '@' . $me['username'] . ( $me['name'] ? ' (' . $me['name'] . ')' : '' ) ); ?></title>
	<link><?php echo esc_xml( (string) $me['profile'] ); ?></link>
	<description><?php echo esc_xml( (string) iar_setting( 'bio' ) ); ?></description>
	<atom:link href="<?php echo esc_xml( (string) $me['feed'] ); ?>" rel="self" type="application/rss+xml" />
	<lastBuildDate><?php echo esc_xml( (string) gmdate( 'r', strtotime( iar_feed_last_modified() . ' UTC' ) ) ); ?></lastBuildDate>
	<generator>instopress-social <?php echo esc_xml( (string) IAR_VERSION ); ?></generator>
	<insto:username><?php echo esc_xml( (string) $me['username'] ); ?></insto:username>
	<insto:name><?php echo esc_xml( (string) $me['name'] ); ?></insto:name>
	<insto:avatar><?php echo esc_xml( (string) $me['avatar'] ); ?></insto:avatar>
	<insto:profile><?php echo esc_xml( (string) $me['profile'] ); ?></insto:profile>
	<insto:count><?php echo (int) wp_count_posts( IAR_POST_TYPE )->publish; ?></insto:count>
<?php iar_feed_story_elements(); ?>
<?php foreach ( $posts as $post ) : $item = iar_post_to_item( $post ); ?>
	<item>
		<title><?php echo esc_xml( (string) wp_html_excerpt( $item['caption'] ?: $item['text'], 100, '…' ) ); ?></title>
		<link><?php echo esc_xml( (string) $item['url'] ); ?></link>
		<guid isPermaLink="true"><?php echo esc_xml( (string) $item['url'] ); ?></guid>
		<pubDate><?php echo esc_xml( (string) get_post_time( 'r', true, $post ) ); ?></pubDate>
		<description><?php echo esc_xml( (string) trim( $item['text'] . ( $item['text'] && $item['caption'] ? "\n\n" : '' ) . $item['caption'] ) ); ?></description>
		<insto:type><?php echo esc_xml( (string) $item['type'] ); ?></insto:type>
		<insto:ratio><?php echo esc_xml( (string) $item['ratio'] ); ?></insto:ratio>
		<insto:likes><?php echo (int) $item['likes']; ?></insto:likes>
		<insto:comments><?php echo (int) $item['comments']; ?></insto:comments>
<?php if ( $item['location'] ) : ?>
		<insto:location><?php echo esc_xml( (string) $item['location'] ); ?></insto:location>
<?php endif; ?>
<?php foreach ( $item['media'] as $m ) : ?>
		<media:content url="<?php echo esc_xml( (string) $m['src'] ); ?>" medium="<?php echo 'video' === $m['type'] ? 'video' : 'image'; ?>"<?php echo 'text' === $m['type'] ? ' type="image/svg+xml"' : ''; ?><?php echo ! empty( $m['width'] ) ? ' width="' . (int) $m['width'] . '" height="' . (int) $m['height'] . '"' : ''; ?><?php echo empty( $m['poster'] ) ? ' />' : '><media:thumbnail url="' . esc_xml( (string) $m['poster'] ) . '" /></media:content>'; ?>

<?php endforeach; ?>
	</item>
<?php endforeach; ?>
</channel>
</rss>
	<?php
}

/** Let readers (and other instopress-social sites) discover the feed from any page. */
add_action( 'wp_head', function () {
	printf(
		'<link rel="alternate" type="application/rss+xml" title="%s" href="%s" data-instopress-social />' . "\n",
		esc_attr( '@' . iar_setting( 'username' ) . ' — instopress-social' ),
		esc_url( iar_feed_url() )
	);
} );
