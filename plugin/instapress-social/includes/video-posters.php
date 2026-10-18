<?php
/**
 * Video posters.
 *
 * WordPress can't pull frames out of videos on most hosts (no ffmpeg), so the
 * browser does it: load the video, grab a frame, upload it as the video's
 * poster (assets/js/video-posters.js). Offered on the Import page for every
 * video that lacks one, and on a post's edit screen for that post's videos.
 *
 * Also keeps each post's "Media type" in step with its gallery, so hand-made
 * posts land in the Reels tab and get poster checks like imported ones.
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Media type follows the gallery
 * ---------------------------------------------------------------------- */

/** image | video | carousel | text, from the post's gallery (text: no media, has words). */
function iar_detect_media_type( $post_id ) {
	$ids = array_values( array_filter( array_map( 'absint', (array) iar_field( 'media', $post_id ) ) ) );
	if ( ! $ids && ( 'text' === iar_field( 'media_type', $post_id ) || '' !== trim( (string) get_post_meta( $post_id, 'ig_text', true ) ) ) ) {
		return 'text';
	}
	if ( count( $ids ) > 1 ) {
		return 'carousel';
	}
	return ( $ids && wp_attachment_is( 'video', $ids[0] ) ) ? 'video' : 'image';
}

function iar_sync_media_type( $post_id ) {
	if ( IAR_POST_TYPE !== get_post_type( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	$type = iar_detect_media_type( $post_id );
	if ( get_post_meta( $post_id, IAR_FIELDS['media_type'], true ) !== $type ) {
		update_post_meta( $post_id, IAR_FIELDS['media_type'], $type );
	}
}
// After ACF has saved the gallery (priority 20), and for saves without ACF.
add_action( 'acf/save_post', 'iar_sync_media_type', 20 );
add_action( 'save_post_' . IAR_POST_TYPE, function ( $post_id ) {
	if ( ! function_exists( 'acf' ) ) {
		iar_sync_media_type( $post_id );
	}
}, 20 );

// One-off correction of existing posts (runs once per plugin version that needs it).
add_action( 'admin_init', function () {
	if ( get_option( 'iar_media_types_synced' ) === '1.7.3' ) {
		return;
	}
	foreach ( get_posts( array( 'post_type' => IAR_POST_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) {
		iar_sync_media_type( $id );
	}
	update_option( 'iar_media_types_synced', '1.7.3', false );
} );

/* -------------------------------------------------------------------------
 * Which videos need a poster
 * ---------------------------------------------------------------------- */

/** Videos without a poster in one post: [ [ video_id, post_id, url, title ], … ] */
function iar_post_videos_missing_posters( $post_id ) {
	$out = array();
	$ids = array_values( array_filter( array_map( 'absint', (array) iar_field( 'media', $post_id ) ) ) );
	foreach ( $ids as $id ) {
		if ( wp_attachment_is( 'video', $id ) && ! iar_video_poster_id( $id, $post_id, 1 === count( $ids ) ) ) {
			$out[] = array(
				'video_id' => $id,
				'post_id'  => $post_id,
				'url'      => wp_get_attachment_url( $id ),
				'title'    => get_the_title( $post_id ),
			);
		}
	}
	return $out;
}

/** Every video in every post, with or without a poster. */
function iar_all_videos() {
	$out = array();
	foreach ( get_posts( array( 'post_type' => IAR_POST_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $post_id ) {
		$ids = array_values( array_filter( array_map( 'absint', (array) iar_field( 'media', $post_id ) ) ) );
		foreach ( $ids as $id ) {
			if ( wp_attachment_is( 'video', $id ) ) {
				$out[] = array(
					'video_id' => $id,
					'post_id'  => $post_id,
					'url'      => wp_get_attachment_url( $id ),
					'title'    => get_the_title( $post_id ),
				);
			}
		}
	}
	return $out;
}

/**
 * Was this attachment made by "Generate video posters" (so safe to delete
 * when regenerating)? Flagged since 1.11; earlier ones are recognised by
 * their "— poster" title, "-poster.jpg" file and parent post.
 */
function iar_is_generated_poster( $attachment_id, $post_id ) {
	if ( get_post_meta( $attachment_id, '_iar_generated_poster', true ) ) {
		return true;
	}
	$a = get_post( $attachment_id );
	return $a && 'attachment' === $a->post_type
		&& (int) $a->post_parent === (int) $post_id
		&& '— poster' === mb_substr( $a->post_title, -8 )
		&& preg_match( '/-poster(-\d+)?\.jpe?g$/i', (string) get_attached_file( $attachment_id ) );
}

/** Every video, in every post, that has no poster — regardless of "Media type". */
function iar_videos_missing_posters() {
	$out = array();
	foreach ( get_posts( array( 'post_type' => IAR_POST_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $post_id ) {
		$out = array_merge( $out, iar_post_videos_missing_posters( $post_id ) );
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * Saving a captured frame
 * ---------------------------------------------------------------------- */

add_action( 'wp_ajax_iar_save_video_poster', function () {
	check_ajax_referer( 'iar_video_posters' );
	$video_id = absint( $_POST['video_id'] ?? 0 );
	$post_id  = absint( $_POST['post_id'] ?? 0 );
	if ( ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( __( 'Sorry, you can\'t do that.', 'instapress-social' ), 403 );
	}
	$media = array_values( array_filter( array_map( 'absint', (array) iar_field( 'media', $post_id ) ) ) );
	if ( IAR_POST_TYPE !== get_post_type( $post_id ) || ! wp_attachment_is( 'video', $video_id ) || ! in_array( $video_id, $media, true ) ) {
		wp_send_json_error( __( 'That video doesn\'t belong to that post.', 'instapress-social' ) );
	}
	if ( empty( $_FILES['poster'] ) ) {
		wp_send_json_error( __( 'No image received.', 'instapress-social' ) );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$name                     = pathinfo( get_attached_file( $video_id ), PATHINFO_FILENAME );
	$_FILES['poster']['name'] = sanitize_file_name( substr( $name, 0, 60 ) . '-poster.jpg' );
	$check                    = wp_check_filetype_and_ext( $_FILES['poster']['tmp_name'], $_FILES['poster']['name'] ); // phpcs:ignore
	if ( 'image/jpeg' !== ( $check['type'] ?? '' ) ) {
		wp_send_json_error( __( 'The poster must be a JPEG.', 'instapress-social' ) );
	}

	// File it alongside the post's date, like the imported media.
	$att = media_handle_upload( 'poster', $post_id, array(
		'post_date'  => get_post_field( 'post_date', $post_id ),
		'post_title' => get_the_title( $video_id ) . ' — poster',
	) );
	if ( is_wp_error( $att ) ) {
		wp_send_json_error( $att->get_error_message() );
	}

	update_post_meta( $att, '_iar_generated_poster', $video_id );

	$old       = iar_own_thumbnail_id( $video_id );
	$post_feat = iar_own_thumbnail_id( $post_id );
	set_post_thumbnail( $video_id, $att );
	// A post that is just this video uses the poster as its featured image too
	// (or keeps using it, if its featured image was the poster being replaced).
	if ( ( 1 === count( $media ) && ! $post_feat ) || ( $old && $post_feat === $old ) ) {
		set_post_thumbnail( $post_id, $att );
	}
	// Regenerating: drop the previous poster if we made it. Imported cover images are kept.
	$removed = false;
	if ( $old && $old !== $att && iar_is_generated_poster( $old, $post_id ) ) {
		$removed = (bool) wp_delete_attachment( $old, true );
	}
	wp_send_json_success( array(
		'poster'  => wp_get_attachment_image_url( $att, 'thumbnail' ),
		'removed' => $removed,
	) );
} );

/* -------------------------------------------------------------------------
 * UI: Import page section + edit-screen notice
 * ---------------------------------------------------------------------- */

function iar_enqueue_video_posters_js() {
	wp_enqueue_script( 'iar-video-posters', IAR_URL . 'assets/js/video-posters.js', array(), IAR_VERSION, true );
}

/** Button + progress, driven by assets/js/video-posters.js. */
function iar_video_posters_widget( $videos, $label, $args = array() ) {
	iar_enqueue_video_posters_js();
	?>
	<span data-iar-posters data-videos="<?php echo esc_attr( wp_json_encode( $videos ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'iar_video_posters' ) ); ?>"<?php echo ! empty( $args['confirm'] ) ? ' data-confirm="' . esc_attr( $args['confirm'] ) . '"' : ''; ?><?php echo ! empty( $args['seek'] ) ? ' data-seek-input="' . esc_attr( $args['seek'] ) . '"' : ''; ?>>
		<button type="button" class="button <?php echo empty( $args['secondary'] ) ? 'button-primary' : ''; ?>" data-iar-posters-go><?php echo esc_html( $label ); ?></button>
		<span data-iar-posters-status style="margin-left:6px;"></span>
		<span data-iar-posters-list style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;"></span>
	</span>
	<?php
}

/** Section on the Import page. */
function iar_render_video_posters() {
	$missing = iar_videos_missing_posters();
	$all     = iar_all_videos();
	?>
	<h2 id="iar-posters"><?php esc_html_e( 'Video posters', 'instapress-social' ); ?></h2>
	<?php if ( ! $all ) : ?>
		<p><?php esc_html_e( 'No videos yet.', 'instapress-social' ); ?></p>
		<?php
		return;
	endif;
	?>
	<?php if ( $missing ) : ?>
		<p style="max-width:720px;">
			<?php
			printf(
				/* translators: %d: number of videos */
				esc_html( _n( '%d video has no poster image, so it shows its first frame (or nothing, on some phones).', '%d videos have no poster image, so they show their first frame (or nothing, on some phones).', count( $missing ), 'instapress-social' ) ),
				count( $missing )
			);
			echo ' ';
			esc_html_e( 'Generate them here: your browser grabs a frame from each video and saves it as its poster. Keep this page open until it finishes.', 'instapress-social' );
			?>
		</p>
		<div style="max-width:900px;margin-bottom:20px;"><?php iar_video_posters_widget( $missing, __( 'Generate missing posters', 'instapress-social' ), array( 'seek' => 'iar-poster-seek' ) ); ?></div>
	<?php else : ?>
		<p><?php esc_html_e( 'Every video has a poster image.', 'instapress-social' ); ?></p>
	<?php endif; ?>

	<p style="max-width:720px;">
		<?php
		printf(
			/* translators: %d: number of videos */
			esc_html( _n( 'Regenerate replaces the poster of your %d video with a new frame — e.g. if a poster is black or blurry.', 'Regenerate replaces the posters of all %d videos with new frames — e.g. if some are black or blurry.', count( $all ), 'instapress-social' ) ),
			count( $all )
		);
		echo ' ';
		esc_html_e( 'Posters made here before are deleted; cover images imported from Instagram stay in your media library.', 'instapress-social' );
		?>
	</p>
	<p>
		<label for="iar-poster-seek"><?php esc_html_e( 'Use the frame at', 'instapress-social' ); ?></label>
		<input type="number" id="iar-poster-seek" class="small-text" min="0" max="60" step="0.5" value="0.5">
		<?php esc_html_e( 'seconds (shorter videos use their middle frame)', 'instapress-social' ); ?>
	</p>
	<div style="max-width:900px;">
		<?php
		iar_video_posters_widget( $all, __( 'Regenerate all posters', 'instapress-social' ), array(
			'secondary' => true,
			'seek'      => 'iar-poster-seek',
			/* translators: %d: number of videos */
			'confirm'   => sprintf( _n( 'Replace the poster of %d video with a new frame?', 'Replace the posters of all %d videos with new frames?', count( $all ), 'instapress-social' ), count( $all ) ),
		) );
		?>
	</div>
	<?php
}

/** On a post's edit screen: offer to make posters for this post's videos. */
add_action( 'admin_notices', function () {
	$screen = get_current_screen();
	if ( ! $screen || 'post' !== $screen->base || IAR_POST_TYPE !== $screen->post_type || empty( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$post_id = absint( $_GET['post'] ); // phpcs:ignore WordPress.Security.NonceVerification
	$missing = current_user_can( 'upload_files' ) ? iar_post_videos_missing_posters( $post_id ) : array();
	if ( ! $missing ) {
		return;
	}
	?>
	<div class="notice notice-warning">
		<p>
			<strong><?php esc_html_e( 'This post\'s video has no poster image.', 'instapress-social' ); ?></strong>
			<?php esc_html_e( 'It shows a blank or first-frame preview until it plays.', 'instapress-social' ); ?>
		</p>
		<p><?php iar_video_posters_widget( $missing, _n( 'Generate poster now', 'Generate posters now', count( $missing ), 'instapress-social' ) ); ?></p>
	</div>
	<?php
} );
