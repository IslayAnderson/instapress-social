<?php
/**
 * Likes and comments, on WordPress's own comment system.
 *
 * Comments are ordinary WordPress comments on instopress-social posts: the
 * Discussion settings, moderation, Akismet, flood checks and notification
 * emails all apply, and they're managed on the Comments screen.
 *
 * Likes are comments too, of type "iar_like" (WordPress has no likes of its
 * own). That keeps them in one place — listed under Comments → "Likes",
 * removed with their post — without a custom table. Each browser (or
 * logged-in user) can like a post once; liking again unlikes.
 *
 * Totals add the imported counts (ig_likes / ig_comments) to what's
 * happened here.
 */

defined( 'ABSPATH' ) || exit;

const IAR_LIKE_TYPE = 'iar_like';

add_action( 'init', function () {
	add_post_type_support( IAR_POST_TYPE, 'comments' );
}, 11 );

function iar_comments_enabled() {
	return (bool) iar_setting( 'allow_comments' );
}

function iar_likes_enabled() {
	return (bool) iar_setting( 'allow_likes' );
}

/* -------------------------------------------------------------------------
 * Counts
 * ---------------------------------------------------------------------- */

/** Likes made on this site. */
function iar_site_like_count( $post_id ) {
	return (int) get_post_meta( $post_id, '_iar_like_count', true );
}

function iar_recount_likes( $post_id ) {
	$n = (int) get_comments( array( 'post_id' => $post_id, 'type' => IAR_LIKE_TYPE, 'status' => 'approve', 'count' => true ) );
	update_post_meta( $post_id, '_iar_like_count', $n );
	return $n;
}

/** Imported likes + likes here. */
function iar_like_total( $post_id ) {
	return (int) iar_field( 'likes', $post_id ) + iar_site_like_count( $post_id );
}

/** Approved comments here (not likes). */
function iar_site_comment_count( $post_id ) {
	return (int) get_comments_number( $post_id ); // kept likes-free by the filter below
}

/** Imported comment count + comments here. */
function iar_comment_total( $post_id ) {
	return (int) iar_field( 'comments', $post_id ) + iar_site_comment_count( $post_id );
}

// WordPress's cached comment count shouldn't include likes.
add_filter( 'pre_wp_update_comment_count_now', function ( $new, $old, $post_id ) {
	if ( IAR_POST_TYPE !== get_post_type( $post_id ) ) {
		return $new;
	}
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_approved = '1' AND comment_type <> %s",
		$post_id,
		IAR_LIKE_TYPE
	) );
}, 10, 3 );

/* -------------------------------------------------------------------------
 * Comments open/closed
 * ---------------------------------------------------------------------- */

/** Open or close comments on every instopress-social post (the setting). */
function iar_apply_comment_status( $open ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare(
		"UPDATE {$wpdb->posts} SET comment_status = %s WHERE post_type = %s",
		$open ? 'open' : 'closed',
		IAR_POST_TYPE
	) );
	wp_cache_flush();
}

add_action( 'update_option_' . IAR_OPTION, function ( $old, $new ) {
	if ( (int) ( $old['allow_comments'] ?? 1 ) !== (int) ( $new['allow_comments'] ?? 1 ) ) {
		iar_apply_comment_status( ! empty( $new['allow_comments'] ) );
	}
}, 10, 2 );

// Imported posts were created with comments closed — open them once if comments are on.
add_action( 'admin_init', function () {
	if ( get_option( 'iar_comments_initialised' ) ) {
		return;
	}
	if ( iar_comments_enabled() ) {
		iar_apply_comment_status( true );
	}
	update_option( 'iar_comments_initialised', 1, false );
} );

/** Is another plugin (e.g. Disable Comments) switching comments off for our posts? */
function iar_comments_blocked_by() {
	$o = get_option( 'disable_comments_options' );
	if ( is_array( $o ) && ( ! empty( $o['remove_everywhere'] ) || in_array( IAR_POST_TYPE, (array) ( $o['disabled_post_types'] ?? array() ), true ) ) ) {
		return 'Disable Comments';
	}
	return '';
}

add_action( 'admin_notices', function () {
	$screen = get_current_screen();
	$ours   = $screen && ( IAR_POST_TYPE === $screen->post_type || 'settings_page_instopress-social' === $screen->id );
	$plugin = iar_comments_blocked_by();
	if ( ! $ours || ! $plugin || ! iar_comments_enabled() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Comments are switched off by the Disable Comments plugin.', 'instopress-social' ) . '</strong> ';
	printf(
		/* translators: %s: settings link */
		esc_html__( 'To allow them on instopress-social posts only, go to %s, choose "On specific post types" and tick Posts, Pages and Media (leave instopress-social unticked). Likes work either way.', 'instopress-social' ),
		'<a href="' . esc_url( admin_url( 'admin.php?page=disable_comments_settings' ) ) . '">' . esc_html__( 'Disable Comments → Settings', 'instopress-social' ) . '</a>'
	);
	echo '</p></div>';
} );

/* -------------------------------------------------------------------------
 * Keep likes out of comment lists, feeds and the dashboard
 * ---------------------------------------------------------------------- */

add_filter( 'admin_comment_types_dropdown', function ( $types ) {
	$types[ IAR_LIKE_TYPE ] = __( 'Likes', 'instopress-social' );
	return $types;
} );

add_filter( 'comments_list_table_query_args', function ( $args ) {
	if ( empty( $args['type'] ) ) {
		$args['type__not_in'] = array_merge( (array) ( $args['type__not_in'] ?? array() ), array( IAR_LIKE_TYPE ) );
	}
	return $args;
} );

add_filter( 'dashboard_recent_comments_query_args', function ( $args ) {
	$args['type__not_in'] = array_merge( (array) ( $args['type__not_in'] ?? array() ), array( IAR_LIKE_TYPE ) );
	return $args;
} );

add_filter( 'comment_feed_where', function ( $where ) {
	global $wpdb;
	return $where . $wpdb->prepare( " AND {$wpdb->comments}.comment_type <> %s", IAR_LIKE_TYPE );
} );

add_filter( 'comments_template_query_args', function ( $args ) {
	$args['type__not_in'] = array_merge( (array) ( $args['type__not_in'] ?? array() ), array( IAR_LIKE_TYPE ) );
	return $args;
} );

/* -------------------------------------------------------------------------
 * Likes: toggle endpoint (works for logged-out visitors too)
 * ---------------------------------------------------------------------- */

/**
 * Who is liking: a logged-in user, or an anonymous browser identified by a
 * random ID it keeps in localStorage. Stored hashed, never the IP.
 */
function iar_liker_key() {
	if ( is_user_logged_in() ) {
		return 'u:' . get_current_user_id();
	}
	$vid = isset( $_POST['vid'] ) ? preg_replace( '/[^a-zA-Z0-9-]/', '', sanitize_text_field( wp_unslash( $_POST['vid'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	return strlen( $vid ) >= 16 ? 'v:' . wp_hash( $vid, 'nonce' ) : '';
}

function iar_like_toggle() {
	$post_id = absint( $_POST['post_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! iar_likes_enabled() || IAR_POST_TYPE !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
		wp_send_json_error( __( 'Likes aren\'t available on this post.', 'instopress-social' ), 400 );
	}
	$key = iar_liker_key();
	if ( ! $key ) {
		wp_send_json_error( __( 'Couldn\'t identify this browser.', 'instopress-social' ), 400 );
	}

	if ( function_exists( 'iar_captcha_require' ) ) { // Pro: bot protection
		iar_captcha_require( 'like' );
	}

	// Light throttle per address: 120 toggles an hour is plenty for a person.
	$bucket = 'iar_like_rate_' . md5( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ) );
	$hits   = (int) get_transient( $bucket );
	if ( $hits > 120 ) {
		wp_send_json_error( __( 'Slow down a little.', 'instopress-social' ), 429 );
	}
	set_transient( $bucket, $hits + 1, HOUR_IN_SECONDS );

	$existing = get_comments( array(
		'post_id'    => $post_id,
		'type'       => IAR_LIKE_TYPE,
		'status'     => 'all',
		'meta_key'   => '_iar_liker',
		'meta_value' => $key,
		'fields'     => 'ids',
		'number'     => 1,
	) );

	if ( $existing ) {
		wp_delete_comment( $existing[0], true );
		$liked = false;
	} else {
		$user = wp_get_current_user();
		$id   = wp_insert_comment( array(
			'comment_post_ID'  => $post_id,
			'comment_type'     => IAR_LIKE_TYPE,
			'comment_approved' => 1,
			'comment_content'  => '',
			'comment_author'   => $user->exists() ? $user->display_name : __( 'Someone', 'instopress-social' ),
			'user_id'          => $user->exists() ? $user->ID : 0,
			'comment_author_IP'=> '',
			'comment_agent'    => '',
		) );
		if ( ! $id ) {
			wp_send_json_error( __( 'Couldn\'t save the like.', 'instopress-social' ), 500 );
		}
		add_comment_meta( $id, '_iar_liker', $key, true );
		$liked = true;
	}
	iar_recount_likes( $post_id );

	wp_send_json_success( array(
		'liked' => $liked,
		'likes' => iar_like_total( $post_id ),
	) );
}
add_action( 'wp_ajax_iar_like', 'iar_like_toggle' );
add_action( 'wp_ajax_nopriv_iar_like', 'iar_like_toggle' );

/* -------------------------------------------------------------------------
 * Comments: rendering
 * ---------------------------------------------------------------------- */

/** One comment (and its replies). */
function iar_comment_html( $comment, $replies = array() ) {
	$pending = '0' === (string) $comment->comment_approved;
	ob_start();
	?>
	<div class="comment comment--reply-<?php echo $comment->comment_parent ? '1' : '0'; ?>" id="comment-<?php echo (int) $comment->comment_ID; ?>" data-comment-id="<?php echo (int) $comment->comment_ID; ?>">
		<img class="avatar" src="<?php echo esc_url( get_avatar_url( $comment, array( 'size' => 64 ) ) ); ?>" alt="">
		<div>
			<p class="comment__text"><span class="post__user"><?php echo esc_html( $comment->comment_author ); ?></span> <?php echo iar_format_caption( $comment->comment_content ); // phpcs:ignore -- escaped inside ?></p>
			<div class="comment__meta">
				<time datetime="<?php echo esc_attr( get_comment_date( 'c', $comment ) ); ?>"><?php echo esc_html( iar_time_ago( get_comment_date( 'c', $comment ) ) ); ?></time>
				<?php if ( $pending ) : ?>
					<span class="comment__pending"><?php esc_html_e( 'Awaiting approval', 'instopress-social' ); ?></span>
				<?php elseif ( ! $comment->comment_parent && comments_open( $comment->comment_post_ID ) ) : ?>
					<button type="button" class="comment__reply" data-reply-to="<?php echo (int) $comment->comment_ID; ?>" data-reply-name="<?php echo esc_attr( $comment->comment_author ); ?>"><?php esc_html_e( 'Reply', 'instopress-social' ); ?></button>
				<?php endif; ?>
			</div>
			<?php if ( $replies ) : ?>
				<div class="comment__replies">
					<?php
					foreach ( $replies as $r ) {
						echo iar_comment_html( $r ); // phpcs:ignore
					}
					?>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/** Comments on a post: top-level comments with one level of replies, oldest first. */
function iar_comments_html( $post_id ) {
	$commenter = wp_get_current_commenter();
	$comments  = get_comments( array(
		'post_id'            => $post_id,
		'type'               => 'comment',
		'status'             => 'approve',
		'include_unapproved' => array_filter( array( is_user_logged_in() ? get_current_user_id() : 0, $commenter['comment_author_email'] ) ),
		'orderby'            => 'comment_date_gmt',
		'order'              => 'ASC',
		'number'             => 200,
	) );

	$top     = array();
	$replies = array();
	foreach ( $comments as $c ) {
		if ( $c->comment_parent ) {
			// Flatten deeper threads under their top-level comment.
			$root = $c;
			while ( $root->comment_parent && ( $p = get_comment( $root->comment_parent ) ) ) {
				$root = $p;
			}
			$replies[ $root->comment_ID ][] = $c;
		} else {
			$top[] = $c;
		}
	}

	$html = '';
	foreach ( $top as $c ) {
		$html .= iar_comment_html( $c, $replies[ $c->comment_ID ] ?? array() );
	}

	$earlier = (int) iar_field( 'comments', $post_id );
	$url     = (string) iar_field( 'url', $post_id );
	if ( $earlier ) {
		$label = sprintf( /* translators: %s: number of comments */ _n( '%s earlier comment (imported)', '%s earlier comments (imported)', $earlier, 'instopress-social' ), iar_count( $earlier ) );
		$html  = '<p class="comments__earlier">' . ( $url ? '<a href="' . esc_url( $url ) . '" rel="noopener" target="_blank">' . esc_html( $label ) . '</a>' : esc_html( $label ) ) . '</p>' . $html;
	}
	return $html;
}

/** "Add a comment…" form, or a note when comments are off / login is required. */
function iar_comment_form_html( $post_id ) {
	if ( ! iar_comments_enabled() || ! comments_open( $post_id ) ) {
		return '';
	}
	if ( get_option( 'comment_registration' ) && ! is_user_logged_in() ) {
		return '<p class="comment-login"><a href="' . esc_url( wp_login_url( get_permalink( $post_id ) ) ) . '">' . esc_html__( 'Log in to comment', 'instopress-social' ) . '</a></p>';
	}
	$guest = ! is_user_logged_in();
	ob_start();
	?>
	<form class="comment-form" data-comment-form <?php echo $guest ? 'data-guest' : ''; ?> novalidate>
		<input type="hidden" name="comment_post_ID" value="<?php echo (int) $post_id; ?>">
		<input type="hidden" name="comment_parent" value="0">
		<?php if ( $guest ) : ?>
			<?php // Filled from the email pop-up (assets/js/app.js) — visitors don't need an account. ?>
			<input type="hidden" name="author" value="">
			<input type="hidden" name="email" value="">
		<?php endif; ?>
		<div class="comment-form__row">
			<input type="text" name="comment" class="comment-form__input" placeholder="<?php esc_attr_e( 'Add a comment…', 'instopress-social' ); ?>" aria-label="<?php esc_attr_e( 'Add a comment', 'instopress-social' ); ?>" maxlength="2000" required autocomplete="off">
			<button type="submit" class="comment-form__post" disabled><?php esc_html_e( 'Post', 'instopress-social' ); ?></button>
		</div>
		<p class="comment-form__msg" data-comment-msg hidden></p>
		<?php if ( $guest ) : ?>
			<p class="comment-form__as" data-comment-as hidden><?php esc_html_e( 'Commenting as', 'instopress-social' ); ?> <span></span> · <button type="button" data-comment-change><?php esc_html_e( 'change', 'instopress-social' ); ?></button></p>
		<?php endif; ?>
	</form>
	<?php
	return ob_get_clean();
}

/* -------------------------------------------------------------------------
 * Comments: endpoints
 * ---------------------------------------------------------------------- */

/** Post a comment through WordPress's own comment handling. */
function iar_ajax_post_comment() {
	$post_id = absint( $_POST['comment_post_ID'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! iar_comments_enabled() || IAR_POST_TYPE !== get_post_type( $post_id ) ) {
		wp_send_json_error( __( 'Comments are closed.', 'instopress-social' ), 403 );
	}
	if ( function_exists( 'iar_captcha_require' ) ) { // Pro: bot protection
		iar_captcha_require( 'comment' );
	}

	$data = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification

	// Visitors comment anonymously: an email is required (never shown), a name is optional.
	if ( ! is_user_logged_in() ) {
		$data['email'] = sanitize_email( (string) ( $data['email'] ?? '' ) );
		if ( ! is_email( $data['email'] ) ) {
			wp_send_json_error( array( 'code' => 'need_email', 'message' => __( 'Please add your email to comment.', 'instopress-social' ) ), 400 );
		}
		$data['author'] = trim( sanitize_text_field( (string) ( $data['author'] ?? '' ) ) );
		if ( '' === $data['author'] ) {
			$data['author'] = __( 'Anonymous', 'instopress-social' );
		}
		$data['url'] = '';
	}

	$comment = wp_handle_comment_submission( $data );
	if ( is_wp_error( $comment ) ) {
		wp_send_json_error( wp_strip_all_tags( $comment->get_error_message() ), 400 );
	}
	$user = wp_get_current_user();
	do_action( 'set_comment_cookies', $comment, $user, true );

	wp_send_json_success( array(
		'html'     => iar_comment_html( $comment ),
		'parent'   => (int) $comment->comment_parent,
		'approved' => '1' === (string) $comment->comment_approved,
	) );
}
add_action( 'wp_ajax_iar_post_comment', 'iar_ajax_post_comment' );
add_action( 'wp_ajax_nopriv_iar_post_comment', 'iar_ajax_post_comment' );
