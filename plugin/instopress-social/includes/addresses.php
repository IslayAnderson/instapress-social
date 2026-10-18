<?php
/**
 * The folders the plugin lives under, set in Settings → instopress-social:
 *
 *   profile folder  /social/        (and /social/home/, /social/new/)
 *   post folder     /p/<shortcode>/
 *
 * The iar_archive_slug / iar_post_slug filters still win over the settings.
 * When a folder changes, the old addresses keep working: they redirect (301)
 * to the new ones, so links and followers' sites don't break.
 */

defined( 'ABSPATH' ) || exit;

function iar_archive_slug() {
	return (string) apply_filters( 'iar_archive_slug', iar_setting( 'archive_slug' ) ?: 'social' );
}

function iar_post_slug() {
	return (string) apply_filters( 'iar_post_slug', iar_setting( 'post_slug' ) ?: 'p' );
}

/** Words WordPress (or the web) already uses at the top level of a site. */
function iar_reserved_slugs() {
	return array(
		'wp-admin', 'wp-content', 'wp-includes', 'wp-json', 'wp-login.php', 'xmlrpc.php', 'admin', 'login',
		'feed', 'rss', 'rss2', 'atom', 'rdf', 'comments', 'page', 'search', 'author', 'category', 'tag',
		'type', 'embed', 'trackback', 'attachment', 'sitemap', 'robots.txt', 'favicon.ico', 'cgi-bin',
	);
}

/**
 * A cleaned-up folder name, or WP_Error saying why it can't be used.
 *
 * @param string $slug  What was typed.
 * @param string $other The other folder (they can't be the same).
 */
function iar_validate_slug( $slug, $other = '' ) {
	$slug = strtolower( trim( (string) $slug, " /\t\n\r" ) );
	if ( '' === $slug ) {
		return new WP_Error( 'empty', __( 'it can\'t be empty', 'instopress-social' ) );
	}
	if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug ) || strlen( $slug ) > 40 ) {
		return new WP_Error( 'chars', __( 'use lowercase letters, numbers and dashes only (up to 40)', 'instopress-social' ) );
	}
	if ( in_array( $slug, iar_reserved_slugs(), true ) ) {
		return new WP_Error( 'reserved', __( 'WordPress already uses that address', 'instopress-social' ) );
	}
	if ( $other && $slug === $other ) {
		return new WP_Error( 'same', __( 'the profile and post folders must be different', 'instopress-social' ) );
	}
	$page = get_page_by_path( $slug );
	if ( $page && 'trash' !== $page->post_status ) {
		/* translators: %s: page title */
		return new WP_Error( 'page', sprintf( __( 'your page "%s" already has that address', 'instopress-social' ), get_the_title( $page ) ) );
	}
	return $slug;
}

/** Settings sanitiser: valid new folders, or keep the current ones and say why. */
function iar_sanitize_slugs( $in ) {
	$current = array( 'archive_slug' => iar_setting( 'archive_slug' ) ?: 'social', 'post_slug' => iar_setting( 'post_slug' ) ?: 'p' );
	$labels  = array( 'archive_slug' => __( 'Profile folder', 'instopress-social' ), 'post_slug' => __( 'Post folder', 'instopress-social' ) );
	$out     = $current;
	foreach ( array( 'archive_slug' => 'post_slug', 'post_slug' => 'archive_slug' ) as $key => $other_key ) {
		if ( ! isset( $in[ $key ] ) ) {
			continue;
		}
		$other = isset( $in[ $other_key ] ) ? strtolower( trim( (string) $in[ $other_key ], ' /' ) ) : $current[ $other_key ];
		$slug  = iar_validate_slug( $in[ $key ], $other );
		if ( is_wp_error( $slug ) ) {
			/* translators: 1: field name, 2: what's wrong */
			add_settings_error( IAR_OPTION, 'iar_' . $key, sprintf( __( '%1$s not changed: %2$s.', 'instopress-social' ), $labels[ $key ], $slug->get_error_message() ) );
		} else {
			$out[ $key ] = $slug;
		}
	}
	return $out;
}

// A folder changed: remember the old one (for redirects) and rebuild the routes.
add_action( 'update_option_' . IAR_OPTION, function ( $old, $new ) {
	if ( ! empty( $GLOBALS['iar_pinning_defaults'] ) ) {
		return;
	}
	$history = (array) get_option( 'iar_slug_history', array() );
	$changed = false;
	foreach ( array( 'archive_slug' => iar_defaults()['archive_slug'], 'post_slug' => iar_defaults()['post_slug'] ) as $key => $default ) {
		$was = ( (array) $old )[ $key ] ?? $default;
		$is  = ( (array) $new )[ $key ] ?? $default;
		if ( $was && $was !== $is ) {
			$history[ $key ] = array_values( array_diff( array_unique( array_merge( (array) ( $history[ $key ] ?? array() ), array( $was ) ) ), array( $is ) ) );
			$changed         = true;
		}
	}
	if ( $changed ) {
		update_option( 'iar_slug_history', $history, false );
		update_option( 'iar_flush_rewrites', 1 );
	}
}, 10, 2 );

// Old addresses → new ones (301), e.g. /gram/home/ → /me/home/, /p/Abc123/ → /post/Abc123/.
add_action( 'template_redirect', function () {
	if ( ! is_404() ) {
		return;
	}
	$history = (array) get_option( 'iar_slug_history', array() );
	if ( ! $history ) {
		return;
	}
	$base = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( '' !== $base ) {
		if ( 0 !== strpos( $path . '/', $base . '/' ) ) {
			return;
		}
		$path = ltrim( substr( $path, strlen( $base ) ), '/' );
	}
	$parts = explode( '/', $path, 2 );
	foreach ( array( 'archive_slug' => iar_archive_slug(), 'post_slug' => iar_post_slug() ) as $key => $now ) {
		if ( in_array( $parts[0], (array) ( $history[ $key ] ?? array() ), true ) ) {
			$query = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$to    = home_url( user_trailingslashit( '/' . $now . '/' . ( $parts[1] ?? '' ) ) );
			wp_safe_redirect( $query ? $to . '?' . $query : $to, 301 );
			exit;
		}
	}
}, 1 );
