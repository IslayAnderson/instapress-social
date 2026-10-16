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
 * A post in the shape the feed and the home feed cards use.
 */
function iar_post_to_item( $post ) {
	$post  = get_post( $post );
	$media = array();
	foreach ( iar_get_media( $post->ID ) as $m ) {
		$meta    = wp_get_attachment_metadata( $m['id'] );
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
		'ratio'    => iar_aspect_ratio( $post->ID ),
		'type'     => (string) iar_field( 'media_type', $post->ID ) ?: ( count( $media ) > 1 ? 'carousel' : 'image' ),
		'likes'    => (int) iar_field( 'likes', $post->ID ),
		'comments' => (int) iar_field( 'comments', $post->ID ),
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
	<lastBuildDate><?php echo $x( $posts ? get_post_modified_time( 'r', true, $posts[0] ) : gmdate( 'r' ) ); ?></lastBuildDate>
	<generator>instapress-social <?php echo $x( IAR_VERSION ); ?></generator>
	<insta:username><?php echo $x( $me['username'] ); ?></insta:username>
	<insta:name><?php echo $x( $me['name'] ); ?></insta:name>
	<insta:avatar><?php echo $x( $me['avatar'] ); ?></insta:avatar>
	<insta:profile><?php echo $x( $me['profile'] ); ?></insta:profile>
	<insta:count><?php echo (int) wp_count_posts( IAR_POST_TYPE )->publish; ?></insta:count>
<?php iar_feed_story_elements(); ?>
<?php foreach ( $posts as $post ) : $item = iar_post_to_item( $post ); ?>
	<item>
		<title><?php echo $x( wp_html_excerpt( $item['caption'], 100, '…' ) ); ?></title>
		<link><?php echo $x( $item['url'] ); ?></link>
		<guid isPermaLink="true"><?php echo $x( $item['url'] ); ?></guid>
		<pubDate><?php echo $x( get_post_time( 'r', true, $post ) ); ?></pubDate>
		<description><?php echo $x( $item['caption'] ); ?></description>
		<insta:type><?php echo $x( $item['type'] ); ?></insta:type>
		<insta:ratio><?php echo $x( $item['ratio'] ); ?></insta:ratio>
		<insta:likes><?php echo (int) $item['likes']; ?></insta:likes>
		<insta:comments><?php echo (int) $item['comments']; ?></insta:comments>
<?php if ( $item['location'] ) : ?>
		<insta:location><?php echo $x( $item['location'] ); ?></insta:location>
<?php endif; ?>
<?php foreach ( $item['media'] as $m ) : ?>
		<media:content url="<?php echo $x( $m['src'] ); ?>" medium="<?php echo 'video' === $m['type'] ? 'video' : 'image'; ?>"<?php echo ! empty( $m['width'] ) ? ' width="' . (int) $m['width'] . '" height="' . (int) $m['height'] . '"' : ''; ?><?php echo empty( $m['poster'] ) ? ' />' : '><media:thumbnail url="' . $x( $m['poster'] ) . '" /></media:content>'; ?>

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
