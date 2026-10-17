<?php
/**
 * Install count: when the plugin is set up (activated, or first run after an
 * update to a version with this file), send one "plugin_setup" event to the
 * plugin's Google Analytics property through the GA4 Measurement Protocol.
 *
 * Sent: the site's domain, plugin / WordPress version and environment type.
 * Nothing about visitors, posts or users. Turn it off with
 * define( 'IAR_DISABLE_SETUP_EVENT', true ) or the iar_send_setup_event filter.
 *
 * The Measurement Protocol API secret is built in; IAR_GA_API_SECRET or the
 * iar_ga_api_secret filter can replace it (an empty one sends nothing).
 */

defined( 'ABSPATH' ) || exit;

const IAR_GA_MEASUREMENT_ID = 'G-XYP40D7EYL';

function iar_ga_api_secret() {
	return (string) apply_filters( 'iar_ga_api_secret', defined( 'IAR_GA_API_SECRET' ) ? IAR_GA_API_SECRET : '8CkQ3rHrRlG11Tw6yjaWSg' );
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

function iar_setup_event_payload( $event = 'plugin_setup' ) {
	return array(
		'client_id' => iar_ga_client_id(),
		'events'    => array(
			array(
				'name'   => $event,
				'params' => array(
					'domain'               => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
					'plugin_version'       => IAR_VERSION,
					'wp_version'           => get_bloginfo( 'version' ),
					'environment'          => wp_get_environment_type(),
					// These two make the event show up in GA's standard reports.
					'session_id'           => (string) time(),
					'engagement_time_msec' => 1,
				),
			),
		),
	);
}

/** @param bool $debug Use GA's validation endpoint and return its reply. */
function iar_send_setup_event( $debug = false ) {
	$secret = iar_ga_api_secret();
	if ( '' === $secret || ( defined( 'IAR_DISABLE_SETUP_EVENT' ) && IAR_DISABLE_SETUP_EVENT ) || ! apply_filters( 'iar_send_setup_event', true ) ) {
		return false;
	}
	$url = add_query_arg(
		array(
			'measurement_id' => IAR_GA_MEASUREMENT_ID,
			'api_secret'     => rawurlencode( $secret ),
		),
		'https://www.google-analytics.com/' . ( $debug ? 'debug/mp/collect' : 'mp/collect' )
	);
	$res = wp_remote_post( $url, array(
		'body'     => wp_json_encode( iar_setup_event_payload() ),
		'headers'  => array( 'Content-Type' => 'application/json' ),
		'timeout'  => $debug ? 10 : 3,
		'blocking' => $debug,
	) );
	return $debug ? $res : true;
}

// Activation only flags it; the event goes out on the next admin page load.
register_activation_hook( IAR_FILE, function () {
	update_option( 'iar_setup_event_pending', 1, false );
} );

add_action( 'admin_init', function () {
	if ( wp_doing_ajax() || '' === iar_ga_api_secret() ) {
		return;
	}
	if ( ! get_option( 'iar_setup_event_pending' ) && get_option( 'iar_setup_event_sent' ) ) {
		return;
	}
	delete_option( 'iar_setup_event_pending' );
	update_option( 'iar_setup_event_sent', time(), false );
	iar_send_setup_event();
} );
