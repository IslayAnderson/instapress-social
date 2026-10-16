<?php
/**
 * "Get instapress-social": lets visitors download an install package of the
 * exact plugin version running on this site, so they can follow back.
 *
 *   https://example.com/?download=instapress-social
 *
 * The zip is built from the installed plugin folder on first request and cached
 * in uploads/instapress-social/ until the plugin changes.
 */

defined( 'ABSPATH' ) || exit;

function iar_download_enabled() {
	return (bool) apply_filters( 'iar_share_plugin', (bool) iar_setting( 'share_plugin' ) );
}

function iar_download_url() {
	// Not ?instapress-social=…: that's the post type's own query var.
	return add_query_arg( 'download', 'instapress-social', home_url( '/' ) );
}

function iar_package_filename() {
	return 'instapress-social-' . IAR_VERSION . '.zip';
}

/** Files to package: everything in the plugin folder except OS junk. */
function iar_package_files() {
	$files = array();
	$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( IAR_DIR, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		$name = $file->getFilename();
		if ( $file->isFile() && ! in_array( $name, array( '.DS_Store', 'Thumbs.db' ), true ) && 0 !== strpos( $name, '._' ) ) {
			$files[] = $file->getPathname();
		}
	}
	sort( $files );
	return $files;
}

/**
 * Build (or reuse) the package. Returns its path or WP_Error.
 */
function iar_build_package() {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'iar_zip', __( 'This server can\'t create zip files (PHP zip extension missing).', 'instapress-social' ) );
	}

	$files = iar_package_files();
	// Cache key: version + newest file time, so edits to the installed plugin rebuild it.
	$stamp = IAR_VERSION . '-' . max( array_map( 'filemtime', $files ) );

	$uploads = wp_upload_dir();
	$dir     = trailingslashit( $uploads['basedir'] ) . 'instapress-social';
	$path    = $dir . '/' . iar_package_filename();

	if ( is_file( $path ) && get_option( 'iar_package_stamp' ) === $stamp ) {
		return $path;
	}
	if ( ! wp_mkdir_p( $dir ) ) {
		return new WP_Error( 'iar_zip', __( 'Couldn\'t create the uploads folder for the package.', 'instapress-social' ) );
	}
	if ( ! is_file( $dir . '/index.php' ) ) {
		file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
	}

	$tmp = $path . '.tmp';
	$zip = new ZipArchive();
	if ( true !== $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		return new WP_Error( 'iar_zip', __( 'Couldn\'t write the package.', 'instapress-social' ) );
	}
	// Keep the installed folder name so the package installs/updates as the same plugin.
	$root = basename( untrailingslashit( IAR_DIR ) );
	foreach ( $files as $file ) {
		$zip->addFile( $file, $root . '/' . ltrim( str_replace( '\\', '/', substr( $file, strlen( IAR_DIR ) ) ), '/' ) );
	}
	$zip->close();
	rename( $tmp, $path );
	update_option( 'iar_package_stamp', $stamp, false );

	return $path;
}

add_action( 'template_redirect', function () {
	if ( ! isset( $_GET['download'] ) || 'instapress-social' !== $_GET['download'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	if ( ! iar_download_enabled() ) {
		wp_die( esc_html__( 'Downloads are turned off on this site.', 'instapress-social' ), '', array( 'response' => 404 ) );
	}
	$path = iar_build_package();
	if ( is_wp_error( $path ) ) {
		wp_die( esc_html( $path->get_error_message() ), '', array( 'response' => 500 ) );
	}

	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="' . iar_package_filename() . '"' );
	header( 'Content-Length: ' . filesize( $path ) );
	header( 'X-Content-Type-Options: nosniff' );
	readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}, 1 );
