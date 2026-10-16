<?php
/**
 * Swap in the plugin's templates for the instapress-social archive and single views.
 * Themes can override any file by copying it to <theme>/instapress-social/.
 */

defined( 'ABSPATH' ) || exit;

function iar_is_view() {
	return is_post_type_archive( IAR_POST_TYPE ) || is_singular( IAR_POST_TYPE ) || iar_is_home();
}

/* -------------------------------------------------------------------------
 * Home feed: /gram/home/ — your posts plus everyone you follow
 * ---------------------------------------------------------------------- */

function iar_home_url( $page = 1 ) {
	$url = home_url( '/' . apply_filters( 'iar_archive_slug', 'gram' ) . '/home/' );
	return $page > 1 ? add_query_arg( 'pg', (int) $page, $url ) : $url;
}

function iar_is_home() {
	return (bool) get_query_var( 'iar_home' );
}

add_action( 'init', function () {
	add_rewrite_rule( '^' . preg_quote( apply_filters( 'iar_archive_slug', 'gram' ), '/' ) . '/home/?$', 'index.php?iar_home=1', 'top' );
} );

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'iar_home';
	return $vars;
} );

// Not a real post query — stop WordPress treating it as a 404 or the blog index.
add_action( 'pre_get_posts', function ( $q ) {
	if ( $q->is_main_query() && $q->get( 'iar_home' ) ) {
		$q->set( 'post_type', IAR_POST_TYPE );
		$q->set( 'posts_per_page', 1 );
		$q->is_home = false;
	}
} );

/**
 * Feed entries, newest first: [ [ 'item' => …, 'profile' => … ], … ].
 */
function iar_home_entries() {
	$entries = array();
	$me      = iar_own_profile();

	if ( apply_filters( 'iar_home_includes_own', true ) ) {
		$own = get_posts( array( 'post_type' => IAR_POST_TYPE, 'posts_per_page' => 30, 'post_status' => 'publish' ) );
		foreach ( $own as $p ) {
			$entries[] = array( 'item' => iar_post_to_item( $p ), 'profile' => $me );
		}
	}
	foreach ( iar_following() as $id => $profile ) {
		foreach ( iar_following_items( $id ) as $item ) {
			$entries[] = array( 'item' => $item, 'profile' => $profile );
		}
	}
	usort( $entries, function ( $a, $b ) {
		return strcmp( $b['item']['date'], $a['item']['date'] );
	} );
	return $entries;
}

/** "3h", "2d", "5w", then a date. */
function iar_time_ago( $iso ) {
	$t    = strtotime( $iso );
	$diff = time() - $t;
	if ( $diff < HOUR_IN_SECONDS ) {
		return max( 1, (int) floor( $diff / MINUTE_IN_SECONDS ) ) . 'm';
	}
	if ( $diff < DAY_IN_SECONDS ) {
		return (int) floor( $diff / HOUR_IN_SECONDS ) . 'h';
	}
	if ( $diff < WEEK_IN_SECONDS ) {
		return (int) floor( $diff / DAY_IN_SECONDS ) . 'd';
	}
	if ( $diff < 8 * WEEK_IN_SECONDS ) {
		return (int) floor( $diff / WEEK_IN_SECONDS ) . 'w';
	}
	return wp_date( gmdate( 'Y', $t ) === gmdate( 'Y' ) ? 'j M' : 'j M Y', $t );
}

function iar_templates_enabled() {
	return (bool) apply_filters( 'iar_use_templates', (bool) iar_setting( 'use_templates' ) );
}

add_filter( 'template_include', function ( $template ) {
	if ( ! iar_templates_enabled() ) {
		return $template;
	}
	if ( iar_is_home() ) {
		status_header( 200 );
		return iar_locate_template( 'home.php' );
	}
	if ( is_post_type_archive( IAR_POST_TYPE ) ) {
		return iar_locate_template( 'archive.php' );
	}
	if ( is_singular( IAR_POST_TYPE ) ) {
		return iar_locate_template( 'single.php' );
	}
	return $template;
}, 99 );

/** Archive: page size from settings; ?view=reels shows videos only. */
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() || ! $q->is_post_type_archive( IAR_POST_TYPE ) ) {
		return;
	}
	$q->set( 'posts_per_page', (int) iar_setting( 'per_page' ) );
	$meta = array();
	if ( 'reels' === iar_current_tab() ) {
		$meta[] = array( 'key' => IAR_FIELDS['media_type'], 'value' => 'video' );
	}
	$tag = iar_current_hashtag();
	if ( '' !== $tag ) {
		// Whole tag only: #film shouldn't match #filmphotography.
		$meta[] = array( 'key' => IAR_FIELDS['caption'], 'value' => '#' . preg_quote( $tag, '' ) . '([^[:alnum:]_]|$)', 'compare' => 'REGEXP' );
	}
	if ( $meta ) {
		$q->set( 'meta_query', $meta );
	}
} );

function iar_current_hashtag() {
	$tag = isset( $_GET['hashtag'] ) ? wp_unslash( $_GET['hashtag'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	return preg_match( '/^[\p{L}\p{N}_]{1,100}$/u', $tag ) ? $tag : '';
}

function iar_current_tab() {
	return ( isset( $_GET['view'] ) && 'reels' === $_GET['view'] ) ? 'reels' : 'posts'; // phpcs:ignore WordPress.Security.NonceVerification
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! iar_templates_enabled() || ! iar_is_view() ) {
		return;
	}
	wp_enqueue_style( 'instapress-social', IAR_URL . 'assets/css/style.css', array(), IAR_VERSION );
	wp_enqueue_script( 'instapress-social', IAR_URL . 'assets/js/app.js', array(), IAR_VERSION, true );
	wp_localize_script( 'instapress-social', 'IAR', array(
		'hashtagBase' => iar_profile_url(),
		'people'      => iar_known_people(),
	) );
} );

function iar_isolating() {
	return iar_templates_enabled() && iar_is_view() && iar_setting( 'isolate_styles' );
}

/**
 * Keep the theme's CSS from leaking into the app-like layout (optional).
 * Runs right before styles print, so it catches themes that enqueue late.
 */
function iar_dequeue_theme_styles() {
	if ( ! iar_isolating() ) {
		return;
	}
	$theme_path = wp_parse_url( get_theme_root_uri(), PHP_URL_PATH ); // e.g. /wp-content/themes
	foreach ( wp_styles()->queue as $handle ) {
		$src = (string) ( wp_styles()->registered[ $handle ]->src ?? '' );
		if ( $src && false !== strpos( (string) wp_parse_url( $src, PHP_URL_PATH ), $theme_path . '/' ) ) {
			wp_dequeue_style( $handle );
		}
	}
	foreach ( array( 'global-styles', 'wp-block-library', 'wp-block-library-theme', 'classic-theme-styles' ) as $handle ) {
		wp_dequeue_style( $handle );
	}
}
add_action( 'wp_print_styles', 'iar_dequeue_theme_styles', 999 );
add_action( 'wp_print_footer_scripts', 'iar_dequeue_theme_styles', 1 );

// Customizer "Additional CSS" and custom background belong to the theme too.
add_action( 'wp', function () {
	if ( iar_isolating() ) {
		remove_action( 'wp_head', 'wp_custom_css_cb', 101 );
		remove_action( 'wp_head', '_custom_background_cb' );
	}
} );

add_filter( 'body_class', function ( $classes ) {
	if ( iar_templates_enabled() && iar_is_view() ) {
		$classes[] = 'instapress-social';
		$classes   = array_diff( $classes, array( 'custom-background' ) );
	}
	return $classes;
} );

/** Nicer <title> on single posts: "caption… • @username". */
add_filter( 'document_title_parts', function ( $parts ) {
	if ( iar_templates_enabled() && is_singular( IAR_POST_TYPE ) ) {
		$parts['title'] = wp_html_excerpt( iar_get_caption(), 60, '…' ) . ' • @' . iar_setting( 'username' );
	} elseif ( iar_templates_enabled() && iar_is_home() ) {
		$parts['title'] = __( 'Home', 'instapress-social' ) . ' • @' . iar_setting( 'username' );
	} elseif ( iar_templates_enabled() && is_post_type_archive( IAR_POST_TYPE ) ) {
		$parts['title'] = iar_setting( 'name' ) . ' (@' . iar_setting( 'username' ) . ')';
	}
	return $parts;
} );
