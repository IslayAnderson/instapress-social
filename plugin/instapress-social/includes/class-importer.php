<?php
/**
 * Imports posts.json (from the imginn scraper) — shared by the WP-CLI command
 * and the Import page. Media are matched to files in a folder by filename.
 *
 * Safe to re-run: posts match on the shortcode field, attachments on their
 * original filename, so nothing is duplicated; captions/dates are updated.
 */

defined( 'ABSPATH' ) || exit;

class IAR_Importer {

	const SOURCE_META = '_ig_source_file'; // on attachments: original filename, for de-duping

	/** @var string[] Warnings collected while importing one item. */
	private static $warnings = array();

	/** @var int Files actually added to the media library for one item (not reused). */
	private static $added = 0;

	public static function load_admin_includes() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	/** Posts from a posts.json file, or WP_Error. */
	public static function load_json( $path ) {
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return new WP_Error( 'iar_json', __( 'posts.json could not be read.', 'instapress-social' ) );
		}
		$data  = json_decode( (string) file_get_contents( $path ), true );
		$posts = isset( $data['posts'] ) ? $data['posts'] : $data;
		if ( ! is_array( $posts ) || ( $posts && ! isset( reset( $posts )['shortcode'] ) ) ) {
			return new WP_Error( 'iar_json', __( 'That file isn\'t a posts.json from the imginn scraper.', 'instapress-social' ) );
		}
		return array_values( $posts );
	}

	/** filename => path for every media file in $dir (searches one level of subfolders too). */
	public static function index_dir( $dir ) {
		$index = array();
		$dirs  = array( $dir );
		foreach ( (array) glob( trailingslashit( $dir ) . '*', GLOB_ONLYDIR ) as $sub ) {
			$dirs[] = $sub; // a zip often wraps everything in one folder
		}
		foreach ( $dirs as $d ) {
			foreach ( new DirectoryIterator( $d ) as $f ) {
				if ( $f->isFile() && preg_match( '/\.(jpe?g|png|webp|heic|mp4|mov)$/i', $f->getFilename() ) && ! isset( $index[ $f->getFilename() ] ) ) {
					$index[ $f->getFilename() ] = $f->getPathname();
				}
			}
		}
		return $index;
	}

	/**
	 * Oldest first; each file claimed by the first post listing it;
	 * a lone video plus a single image → the image is the video's poster.
	 *
	 * Files missing from the folder but already in the media library are kept
	 * (attach() reuses them).
	 *
	 * @return array [ plan, warnings, unmatched local files ]
	 */
	public static function plan( array $posts, array $local ) {
		usort( $posts, function ( $a, $b ) {
			return strcmp( (string) ( $a['date'] ?? '' ), (string) ( $b['date'] ?? '' ) );
		} );

		$claimed = array();
		$plan    = array();
		$warn    = array();

		foreach ( $posts as $p ) {
			if ( empty( $p['shortcode'] ) || ! preg_match( '/^[A-Za-z0-9_-]{5,40}$/', $p['shortcode'] ) ) {
				continue;
			}
			$files = array();
			foreach ( (array) ( $p['media'] ?? array() ) as $name ) {
				$name = basename( (string) $name );
				if ( isset( $claimed[ $name ] ) ) {
					$warn[] = "{$p['shortcode']}: {$name} already used by {$claimed[ $name ]}, skipped";
					continue;
				}
				// Not in the folder but imported before? Reuse it — otherwise a re-import
				// without the media would empty the post's gallery.
				if ( ! isset( $local[ $name ] ) && ! self::find_attachment( $name ) ) {
					$warn[] = "{$p['shortcode']}: {$name} not found in media folder";
					continue;
				}
				$claimed[ $name ] = $p['shortcode'];
				$files[]          = $name;
			}

			$videos = array_values( array_filter( $files, function ( $n ) {
				return (bool) preg_match( '/\.(mp4|mov)$/i', $n );
			} ) );
			$images = array_values( array_diff( $files, $videos ) );
			$poster = null;
			if ( 1 === count( $videos ) && 1 === count( $images ) ) {
				$poster = $images[0];
				$files  = $videos;
			}

			if ( count( $files ) > 1 ) {
				$type = 'carousel';
			} elseif ( $videos && $files === $videos ) {
				$type = 'video';
			} else {
				$type = 'image';
			}

			$plan[] = array( 'post' => $p, 'files' => $files, 'poster' => $poster, 'type' => $type );
		}

		$orphans = array_values( array_diff( array_keys( $local ), array_keys( $claimed ) ) );
		sort( $orphans );
		return array( $plan, $warn, $orphans );
	}

	/** Whether a planned post already exists (for previews). */
	public static function exists( array $item ) {
		return (bool) self::find_post( $item['post']['shortcode'] );
	}

	/**
	 * Import one planned post.
	 *
	 * @param array  $item  From plan().
	 * @param array  $local filename => path, from index_dir().
	 * @param array  $opts  status (default publish), keep_case (bool).
	 * @return array { action: create|update|error, post_id, files (attached), added (new to the library), warnings[], label }
	 */
	public static function import_item( array $item, array $local, array $opts = array() ) {
		self::load_admin_includes();
		self::$warnings = array();
		self::$added    = 0;

		$p        = $item['post'];
		$code     = $p['shortcode'];
		$gmt      = ! empty( $p['date'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $p['date'] ) ) : null;
		$date     = $gmt ? get_date_from_gmt( $gmt ) : null;
		$existing = self::find_post( $code );
		$title    = self::title( $p['caption'] ?? '', $code );

		$postarr = array(
			'post_type'   => IAR_POST_TYPE,
			'post_status' => $opts['status'] ?? 'publish',
			'post_title'  => $title,
			'post_name'   => ! empty( $opts['keep_case'] ) ? $code : strtolower( $code ),
		);
		if ( $gmt ) {
			$postarr['post_date']     = $date;
			$postarr['post_date_gmt'] = $gmt;
		}
		if ( $existing ) {
			$postarr['ID'] = $existing;
			$post_id       = wp_update_post( $postarr, true );
		} else {
			$post_id = wp_insert_post( $postarr, true );
		}
		if ( is_wp_error( $post_id ) ) {
			return array( 'action' => 'error', 'post_id' => 0, 'files' => 0, 'added' => 0, 'warnings' => array( "{$code}: " . $post_id->get_error_message() ), 'label' => $code );
		}

		$ids = array();
		foreach ( $item['files'] as $name ) {
			$id = self::attach( $name, $local[ $name ] ?? '', $post_id, $date );
			if ( $id ) {
				$ids[] = $id;
			}
		}

		$thumb = $item['poster'] ? self::attach( $item['poster'], $local[ $item['poster'] ] ?? '', $post_id, $date ) : 0;
		if ( ! $thumb ) {
			foreach ( $ids as $id ) {
				if ( wp_attachment_is_image( $id ) ) {
					$thumb = $id;
					break;
				}
			}
		}
		if ( $thumb ) {
			set_post_thumbnail( $post_id, $thumb );
		}

		self::set( 'shortcode', $code, $post_id );
		self::set( 'caption', (string) ( $p['caption'] ?? '' ), $post_id );
		self::set( 'media', $ids, $post_id );
		self::set( 'media_type', $item['type'], $post_id );
		self::set( 'posted_at', $date, $post_id );
		self::set( 'likes', isset( $p['likes'] ) ? (int) $p['likes'] : null, $post_id );
		self::set( 'comments', isset( $p['comments'] ) ? (int) $p['comments'] : null, $post_id );
		self::set( 'location', (string) ( $p['location'] ?? '' ), $post_id );
		self::set( 'url', esc_url_raw( $p['url'] ?? '' ), $post_id );

		return array(
			'action'   => $existing ? 'update' : 'create',
			'post_id'  => (int) $post_id,
			'files'    => count( $ids ),
			'added'    => self::$added,
			'warnings' => self::$warnings,
			'label'    => sprintf( '%s %s — %d file(s)%s', $existing ? 'updated' : 'created', $code, count( $ids ), $item['poster'] ? ' + poster' : '' ),
		);
	}

	public static function title( $caption, $code ) {
		$line = trim( preg_replace( '/\s+/', ' ', (string) strtok( (string) $caption, "\n" ) ) );
		return '' === $line ? 'instapress-social post ' . $code : wp_html_excerpt( $line, 69, '…' );
	}

	private static function set( $key, $value, $post_id ) {
		if ( function_exists( 'update_field' ) ) {
			update_field( IAR_FIELDS[ $key ], $value, $post_id );
		} else {
			update_post_meta( $post_id, IAR_FIELDS[ $key ], $value );
		}
	}

	private static function find_post( $shortcode ) {
		$ids = get_posts( array(
			'post_type'      => IAR_POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => IAR_FIELDS['shortcode'],
			'meta_value'     => $shortcode,
		) );
		return $ids ? (int) $ids[0] : 0;
	}

	private static function find_attachment( $name ) {
		$ids = get_posts( array(
			'post_type'      => 'attachment',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => self::SOURCE_META,
			'meta_value'     => $name,
		) );
		return $ids ? (int) $ids[0] : 0;
	}

	private static function attach( $name, $path, $post_id, $date ) {
		$existing = self::find_attachment( $name );
		if ( $existing ) {
			if ( (int) wp_get_post_parent_id( $existing ) !== $post_id ) {
				wp_update_post( array( 'ID' => $existing, 'post_parent' => $post_id ) );
			}
			return $existing;
		}
		if ( ! $path || ! is_file( $path ) ) {
			self::$warnings[] = "{$name}: file missing";
			return 0;
		}

		// media_handle_sideload moves the file, so hand it a temp copy.
		$tmp = wp_tempnam( $name );
		copy( $path, $tmp );

		$id = media_handle_sideload(
			array( 'name' => $name, 'tmp_name' => $tmp ),
			$post_id,
			null,
			$date ? array( 'post_date' => $date ) : array()
		);
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp ); // phpcs:ignore
			self::$warnings[] = "{$name}: " . $id->get_error_message();
			return 0;
		}
		update_post_meta( $id, self::SOURCE_META, $name );
		self::touch_files( $id );
		self::$added++;
		return (int) $id;
	}

	/** Set file timestamps (original + generated sizes) to the attachment's date. */
	private static function touch_files( $id ) {
		$time = strtotime( get_post_field( 'post_date_gmt', $id ) . ' UTC' );
		$file = get_attached_file( $id );
		$meta = wp_get_attachment_metadata( $id );
		$all  = array( $file );
		if ( ! empty( $meta['original_image'] ) ) {
			$all[] = dirname( $file ) . '/' . $meta['original_image'];
		}
		foreach ( (array) ( $meta['sizes'] ?? array() ) as $size ) {
			$all[] = dirname( $file ) . '/' . $size['file'];
		}
		foreach ( array_unique( $all ) as $p ) {
			if ( $time && is_file( $p ) ) {
				touch( $p, $time );
			}
		}
	}
}
