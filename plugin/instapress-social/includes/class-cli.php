<?php
/**
 * WP-CLI commands.
 *
 *   wp instapress-social import <posts.json> <media-dir> [--dry-run] [--status=<status>] [--keep-case]
 *   wp instapress-social shortcode [--date=<date>] [--count=<n>]
 *
 * `wp insta …` is a short alias. The Import page in wp-admin does the same import.
 */

defined( 'ABSPATH' ) || exit;

class IAR_CLI {

	/**
	 * Import posts scraped from imginn, matching media to downloaded files by filename.
	 *
	 * Safe to re-run: posts match on the shortcode field, attachments on their
	 * original filename, so nothing is duplicated; captions/dates are updated.
	 *
	 * ## OPTIONS
	 *
	 * <json>
	 * : posts.json from the imginn bookmarklet.
	 *
	 * <dir>
	 * : Folder of downloaded .jpg/.mp4 files.
	 *
	 * [--dry-run]
	 * : Show what would happen without writing anything.
	 *
	 * [--status=<status>]
	 * : Post status for imported posts.
	 * ---
	 * default: publish
	 * ---
	 *
	 * [--keep-case]
	 * : Store slugs with the shortcode's original case (/p/DYLRvc7CC0h/) instead of lowercase.
	 *
	 * ## EXAMPLES
	 *
	 *     wp instapress-social import posts.json ./islayanderson64-2026-10-16 --dry-run
	 *
	 * @when after_wp_load
	 */
	public function import( $args, $assoc ) {
		list( $json, $dir ) = $args;
		$dir = rtrim( $dir, '/' );
		$dry = ! empty( $assoc['dry-run'] );

		if ( ! is_dir( $dir ) ) {
			WP_CLI::error( "Not a folder: {$dir}" );
		}
		if ( ! function_exists( 'update_field' ) ) {
			WP_CLI::warning( 'ACF not active — writing plain post meta with the same field names.' );
		}

		$posts = IAR_Importer::load_json( $json );
		if ( is_wp_error( $posts ) ) {
			WP_CLI::error( $posts->get_error_message() );
		}

		$local                         = IAR_Importer::index_dir( $dir );
		list( $plan, $warn, $orphans ) = IAR_Importer::plan( $posts, $local );

		WP_CLI::log( sprintf( '%d posts in JSON, %d planned, %d local files unmatched.', count( $posts ), count( $plan ), count( $orphans ) ) );
		foreach ( $warn as $w ) {
			WP_CLI::warning( $w );
		}

		$counts = array( 'create' => 0, 'update' => 0, 'error' => 0 );
		$files  = 0;
		$added  = 0;
		$total  = count( $plan );

		foreach ( $plan as $i => $item ) {
			if ( $dry ) {
				WP_CLI::log( sprintf( '[%d/%d] %s %s — %d file(s)%s — %s', $i + 1, $total, IAR_Importer::exists( $item ) ? 'update' : 'create', $item['post']['shortcode'], count( $item['files'] ), $item['poster'] ? ' + poster' : '', IAR_Importer::title( $item['post']['caption'] ?? '', $item['post']['shortcode'] ) ) );
				continue;
			}
			$r = IAR_Importer::import_item( $item, $local, array(
				'status'    => $assoc['status'] ?? 'publish',
				'keep_case' => ! empty( $assoc['keep-case'] ),
			) );
			$counts[ $r['action'] ]++;
			$files += $r['files'];
			$added += $r['added'];
			foreach ( $r['warnings'] as $w ) {
				WP_CLI::warning( $w );
			}
			WP_CLI::log( sprintf( '[%d/%d] %s', $i + 1, $total, $r['label'] ) );
		}

		if ( $orphans ) {
			WP_CLI::log( "\nLocal files not referenced by any post (" . count( $orphans ) . '):' );
			foreach ( array_slice( $orphans, 0, 25 ) as $o ) {
				WP_CLI::log( '  ' . $o );
			}
			if ( count( $orphans ) > 25 ) {
				WP_CLI::log( '  …' );
			}
		}

		if ( $dry ) {
			WP_CLI::success( 'Dry run — nothing written.' );
		} else {
			WP_CLI::success( "Created {$counts['create']}, updated {$counts['update']}, {$files} media attached ({$added} new)." . ( $counts['error'] ? " {$counts['error']} failed." : '' ) );
		}
	}

	/**
	 * Print instapress-social shortcodes.
	 *
	 * ## OPTIONS
	 *
	 * [--date=<date>]
	 * : Date the code should look like it's from (anything strtotime() reads). Defaults to now.
	 *
	 * [--count=<n>]
	 * : How many to print.
	 * ---
	 * default: 1
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp instapress-social shortcode --date="2019-06-01"
	 *
	 * @when after_wp_load
	 */
	public function shortcode( $args, $assoc ) {
		$time = isset( $assoc['date'] ) ? strtotime( $assoc['date'] ) : null;
		if ( false === $time ) {
			WP_CLI::error( 'Could not parse --date.' );
		}
		for ( $i = 0; $i < max( 1, (int) $assoc['count'] ); $i++ ) {
			WP_CLI::line( iar_unique_shortcode( $time ) );
		}
	}
}

WP_CLI::add_command( 'instapress-social', 'IAR_CLI' );
WP_CLI::add_command( 'insta', 'IAR_CLI' ); // short alias
