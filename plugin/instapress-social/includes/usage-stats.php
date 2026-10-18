<?php
/**
 * Anonymous usage statistics for the plugin's author: which of the plugin's
 * admin screens get used, and which actions people take there. Sent to the
 * plugin's Google Analytics property through iar_ga_send() (setup-event.php),
 * from the server, without waiting for an answer.
 *
 * Every event carries the site's domain, plugin / WordPress version and
 * environment type, plus names only (a screen, an action, which settings
 * changed) — never post content, settings values, people or visitors.
 *
 * Events:
 *   admin_screen     screen = posts | post_new | post_edit | stories | story_edit | import | following | settings
 *   settings_saved   changed = comma-separated setting names
 *   follow_add, follow_unfollow, follow_refresh, follow_refresh_all
 *   import_start, import_cancel, check_updates
 *   compose_publish  kind = post | text | story
 *   status_change    status = publish | private | draft | trash
 *
 * Off with Settings → Privacy → "Share anonymous usage statistics", the
 * IAR_DISABLE_SETUP_EVENT constant, or the iar_send_usage_stats filter.
 */

defined( 'ABSPATH' ) || exit;

/** Only count people working on the site, not subscribers. */
function iar_usage_track( $name, $params = array() ) {
	if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	iar_ga_send( $name, $params );
}

// Screen views.
add_action( 'current_screen', function ( $screen ) {
	if ( wp_doing_ajax() || 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return;
	}
	$name = '';
	if ( IAR_POST_TYPE === $screen->post_type ) {
		if ( 'edit' === $screen->base ) {
			$name = 'posts';
		} elseif ( 'post' === $screen->base ) {
			$name = 'add' === $screen->action ? 'post_new' : 'post_edit';
		}
	} elseif ( defined( 'IAR_STORY_TYPE' ) && IAR_STORY_TYPE === $screen->post_type ) {
		$name = 'edit' === $screen->base ? 'stories' : ( 'post' === $screen->base ? 'story_edit' : '' );
	}
	if ( 'settings_page_instapress-social' === $screen->id ) {
		$name = 'settings';
	} elseif ( str_ends_with( $screen->id, '_page_iar-import' ) ) {
		$name = 'import';
	} elseif ( str_ends_with( $screen->id, '_page_iar-following' ) ) {
		$name = 'following';
	}
	if ( $name ) {
		iar_usage_track( 'admin_screen', array( 'screen' => $name ) );
	}
} );

// Settings saved: which ones changed (names only).
add_action( 'update_option_' . IAR_OPTION, function ( $old, $new ) {
	if ( ! empty( $GLOBALS['iar_pinning_defaults'] ) ) {
		return;
	}
	$changed = array();
	$old     = wp_parse_args( (array) $old, iar_defaults() ); // a setting never saved before counts as its default
	foreach ( (array) $new as $key => $value ) {
		$before = $old[ $key ] ?? null;
		if ( $before !== $value ) {
			$changed[] = $key;
		}
	}
	if ( $changed ) {
		iar_usage_track( 'settings_saved', array( 'changed' => implode( ',', $changed ) ) );
	}
}, 20, 2 );

// Actions (before the handlers, which end the request).
$iar_usage_actions = array(
	'admin_post_iar_follow'          => 'follow_add',
	'admin_post_iar_import_upload'   => 'import_start',
	'admin_post_iar_import_cancel'   => 'import_cancel',
	'admin_post_iar_check_updates'   => 'check_updates',
);
foreach ( $iar_usage_actions as $hook => $event ) {
	add_action( $hook, function () use ( $event ) {
		iar_usage_track( $event );
	}, 1 );
}
unset( $iar_usage_actions );

add_action( 'admin_post_iar_following_action', function () {
	$do = sanitize_key( $_POST['do'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification -- the handler checks it
	if ( in_array( $do, array( 'unfollow', 'refresh', 'refresh_all' ), true ) ) {
		iar_usage_track( 'follow_' . $do );
	}
}, 1 );

add_action( 'wp_ajax_iar_compose_publish', function () {
	$kind = sanitize_key( $_POST['kind'] ?? 'post' ); // phpcs:ignore WordPress.Security.NonceVerification -- the handler checks it
	iar_usage_track( 'compose_publish', array( 'kind' => in_array( $kind, array( 'post', 'text', 'story' ), true ) ? $kind : 'post' ) );
}, 1 );

add_action( 'wp_ajax_iar_set_status', function () {
	$status = sanitize_key( $_POST['status'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification -- the handler checks it
	if ( in_array( $status, array( 'publish', 'private', 'draft', 'trash' ), true ) ) {
		iar_usage_track( 'status_change', array( 'status' => $status ) );
	}
}, 1 );

// Say so once, on the plugin's screens, with a way to turn it off.
add_action( 'admin_notices', function () {
	$screen = get_current_screen();
	$ours   = $screen && ( IAR_POST_TYPE === $screen->post_type || 'settings_page_instapress-social' === $screen->id || 'plugins' === $screen->id );
	if ( ! $ours || ! current_user_can( 'manage_options' ) || ! iar_ga_enabled() || get_user_meta( get_current_user_id(), 'iar_usage_notice_dismissed', true ) ) {
		return;
	}
	$dismiss  = wp_nonce_url( add_query_arg( 'iar_usage_notice', 'dismiss' ), 'iar_usage_notice' );
	$settings = admin_url( 'options-general.php?page=instapress-social#iar-usage-stats' );
	?>
	<div class="notice notice-info">
		<p>
			<?php esc_html_e( 'instapress-social shares anonymous usage statistics with its author (this site\'s domain, versions, and which of its admin screens and actions are used, never content or people) to help decide what to improve.', 'instapress-social' ); ?>
			<a href="<?php echo esc_url( $settings ); ?>"><?php esc_html_e( 'Turn it off', 'instapress-social' ); ?></a>
			&middot; <a href="<?php echo esc_url( $dismiss ); ?>"><?php esc_html_e( 'OK, hide this', 'instapress-social' ); ?></a>
		</p>
	</div>
	<?php
} );
add_action( 'admin_init', function () {
	if ( 'dismiss' === sanitize_key( wp_unslash( $_GET['iar_usage_notice'] ?? '' ) ) && check_admin_referer( 'iar_usage_notice' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		update_user_meta( get_current_user_id(), 'iar_usage_notice_dismissed', 1 );
		wp_safe_redirect( remove_query_arg( array( 'iar_usage_notice', '_wpnonce' ) ) );
		exit;
	}
} );
