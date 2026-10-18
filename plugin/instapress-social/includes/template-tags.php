<?php
/**
 * Helpers used by the templates. Safe to call from theme overrides.
 */

defined( 'ABSPATH' ) || exit;

/** Raw field value (works with or without ACF — ACF stores under the same meta key). */
function iar_field( $key, $post_id = null ) {
	return get_post_meta( $post_id ?: get_the_ID(), IAR_FIELDS[ $key ], true );
}

function iar_avatar_url( $size = 'thumbnail' ) {
	$id  = (int) iar_setting( 'avatar_id' );
	$url = $id ? wp_get_attachment_image_url( $id, $size ) : '';
	return $url ?: ( get_site_icon_url( 300 ) ?: includes_url( 'images/w-logo-blue.png' ) );
}

function iar_profile_url() {
	return get_post_type_archive_link( IAR_POST_TYPE );
}

/**
 * The featured image actually set on a post or attachment — read straight from
 * the database, so plugins that substitute a default image (e.g. "Default
 * Featured Image") can't make it look like a video has a poster.
 */
function iar_own_thumbnail_id( $post_id ) {
	// The raw meta cache (filled by iar_prime_media) isn't filtered either, and saves a query per item.
	$meta = wp_cache_get( $post_id, 'post_meta' );
	if ( is_array( $meta ) ) {
		$id = (int) ( $meta['_thumbnail_id'][0] ?? 0 );
	} else {
		global $wpdb;
		$id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_thumbnail_id' LIMIT 1",
			$post_id
		) );
	}
	return $id && wp_attachment_is_image( $id ) ? $id : 0;
}

/**
 * Load every attachment a list of posts will show (gallery items, their
 * posters, featured images) in a few queries instead of several per item.
 */
function iar_prime_media( $posts ) {
	$post_ids = array();
	foreach ( (array) $posts as $p ) {
		$p = get_post( $p );
		if ( $p && IAR_POST_TYPE === $p->post_type ) {
			$post_ids[] = $p->ID;
		}
	}
	if ( ! $post_ids ) {
		return;
	}
	update_meta_cache( 'post', $post_ids );

	$ids = array();
	foreach ( $post_ids as $id ) {
		$ids   = array_merge( $ids, array_map( 'absint', (array) get_post_meta( $id, IAR_FIELDS['media'], true ) ) );
		$meta  = wp_cache_get( $id, 'post_meta' );
		$ids[] = (int) ( $meta['_thumbnail_id'][0] ?? 0 );
	}
	$ids = array_values( array_unique( array_filter( $ids ) ) );
	_prime_post_caches( $ids, false, true );

	// Then the videos' posters.
	$posters = array();
	foreach ( $ids as $id ) {
		$meta      = wp_cache_get( $id, 'post_meta' );
		$posters[] = (int) ( is_array( $meta ) ? ( $meta['_thumbnail_id'][0] ?? 0 ) : 0 );
	}
	_prime_post_caches( array_values( array_unique( array_filter( $posters ) ) ), false, true );
}

add_filter( 'the_posts', function ( $posts, $query ) {
	if ( count( $posts ) > 1 && in_array( $query->get( 'fields' ), array( '', 'all' ), true ) ) {
		iar_prime_media( $posts );
	}
	return $posts;
}, 10, 2 );

/**
 * Poster image for a video: the video's own thumbnail (set by WordPress or by
 * "Generate video posters"), or — for a post that is just this one video — the
 * post's own featured image (the importer stores imginn's poster there).
 */
function iar_video_poster_id( $video_id, $post_id = 0, $only_item = false ) {
	$id = iar_own_thumbnail_id( $video_id );
	if ( ! $id && $only_item && $post_id ) {
		$id = iar_own_thumbnail_id( $post_id );
	}
	return $id;
}

/**
 * Media for a post, in carousel order:
 * [ [ 'id' => 12, 'type' => 'image'|'video', 'src' => url, 'thumb' => url, 'poster' => url, 'poster_id' => id ], … ]
 * A video with no poster has empty 'thumb'/'poster' — templates then show its first frame.
 */
function iar_get_media( $post_id = null ) {
	$post_id = $post_id ?: get_the_ID();
	if ( function_exists( 'iar_is_text_post' ) && iar_is_text_post( $post_id ) ) {
		// A text post's one "slide" is its card (includes/text-posts.php).
		$url = iar_text_svg_url( $post_id );
		return array( array( 'id' => 0, 'type' => 'text', 'src' => $url, 'thumb' => $url, 'poster' => '' ) );
	}
	$ids     = array_values( array_filter( array_map( 'absint', (array) iar_field( 'media', $post_id ) ) ) );
	$out     = array();

	foreach ( $ids as $id ) {
		if ( wp_attachment_is( 'video', $id ) ) {
			$poster_id = iar_video_poster_id( $id, $post_id, 1 === count( $ids ) );
			$out[]     = array(
				'id'        => $id,
				'type'      => 'video',
				'src'       => wp_get_attachment_url( $id ),
				'thumb'     => $poster_id ? (string) wp_get_attachment_image_url( $poster_id, 'medium_large' ) : '',
				'poster'    => $poster_id ? (string) wp_get_attachment_image_url( $poster_id, 'large' ) : '',
				'poster_id' => $poster_id,
			);
		} elseif ( wp_attachment_is_image( $id ) ) {
			$out[] = array(
				'id'    => $id,
				'type'  => 'image',
				'src'   => wp_get_attachment_image_url( $id, 'large' ),
				'thumb' => wp_get_attachment_image_url( $id, 'medium_large' ),
			);
		}
	}

	// No gallery yet (e.g. a hand-made post): fall back to the post's own featured image.
	$own = $out ? 0 : iar_own_thumbnail_id( $post_id );
	if ( $own ) {
		$out[] = array(
			'id'    => $own,
			'type'  => 'image',
			'src'   => wp_get_attachment_image_url( $own, 'large' ),
			'thumb' => wp_get_attachment_image_url( $own, 'medium_large' ),
		);
	}
	return $out;
}

/**
 * Supported aspect ratios (width / height). null = work it out.
 */
function iar_ratios() {
	return apply_filters( 'iar_ratios', array(
		'auto'     => null,
		'1:1'      => 1,
		'4:5'      => 0.8,
		'3:4'      => 0.75,
		'1.91:1'   => 1.91,
		'9:16'     => 0.5625,
		'original' => null,
	) );
}

function iar_ratio_labels() {
	return array(
		'auto'     => __( 'Auto', 'instapress-social' ),
		'1:1'      => __( 'Square 1:1', 'instapress-social' ),
		'4:5'      => __( 'Portrait 4:5', 'instapress-social' ),
		'3:4'      => __( 'Portrait 3:4', 'instapress-social' ),
		'1.91:1'   => __( 'Landscape 1.91:1', 'instapress-social' ),
		'9:16'     => __( 'Vertical 9:16', 'instapress-social' ),
		'original' => __( 'Original', 'instapress-social' ),
	);
}

/** width / height of an attachment, or null if unknown. */
function iar_media_ratio( $attachment_id ) {
	$meta = wp_get_attachment_metadata( $attachment_id );
	if ( empty( $meta['width'] ) || empty( $meta['height'] ) ) {
		return null;
	}
	return $meta['width'] / $meta['height'];
}

/**
 * The frame ratio for a post (width / height):
 * the chosen ratio, or — for Auto — the first item's ratio clamped to
 * 4:5…1.91:1 (videos may go down to 9:16). Original skips the clamp.
 */
function iar_aspect_ratio( $post_id = null ) {
	$post_id = $post_id ?: get_the_ID();
	$choice  = (string) iar_field( 'ratio', $post_id ) ?: 'auto';
	$ratios  = iar_ratios();

	if ( isset( $ratios[ $choice ] ) && $ratios[ $choice ] ) {
		return (float) $ratios[ $choice ];
	}

	$media = iar_get_media( $post_id );
	$first = $media[0] ?? null;
	$ratio = $first ? iar_media_ratio( $first['id'] ) : null;
	if ( ! $ratio && $first && 'video' === $first['type'] && ! empty( $first['poster_id'] ) ) {
		$ratio = iar_media_ratio( $first['poster_id'] );
	}
	if ( ! $ratio ) {
		return 1.0;
	}
	if ( 'original' === $choice ) {
		return round( $ratio, 4 );
	}
	$min = ( $first && 'video' === $first['type'] ) ? 0.5625 : 0.8;
	return round( max( $min, min( 1.91, $ratio ) ), 4 );
}

/** Grid tile ratio from settings (width / height). */
function iar_grid_ratio() {
	$map = array( '1:1' => 1, '3:4' => 0.75, '4:5' => 0.8 );
	return $map[ iar_setting( 'grid_ratio' ) ] ?? 1;
}

/** Caption text, falling back to the title. */
function iar_get_caption( $post_id = null ) {
	$caption = (string) iar_field( 'caption', $post_id );
	if ( '' !== trim( $caption ) ) {
		return $caption;
	}
	// A text post's title is its own words, already on the card.
	return function_exists( 'iar_is_text_post' ) && iar_is_text_post( $post_id ?: get_the_ID() ) ? '' : get_the_title( $post_id );
}

/** Profile URL for a username you know (yourself or someone you follow), keyed lowercase. */
function iar_known_people() {
	static $people = null;
	if ( null === $people ) {
		$people = array( strtolower( (string) iar_setting( 'username' ) ) => iar_profile_url() );
		foreach ( iar_following() as $f ) {
			$people[ strtolower( $f['username'] ) ] = $f['profile'];
		}
	}
	return $people;
}

/** Your profile grid filtered to a hashtag. */
function iar_hashtag_url( $tag ) {
	return add_query_arg( 'hashtag', rawurlencode( $tag ), iar_profile_url() );
}

/**
 * Escaped caption with #hashtags linked to your own archive and @mentions
 * linked to people you know. Use inside an element with white-space: pre-line.
 */
function iar_format_caption( $text ) {
	$html = esc_html( $text );
	$html = preg_replace_callback( '/(^|\s)#([\p{L}\p{N}_]+)/u', function ( $m ) {
		return $m[1] . '<a class="hashtag" href="' . esc_url( iar_hashtag_url( $m[2] ) ) . '">#' . $m[2] . '</a>';
	}, $html );
	$html = preg_replace_callback( '/(^|\s)@([\w.]+)/', function ( $m ) {
		$people = iar_known_people();
		$key    = strtolower( rtrim( $m[2], '.' ) );
		return isset( $people[ $key ] )
			? $m[1] . '<a class="hashtag" href="' . esc_url( $people[ $key ] ) . '">@' . $m[2] . '</a>'
			: $m[0];
	}, $html );
	return $html;
}

function iar_icon( $name ) {
	return '<svg class="icon" aria-hidden="true"><use href="#i-' . esc_attr( $name ) . '"></use></svg>';
}

function iar_count( $n ) {
	return number_format_i18n( (int) $n );
}

/** Locate a template: theme/instapress-social/<file> first, then the plugin's templates/. */
function iar_locate_template( $file ) {
	$theme = locate_template( array( 'instapress-social/' . $file ) );
	return $theme ?: IAR_DIR . 'templates/' . $file;
}

function iar_template_part( $name, $args = array() ) {
	$file = iar_locate_template( 'parts/' . $name . '.php' );
	if ( is_file( $file ) ) {
		include $file; // $args is in scope.
	}
}
