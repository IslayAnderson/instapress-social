<?php
/**
 * Simplified posting for phones: /gram/new/ (templates/compose.php + assets/js/compose.js).
 *
 * Pick photos/videos, reorder, choose the aspect ratio, write a caption and
 * share — or make a story. Files upload one at a time through
 * iar_compose_upload (videos also get a poster captured in the browser), then
 * iar_compose_publish creates the post with a generated shortcode permalink.
 * Logged-in users who can publish posts only.
 */

defined( 'ABSPATH' ) || exit;

function iar_compose_url( $kind = 'post' ) {
	$url = home_url( '/' . apply_filters( 'iar_archive_slug', 'gram' ) . '/new/' );
	return 'story' === $kind ? add_query_arg( 'type', 'story', $url ) : $url;
}

function iar_can_compose() {
	$pto = get_post_type_object( IAR_POST_TYPE );
	return is_user_logged_in() && current_user_can( 'upload_files' ) && $pto && current_user_can( $pto->cap->publish_posts );
}

function iar_is_compose() {
	return (bool) get_query_var( 'iar_new' );
}

add_action( 'init', function () {
	add_rewrite_rule( '^' . preg_quote( apply_filters( 'iar_archive_slug', 'gram' ), '/' ) . '/new/?$', 'index.php?iar_new=1', 'top' );
} );

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'iar_new';
	return $vars;
} );

add_action( 'pre_get_posts', function ( $q ) {
	if ( $q->is_main_query() && $q->get( 'iar_new' ) ) {
		$q->set( 'post_type', IAR_POST_TYPE );
		$q->set( 'posts_per_page', 1 );
		$q->is_home = false;
	}
} );

// Not logged in (or not allowed): send to the login screen, then back here.
add_action( 'template_redirect', function () {
	if ( ! iar_is_compose() ) {
		return;
	}
	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( iar_compose_url( isset( $_GET['type'] ) && 'story' === $_GET['type'] ? 'story' : 'post' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		exit;
	}
	if ( ! iar_can_compose() ) {
		wp_die( esc_html__( 'Sorry, your account can\'t publish posts here.', 'instapress-social' ), '', array( 'response' => 403 ) );
	}
	nocache_headers();
} );

/* -------------------------------------------------------------------------
 * Endpoints
 * ---------------------------------------------------------------------- */

function iar_compose_guard() {
	check_ajax_referer( 'iar_compose' );
	if ( ! iar_can_compose() ) {
		wp_send_json_error( __( 'Sorry, you can\'t post here.', 'instapress-social' ), 403 );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	// Resizing phone photos and reading videos can need more than a normal request.
	wp_raise_memory_limit( 'image' );
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- may be disabled by the host
	}
}

// If PHP dies during a composer request, say why (as JSON the composer can show)
// instead of WordPress's generic "critical error" page. Only people who can post get here.
add_filter( 'wp_php_error_message', function ( $message, $error ) {
	$action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! wp_doing_ajax() || ! in_array( $action, array( 'iar_compose_upload', 'iar_compose_publish' ), true ) || ! iar_can_compose() ) {
		return $message;
	}
	// First line only, without the stack trace or server paths.
	$what = preg_replace( '/ in \/.*$/', '', strtok( wp_strip_all_tags( (string) ( $error['message'] ?? '' ) ), "\n" ) );
	$where = isset( $error['file'] ) ? ' (' . basename( $error['file'] ) . ':' . (int) ( $error['line'] ?? 0 ) . ')' : '';
	return wp_json_encode( array(
		'success' => false,
		/* translators: %s: PHP error message */
		'data'    => sprintf( __( 'The server hit an error: %s', 'instapress-social' ), $what . $where ),
	) );
}, 10, 2 );

/** An attachment this user uploaded through the composer (and not yet used). */
function iar_compose_own_attachment( $id ) {
	$a = get_post( $id );
	return $a && 'attachment' === $a->post_type
		&& (int) $a->post_author === get_current_user_id()
		&& get_post_meta( $id, '_iar_compose_upload', true )
		&& ( wp_attachment_is_image( $id ) || wp_attachment_is( 'video', $id ) );
}

/** Upload one file. With poster_for=<video id>, it becomes that video's poster. */
add_action( 'wp_ajax_iar_compose_upload', function () {
	iar_compose_guard();
	if ( empty( $_FILES['file'] ) ) {
		wp_send_json_error( __( 'No file received.', 'instapress-social' ) );
	}
	$poster_for = absint( $_POST['poster_for'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification -- checked in the guard
	if ( $poster_for && ! iar_compose_own_attachment( $poster_for ) ) {
		wp_send_json_error( __( 'That video isn\'t yours.', 'instapress-social' ), 403 );
	}

	$id = media_handle_upload( 'file', 0 );
	if ( is_wp_error( $id ) ) {
		wp_send_json_error( $id->get_error_message() );
	}
	if ( ! wp_attachment_is_image( $id ) && ! wp_attachment_is( 'video', $id ) ) {
		wp_delete_attachment( $id, true );
		wp_send_json_error( __( 'Only photos and videos can be posted.', 'instapress-social' ) );
	}

	if ( $poster_for ) {
		if ( ! wp_attachment_is_image( $id ) ) {
			wp_delete_attachment( $id, true );
			wp_send_json_error( __( 'A poster must be an image.', 'instapress-social' ) );
		}
		update_post_meta( $id, '_iar_generated_poster', $poster_for );
		set_post_thumbnail( $poster_for, $id );
	} else {
		update_post_meta( $id, '_iar_compose_upload', 1 );
	}

	wp_send_json_success( array(
		'id'   => $id,
		'type' => wp_attachment_is( 'video', $id ) ? 'video' : 'image',
	) );
} );

/** Create the post (or story) from uploaded media. */
add_action( 'wp_ajax_iar_compose_publish', function () {
	iar_compose_guard();
	// phpcs:disable WordPress.Security.NonceVerification -- checked in the guard
	$kind  = 'story' === ( $_POST['kind'] ?? '' ) ? 'story' : 'post';
	$media = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $_POST['media'] ?? array() ) ) ) ) );
	// phpcs:enable
	if ( ! $media ) {
		wp_send_json_error( __( 'Add at least one photo or video.', 'instapress-social' ) );
	}
	foreach ( $media as $id ) {
		if ( ! iar_compose_own_attachment( $id ) ) {
			wp_send_json_error( __( 'One of the files couldn\'t be used — please try again.', 'instapress-social' ), 403 );
		}
	}

	if ( 'story' === $kind ) {
		$post_id = iar_compose_create_story( $media[0] );
		$media   = array( $media[0] );
	} else {
		$post_id = iar_compose_create_post( $media );
	}
	if ( is_wp_error( $post_id ) ) {
		wp_send_json_error( $post_id->get_error_message() );
	}

	foreach ( $media as $id ) {
		wp_update_post( array( 'ID' => $id, 'post_parent' => $post_id ) );
		delete_post_meta( $id, '_iar_compose_upload' );
		$poster = iar_own_thumbnail_id( $id );
		if ( $poster ) {
			wp_update_post( array( 'ID' => $poster, 'post_parent' => $post_id ) );
		}
	}

	wp_send_json_success( array(
		'id'       => $post_id,
		'redirect' => 'story' === $kind ? iar_home_url() : get_permalink( $post_id ),
	) );
} );

function iar_compose_create_post( $media ) {
	// phpcs:disable WordPress.Security.NonceVerification -- checked in the guard
	$caption  = sanitize_textarea_field( wp_unslash( $_POST['caption'] ?? '' ) );
	$location = sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) );
	$ratio    = sanitize_text_field( wp_unslash( $_POST['ratio'] ?? 'auto' ) );
	// phpcs:enable
	if ( ! array_key_exists( $ratio, iar_ratios() ) ) {
		$ratio = 'auto';
	}
	$code    = iar_unique_shortcode();
	$post_id = wp_insert_post( array(
		'post_type'      => IAR_POST_TYPE,
		'post_status'    => 'publish',
		'post_title'     => class_exists( 'IAR_Importer' ) ? IAR_Importer::title( $caption, $code ) : $code,
		'post_name'      => $code, // kept in its original case by the slug filter
		'comment_status' => iar_comments_enabled() ? 'open' : 'closed',
	), true );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	$f = IAR_FIELDS;
	update_post_meta( $post_id, $f['shortcode'], $code );
	update_post_meta( $post_id, $f['caption'], $caption );
	update_post_meta( $post_id, $f['media'], array_map( 'strval', $media ) );
	update_post_meta( $post_id, $f['ratio'], $ratio );
	update_post_meta( $post_id, $f['location'], $location );
	update_post_meta( $post_id, $f['posted_at'], current_time( 'mysql' ) );
	// ACF field references, so the fields show up properly in the editor.
	foreach ( array( 'shortcode', 'caption', 'media', 'ratio', 'location', 'posted_at' ) as $k ) {
		update_post_meta( $post_id, '_' . $f[ $k ], 'field_' . $f[ $k ] );
	}
	iar_sync_media_type( $post_id );

	// Featured image: the first photo, or the first video's poster.
	foreach ( $media as $id ) {
		$thumb = wp_attachment_is_image( $id ) ? $id : iar_own_thumbnail_id( $id );
		if ( $thumb ) {
			set_post_thumbnail( $post_id, $thumb );
			break;
		}
	}
	return $post_id;
}

function iar_compose_create_story( $media_id ) {
	// phpcs:disable WordPress.Security.NonceVerification -- checked in the guard
	$text = sanitize_text_field( wp_unslash( $_POST['story_text'] ?? '' ) );
	$link = esc_url_raw( wp_unslash( $_POST['story_link'] ?? '' ), array( 'http', 'https' ) );
	// phpcs:enable
	$post_id = wp_insert_post( array(
		'post_type'   => IAR_STORY_TYPE,
		'post_status' => 'publish',
		'post_title'  => '',
	), true );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}
	$f = IAR_STORY_FIELDS;
	update_post_meta( $post_id, $f['media'], $media_id );
	update_post_meta( $post_id, $f['text'], mb_substr( $text, 0, 140 ) );
	update_post_meta( $post_id, $f['link'], $link );
	update_post_meta( $post_id, $f['duration'], 5 );
	foreach ( array( 'media', 'text', 'link', 'duration' ) as $k ) {
		update_post_meta( $post_id, '_' . $f[ $k ], 'field_' . $f[ $k ] );
	}
	$poster = wp_attachment_is( 'video', $media_id ) ? iar_own_thumbnail_id( $media_id ) : 0;
	if ( $poster ) {
		set_post_thumbnail( $post_id, $poster );
	}
	return $post_id;
}

// Abandoned uploads (picked, never shared) are tidied up after a day.
add_action( 'iar_compose_cleanup', function () {
	$old = get_posts( array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => 50,
		'fields'         => 'ids',
		'post_parent'    => 0,
		'meta_key'       => '_iar_compose_upload',
		'date_query'     => array( array( 'before' => '1 day ago' ) ),
	) );
	foreach ( $old as $id ) {
		$poster = iar_own_thumbnail_id( $id );
		if ( $poster && get_post_meta( $poster, '_iar_generated_poster', true ) ) {
			wp_delete_attachment( $poster, true );
		}
		wp_delete_attachment( $id, true );
	}
} );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'iar_compose_cleanup' ) ) {
		wp_schedule_event( time() + 3600, 'daily', 'iar_compose_cleanup' );
	}
} );
