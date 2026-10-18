<?php
/**
 * Plugin Name:       instopress-social Pro
 * Description:       Your own instopress-social profile on WordPress: profile grid and post pages, short-code permalinks (/p/DYLRvc7CC0h/), aspect ratios, following other instopress-social sites with a home feed, and a WP-CLI importer.
 * Version:           1.31.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Islay Anderson
 * Author URI:        https://islayanderson.co.uk
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       instopress-social
 * Update URI:        https://github.com/IslayAnderson/instopress-social
 */

defined( 'ABSPATH' ) || exit;

// An older copy of this plugin (under a previous name or folder) is still active — let it be deactivated first.
if ( defined( 'IAR_VERSION' ) ) {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-error"><p><strong>instopress-social:</strong> an older copy of this plugin is still active. Deactivate and delete it — this version replaces it, and your settings, posts and follows carry over.</p></div>';
	} );
	return;
}

// Older copies of this code may still be loaded as mu-plugins — don't double-register.
if ( function_exists( 'igp_generate_shortcode' ) || defined( 'IGP_POST_TYPE' ) ) {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-error"><p><strong>instopress-social:</strong> remove the old post-type and permalink files from <code>wp-content/mu-plugins</code> — this plugin replaces them.</p></div>';
	} );
	return;
}

define( 'IAR_VERSION', '1.31.0' );
define( 'IAR_FILE', __FILE__ );
define( 'IAR_DIR', plugin_dir_path( __FILE__ ) );
// Pro edition: includes/pro/ is present (bot protection, GitHub updates). The WordPress.org build leaves it out (bin/build.sh).
define( 'IAR_PRO', is_dir( IAR_DIR . 'includes/pro' ) );
define( 'IAR_URL', plugin_dir_url( __FILE__ ) );

/**
 * URL of a file in assets/, minified when the release build made a .min copy
 * (bin/build.sh) — unless SCRIPT_DEBUG is on, which gets the readable original.
 *
 * @param string $file Path inside assets/, e.g. 'js/app.js'.
 */
function iar_asset_url( $file ) {
	return IAR_URL . 'assets/' . iar_asset_file( $file );
}

/** The same file as iar_asset_url(), as a path on disk. */
function iar_asset_path( $file ) {
	return IAR_DIR . 'assets/' . iar_asset_file( $file );
}

/** 'js/app.js' → 'js/app.min.js' when that exists and SCRIPT_DEBUG is off. */
function iar_asset_file( $file ) {
	if ( ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ) {
		$min = preg_replace( '/\.(js|css)$/', '.min.$1', $file );
		if ( is_file( IAR_DIR . 'assets/' . $min ) ) {
			return $min;
		}
	}
	return $file;
}

/** Post type + field names. The importer, templates and editor button all use these. */
const IAR_POST_TYPE = 'instopress-social';
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

require IAR_DIR . 'includes/legacy.php';
require IAR_DIR . 'includes/post-type.php';
require IAR_DIR . 'includes/permalinks.php';
require IAR_DIR . 'includes/settings.php';
require IAR_DIR . 'includes/addresses.php';
require IAR_DIR . 'includes/template-tags.php';
require IAR_DIR . 'includes/image-sizes.php';
require IAR_DIR . 'includes/templates.php';
require IAR_DIR . 'includes/feed-out.php';
require IAR_DIR . 'includes/following.php';
require IAR_DIR . 'includes/stories.php';
require IAR_DIR . 'includes/engagement.php';
require IAR_DIR . 'includes/text-posts.php';
require IAR_DIR . 'includes/post-status.php';
require IAR_DIR . 'includes/compose.php';
foreach ( array( 'bot-check', 'updater' ) as $iar_pro_file ) {
	if ( IAR_PRO && is_file( IAR_DIR . 'includes/pro/' . $iar_pro_file . '.php' ) ) {
		require IAR_DIR . 'includes/pro/' . $iar_pro_file . '.php';
	}
}
unset( $iar_pro_file );
require IAR_DIR . 'includes/download.php';
require IAR_DIR . 'includes/class-importer.php';
require IAR_DIR . 'includes/import-page.php';
require IAR_DIR . 'includes/import-upload.php';
require IAR_DIR . 'includes/video-posters.php';
require IAR_DIR . 'includes/setup-event.php';
require IAR_DIR . 'includes/usage-stats.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require IAR_DIR . 'includes/class-cli.php';
}

register_activation_hook( __FILE__, function () {
	update_option( 'iar_flush_rewrites', 1 );
} );

register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'iar_refresh_following' );
	wp_clear_scheduled_hook( 'iar_expire_stories' );
	flush_rewrite_rules();
} );

// Flush once after activation or an update (new routes like /social/home/ and /social/feed/).
add_action( 'init', function () {
	if ( get_option( 'iar_flush_rewrites' ) || get_option( 'iar_version' ) !== IAR_VERSION ) {
		flush_rewrite_rules();
		delete_option( 'iar_flush_rewrites' );
		update_option( 'iar_version', IAR_VERSION );
	}
}, 99 );
