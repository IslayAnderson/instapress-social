<?php
/**
 * "Generate video posters": WordPress can't pull frames out of videos on most
 * hosts (no ffmpeg), so the browser does it — load each video, grab a frame,
 * upload it as the video's poster. Shown on the Import page.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Videos in instapress-social posts that have no poster image.
 * [ [ 'video_id', 'post_id', 'url', 'title' ], … ]
 */
function iar_videos_missing_posters() {
	$out   = array();
	$posts = get_posts( array(
		'post_type'      => IAR_POST_TYPE,
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => IAR_FIELDS['media_type'],
		'meta_compare'   => 'IN',
		'meta_value'     => array( 'video', 'carousel' ),
	) );
	foreach ( $posts as $post_id ) {
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
	}
	return $out;
}

add_action( 'wp_ajax_iar_save_video_poster', function () {
	check_ajax_referer( 'iar_video_posters' );
	$video_id = absint( $_POST['video_id'] ?? 0 );
	$post_id  = absint( $_POST['post_id'] ?? 0 );
	if ( ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( __( 'Sorry, you can\'t do that.', 'instapress-social' ), 403 );
	}
	$media = array_map( 'absint', (array) iar_field( 'media', $post_id ) );
	if ( IAR_POST_TYPE !== get_post_type( $post_id ) || ! wp_attachment_is( 'video', $video_id ) || ! in_array( $video_id, $media, true ) ) {
		wp_send_json_error( __( 'That video doesn\'t belong to that post.', 'instapress-social' ) );
	}
	if ( empty( $_FILES['poster'] ) ) {
		wp_send_json_error( __( 'No image received.', 'instapress-social' ) );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$name                      = pathinfo( get_attached_file( $video_id ), PATHINFO_FILENAME );
	$_FILES['poster']['name']  = sanitize_file_name( substr( $name, 0, 60 ) . '-poster.jpg' );
	$check                     = wp_check_filetype_and_ext( $_FILES['poster']['tmp_name'], $_FILES['poster']['name'] ); // phpcs:ignore
	if ( 'image/jpeg' !== ( $check['type'] ?? '' ) ) {
		wp_send_json_error( __( 'The poster must be a JPEG.', 'instapress-social' ) );
	}

	// File it alongside the post's date, like the imported media.
	$date = get_post_field( 'post_date', $post_id );
	$att  = media_handle_upload( 'poster', $post_id, array( 'post_date' => $date, 'post_title' => get_the_title( $video_id ) . ' — poster' ) );
	if ( is_wp_error( $att ) ) {
		wp_send_json_error( $att->get_error_message() );
	}

	set_post_thumbnail( $video_id, $att );
	// A post that is just this video uses the poster as its featured image too.
	if ( 1 === count( array_filter( $media ) ) && ! iar_own_thumbnail_id( $post_id ) ) {
		set_post_thumbnail( $post_id, $att );
	}
	wp_send_json_success( array( 'poster' => wp_get_attachment_image_url( $att, 'thumbnail' ) ) );
} );

/** Section on the Import page. */
function iar_render_video_posters() {
	$missing = iar_videos_missing_posters();
	?>
	<h2 id="iar-posters"><?php esc_html_e( 'Video posters', 'instapress-social' ); ?></h2>
	<?php if ( ! $missing ) : ?>
		<p><?php esc_html_e( 'Every video has a poster image.', 'instapress-social' ); ?></p>
		<?php
		return;
	endif;
	?>
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
	<p><button type="button" class="button button-primary" id="iar-posters-go"><?php esc_html_e( 'Generate video posters', 'instapress-social' ); ?></button> <span id="iar-posters-status"></span></p>
	<div id="iar-posters-list" style="display:flex;flex-wrap:wrap;gap:6px;max-width:900px;"></div>
	<script>
	(function () {
		var videos = <?php echo wp_json_encode( $missing ); ?>;
		var nonce = <?php echo wp_json_encode( wp_create_nonce( 'iar_video_posters' ) ); ?>;
		var btn = document.getElementById('iar-posters-go'), status = document.getElementById('iar-posters-status'), list = document.getElementById('iar-posters-list');

		// Same origin as this page, so the canvas isn't "tainted" (http vs https site URLs, CDN hosts…).
		function sameOrigin(url) {
			try { var u = new URL(url, location.href); return u.hostname === location.hostname ? u.pathname + u.search : url; } catch (e) { return url; }
		}

		function frame(url) {
			return new Promise(function (resolve, reject) {
				var v = document.createElement('video');
				v.muted = true; v.playsInline = true; v.preload = 'auto'; v.crossOrigin = 'anonymous';
				var done = false, timer = setTimeout(function () { finish(new Error('timed out')); }, 20000);
				function finish(err, blob) { if (done) return; done = true; clearTimeout(timer); v.removeAttribute('src'); v.load(); err ? reject(err) : resolve(blob); }
				v.addEventListener('loadedmetadata', function () { v.currentTime = Math.min(0.5, (v.duration || 1) / 2); });
				v.addEventListener('seeked', function () {
					var scale = Math.min(1, 1080 / Math.max(v.videoWidth, v.videoHeight));
					var c = document.createElement('canvas');
					c.width = Math.round(v.videoWidth * scale); c.height = Math.round(v.videoHeight * scale);
					try {
						c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
						c.toBlob(function (b) { b ? finish(null, b) : finish(new Error('could not encode')); }, 'image/jpeg', 0.86);
					} catch (e) { finish(e); }
				});
				v.addEventListener('error', function () { finish(new Error('video failed to load')); });
				v.src = sameOrigin(url);
			});
		}

		btn.addEventListener('click', async function () {
			btn.disabled = true;
			var ok = 0, failed = 0;
			for (var i = 0; i < videos.length; i++) {
				var item = videos[i];
				status.textContent = (i + 1) + ' / ' + videos.length + ' — ' + item.title;
				var tile = document.createElement('span');
				tile.style.cssText = 'width:54px;height:96px;border-radius:4px;background:#f0f0f1;display:inline-flex;align-items:center;justify-content:center;font-size:11px;overflow:hidden;';
				tile.textContent = '…';
				list.appendChild(tile);
				try {
					var blob = await frame(item.url);
					var body = new FormData();
					body.append('action', 'iar_save_video_poster');
					body.append('_ajax_nonce', nonce);
					body.append('video_id', item.video_id);
					body.append('post_id', item.post_id);
					body.append('poster', blob, 'poster.jpg');
					var res = await (await fetch(ajaxurl, { method: 'POST', body: body, credentials: 'same-origin' })).json();
					if (!res.success) throw new Error(res.data || 'upload failed');
					tile.textContent = '';
					var img = new Image(); img.src = res.data.poster; img.style.cssText = 'width:100%;height:100%;object-fit:cover;';
					tile.appendChild(img);
					ok++;
				} catch (e) {
					tile.textContent = '✕'; tile.title = e.message; tile.style.color = '#b32d2e';
					failed++;
				}
			}
			status.textContent = ok + ' poster' + (ok === 1 ? '' : 's') + ' saved' + (failed ? ', ' + failed + ' failed (hover the ✕ for why)' : '') + '.';
		});
	})();
	</script>
	<?php
}
