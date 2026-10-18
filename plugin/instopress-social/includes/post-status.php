<?php
/**
 * Post status from the front end: the ⋯ button on a post (post page and home
 * feed card) opens a menu for people who can edit it — Published, Private
 * (only you), Draft (hidden), Move to bin — plus a link to the editor.
 * Markup: templates/parts/post-menu.php. Behaviour: assets/js/app.js.
 *
 * So hidden posts can be found and switched back, editors also see their
 * drafts and private posts in the grid and on their pages, with a badge.
 */

defined( 'ABSPATH' ) || exit;

/** Statuses offered in the menu, in order. */
function iar_status_choices() {
	return array(
		'publish' => __( 'Published', 'instopress-social' ),
		'private' => __( 'Private — only you', 'instopress-social' ),
		'draft'   => __( 'Draft — hidden', 'instopress-social' ),
	);
}

/** Short label for a badge, or '' for a published post. */
function iar_status_badge( $status ) {
	$badges = array(
		'private' => __( 'Private', 'instopress-social' ),
		'draft'   => __( 'Draft', 'instopress-social' ),
		'pending' => __( 'Pending', 'instopress-social' ),
		'future'  => __( 'Scheduled', 'instopress-social' ),
	);
	return $badges[ $status ] ?? '';
}

function iar_can_manage_status( $post_id ) {
	return is_user_logged_in() && IAR_POST_TYPE === get_post_type( $post_id ) && current_user_can( 'edit_post', $post_id );
}

/** Can the current user see this site's unpublished posts on the front end? */
function iar_sees_unpublished() {
	$pto = get_post_type_object( IAR_POST_TYPE );
	return is_user_logged_in() && $pto && current_user_can( $pto->cap->edit_posts );
}

// Editors see their drafts and private posts in the grid and on /p/ pages.
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() || ! iar_sees_unpublished() ) {
		return;
	}
	$type = $q->get( 'post_type' );
	if ( IAR_POST_TYPE === $type || ( is_array( $type ) && in_array( IAR_POST_TYPE, $type, true ) ) || $q->is_post_type_archive( IAR_POST_TYPE ) ) {
		$q->set( 'post_status', array( 'publish', 'private', 'draft', 'pending', 'future' ) );
	}
}, 20 );

// Unpublished pages shouldn't be cached or indexed.
add_action( 'template_redirect', function () {
	if ( is_singular( IAR_POST_TYPE ) && 'publish' !== get_post_status() ) {
		nocache_headers();
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
	}
} );

add_action( 'wp_ajax_iar_set_status', function () {
	check_ajax_referer( 'iar_status' );
	$post_id = absint( $_POST['post_id'] ?? 0 );
	$status  = sanitize_key( $_POST['status'] ?? '' );
	if ( ! iar_can_manage_status( $post_id ) ) {
		wp_send_json_error( __( 'You can\'t change this post.', 'instopress-social' ), 403 );
	}

	if ( 'trash' === $status ) {
		if ( ! current_user_can( 'delete_post', $post_id ) || ! wp_trash_post( $post_id ) ) {
			wp_send_json_error( __( 'Couldn\'t move this post to the bin.', 'instopress-social' ) );
		}
		wp_send_json_success( array( 'status' => 'trash', 'redirect' => iar_profile_url() ) );
	}

	if ( ! array_key_exists( $status, iar_status_choices() ) ) {
		wp_send_json_error( __( 'Unknown status.', 'instopress-social' ) );
	}
	$pto = get_post_type_object( IAR_POST_TYPE );
	if ( in_array( $status, array( 'publish', 'private' ), true ) && ! current_user_can( $pto->cap->publish_posts ) ) {
		wp_send_json_error( __( 'Your account can\'t publish posts.', 'instopress-social' ), 403 );
	}
	$res = wp_update_post( array( 'ID' => $post_id, 'post_status' => $status ), true );
	if ( is_wp_error( $res ) ) {
		wp_send_json_error( $res->get_error_message() );
	}
	wp_send_json_success( array(
		'status' => get_post_status( $post_id ),
		'badge'  => iar_status_badge( get_post_status( $post_id ) ),
	) );
} );

// Drafts keep their /p/<shortcode>/ address (WordPress would give them ?p=123); editors can open it.
add_filter( 'post_type_link', function ( $link, $post ) {
	if ( IAR_POST_TYPE === $post->post_type && $post->post_name && in_array( $post->post_status, array( 'draft', 'pending', 'future' ), true ) && get_option( 'permalink_structure' ) ) {
		return home_url( user_trailingslashit( iar_post_slug() . '/' . $post->post_name ) );
	}
	return $link;
}, 10, 2 );
