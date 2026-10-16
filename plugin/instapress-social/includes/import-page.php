<?php
/**
 * instapress-social → Import: the imginn scraper as a bookmarklet.
 *
 * The whole scraper is inlined into the bookmark (not loaded from this site),
 * so imginn's Content-Security-Policy can't block it — browsers don't apply a
 * page's CSP to bookmarklets.
 */

defined( 'ABSPATH' ) || exit;

function iar_scraper_source() {
	$file = IAR_DIR . 'tools/imginn-scrape.js';
	return is_readable( $file ) ? (string) file_get_contents( $file ) : '';
}

/** javascript: URL for the bookmarklet. */
function iar_bookmarklet_href() {
	// Wrapped so the bookmark evaluates to undefined (otherwise some browsers
	// replace the page with the expression's value).
	return 'javascript:' . rawurlencode( "(function(){\n" . iar_scraper_source() . "\n})();void 0" );
}

function iar_import_admin_url() {
	return admin_url( 'edit.php?post_type=' . IAR_POST_TYPE . '&page=iar-import' );
}

add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=' . IAR_POST_TYPE,
		__( 'Import', 'instapress-social' ),
		__( 'Import', 'instapress-social' ),
		'manage_options',
		'iar-import',
		'iar_render_import_page'
	);
}, 9 );

function iar_render_import_page() {
	$username = iar_setting( 'username' );
	$profile  = 'https://imginn.com/' . rawurlencode( $username ) . '/';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Import from imginn', 'instapress-social' ); ?></h1>
		<p style="max-width:720px;"><?php esc_html_e( 'imginn shows your public posts but sits behind a "verify you are human" check, so the scraping runs in your own browser. The bookmarklet below does it in one click and downloads posts.json — captions, dates, likes and which media files belong to which post.', 'instapress-social' ); ?></p>

		<h2><?php esc_html_e( '1. Add the bookmarklet', 'instapress-social' ); ?></h2>
		<p><?php esc_html_e( 'Drag this button to your bookmarks bar (show it with ⌘⇧B on Mac, Ctrl+Shift+B on Windows):', 'instapress-social' ); ?></p>
		<p>
			<a class="button button-primary button-hero" id="iar-bookmarklet" href="<?php echo esc_attr( iar_bookmarklet_href() ); ?>" style="cursor:grab;">📷 <?php esc_html_e( 'imginn → posts.json', 'instapress-social' ); ?></a>
		</p>
		<p class="description" id="iar-bookmarklet-hint" hidden><?php esc_html_e( 'Drag it to the bookmarks bar rather than clicking it here — it only works on imginn.com.', 'instapress-social' ); ?></p>

		<h2><?php esc_html_e( '2. Run it on imginn', 'instapress-social' ); ?></h2>
		<ol>
			<li>
				<?php
				printf(
					/* translators: %s: imginn profile link */
					esc_html__( 'Open your profile: %s', 'instapress-social' ),
					'<a href="' . esc_url( $profile ) . '" target="_blank" rel="noopener">' . esc_html( $profile ) . '</a>'
				);
				?>
			</li>
			<li><?php esc_html_e( 'Pass the "verify you are human" check if it appears.', 'instapress-social' ); ?></li>
			<li><?php esc_html_e( 'Click the bookmark. A box in the corner shows progress; when it says done, posts.json is in your Downloads.', 'instapress-social' ); ?></li>
		</ol>
		<p class="description"><?php esc_html_e( 'It pauses between posts to stay polite. If imginn rate-limits it or asks you to verify again, reload, pass the check and click the bookmark again — it carries on where it stopped.', 'instapress-social' ); ?></p>

		<h2 id="iar-import"><?php esc_html_e( '3. Upload and import', 'instapress-social' ); ?></h2>
		<p style="max-width:720px;"><?php esc_html_e( 'Upload posts.json with the photos and videos you downloaded. You\'ll see a preview before anything is imported. Safe to re-run: existing posts are updated, nothing is duplicated.', 'instapress-social' ); ?></p>
		<?php iar_render_import_upload(); ?>

		<details style="margin-top:24px;max-width:900px;">
			<summary style="cursor:pointer;font-weight:600;"><?php esc_html_e( 'Prefer the command line?', 'instapress-social' ); ?></summary>
			<p><code>wp instapress-social import posts.json path/to/media-folder --dry-run</code></p>
			<p><code>wp instapress-social import posts.json path/to/media-folder</code></p>
		</details>

		<details style="margin-top:24px;max-width:900px;">
			<summary style="cursor:pointer;font-weight:600;"><?php esc_html_e( 'Can\'t use bookmarklets? Paste the script into the console instead', 'instapress-social' ); ?></summary>
			<p><?php esc_html_e( 'On your imginn profile, open DevTools → Console, paste this and press Enter.', 'instapress-social' ); ?></p>
			<p><button type="button" class="button" id="iar-copy-script"><?php esc_html_e( 'Copy script', 'instapress-social' ); ?></button> <span id="iar-copy-done" hidden><?php esc_html_e( 'Copied.', 'instapress-social' ); ?></span></p>
			<textarea id="iar-script" class="large-text code" rows="12" readonly><?php echo esc_textarea( iar_scraper_source() ); ?></textarea>
		</details>
	</div>
	<script>
	(function () {
		// Clicking it here would just show the "open imginn first" message — nudge towards dragging.
		document.getElementById('iar-bookmarklet').addEventListener('click', function (e) {
			e.preventDefault();
			document.getElementById('iar-bookmarklet-hint').hidden = false;
		});
		document.getElementById('iar-copy-script').addEventListener('click', function () {
			var ta = document.getElementById('iar-script');
			var done = function () { document.getElementById('iar-copy-done').hidden = false; };
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(ta.value).then(done);
			} else {
				ta.select();
				document.execCommand('copy');
				done();
			}
		});
	})();
	</script>
	<?php
}
