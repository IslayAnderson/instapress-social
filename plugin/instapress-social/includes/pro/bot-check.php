<?php
/**
 * Bot protection for comments and likes: Cloudflare Turnstile, Google
 * reCAPTCHA v3 or Google reCAPTCHA v2 (invisible).
 *
 * Off until a provider is chosen and its keys are set (Settings →
 * instapress-social → Bot protection). The browser fetches a single-use token
 * per protected request (assets/js/app.js); the comment and like handlers call
 * iar_captcha_require() before doing anything else. The server asks the
 * provider's siteverify and requires success, this site's hostname, the
 * expected action (Turnstile, reCAPTCHA v3) and, for v3, a score at or above
 * the threshold. Failures — including network errors — reject the request.
 *
 * Secrets are stored in their own non-autoloaded options, or defined in
 * wp-config.php (which wins). They are never output.
 */

defined( 'ABSPATH' ) || exit;

/** Supported providers. Turnstile keeps its pre-1.10 setting/option/constant names. */
function iar_captcha_providers() {
	return array(
		'turnstile'    => array(
			'label'        => __( 'Cloudflare Turnstile', 'instapress-social' ),
			'sitekey'      => 'ts_sitekey',
			'secret_opt'   => 'iar_turnstile_secret',
			'secret_const' => 'IAR_TURNSTILE_SECRET',
			'verify'       => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			'actions'      => true,
			'score'        => false,
			'test_secret'  => '/^[123]x0{31}AA$/',
			'keys_url'     => 'https://dash.cloudflare.com/?to=/:account/turnstile',
		),
		'recaptcha_v3' => array(
			'label'        => __( 'Google reCAPTCHA v3 (invisible, score-based)', 'instapress-social' ),
			'sitekey'      => 'rc3_sitekey',
			'secret_opt'   => 'iar_recaptcha_v3_secret',
			'secret_const' => 'IAR_RECAPTCHA_V3_SECRET',
			'verify'       => 'https://www.google.com/recaptcha/api/siteverify',
			'actions'      => true,
			'score'        => true,
			'test_secret'  => '/^6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe$/',
			'keys_url'     => 'https://www.google.com/recaptcha/admin',
		),
		'recaptcha_v2' => array(
			'label'        => __( 'Google reCAPTCHA v2 (invisible, may show a challenge)', 'instapress-social' ),
			'sitekey'      => 'rc2_sitekey',
			'secret_opt'   => 'iar_recaptcha_v2_secret',
			'secret_const' => 'IAR_RECAPTCHA_V2_SECRET',
			'verify'       => 'https://www.google.com/recaptcha/api/siteverify',
			'actions'      => false, // v2 doesn't report an action
			'score'        => false,
			'test_secret'  => '/^6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe$/',
			'keys_url'     => 'https://www.google.com/recaptcha/admin',
		),
	);
}

/** The chosen provider key, or '' when off. Sites from before 1.10 with Turnstile keys stay on Turnstile. */
function iar_captcha_provider() {
	$p = (string) iar_setting( 'captcha_provider' );
	if ( '' === $p ) {
		$p = '' !== trim( (string) iar_setting( 'ts_sitekey' ) ) ? 'turnstile' : 'off';
	}
	return isset( iar_captcha_providers()[ $p ] ) ? $p : '';
}

function iar_captcha_conf( $provider = null ) {
	$provider = $provider ?? iar_captcha_provider();
	return iar_captcha_providers()[ $provider ] ?? null;
}

function iar_captcha_sitekey( $provider = null ) {
	$c = iar_captcha_conf( $provider );
	return $c ? trim( (string) iar_setting( $c['sitekey'] ) ) : '';
}

function iar_captcha_secret( $provider = null ) {
	$c = iar_captcha_conf( $provider );
	if ( ! $c ) {
		return '';
	}
	if ( defined( $c['secret_const'] ) && constant( $c['secret_const'] ) ) {
		return (string) constant( $c['secret_const'] );
	}
	return (string) get_option( $c['secret_opt'], '' );
}

function iar_captcha_is_test_secret( $secret, $provider = null ) {
	$c = iar_captcha_conf( $provider );
	return $c && preg_match( $c['test_secret'], (string) $secret );
}

/** Is a bot check switched on for this action ("comment" or "like")? */
function iar_captcha_active( $action ) {
	if ( ! iar_captcha_conf() || '' === iar_captcha_sitekey() || '' === iar_captcha_secret() ) {
		return false;
	}
	return (bool) iar_setting( 'comment' === $action ? 'ts_comments' : 'ts_likes' );
}

/** Settings for the front end (no secret). */
function iar_captcha_client_config() {
	if ( ! iar_captcha_active( 'comment' ) && ! iar_captcha_active( 'like' ) ) {
		return null;
	}
	$exempt = current_user_can( 'moderate_comments' );
	return array(
		'provider' => iar_captcha_provider(),
		'sitekey'  => iar_captcha_sitekey(),
		'comment'  => iar_captcha_active( 'comment' ) && ! $exempt,
		'like'     => iar_captcha_active( 'like' ) && ! $exempt,
	);
}

/** The provider's script, for pages that need it. */
function iar_captcha_enqueue() {
	$cfg = iar_captcha_client_config();
	if ( ! $cfg || ( ! $cfg['comment'] && ! $cfg['like'] ) ) {
		return;
	}
	$src = array(
		'turnstile'    => 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
		'recaptcha_v3' => 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $cfg['sitekey'] ),
		'recaptcha_v2' => 'https://www.google.com/recaptcha/api.js?render=explicit',
	)[ $cfg['provider'] ];
	wp_enqueue_script( 'iar-captcha-provider', $src, array(), null, array( 'strategy' => 'defer', 'in_footer' => true ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
}

function iar_captcha_client_ip() {
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
function iar_captcha_siteverify( $provider, $secret, $token ) {
	$c = iar_captcha_conf( $provider );
	if ( ! $c ) {
		return null;
	}
	$body = array( 'secret' => $secret, 'response' => $token );
	$ip   = iar_captcha_client_ip();
	if ( $ip ) {
		$body['remoteip'] = $ip;
	}
	$res = wp_remote_post( $c['verify'], array( 'timeout' => 10, 'body' => $body ) );
	if ( is_wp_error( $res ) ) {
		return null;
	}
	// Some failures come back with a 4xx status and a JSON body.
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	return is_array( $data ) && array_key_exists( 'success', $data ) ? $data : null;
}

function iar_captcha_threshold() {
	$t = (float) iar_setting( 'rc3_threshold' );
	return $t > 0 && $t <= 1 ? $t : 0.5;
}

/**
 * Check the request's token for an action. True when no check is on for the
 * action, when a comment moderator is logged in, or when the provider
 * confirms the token for this site (and action / score, where supported).
 */
function iar_captcha_verify( $action ) {
	if ( ! iar_captcha_active( $action ) || current_user_can( 'moderate_comments' ) ) {
		return true;
	}
	// phpcs:disable WordPress.Security.NonceVerification
	$token = sanitize_text_field( wp_unslash( $_POST['captcha_token'] ?? ( $_POST['cf-turnstile-response'] ?? ( $_POST['g-recaptcha-response'] ?? '' ) ) ) );
	// phpcs:enable
	if ( '' === $token || strlen( $token ) > 4096 ) {
		return false;
	}
	$provider = iar_captcha_provider();
	$c        = iar_captcha_conf( $provider );
	$secret   = iar_captcha_secret( $provider );
	$result   = iar_captcha_siteverify( $provider, $secret, $token );
	if ( $result ) {
		iar_captcha_note_secret_error( $provider, $result );
	}
	if ( ! $result || true !== ( $result['success'] ?? false ) ) {
		return false;
	}
	// The providers' published test keys don't report a real hostname/action/score.
	if ( iar_captcha_is_test_secret( $secret, $provider ) ) {
		return true;
	}
	$hostname = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	if ( strtolower( (string) ( $result['hostname'] ?? '' ) ) !== $hostname ) {
		return false;
	}
	if ( $c['actions'] && ( $result['action'] ?? '' ) !== $action ) {
		return false;
	}
	if ( $c['score'] && (float) ( $result['score'] ?? 0 ) < iar_captcha_threshold() ) {
		return false;
	}
	return true;
}

/**
 * A wrong or missing secret makes every check fail, silently blocking all
 * comments and likes — record it so admins see a warning.
 */
function iar_captcha_note_secret_error( $provider, $result ) {
	$codes = (array) ( $result['error-codes'] ?? array() );
	$bad   = array_intersect( $codes, array( 'invalid-input-secret', 'missing-input-secret' ) );
	if ( $bad ) {
		update_option( 'iar_captcha_secret_error', array( 'provider' => $provider, 'time' => time() ), false );
	} elseif ( ! empty( $result['success'] ) && get_option( 'iar_captcha_secret_error' ) ) {
		delete_option( 'iar_captcha_secret_error' );
	}
}

add_action( 'admin_notices', function () {
	$err = get_option( 'iar_captcha_secret_error' );
	if ( ! $err || ! current_user_can( 'manage_options' ) || ( $err['provider'] ?? '' ) !== iar_captcha_provider() ) {
		return;
	}
	$c = iar_captcha_conf( $err['provider'] );
	echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Bot protection is blocking comments and likes.', 'instapress-social' ) . '</strong> ';
	printf(
		/* translators: 1: provider name, 2: settings link */
		esc_html__( '%1$s rejected the secret key, so every check fails. Paste the right secret key under %2$s, or switch bot protection off.', 'instapress-social' ),
		esc_html( $c['label'] ),
		'<a href="' . esc_url( admin_url( 'options-general.php?page=instapress-social#iar-captcha' ) ) . '">' . esc_html__( 'Settings → instapress-social → Bot protection', 'instapress-social' ) . '</a>'
	);
	echo '</p></div>';
} );

/** Reject the current AJAX request if its bot check fails. */
function iar_captcha_require( $action ) {
	if ( ! iar_captcha_verify( $action ) ) {
		wp_send_json_error( array(
			'code'    => 'captcha',
			'message' => __( 'Couldn\'t confirm you\'re human — please try again.', 'instapress-social' ),
		), 403 );
	}
}

/**
 * Is a secret usable? A dummy token makes a valid secret answer
 * "invalid-input-response" and a wrong one "invalid-input-secret".
 * Google only checks the secret once it gets a genuine token, so reCAPTCHA
 * secrets can't be confirmed in advance ('unverifiable'); a bad one is caught
 * on the first real request instead (see iar_captcha_note_secret_error()).
 * Returns 'valid' | 'test' | 'invalid' | 'unverifiable' | 'unreachable'.
 */
function iar_captcha_check_secret( $provider, $secret ) {
	if ( iar_captcha_is_test_secret( $secret, $provider ) ) {
		return 'test';
	}
	if ( 'turnstile' !== $provider ) {
		return 'unverifiable';
	}
	$r = iar_captcha_siteverify( $provider, $secret, 'XXXX.DUMMY.TOKEN.XXXX' );
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
 * Settings: one secret option per provider, never echoed back
 * ---------------------------------------------------------------------- */

add_action( 'admin_init', function () {
	foreach ( iar_captcha_providers() as $provider => $c ) {
		register_setting( 'instapress-social', $c['secret_opt'], array(
			'type'              => 'string',
			'sanitize_callback' => function ( $in ) use ( $provider, $c ) {
				$old = (string) get_option( $c['secret_opt'], '' );
				if ( ! empty( $_POST[ 'iar_clear_' . $c['secret_opt'] ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- options.php checks the nonce
					return '';
				}
				$in = trim( (string) $in );
				if ( '' === $in ) {
					return $old; // left blank: keep the saved secret
				}
				$who = 'turnstile' === $provider ? 'Cloudflare' : 'Google';
				if ( ! preg_match( '/^[A-Za-z0-9_\-\.]{10,200}$/', $in ) ) {
					/* translators: %s: provider name */
					add_settings_error( $c['secret_opt'], 'iar_format', sprintf( __( 'That doesn\'t look like a %s secret key — not saved.', 'instapress-social' ), $c['label'] ) );
					return $old;
				}
				$check = iar_captcha_check_secret( $provider, $in );
				if ( 'invalid' === $check ) {
					/* translators: 1: Cloudflare/Google, 2: provider name */
					add_settings_error( $c['secret_opt'], 'iar_invalid', sprintf( __( '%1$s rejected that %2$s secret key — not saved. Copy it again from its settings.', 'instapress-social' ), $who, $c['label'] ) );
					return $old;
				}
				$msgs = array(
					/* translators: 1: provider name, 2: Cloudflare/Google */
					'valid'       => array( 'success', sprintf( __( '%1$s secret key saved — %2$s confirmed it.', 'instapress-social' ), $c['label'], $who ) ),
					/* translators: %s: provider name */
					'test'        => array( 'warning', sprintf( __( 'Saved the published %s test secret: fine for testing, but it doesn\'t protect anything.', 'instapress-social' ), $c['label'] ) ),
					/* translators: 1: provider name, 2: Cloudflare/Google */
					'unreachable' => array( 'warning', sprintf( __( '%1$s secret key saved, but %2$s couldn\'t be reached to check it.', 'instapress-social' ), $c['label'], $who ) ),
					/* translators: %s: provider name */
					'unverifiable' => array( 'warning', sprintf( __( '%s secret key saved. Google can\'t confirm secret keys in advance — if it\'s wrong, you\'ll see a warning here after the first comment or like.', 'instapress-social' ), $c['label'] ) ),
				);
				delete_option( 'iar_captcha_secret_error' );
				add_settings_error( $c['secret_opt'], 'iar_' . $check, $msgs[ $check ][1], $msgs[ $check ][0] );
				return $in;
			},
		) );
		// Keep secrets out of autoloaded options.
		add_action( 'add_option_' . $c['secret_opt'], function () use ( $c ) {
			global $wpdb;
			$wpdb->update( $wpdb->options, array( 'autoload' => 'off' ), array( 'option_name' => $c['secret_opt'] ) );
		} );
	}
} );

/** Settings rows (called from the settings page). */
function iar_render_captcha_settings( $s, $name ) {
	$current = iar_captcha_provider() ?: 'off';
	?>
	<h2 class="title"><?php esc_html_e( 'Bot protection', 'instapress-social' ); ?></h2>
	<p style="max-width:760px;"><?php esc_html_e( 'Checks that comments and likes come from people, usually invisibly. Choose a provider and paste its keys. Comment moderators (you) are never checked.', 'instapress-social' ); ?></p>
	<table class="form-table" role="presentation" id="iar-captcha">
		<tr>
			<th scope="row"><?php esc_html_e( 'Provider', 'instapress-social' ); ?></th>
			<td>
				<fieldset>
					<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[captcha_provider]" value="off" <?php checked( $current, 'off' ); ?>> <?php esc_html_e( 'Off', 'instapress-social' ); ?></label><br>
					<?php foreach ( iar_captcha_providers() as $key => $c ) : ?>
						<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[captcha_provider]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $current, $key ); ?>> <?php echo esc_html( $c['label'] ); ?></label><br>
					<?php endforeach; ?>
				</fieldset>
				<p class="description"><?php esc_html_e( 'reCAPTCHA loads Google scripts on your instapress-social pages — mention it in your privacy/cookie notice. v3 shows Google\'s badge in the corner.', 'instapress-social' ); ?></p>
			</td>
		</tr>
		<?php foreach ( iar_captcha_providers() as $key => $c ) : ?>
			<?php
			$has_secret  = '' !== iar_captcha_secret( $key );
			$from_config = defined( $c['secret_const'] ) && constant( $c['secret_const'] );
			?>
			<tr class="iar-captcha-keys" data-provider="<?php echo esc_attr( $key ); ?>" <?php echo $current === $key ? '' : 'hidden'; ?>>
				<th scope="row"><?php echo esc_html( $c['label'] ); ?></th>
				<td>
					<p>
						<label><?php esc_html_e( 'Site key', 'instapress-social' ); ?><br>
						<input type="text" class="regular-text code" name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $c['sitekey'] ); ?>]" value="<?php echo esc_attr( $s[ $c['sitekey'] ] ?? '' ); ?>" autocomplete="off"></label>
					</p>
					<p>
						<?php esc_html_e( 'Secret key', 'instapress-social' ); ?><br>
						<?php if ( $from_config ) : ?>
							<?php
							/* translators: %s: constant name */
							printf( esc_html__( 'Set in wp-config.php (%s).', 'instapress-social' ), '<code>' . esc_html( $c['secret_const'] ) . '</code>' );
							?>
						<?php else : ?>
							<input type="password" class="regular-text code" name="<?php echo esc_attr( $c['secret_opt'] ); ?>" value="" placeholder="<?php echo esc_attr( $has_secret ? __( '•••••••• saved — paste a new one to replace', 'instapress-social' ) : __( 'Paste the secret key', 'instapress-social' ) ); ?>" autocomplete="new-password" spellcheck="false">
							<?php if ( $has_secret ) : ?>
								<label style="margin-left:8px;"><input type="checkbox" name="iar_clear_<?php echo esc_attr( $c['secret_opt'] ); ?>" value="1"> <?php esc_html_e( 'Remove', 'instapress-social' ); ?></label>
							<?php endif; ?>
						<?php endif; ?>
					</p>
					<?php if ( $c['score'] ) : ?>
						<p>
							<label><?php esc_html_e( 'Minimum score', 'instapress-social' ); ?>
							<input type="number" class="small-text" min="0.1" max="1" step="0.1" name="<?php echo esc_attr( $name ); ?>[rc3_threshold]" value="<?php echo esc_attr( $s['rc3_threshold'] ?? 0.5 ); ?>"></label>
							<span class="description"><?php esc_html_e( '0.0 = likely bot, 1.0 = likely human. Requests below this are rejected; 0.5 is Google\'s suggested default.', 'instapress-social' ); ?></span>
						</p>
					<?php endif; ?>
					<p class="description">
						<?php
						printf(
							/* translators: 1: link, 2: constant name */
							esc_html__( 'Get keys at %1$s. Secrets are checked when you save and never shown again; or define %2$s in wp-config.php.', 'instapress-social' ),
							'<a href="' . esc_url( $c['keys_url'] ) . '" target="_blank" rel="noopener">' . esc_html( wp_parse_url( $c['keys_url'], PHP_URL_HOST ) ) . '</a>',
							'<code>' . esc_html( $c['secret_const'] ) . '</code>'
						);
						?>
						<?php if ( 'turnstile' !== $key ) : ?>
							<?php
							/* translators: %s: reCAPTCHA type */
							printf( esc_html__( 'Create keys of type "%s" — keys for the other version won\'t work.', 'instapress-social' ), 'recaptcha_v3' === $key ? 'Score based (v3)' : 'Challenge (v2) → Invisible reCAPTCHA badge' );
							?>
						<?php endif; ?>
					</p>
				</td>
			</tr>
		<?php endforeach; ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Protect', 'instapress-social' ); ?></th>
			<td>
				<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[ts_comments]" value="1" <?php checked( $s['ts_comments'] ); ?>> <?php esc_html_e( 'Comments', 'instapress-social' ); ?></label><br>
				<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[ts_likes]" value="1" <?php checked( $s['ts_likes'] ); ?>> <?php esc_html_e( 'Likes', 'instapress-social' ); ?></label>
				<p class="description"><?php esc_html_e( 'Status:', 'instapress-social' ); ?>
					<strong>
						<?php
						if ( 'off' === $current ) {
							esc_html_e( 'off', 'instapress-social' );
						} elseif ( iar_captcha_active( 'comment' ) || iar_captcha_active( 'like' ) ) {
							/* translators: %s: provider name */
							printf( esc_html__( 'on (%s)', 'instapress-social' ), esc_html( iar_captcha_conf( $current )['label'] ) );
						} else {
							esc_html_e( 'off — needs both keys for the chosen provider', 'instapress-social' );
						}
						?>
					</strong>
				</p>
			</td>
		</tr>
	</table>
	<script>
	document.querySelectorAll('#iar-captcha input[name$="[captcha_provider]"]').forEach(function (r) {
		r.addEventListener('change', function () {
			document.querySelectorAll('#iar-captcha .iar-captcha-keys').forEach(function (row) { row.hidden = row.dataset.provider !== r.value; });
		});
	});
	</script>
	<?php
}
