<?php
/**
 * Import page, step 3: upload posts.json (+ media) and import from the browser.
 *
 *   upload  → job folder in uploads/instopress-social-import/<random>/
 *   preview → what will be created/updated, files matched or missing
 *   import  → AJAX in small batches (sideloading images is slow), with progress
 *
 * Only files this page uploaded are ever deleted; a server folder given as the
 * media source is read, never touched.
 */

defined( 'ABSPATH' ) || exit;

const IAR_JOB_OPTION = 'iar_import_job';

function iar_import_base_dir() {
	$uploads = wp_upload_dir();
	return trailingslashit( $uploads['basedir'] ) . 'instopress-social-import';
}

function iar_import_job() {
	$job = get_option( IAR_JOB_OPTION );
	return is_array( $job ) && ! empty( $job['json'] ) && is_file( $job['json'] ) ? $job : null;
}

/** Delete a job's own folder (and only if it's inside our import folder). */
function iar_import_cleanup( $job ) {
	if ( ! empty( $job['dir'] ) ) {
		$base = realpath( iar_import_base_dir() );
		$dir  = realpath( $job['dir'] );
		if ( $base && $dir && 0 === strpos( $dir, $base . DIRECTORY_SEPARATOR ) ) {
			iar_fs()->rmdir( $dir, true );
		}
	}
	delete_option( IAR_JOB_OPTION );
}

/** Plan + local file index for a job (recomputed each request — cheap, and keeps the option small). */
function iar_import_job_plan( $job ) {
	$posts = IAR_Importer::load_json( $job['json'] );
	if ( is_wp_error( $posts ) ) {
		return $posts;
	}
	$local = is_dir( $job['media'] ) ? IAR_Importer::index_dir( $job['media'] ) : array();
	list( $plan, $warn, $orphans ) = IAR_Importer::plan( $posts, $local );
	return compact( 'posts', 'local', 'plan', 'warn', 'orphans' );
}

function iar_import_redirect( $args = array() ) {
	wp_safe_redirect( add_query_arg( $args, iar_import_admin_url() ) . '#iar-import' );
	exit;
}

/* -------------------------------------------------------------------------
 * Upload
 * ---------------------------------------------------------------------- */

add_action( 'admin_post_iar_import_upload', function () {
	check_admin_referer( 'iar_import_upload' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you can\'t do that.', 'instopress-social' ) );
	}
	$fail = function ( $message, $dir = '' ) {
		if ( $dir ) {
			iar_import_cleanup( array( 'dir' => $dir ) );
		}
		iar_import_redirect( array( 'iar_error' => rawurlencode( $message ) ) );
	};

	$json = $_FILES['posts_json'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( ! $json || UPLOAD_ERR_OK !== $json['error'] || ! is_uploaded_file( $json['tmp_name'] ) ) {
		$fail( __( 'Choose your posts.json file.', 'instopress-social' ) );
	}
	if ( $json['size'] > 25 * MB_IN_BYTES ) {
		$fail( __( 'That posts.json is too big (over 25 MB).', 'instopress-social' ) );
	}

	// Private job folder.
	$base = iar_import_base_dir();
	wp_mkdir_p( $base );
	if ( ! is_file( $base . '/index.php' ) ) {
		iar_fs()->put_contents( $base . '/index.php', "<?php\n// Silence is golden.\n" );
		iar_fs()->put_contents( $base . '/.htaccess', "Require all denied\nDeny from all\n" );
	}
	$dir = $base . '/' . wp_generate_password( 16, false );
	wp_mkdir_p( $dir );

	$json_path = $dir . '/posts.json';
	if ( ! is_uploaded_file( $json['tmp_name'] ) || ! iar_fs()->move( $json['tmp_name'], $json_path, true ) ) {
		$fail( __( 'Couldn\'t save the uploaded posts.json.', 'instopress-social' ), $dir );
	}
	$posts = IAR_Importer::load_json( $json_path );
	if ( is_wp_error( $posts ) ) {
		$fail( $posts->get_error_message(), $dir );
	}

	// Media: an uploaded zip, or a folder already on the server.
	$source = sanitize_key( $_POST['media_source'] ?? 'zip' );
	if ( 'folder' === $source ) {
		$media = realpath( trim( wp_unslash( $_POST['media_folder'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! $media || ! is_dir( $media ) || ! is_readable( $media ) ) {
			$fail( __( 'That media folder doesn\'t exist on the server (or can\'t be read).', 'instopress-social' ), $dir );
		}
	} else {
		$zip   = $_FILES['media_zip'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$media = $dir . '/media';
		if ( $zip && UPLOAD_ERR_NO_FILE !== $zip['error'] ) {
			if ( UPLOAD_ERR_OK !== $zip['error'] || ! is_uploaded_file( $zip['tmp_name'] ) ) {
				$fail( in_array( $zip['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true )
					? __( 'The media zip is bigger than this server accepts. Upload the folder to the server instead (e.g. by SFTP) and choose "A folder on the server".', 'instopress-social' )
					: __( 'The media zip didn\'t upload.', 'instopress-social' ), $dir );
			}
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
			$unzipped = unzip_file( $zip['tmp_name'], $media );
			if ( is_wp_error( $unzipped ) ) {
				$fail( __( 'Couldn\'t unzip the media: ', 'instopress-social' ) . $unzipped->get_error_message(), $dir );
			}
		} else {
			wp_mkdir_p( $media ); // no media: captions/dates still update, already-imported files are reused
		}
	}

	update_option( IAR_JOB_OPTION, array(
		'dir'     => $dir,
		'json'    => $json_path,
		'media'   => $media,
		'source'  => $source,
		'name'    => sanitize_file_name( $json['name'] ),
		'created' => time(),
		'pos'     => 0,
		'counts'  => array( 'create' => 0, 'update' => 0, 'error' => 0, 'files' => 0 ),
		'status'  => 'ready',
	), false );

	iar_import_redirect();
} );

add_action( 'admin_post_iar_import_cancel', function () {
	check_admin_referer( 'iar_import_cancel' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you can\'t do that.', 'instopress-social' ) );
	}
	iar_import_cleanup( (array) get_option( IAR_JOB_OPTION, array() ) );
	iar_import_redirect();
} );

/* -------------------------------------------------------------------------
 * Import, a few posts per request
 * ---------------------------------------------------------------------- */

add_action( 'wp_ajax_iar_import_step', function () {
	check_ajax_referer( 'iar_import_step' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( __( 'Sorry, you can\'t do that.', 'instopress-social' ), 403 );
	}
	$job = iar_import_job();
	if ( ! $job ) {
		wp_send_json_error( __( 'No import in progress — upload posts.json again.', 'instopress-social' ) );
	}
	$data = iar_import_job_plan( $job );
	if ( is_wp_error( $data ) ) {
		wp_send_json_error( $data->get_error_message() );
	}

	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 120 ); // phpcs:ignore
	}
	$plan    = $data['plan'];
	$total   = count( $plan );
	$started = microtime( true );
	$log     = array();

	// Keep each request well under typical 30s limits: stop after ~15s or 5 posts.
	for ( $n = 0; $job['pos'] < $total && $n < 5 && microtime( true ) - $started < 15; $n++ ) {
		$r = IAR_Importer::import_item( $plan[ $job['pos'] ], $data['local'] );
		$job['counts'][ $r['action'] ]++;
		$job['counts']['files'] += $r['added'];
		$log[] = array( 'text' => $r['label'], 'warn' => false );
		foreach ( $r['warnings'] as $w ) {
			$log[] = array( 'text' => $w, 'warn' => true );
		}
		$job['pos']++;
		$job['status'] = 'running';
		update_option( IAR_JOB_OPTION, $job, false ); // save after every post, so a reload resumes
	}

	$done = $job['pos'] >= $total;
	if ( $done ) {
		iar_import_cleanup( $job );
	}
	wp_send_json_success( array(
		'pos'    => $job['pos'],
		'total'  => $total,
		'done'   => $done,
		'counts' => $job['counts'],
		'log'    => $log,
	) );
} );

/* -------------------------------------------------------------------------
 * Rendering (called from the Import page)
 * ---------------------------------------------------------------------- */

function iar_render_import_upload() {
	if ( isset( $_GET['iar_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-error inline"><p>' . esc_html( wp_unslash( rawurldecode( $_GET['iar_error'] ) ) ) . '</p></div>'; // phpcs:ignore
	}

	$job = iar_import_job();
	if ( $job ) {
		iar_render_import_job( $job );
		return;
	}

	$max     = size_format( wp_max_upload_size() );
	$default = iar_import_base_dir() . '/media';
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" style="max-width:760px;">
		<?php wp_nonce_field( 'iar_import_upload' ); ?>
		<input type="hidden" name="action" value="iar_import_upload">
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="iar-posts-json">posts.json</label></th>
				<td><input type="file" id="iar-posts-json" name="posts_json" accept=".json,application/json" required></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Photos & videos', 'instopress-social' ); ?></th>
				<td>
					<fieldset>
						<label><input type="radio" name="media_source" value="zip" checked> <?php esc_html_e( 'Upload a .zip of the media folder', 'instopress-social' ); ?></label><br>
						<div class="iar-src iar-src--zip" style="margin:6px 0 12px 24px;">
							<input type="file" name="media_zip" accept=".zip,application/zip">
							<p class="description">
								<?php
								printf(
									/* translators: %s: max upload size */
									esc_html__( 'This server accepts uploads up to %s. Leave empty to only update captions and dates of posts you\'ve already imported.', 'instopress-social' ),
									'<strong>' . esc_html( $max ) . '</strong>'
								);
								?>
							</p>
						</div>
						<label><input type="radio" name="media_source" value="folder"> <?php esc_html_e( 'A folder on the server', 'instopress-social' ); ?></label>
						<div class="iar-src iar-src--folder" style="margin:6px 0 0 24px;" hidden>
							<input type="text" name="media_folder" class="large-text code" value="<?php echo esc_attr( $default ); ?>">
							<p class="description"><?php esc_html_e( 'For big archives: upload the folder by SFTP (or it\'s already on this machine) and give its full path. It\'s only read, never changed.', 'instopress-social' ); ?></p>
						</div>
					</fieldset>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Upload & preview', 'instopress-social' ), 'primary', 'submit', false ); ?>
		<span class="description" style="margin-left:8px;"><?php esc_html_e( 'Nothing is imported until you confirm.', 'instopress-social' ); ?></span>
	</form>
	<script>
	document.querySelectorAll('input[name="media_source"]').forEach(function (r) {
		r.addEventListener('change', function () {
			document.querySelector('.iar-src--zip').hidden = r.value !== 'zip';
			document.querySelector('.iar-src--folder').hidden = r.value !== 'folder';
		});
	});
	</script>
	<?php
}

function iar_render_import_job( $job ) {
	$data = iar_import_job_plan( $job );
	if ( is_wp_error( $data ) ) {
		echo '<div class="notice notice-error inline"><p>' . esc_html( $data->get_error_message() ) . '</p></div>';
		return;
	}
	$plan    = $data['plan'];
	$creates = 0;
	$files   = 0; // new files from the folder/zip
	$reused  = 0; // already in the media library
	foreach ( $plan as $item ) {
		$creates += IAR_Importer::exists( $item ) ? 0 : 1;
		foreach ( array_merge( $item['files'], $item['poster'] ? array( $item['poster'] ) : array() ) as $name ) {
			isset( $data['local'][ $name ] ) ? $files++ : $reused++;
		}
	}
	$missing = array_values( array_filter( $data['warn'], function ( $w ) {
		return false !== strpos( $w, 'not found' );
	} ) );
	$resume  = $job['pos'] > 0;
	?>
	<div class="card" style="max-width:760px;padding:16px 20px;">
		<h3 style="margin-top:0;"><?php echo esc_html( sprintf( /* translators: %s: name of the uploaded file or folder */ __( 'Ready to import %s', 'instopress-social' ), $job['name'] ) ); ?></h3>
		<ul style="list-style:disc;margin-left:20px;">
			<li><?php echo esc_html( sprintf( /* translators: %d: number of posts */ _n( '%d post', '%d posts', count( $plan ), 'instopress-social' ), count( $plan ) ) ); ?> — <?php echo esc_html( sprintf( /* translators: %1$d: posts to create, %2$d: posts to update */ __( '%1$d new, %2$d already here (will be updated)', 'instopress-social' ), $creates, count( $plan ) - $creates ) ); ?></li>
			<li><?php echo esc_html( sprintf( /* translators: %d: number of files */ _n( '%d photo/video matched', '%d photos/videos matched', $files, 'instopress-social' ), $files ) ); ?><?php echo $data['orphans'] ? esc_html( sprintf( /* translators: %d: number of files */ __( ' (%d other files in the folder aren\'t in any post)', 'instopress-social' ), count( $data['orphans'] ) ) ) : ''; ?></li>
			<?php if ( $reused ) : ?>
				<li><?php echo esc_html( sprintf( /* translators: %d: number of files */ _n( '%d file is already in your media library and will be reused', '%d files are already in your media library and will be reused', $reused, 'instopress-social' ), $reused ) ); ?></li>
			<?php endif; ?>
			<?php if ( $missing ) : ?>
				<li style="color:#b32d2e;"><?php echo esc_html( sprintf( /* translators: %d: number of files */ _n( '%d file listed in posts.json is missing', '%d files listed in posts.json are missing', count( $missing ), 'instopress-social' ), count( $missing ) ) ); ?> — <?php esc_html_e( 'those slides will be skipped.', 'instopress-social' ); ?></li>
			<?php endif; ?>
		</ul>
		<?php if ( $missing ) : ?>
			<details><summary><?php esc_html_e( 'Missing files', 'instopress-social' ); ?></summary><pre style="white-space:pre-wrap;font-size:11px;"><?php echo esc_html( implode( "\n", array_slice( $missing, 0, 50 ) ) . ( count( $missing ) > 50 ? "\n…" : '' ) ); ?></pre></details>
		<?php endif; ?>

		<div id="iar-progress" style="margin:16px 0 8px;<?php echo $resume ? '' : 'display:none;'; ?>">
			<div style="height:10px;background:#f0f0f1;border-radius:5px;overflow:hidden;"><div id="iar-bar" style="height:100%;width:<?php echo esc_attr( $plan ? round( 100 * $job['pos'] / count( $plan ) ) : 0 ); ?>%;background:#2271b1;transition:width .3s;"></div></div>
			<p id="iar-status" style="margin:6px 0 0;"><?php echo $resume ? esc_html( sprintf( /* translators: %1$d: posts imported so far, %2$d: total posts */ __( 'Paused at %1$d of %2$d.', 'instopress-social' ), $job['pos'], count( $plan ) ) ) : ''; ?></p>
		</div>

		<p>
			<button type="button" class="button button-primary" id="iar-start"><?php echo esc_html( $resume ? __( 'Resume import', 'instopress-social' ) : __( 'Start import', 'instopress-social' ) ); ?></button>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
				<?php wp_nonce_field( 'iar_import_cancel' ); ?>
				<input type="hidden" name="action" value="iar_import_cancel">
				<button class="button" id="iar-cancel"><?php esc_html_e( 'Cancel', 'instopress-social' ); ?></button>
			</form>
		</p>
		<pre id="iar-log" style="display:none;max-height:240px;overflow:auto;background:#f6f7f7;padding:8px 10px;font-size:11px;white-space:pre-wrap;"></pre>
	</div>
	<script>
	(function () {
		var start = document.getElementById('iar-start'), log = document.getElementById('iar-log');
		var bar = document.getElementById('iar-bar'), status = document.getElementById('iar-status');
		var nonce = <?php echo wp_json_encode( wp_create_nonce( 'iar_import_step' ) ); ?>;
		var grid = <?php echo wp_json_encode( get_post_type_archive_link( IAR_POST_TYPE ) ); ?>;

		function line(text, warn) {
			log.style.display = 'block';
			log.appendChild(document.createTextNode((warn ? '⚠ ' : '') + text + '\n'));
			log.scrollTop = log.scrollHeight;
		}

		function step() {
			var body = new FormData();
			body.append('action', 'iar_import_step');
			body.append('_ajax_nonce', nonce);
			fetch(ajaxurl, { method: 'POST', body: body, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (!res.success) throw new Error(res.data || 'Import failed');
					var d = res.data;
					d.log.forEach(function (l) { line(l.text, l.warn); });
					bar.style.width = (d.total ? Math.round(100 * d.pos / d.total) : 100) + '%';
					status.textContent = d.pos + ' / ' + d.total + ' — ' + d.counts.create + ' created, ' + d.counts.update + ' updated, ' + d.counts.files + ' new media';
					if (d.done) {
						status.innerHTML = '';
						status.appendChild(document.createTextNode('Done: ' + d.counts.create + ' created, ' + d.counts.update + ' updated, ' + d.counts.files + ' new media. '));
						var a = document.createElement('a'); a.href = grid; a.textContent = 'View your grid →'; status.appendChild(a);
						start.remove(); document.getElementById('iar-cancel').closest('form').remove();
						return;
					}
					step();
				})
				.catch(function (err) {
					line(err.message + ' — press Resume to carry on.', true);
					start.disabled = false; start.textContent = 'Resume import';
				});
		}

		start.addEventListener('click', function () {
			start.disabled = true;
			document.getElementById('iar-progress').style.display = 'block';
			step();
		});
		window.addEventListener('beforeunload', function (e) {
			if (start.disabled && document.body.contains(start)) { e.preventDefault(); e.returnValue = ''; }
		});
	})();
	</script>
	<?php
}
