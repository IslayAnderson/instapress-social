<?php
/**
 * The feed other instapress-social sites follow: /feed/insta/
 *
 * Plain RSS 2.0 so any reader can use it, plus Media RSS for the images/videos
 * and an insta: namespace for the profile and the bits the cards need
 * (aspect ratio, likes, carousel order).
 */

defined( 'ABSPATH' ) || exit;

const IAR_NS     = 'urn:instapress-social:1';
const IAR_OLD_NS = 'urn:insta-archive:1'; // feeds from versions before 1.3.0

function iar_feed_url() {
	return get_feed_link( 'insta' );
}

add_action( 'init', function () {
	add_feed( 'insta', 'iar_render_feed' );
} );

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
	if ( 'any' === $post_type && 'gmt' === strtolower( $timezone ) && isset( $wp->query_vars['feed'] ) && 'insta' === $wp->query_vars['feed'] ) {
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
	$x  = function ( $s ) {
		return esc_xml( (string) $s );
	};

	header( 'Content-Type: application/rss+xml; charset=' . get_option( 'blog_charset' ), true );
	echo '<?xml version="1.0" encoding="' . esc_attr( get_option( 'blog_charset' ) ) . '"?>' . "\n";
	?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/" xmlns:insta="<?php echo $x( IAR_NS ); ?>">
<channel>
	<title><?php echo $x( '@' . $me['username'] . ( $me['name'] ? ' (' . $me['name'] . ')' : '' ) ); ?></title>
	<link><?php echo $x( $me['profile'] ); ?></link>
	<description><?php echo $x( iar_setting( 'bio' ) ); ?></description>
	<atom:link href="<?php echo $x( $me['feed'] ); ?>" rel="self" type="application/rss+xml" />
	<lastBuildDate><?php echo $x( gmdate( 'r', strtotime( iar_feed_last_modified() . ' UTC' ) ) ); ?></lastBuildDate>
	<generator>instapress-social <?php echo $x( IAR_VERSION ); ?></generator>
	<insta:username><?php echo $x( $me['username'] ); ?></insta:username>
	<insta:name><?php echo $x( $me['name'] ); ?></insta:name>
	<insta:avatar><?php echo $x( $me['avatar'] ); ?></insta:avatar>
	<insta:profile><?php echo $x( $me['profile'] ); ?></insta:profile>
	<insta:count><?php echo (int) wp_count_posts( IAR_POST_TYPE )->publish; ?></insta:count>
<?php iar_feed_story_elements(); ?>
<?php foreach ( $posts as $post ) : $item = iar_post_to_item( $post ); ?>
	<item>
		<title><?php echo $x( wp_html_excerpt( $item['caption'] ?: $item['text'], 100, '…' ) ); ?></title>
		<link><?php echo $x( $item['url'] ); ?></link>
		<guid isPermaLink="true"><?php echo $x( $item['url'] ); ?></guid>
		<pubDate><?php echo $x( get_post_time( 'r', true, $post ) ); ?></pubDate>
		<description><?php echo $x( trim( $item['text'] . ( $item['text'] && $item['caption'] ? "\n\n" : '' ) . $item['caption'] ) ); ?></description>
		<insta:type><?php echo $x( $item['type'] ); ?></insta:type>
		<insta:ratio><?php echo $x( $item['ratio'] ); ?></insta:ratio>
		<insta:likes><?php echo (int) $item['likes']; ?></insta:likes>
		<insta:comments><?php echo (int) $item['comments']; ?></insta:comments>
<?php if ( $item['location'] ) : ?>
		<insta:location><?php echo $x( $item['location'] ); ?></insta:location>
<?php endif; ?>
<?php foreach ( $item['media'] as $m ) : ?>
		<media:content url="<?php echo $x( $m['src'] ); ?>" medium="<?php echo 'video' === $m['type'] ? 'video' : 'image'; ?>"<?php echo 'text' === $m['type'] ? ' type="image/svg+xml"' : ''; ?><?php echo ! empty( $m['width'] ) ? ' width="' . (int) $m['width'] . '" height="' . (int) $m['height'] . '"' : ''; ?><?php echo empty( $m['poster'] ) ? ' />' : '><media:thumbnail url="' . $x( $m['poster'] ) . '" /></media:content>'; ?>

<?php endforeach; ?>
	</item>
<?php endforeach; ?>
</channel>
</rss>
	<?php
}

/** Let readers (and other instapress-social sites) discover the feed from any page. */
add_action( 'wp_head', function () {
	printf(
		'<link rel="alternate" type="application/rss+xml" title="%s" href="%s" data-instapress-social />' . "\n",
		esc_attr( '@' . iar_setting( 'username' ) . ' — instapress-social' ),
		esc_url( iar_feed_url() )
	);
} );
