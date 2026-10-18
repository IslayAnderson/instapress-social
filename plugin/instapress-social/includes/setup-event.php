<?php
/**
 * Install count: when the plugin is set up (activated, or first run after an
 * update to a version with this file), send one "plugin_setup" event to the
 * plugin's Google Analytics property through the GA4 Measurement Protocol.
 *
 * Sent: the site's domain, plugin / WordPress version and environment type.
 * Nothing about visitors, posts or users. includes/usage-stats.php sends
 * admin-screen events through the same iar_ga_send(). Turn all of it off in
 * Settings → Privacy, with define( 'IAR_DISABLE_SETUP_EVENT', true ), or the
 * iar_send_usage_stats filter (iar_send_setup_event: just this one).
 *
 * The Measurement Protocol API secret isn't in this code: it's fetched from
 * IAR_GA_CONFIG_URL (the plugin author's site) and cached. IAR_GA_API_SECRET /
 * IAR_GA_MEASUREMENT_ID or the iar_ga_config filter can supply it instead.
 * No secret, no event.
 */

defined( 'ABSPATH' ) || exit;

const IAR_GA_CONFIG_URL = 'https://blog.islayanderson.co.uk/wp-json/instapress/v1/ga-secret';

/** @return array{measurement_id:string,api_secret:string} Empty strings when unavailable. */
function iar_ga_config() {
	if ( defined( 'IAR_GA_API_SECRET' ) ) {
		$config = array(
			'measurement_id' => defined( 'IAR_GA_MEASUREMENT_ID' ) ? IAR_GA_MEASUREMENT_ID : 'G-XYP40D7EYL',
			'api_secret'     => IAR_GA_API_SECRET,
		);
	} else {
		$config = get_transient( 'iar_ga_config' );
		if ( ! is_array( $config ) ) {
			$config = array( 'measurement_id' => '', 'api_secret' => '' );
			$res    = wp_remote_get( apply_filters( 'iar_ga_config_url', IAR_GA_CONFIG_URL ), array( 'timeout' => 5 ) );
			$body   = 200 === wp_remote_retrieve_response_code( $res ) ? json_decode( wp_remote_retrieve_body( $res ), true ) : null;
			if ( is_array( $body ) && ! empty( $body['api_secret'] ) && ! empty( $body['measurement_id'] ) ) {
				$config = array(
					'measurement_id' => (string) $body['measurement_id'],
					'api_secret'     => (string) $body['api_secret'],
				);
			}
			// Keep a good answer for 12 hours; after a failure, try again in an hour.
			set_transient( 'iar_ga_config', $config, $config['api_secret'] ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
		}
	}
	$config = (array) apply_filters( 'iar_ga_config', $config );
	return array(
		'measurement_id' => (string) ( $config['measurement_id'] ?? '' ),
		'api_secret'     => (string) ( $config['api_secret'] ?? '' ),
	);
}

/** A random, stable id for this site (GA needs a client_id; nothing personal). */
function iar_ga_client_id() {
	$id = get_option( 'iar_ga_client_id' );
	if ( ! $id ) {
		$id = wp_rand( 100000000, 999999999 ) . '.' . time();
		update_option( 'iar_ga_client_id', $id, false );
	}
	return $id;
}

/** Usage statistics on? (Settings → Privacy, the IAR_DISABLE_SETUP_EVENT constant, the iar_send_usage_stats filter.) */
function iar_ga_enabled() {
	if ( defined( 'IAR_DISABLE_SETUP_EVENT' ) && IAR_DISABLE_SETUP_EVENT ) {
		return false;
	}
	return (bool) apply_filters( 'iar_send_usage_stats', (bool) iar_setting( 'usage_stats' ) );
}

/** GA groups events into sessions: one per site per half hour of activity. */
function iar_ga_session_id() {
	$id = get_transient( 'iar_ga_session' );
	if ( ! $id ) {
		$id = (string) time();
	}
	set_transient( 'iar_ga_session', $id, 30 * MINUTE_IN_SECONDS );
	return $id;
}

/** One event, with what every event carries: the site's domain, versions and environment. */
function iar_ga_payload( $name, $params = array() ) {
	return array(
		'client_id' => iar_ga_client_id(),
		'events'    => array(
			array(
				'name'   => $name,
				'params' => array_merge(
					array(
						'domain'               => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
						'plugin_version'       => IAR_VERSION,
						'wp_version'           => get_bloginfo( 'version' ),
						'environment'          => wp_get_environment_type(),
						// These two make the event show up in GA's standard reports.
						'session_id'           => iar_ga_session_id(),
						'engagement_time_msec' => 1,
					),
					array_map( function ( $v ) {
						return is_bool( $v ) ? (int) $v : ( is_scalar( $v ) ? mb_substr( (string) $v, 0, 100 ) : '' );
					}, (array) $params )
				),
			),
		),
	);
}

/**
 * Send one event. Doesn't wait for GA's answer, so it never slows a page down.
 *
 * @param bool $debug Use GA's validation endpoint and return its reply.
 */
function iar_ga_send( $name, $params = array(), $debug = false ) {
	if ( ! iar_ga_enabled() ) {
		return false;
	}
	$ga = iar_ga_config();
	if ( '' === $ga['api_secret'] || '' === $ga['measurement_id'] ) {
		return false;
	}
	$url = add_query_arg(
		array(
			'measurement_id' => rawurlencode( $ga['measurement_id'] ),
			'api_secret'     => rawurlencode( $ga['api_secret'] ),
		),
		'https://www.google-analytics.com/' . ( $debug ? 'debug/mp/collect' : 'mp/collect' )
	);
	$res = wp_remote_post( $url, array(
		'body'     => wp_json_encode( iar_ga_payload( $name, $params ) ),
		'headers'  => array( 'Content-Type' => 'application/json' ),
		'timeout'  => $debug ? 10 : 3,
		'blocking' => $debug,
	) );
	return $debug ? $res : true;
}

/** @param bool $debug Use GA's validation endpoint and return its reply. */
function iar_send_setup_event( $debug = false ) {
	if ( ! apply_filters( 'iar_send_setup_event', true ) ) {
		return false;
	}
	return iar_ga_send( 'plugin_setup', array(), $debug );
}

// Activation only flags it; the event goes out on the next admin page load.
register_activation_hook( IAR_FILE, function () {
	update_option( 'iar_setup_event_pending', 1, false );
} );

add_action( 'admin_init', function () {
	if ( wp_doing_ajax() || ( ! get_option( 'iar_setup_event_pending' ) && get_option( 'iar_setup_event_sent' ) ) ) {
		return;
	}
	// Only counts as sent once it actually went; otherwise it's retried (at most hourly, see iar_ga_config).
	if ( iar_send_setup_event() ) {
		delete_option( 'iar_setup_event_pending' );
		update_option( 'iar_setup_event_sent', time(), false );
	}
} );
