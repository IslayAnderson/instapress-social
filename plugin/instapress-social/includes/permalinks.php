<?php
/**
 * instapress-social permalinks: /p/DYLRvc7CC0h/
 *
 * Shortcodes are a base64url encoding of a media ID whose top bits are
 * milliseconds since an epoch of 2011-08-24. We build an ID the same way
 * (post time << 23 | random bits) so generated codes look right *and* sort like
 * real ones: 2019 posts start with "B…", 2026 posts with "D…".
 */

defined( 'ABSPATH' ) || exit;

const IAR_EPOCH_MS   = 1314220021721; // 2011-08-24 21:07:01.721 UTC
const IAR_ALPHABET   = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
const IAR_CODE_REGEX = '/^[A-Za-z0-9_-]{10,12}$/';

/**
 * Generate a shortcode for a Unix timestamp (seconds). Defaults to now.
 */
function iar_generate_shortcode( $timestamp = null ) {
	$ms = (int) round( ( $timestamp ?? microtime( true ) ) * 1000 );
	$ms = max( $ms - IAR_EPOCH_MS, 0 );
	$id = ( $ms << 23 ) | random_int( 0, ( 1 << 23 ) - 1 );

	$code = '';
	do {
		$code = IAR_ALPHABET[ $id & 63 ] . $code;
		$id   = $id >> 6;
	} while ( $id > 0 );

	return $code;
}

/**
 * A shortcode not already used as a slug.
 */
function iar_unique_shortcode( $timestamp = null ) {
	global $wpdb;
	do {
		$code  = iar_generate_shortcode( $timestamp );
		$taken = $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->posts} WHERE post_name = %s LIMIT 1", $code ) );
	} while ( $taken );
	return $code;
}

/**
 * WordPress lowercases slugs; shortcodes are case-sensitive. If the
 * submitted slug looks like a shortcode, store it with its original case.
 * (Lookups still work: MySQL compares post_name case-insensitively.)
 */
add_filter( 'wp_insert_post_data', function ( $data, $postarr, $unsanitized ) {
	if ( IAR_POST_TYPE !== $data['post_type'] || empty( $unsanitized['post_name'] ) ) {
		return $data;
	}
	$raw = $unsanitized['post_name'];
	if ( preg_match( IAR_CODE_REGEX, $raw ) && strtolower( $raw ) === $data['post_name'] ) {
		$data['post_name'] = $raw;
	}
	return $data;
}, 10, 3 );

// The edit screen's permalink preview re-sanitises the slug; show the stored case.
add_filter( 'get_sample_permalink', function ( $permalink, $post_id, $title, $name, $post ) {
	if ( $post && IAR_POST_TYPE === $post->post_type && strtolower( $post->post_name ) === $permalink[1] ) {
		$permalink[1] = $post->post_name;
	}
	return $permalink;
}, 10, 5 );

/* -------------------------------------------------------------------------
 * "Generate shortcode" button under the title (classic edit screen)
 * ---------------------------------------------------------------------- */

add_action( 'edit_form_after_title', function ( $post ) {
	if ( IAR_POST_TYPE !== $post->post_type ) {
		return;
	}
	?>
	<div id="igp-generate" style="margin:6px 10px 0;display:flex;flex-wrap:wrap;align-items:center;gap:4px 8px;line-height:2;color:#646970;">
		<button type="button" class="button button-small" id="igp-generate-btn" style="display:inline-flex;align-items:center;gap:3px;">
			<span class="dashicons dashicons-camera" style="font-size:14px;width:14px;height:14px;"></span>
			<?php esc_html_e( 'Generate shortcode', 'instapress-social' ); ?>
		</button>
		<label style="display:flex;align-items:center;gap:4px;white-space:nowrap;">
			<input type="checkbox" id="igp-fill-title"> <?php esc_html_e( 'also replace title', 'instapress-social' ); ?>
		</label>
		<code id="igp-preview" style="display:none;word-break:break-all;"></code>
	</div>
	<?php
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	$screen = get_current_screen();
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! $screen || IAR_POST_TYPE !== $screen->post_type ) {
		return;
	}
	wp_enqueue_script( 'iar-generate', IAR_URL . 'assets/js/admin-generate.js', array(), IAR_VERSION, true );
	wp_localize_script( 'iar-generate', 'IGP', array(
		'epoch'   => IAR_EPOCH_MS,
		'base'    => home_url( '/' . iar_post_slug() . '/' ),
		'field'   => IAR_FIELDS['shortcode'],
		'confirm' => /* translators: %s: the post's current shortcode */ __( "This post already has the shortcode \"%s\".\nReplace it (and the slug) with a new one?", 'instapress-social' ),
	) );
} );
