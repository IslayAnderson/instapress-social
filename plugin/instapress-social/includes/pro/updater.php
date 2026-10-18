<?php
/**
 * Updates from GitHub Releases.
 *
 * The plugin header's "Update URI: https://github.com/IslayAnderson/instapress-social"
 * tells WordPress (5.8+) not to ask WordPress.org about this plugin and to
 * call the `update_plugins_github.com` filter instead. We answer it with the
 * latest GitHub release, so the normal "Update now" button and the
 * "Enable auto-updates" toggle on the Plugins screen just work.
 *
 * Only the zip attached to a release of this repository is ever installed.
 */

defined( 'ABSPATH' ) || exit;

const IAR_UPDATE_REPO      = 'IslayAnderson/instapress-social';
const IAR_UPDATE_TRANSIENT = 'iar_github_release';

function iar_update_repo() {
	return (string) apply_filters( 'iar_update_repo', IAR_UPDATE_REPO );
}

/** A release download from this repository, under its old or new name (it was renamed to instopress-social). */
function iar_update_url_is_ours( $url ) {
	$owner = strtok( iar_update_repo(), '/' );
	foreach ( array( iar_update_repo(), $owner . '/instopress-social', $owner . '/instapress-social' ) as $repo ) {
		if ( 0 === strpos( $url, 'https://github.com/' . $repo . '/releases/download/' ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Latest release as { version, package, url, notes, published }, or null.
 * Cached for 6 hours (1 hour after a failure) to stay well inside GitHub's
 * 60-requests-per-hour limit for anonymous API calls.
 */
function iar_latest_release( $force = false ) {
	$cached = get_site_transient( IAR_UPDATE_TRANSIENT );
	if ( ! $force && is_array( $cached ) ) {
		return $cached['release'];
	}

	$repo    = iar_update_repo();
	$release = null;
	$res     = wp_remote_get( 'https://api.github.com/repos/' . $repo . '/releases/latest', array(
		'timeout' => 10,
		'headers' => array( 'Accept' => 'application/vnd.github+json' ),
	) );

	if ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) ) {
		$data    = json_decode( wp_remote_retrieve_body( $res ), true );
		$version = ltrim( (string) ( $data['tag_name'] ?? '' ), 'vV' );
		$package = '';
		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			$name = (string) ( $asset['name'] ?? '' );
			$url  = (string) ( $asset['browser_download_url'] ?? '' );
			// Only this repo's own release download, named like instopress-social-1.2.3.zip (or,
			// before the rename, instapress-social-…). The repo itself may answer under either name.
			if ( preg_match( '/^inst[ao]press-social-[\d.]+\.zip$/', $name ) && iar_update_url_is_ours( $url ) ) {
				$package = $url;
				break;
			}
		}
		if ( $version && preg_match( '/^\d+(\.\d+)*$/', $version ) && $package && empty( $data['draft'] ) && empty( $data['prerelease'] ) ) {
			$release = array(
				'version'   => $version,
				'package'   => $package,
				'url'       => esc_url_raw( (string) ( $data['html_url'] ?? 'https://github.com/' . $repo . '/releases' ) ),
				'notes'     => (string) ( $data['body'] ?? '' ),
				'published' => (string) ( $data['published_at'] ?? '' ),
			);
		}
	}

	set_site_transient( IAR_UPDATE_TRANSIENT, array( 'release' => $release ), $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	return $release;
}

/**
 * Answer WordPress's update check for plugins whose Update URI is on github.com.
 */
add_filter( 'update_plugins_github.com', function ( $update, $plugin_data, $plugin_file ) {
	if ( plugin_basename( IAR_FILE ) !== $plugin_file ) {
		return $update;
	}
	$release = iar_latest_release();
	if ( ! $release ) {
		return $update;
	}
	return array(
		'id'           => 'https://github.com/' . iar_update_repo(),
		'slug'         => dirname( plugin_basename( IAR_FILE ) ),
		'version'      => $release['version'],
		'new_version'  => $release['version'],
		'url'          => 'https://github.com/' . iar_update_repo(),
		'package'      => $release['package'],
		'requires'     => '6.2',
		'requires_php' => '7.4',
		'tested'       => get_bloginfo( 'version' ),
	);
}, 10, 3 );

/**
 * "View details" popup on the Plugins / Updates screens.
 */
add_filter( 'plugins_api', function ( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || empty( $args->slug ) || dirname( plugin_basename( IAR_FILE ) ) !== $args->slug ) {
		return $result;
	}
	$release = iar_latest_release();
	$repo    = 'https://github.com/' . iar_update_repo();

	return (object) array(
		'name'          => 'instapress-social',
		'slug'          => $args->slug,
		'version'       => $release['version'] ?? IAR_VERSION,
		'author'        => '<a href="https://islayanderson.co.uk">Islay Anderson</a>',
		'homepage'      => $repo,
		'requires'      => '6.2',
		'requires_php'  => '7.4',
		'tested'        => get_bloginfo( 'version' ),
		'last_updated'  => $release['published'] ?? '',
		'download_link' => $release['package'] ?? '',
		'sections'      => array(
			'description' => '<p>' . esc_html__( 'Your own instapress-social profile on WordPress: profile grid and post pages, stories, short-code permalinks, aspect ratios, following other instapress-social sites, and an importer.', 'instapress-social' ) . '</p><p><a href="' . esc_url( $repo ) . '" target="_blank" rel="noopener">' . esc_html( $repo ) . '</a></p>',
			'changelog'   => $release ? iar_release_notes_html( $release ) : '<p>' . esc_html__( 'Couldn\'t reach GitHub — try again later.', 'instapress-social' ) . '</p>',
		),
	);
}, 10, 3 );

/** Release notes (GitHub Markdown) as simple, escaped HTML for the details popup. */
function iar_release_notes_html( $release ) {
	$html = '<h4>' . esc_html( $release['version'] ) . '</h4>';
	$list = false;
	foreach ( preg_split( '/\R/', $release['notes'] ) as $line ) {
		$line = trim( $line );
		if ( '' === $line || 0 === strpos( $line, '🤖' ) ) {
			continue;
		}
		$text = esc_html( preg_replace( '/^(#+|[-*])\s*/', '', $line ) );
		$text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
		$text = preg_replace( '/`(.+?)`/', '<code>$1</code>', $text );
		$text = preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $text );
		if ( preg_match( '/^[-*]\s/', $line ) ) {
			$html .= ( $list ? '' : '<ul>' ) . '<li>' . $text . '</li>';
			$list  = true;
			continue;
		}
		if ( $list ) {
			$html .= '</ul>';
			$list  = false;
		}
		$html .= preg_match( '/^#+\s/', $line ) ? '<h4>' . $text . '</h4>' : '<p>' . $text . '</p>';
	}
	return $html . ( $list ? '</ul>' : '' ) . '<p><a href="' . esc_url( $release['url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Release on GitHub', 'instapress-social' ) . '</a></p>';
}

/**
 * Keep the plugin's folder name when updating, even if it was installed from
 * a zip with a different top-level folder (e.g. a GitHub source download).
 */
add_filter( 'upgrader_source_selection', function ( $source, $remote_source, $upgrader, $extra ) {
	if ( empty( $extra['plugin'] ) || plugin_basename( IAR_FILE ) !== $extra['plugin'] ) {
		return $source;
	}
	// The renamed plugin (instopress-social.php) goes in its own instopress-social/ folder;
	// iar_follow_renamed_plugin() then points WordPress at it.
	$folder = is_file( trailingslashit( $source ) . 'instopress-social.php' ) ? 'instopress-social' : dirname( plugin_basename( IAR_FILE ) );
	$want   = trailingslashit( $remote_source ) . $folder . '/';
	if ( untrailingslashit( $source ) === untrailingslashit( $want ) ) {
		return $source;
	}
	global $wp_filesystem;
	return $wp_filesystem && $wp_filesystem->move( $source, $want ) ? $want : $source;
}, 10, 4 );

/**
 * The plugin was renamed (instapress-social → instopress-social, with a new folder and
 * main file). WordPress removes the old folder when updating; keep it active, network-
 * active and auto-updating under its new name instead of finding it "missing".
 */
function iar_follow_renamed_plugin( $old, $new, $was_active = false ) {
	$swap = function ( $list ) use ( $old, $new ) {
		$list = array_values( array_unique( array_map( function ( $p ) use ( $old, $new ) {
			return $p === $old ? $new : $p;
		}, (array) $list ) ) );
		return $list;
	};
	$active = $swap( get_option( 'active_plugins', array() ) );
	// Updates run from wp-admin deactivate the plugin first and reactivate it by its old name
	// afterwards, which no longer exists: switch the new one on instead (without running it now).
	if ( $was_active && ! in_array( $new, $active, true ) ) {
		$active[] = $new;
		sort( $active );
	}
	update_option( 'active_plugins', $active );
	update_site_option( 'auto_update_plugins', $swap( get_site_option( 'auto_update_plugins', array() ) ) );
	if ( is_multisite() ) {
		$network = (array) get_site_option( 'active_sitewide_plugins', array() );
		if ( isset( $network[ $old ] ) ) {
			$network[ $new ] = $network[ $old ];
			unset( $network[ $old ] );
			update_site_option( 'active_sitewide_plugins', $network );
		}
	}
}

// Remember whether the plugin is on before an update deactivates it (WordPress does that at priority 10).
add_filter( 'upgrader_pre_install', function ( $response, $extra ) {
	if ( ! empty( $extra['plugin'] ) && plugin_basename( IAR_FILE ) === $extra['plugin'] ) {
		$GLOBALS['iar_active_before_update'] = in_array( $extra['plugin'], (array) get_option( 'active_plugins', array() ), true );
	}
	return $response;
}, 1, 2 );

add_filter( 'upgrader_post_install', function ( $response, $extra, $result ) {
	if ( empty( $extra['plugin'] ) || plugin_basename( IAR_FILE ) !== $extra['plugin'] || is_wp_error( $response ) ) {
		return $response;
	}
	$folder = (string) ( $result['destination_name'] ?? '' );
	if ( $folder && is_file( WP_PLUGIN_DIR . '/' . $folder . '/instopress-social.php' ) ) {
		$new = $folder . '/instopress-social.php';
		if ( $new !== $extra['plugin'] ) {
			iar_follow_renamed_plugin( $extra['plugin'], $new, ! empty( $GLOBALS['iar_active_before_update'] ) );
		}
	}
	return $response;
}, 10, 3 );

// Forget the cached release after this plugin updates, so the next check is fresh.
add_action( 'upgrader_process_complete', function ( $upgrader, $extra ) {
	$updated = array_merge( (array) ( $extra['plugins'] ?? array() ), isset( $extra['plugin'] ) ? array( $extra['plugin'] ) : array() );
	if ( 'plugin' === ( $extra['type'] ?? '' ) && in_array( plugin_basename( IAR_FILE ), $updated, true ) ) {
		delete_site_transient( IAR_UPDATE_TRANSIENT );
	}
}, 10, 2 );

/**
 * "Check for updates" link under the plugin on the Plugins screen.
 */
add_filter( 'plugin_row_meta', function ( $links, $file ) {
	if ( plugin_basename( IAR_FILE ) === $file && current_user_can( 'update_plugins' ) ) {
		$links[] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=iar_check_updates' ), 'iar_check_updates' ) ) . '">' . esc_html__( 'Check for updates', 'instapress-social' ) . '</a>';
		$links[] = '<a href="' . esc_url( 'https://github.com/' . iar_update_repo() . '/releases' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Releases', 'instapress-social' ) . '</a>';
	}
	return $links;
}, 10, 2 );

add_action( 'admin_post_iar_check_updates', function () {
	check_admin_referer( 'iar_check_updates' );
	if ( ! current_user_can( 'update_plugins' ) ) {
		wp_die( esc_html__( 'Sorry, you can\'t do that.', 'instapress-social' ) );
	}
	iar_latest_release( true );
	delete_site_transient( 'update_plugins' );
	wp_update_plugins();
	wp_safe_redirect( admin_url( 'plugins.php?plugin_status=all&iar_checked=1' ) );
	exit;
} );

add_action( 'admin_notices', function () {
	if ( empty( $_GET['iar_checked'] ) || 'plugins' !== get_current_screen()->id ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$release = iar_latest_release();
	if ( ! $release ) {
		$msg = __( 'instapress-social: couldn\'t reach GitHub to check for updates. Try again later.', 'instapress-social' );
	} elseif ( version_compare( $release['version'], IAR_VERSION, '>' ) ) {
		/* translators: %s: version */
		$msg = sprintf( __( 'instapress-social %s is available — use "Update now" below.', 'instapress-social' ), $release['version'] );
	} else {
		/* translators: %s: version */
		$msg = sprintf( __( 'instapress-social is up to date (%s).', 'instapress-social' ), IAR_VERSION );
	}
	echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
} );
