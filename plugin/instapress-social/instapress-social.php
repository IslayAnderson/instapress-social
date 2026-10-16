<?php
/**
 * Plugin Name:       instapress-social
 * Description:       Your own instapress-social profile on WordPress: profile grid and post pages, short-code permalinks (/p/DYLRvc7CC0h/), aspect ratios, following other instapress-social sites with a home feed, and a WP-CLI importer.
 * Version:           1.5.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Islay Anderson
 * Author URI:        https://islayanderson.co.uk
 * License:           WTFPL
 * License URI:       http://www.wtfpl.net/
 * Text Domain:       instapress-social
 */

defined( 'ABSPATH' ) || exit;

// The pre-1.3.0 copy (folder "insta-archive") is still active — let it be deactivated first.
if ( defined( 'IAR_VERSION' ) ) {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-error"><p><strong>instapress-social:</strong> deactivate and delete the old <em>Insta Archive</em> plugin — this version replaces it, and your settings, posts and follows carry over.</p></div>';
	} );
	return;
}

// Older copies of this code may still be loaded as mu-plugins — don't double-register.
if ( function_exists( 'igp_generate_shortcode' ) || defined( 'IGP_POST_TYPE' ) ) {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-error"><p><strong>instapress-social:</strong> remove the old post-type and permalink files from <code>wp-content/mu-plugins</code> — this plugin replaces them.</p></div>';
	} );
	return;
}

define( 'IAR_VERSION', '1.5.0' );
define( 'IAR_FILE', __FILE__ );
define( 'IAR_DIR', plugin_dir_path( __FILE__ ) );
define( 'IAR_URL', plugin_dir_url( __FILE__ ) );

/** Post type + field names. The importer, templates and editor button all use these. */
const IAR_POST_TYPE = 'instapress-social';
const IAR_OLD_POST_TYPE = 'instagram_post'; // migrated on update, see includes/post-type.php
const IAR_FIELDS    = array(
	'shortcode'  => 'ig_shortcode',
	'caption'    => 'ig_caption',
	'media'      => 'ig_media',
	'media_type' => 'ig_media_type',
	'posted_at'  => 'ig_posted_at',
	'likes'      => 'ig_likes',
	'comments'   => 'ig_comments',
	'location'   => 'ig_location',
	'url'        => 'ig_url',
	'ratio'      => 'ig_aspect_ratio',
);

require IAR_DIR . 'includes/post-type.php';
require IAR_DIR . 'includes/permalinks.php';
require IAR_DIR . 'includes/settings.php';
require IAR_DIR . 'includes/template-tags.php';
require IAR_DIR . 'includes/templates.php';
require IAR_DIR . 'includes/feed-out.php';
require IAR_DIR . 'includes/following.php';
require IAR_DIR . 'includes/download.php';
require IAR_DIR . 'includes/class-importer.php';
require IAR_DIR . 'includes/import-page.php';
require IAR_DIR . 'includes/import-upload.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require IAR_DIR . 'includes/class-cli.php';
}

register_activation_hook( __FILE__, function () {
	update_option( 'iar_flush_rewrites', 1 );
} );

register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'iar_refresh_following' );
	flush_rewrite_rules();
} );

// Flush once after activation or an update (new routes like /gram/home/ and /feed/insta/).
add_action( 'init', function () {
	if ( get_option( 'iar_flush_rewrites' ) || get_option( 'iar_version' ) !== IAR_VERSION ) {
		flush_rewrite_rules();
		delete_option( 'iar_flush_rewrites' );
		update_option( 'iar_version', IAR_VERSION );
	}
}, 99 );
