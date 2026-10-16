<?php
/**
 * Cloudflare Turnstile bot protection for comments and likes.
 *
 * Off until a site key and secret are set (Settings → instapress-social).
 * The browser gets a single-use token per protected request (assets/js/app.js);
 * the comment and like handlers call iar_turnstile_verify() before doing
 * anything else, which asks Cloudflare's siteverify and requires
 * success === true, the expected action, and this site's own hostname.
 * Failures — including network errors — reject the request.
 *
 * The secret is stored in its own non-autoloaded option, or can be defined as
 * IAR_TURNSTILE_SECRET in wp-config.php (which wins). It is never output.
 */

defined( 'ABSPATH' ) || exit;

const IAR_TS_SECRET_OPTION = 'iar_turnstile_secret';
const IAR_TS_VERIFY_URL    = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

function iar_turnstile_sitekey() {
	return trim( (string) iar_setting( 'ts_sitekey' ) );
}

function iar_turnstile_secret() {
	if ( defined( 'IAR_TURNSTILE_SECRET' ) && IAR_TURNSTILE_SECRET ) {
		return (string) IAR_TURNSTILE_SECRET;
	}
	return (string) get_option( IAR_TS_SECRET_OPTION, '' );
}

function iar_turnstile_secret_from_config() {
	return defined( 'IAR_TURNSTILE_SECRET' ) && IAR_TURNSTILE_SECRET;
}

/** Cloudflare's published test secrets (1x… pass, 2x… fail, 3x… "already spent"). */
function iar_turnstile_is_test_secret( $secret ) {
	return (bool) preg_match( '/^[123]x0{31}AA$/', (string) $secret );
}

/** Is Turnstile switched on for this action ("comment" or "like")? */
function iar_turnstile_active( $action ) {
	if ( '' === iar_turnstile_sitekey() || '' === iar_turnstile_secret() ) {
		return false;
	}
	return (bool) iar_setting( 'comment' === $action ? 'ts_comments' : 'ts_likes' );
}

/** Settings for the front end (no secret). */
function iar_turnstile_client_config() {
	if ( ! iar_turnstile_active( 'comment' ) && ! iar_turnstile_active( 'like' ) ) {
		return null;
	}
	return array(
		'sitekey' => iar_turnstile_sitekey(),
		'comment' => iar_turnstile_active( 'comment' ) && ! current_user_can( 'moderate_comments' ),
		'like'    => iar_turnstile_active( 'like' ) && ! current_user_can( 'moderate_comments' ),
	);
}

function iar_turnstile_client_ip() {
	$candidates = array( $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '', $_SERVER['REMOTE_ADDR'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	foreach ( $candidates as $ip ) {
		$ip = trim( (string) $ip );
		if ( $ip && filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return $ip;
		}
	}
	return '';
}

/** Raw siteverify call. Returns the decoded response, or null on any transport error. */
function iar_turnstile_siteverify( $secret, $token ) {
	$body = array( 'secret' => $secret, 'response' => $token );
	$ip   = iar_turnstile_client_ip();
	if ( $ip ) {
		$body['remoteip'] = $ip;
	}
	$res = wp_remote_post( IAR_TS_VERIFY_URL, array(
		'timeout' => 10,
		'body'    => $body,
	) );
	if ( is_wp_error( $res ) ) {
		return null;
	}
	// Cloudflare answers some failures (e.g. a malformed secret) with a 4xx status and a JSON body.
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	return is_array( $data ) && array_key_exists( 'success', $data ) ? $data : null;
}

/**
 * Check the request's Turnstile token for an action. True when Turnstile is
 * off for the action, when a comment moderator is logged in, or when
 * Cloudflare confirms the token for this action and this site's hostname.
 */
function iar_turnstile_verify( $action ) {
	if ( ! iar_turnstile_active( $action ) || current_user_can( 'moderate_comments' ) ) {
		return true;
	}
	$token = isset( $_POST['cf-turnstile-response'] ) ? (string) wp_unslash( $_POST['cf-turnstile-response'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( '' === $token || strlen( $token ) > 2048 ) {
		return false;
	}
	$secret = iar_turnstile_secret();
	$result = iar_turnstile_siteverify( $secret, $token );
	if ( ! $result || true !== ( $result['success'] ?? false ) ) {
		return false;
	}
	// Cloudflare's test secrets don't report a real action/hostname — skip those checks for them only.
	if ( iar_turnstile_is_test_secret( $secret ) ) {
		return true;
	}
	$hostname = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	return ( $result['action'] ?? '' ) === $action && strtolower( (string) ( $result['hostname'] ?? '' ) ) === $hostname;
}

/** Reject the current AJAX request if its Turnstile check fails. */
function iar_turnstile_require( $action ) {
	if ( ! iar_turnstile_verify( $action ) ) {
		wp_send_json_error( array(
			'code'    => 'turnstile',
			'message' => __( 'Couldn\'t confirm you\'re human — please try again.', 'instapress-social' ),
		), 403 );
	}
}

/**
 * Is a secret usable? Sends a dummy token: a valid secret is answered with
 * "invalid-input-response", a wrong one with "invalid-input-secret".
 * Returns 'valid' | 'test' | 'invalid' | 'unreachable'.
 */
function iar_turnstile_check_secret( $secret ) {
	if ( iar_turnstile_is_test_secret( $secret ) ) {
		return 'test';
	}
	$r = iar_turnstile_siteverify( $secret, 'XXXX.DUMMY.TOKEN.XXXX' );
	if ( null === $r ) {
		return 'unreachable';
	}
	$codes = (array) ( $r['error-codes'] ?? array() );
	if ( in_array( 'invalid-input-secret', $codes, true ) ) {
		return 'invalid';
	}
	return ( false === ( $r['success'] ?? null ) && in_array( 'invalid-input-response', $codes, true ) ) ? 'valid' : 'invalid';
}

/* -------------------------------------------------------------------------
 * Settings: the secret field (its own option, never echoed back)
 * ---------------------------------------------------------------------- */

add_action( 'admin_init', function () {
	register_setting( 'instapress-social', IAR_TS_SECRET_OPTION, array(
		'type'              => 'string',
		'sanitize_callback' => function ( $in ) {
			$old = (string) get_option( IAR_TS_SECRET_OPTION, '' );
			if ( ! empty( $_POST['iar_ts_clear_secret'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- options.php checks the nonce
				return '';
			}
			$in = trim( (string) $in );
			if ( '' === $in ) {
				return $old; // left blank: keep the saved secret
			}
			if ( ! preg_match( '/^[A-Za-z0-9_\-\.]{10,200}$/', $in ) ) {
				add_settings_error( IAR_TS_SECRET_OPTION, 'iar_ts_format', __( 'That doesn\'t look like a Turnstile secret key — not saved.', 'instapress-social' ) );
				return $old;
			}
			$check = iar_turnstile_check_secret( $in );
			$msgs  = array(
				'valid'       => array( 'success', __( 'Turnstile secret key saved — Cloudflare confirmed it.', 'instapress-social' ) ),
				'test'        => array( 'warning', __( 'Saved Cloudflare\'s test secret key: fine for testing, but it doesn\'t protect anything. Use your widget\'s real key on a live site.', 'instapress-social' ) ),
				'unreachable' => array( 'warning', __( 'Turnstile secret key saved, but Cloudflare couldn\'t be reached to check it.', 'instapress-social' ) ),
			);
			if ( 'invalid' === $check ) {
				add_settings_error( IAR_TS_SECRET_OPTION, 'iar_ts_invalid', __( 'Cloudflare rejected that Turnstile secret key — not saved. Copy it again from the widget\'s settings.', 'instapress-social' ) );
				return $old;
			}
			add_settings_error( IAR_TS_SECRET_OPTION, 'iar_ts_' . $check, $msgs[ $check ][1], $msgs[ $check ][0] );
			return $in;
		},
	) );
} );

// Keep the secret out of autoloaded options.
add_action( 'add_option_' . IAR_TS_SECRET_OPTION, function () {
	global $wpdb;
	$wpdb->update( $wpdb->options, array( 'autoload' => 'off' ), array( 'option_name' => IAR_TS_SECRET_OPTION ) );
} );

/** Settings rows (called from the settings page). */
function iar_render_turnstile_settings( $s, $name ) {
	$has_secret  = '' !== iar_turnstile_secret();
	$from_config = iar_turnstile_secret_from_config();
	?>
	<h2 class="title"><?php esc_html_e( 'Bot protection (Cloudflare Turnstile)', 'instapress-social' ); ?></h2>
	<p style="max-width:760px;">
		<?php
		printf(
			/* translators: %s: Cloudflare dashboard link */
			esc_html__( 'Checks that comments and likes come from people, usually invisibly. Get the keys from %s → your widget → Settings. Leave the site key empty to switch it off.', 'instapress-social' ),
			'<a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener">' . esc_html__( 'Cloudflare → Turnstile', 'instapress-social' ) . '</a>'
		);
		?>
	</p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="iar-ts-sitekey"><?php esc_html_e( 'Site key', 'instapress-social' ); ?></label></th>
			<td><input type="text" id="iar-ts-sitekey" class="regular-text code" name="<?php echo esc_attr( $name ); ?>[ts_sitekey]" value="<?php echo esc_attr( $s['ts_sitekey'] ); ?>" placeholder="0x4AAAAAAA…" autocomplete="off"></td>
		</tr>
		<tr>
			<th scope="row"><label for="iar-ts-secret"><?php esc_html_e( 'Secret key', 'instapress-social' ); ?></label></th>
			<td>
				<?php if ( $from_config ) : ?>
					<p><?php echo wp_kses( __( 'Set in <code>wp-config.php</code> (<code>IAR_TURNSTILE_SECRET</code>).', 'instapress-social' ), array( 'code' => array() ) ); ?></p>
				<?php else : ?>
					<input type="password" id="iar-ts-secret" class="regular-text code" name="<?php echo esc_attr( IAR_TS_SECRET_OPTION ); ?>" value="" placeholder="<?php echo esc_attr( $has_secret ? __( '•••••••• saved — paste a new one to replace', 'instapress-social' ) : __( 'Paste the secret key', 'instapress-social' ) ); ?>" autocomplete="new-password" spellcheck="false">
					<?php if ( $has_secret ) : ?>
						<label style="margin-left:8px;"><input type="checkbox" name="iar_ts_clear_secret" value="1"> <?php esc_html_e( 'Remove', 'instapress-social' ); ?></label>
					<?php endif; ?>
					<p class="description"><?php esc_html_e( 'Checked with Cloudflare when you save, and never shown again. Or define IAR_TURNSTILE_SECRET in wp-config.php.', 'instapress-social' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Protect', 'instapress-social' ); ?></th>
			<td>
				<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[ts_comments]" value="1" <?php checked( $s['ts_comments'] ); ?>> <?php esc_html_e( 'Comments', 'instapress-social' ); ?></label><br>
				<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[ts_likes]" value="1" <?php checked( $s['ts_likes'] ); ?>> <?php esc_html_e( 'Likes', 'instapress-social' ); ?></label>
				<p class="description"><?php esc_html_e( 'Status:', 'instapress-social' ); ?>
					<strong><?php echo ( '' !== iar_turnstile_sitekey() && $has_secret ) ? esc_html__( 'on', 'instapress-social' ) : esc_html__( 'off (needs both keys)', 'instapress-social' ); ?></strong>.
					<?php esc_html_e( 'You and other comment moderators are never challenged.', 'instapress-social' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}
