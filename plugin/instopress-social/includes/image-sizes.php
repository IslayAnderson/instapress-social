<?php
/**
 * Grid tile image size.
 *
 * A tile is a third of the screen on phones (~130–140px), so even at 3x it needs
 * under 480px, but WordPress's next size above 300px is 768px. One uncropped
 * 480px-wide size fills that gap in the tiles' srcset (templates/parts/grid-item.php).
 *
 * New uploads get it from WordPress as usual. Pictures already used by posts get it
 * in the background, a few posts per cron run, or all at once with `wp insto tiles`.
 */

defined( 'ABSPATH' ) || exit;

const IAR_TILE_SIZE  = 'iar_tile';
const IAR_TILE_WIDTH = 480;
const IAR_TILE_CRON  = 'iar_tile_backfill';
// Bump to backfill again (e.g. if the width changes).
const IAR_TILE_VER   = '480';

add_action( 'after_setup_theme', function () {
	add_image_size( IAR_TILE_SIZE, IAR_TILE_WIDTH, 0, false );
} );

/** Adds the tile size to one image if it's missing and the image is wider. True if a file was made. */
function iar_tile_add( $id ) {
	$meta = wp_get_attachment_metadata( $id );
	if ( ! wp_attachment_is_image( $id ) || ! is_array( $meta ) || empty( $meta['width'] ) || ! empty( $meta['sizes'][ IAR_TILE_SIZE ] ) || $meta['width'] <= IAR_TILE_WIDTH ) {
		return false;
	}
	$file = get_attached_file( $id );
	if ( ! $file || ! file_exists( $file ) ) {
		return false;
	}
	$editor = wp_get_image_editor( $file );
	if ( is_wp_error( $editor ) ) {
		return false;
	}
	$size = $editor->make_subsize( array( 'width' => IAR_TILE_WIDTH, 'height' => 0, 'crop' => false ) );
	if ( is_wp_error( $size ) ) {
		return false;
	}
	$meta['sizes'][ IAR_TILE_SIZE ] = $size;
	wp_update_attachment_metadata( $id, $meta );
	return true;
}

/** The images a post's tile can show: its pictures and its videos' posters. */
function iar_tile_image_ids( $post_id ) {
	$ids = array();
	foreach ( (array) iar_get_media( $post_id ) as $slide ) {
		$ids[] = 'video' === $slide['type'] ? ( $slide['poster_id'] ?? 0 ) : ( 'image' === $slide['type'] ? $slide['id'] : 0 );
	}
	return array_filter( array_map( 'absint', $ids ) );
}

/**
 * Adds the tile size to the images of up to $limit posts after post ID $after.
 * Returns the last post ID looked at, or 0 when there are none left.
 */
function iar_tile_backfill_batch( $after, $limit, &$made = 0 ) {
	global $wpdb;
	$posts = $wpdb->get_col( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('auto-draft', 'trash') AND ID > %d ORDER BY ID ASC LIMIT %d",
		IAR_POST_TYPE,
		$after,
		$limit
	) );
	foreach ( $posts as $post_id ) {
		foreach ( iar_tile_image_ids( (int) $post_id ) as $id ) {
			$made += iar_tile_add( $id ) ? 1 : 0;
		}
	}
	return $posts ? (int) end( $posts ) : 0;
}

// Background backfill: 3 posts a run (a carousel can hold 20 pictures, and resizing is slow
// on small hosts), a minute apart, until every post has been seen.
add_action( IAR_TILE_CRON, function () {
	$state = (array) get_option( IAR_TILE_CRON, array() );
	$last  = iar_tile_backfill_batch( (int) ( $state['after'] ?? 0 ), 3 );
	update_option( IAR_TILE_CRON, $last ? array( 'ver' => IAR_TILE_VER, 'after' => $last ) : array( 'ver' => IAR_TILE_VER, 'done' => true ), false );
	if ( $last ) {
		wp_schedule_single_event( time() + 60, IAR_TILE_CRON );
	}
} );

add_action( 'init', function () {
	$state = (array) get_option( IAR_TILE_CRON, array() );
	if ( ( $state['ver'] ?? '' ) === IAR_TILE_VER && ! empty( $state['done'] ) ) {
		return;
	}
	if ( ( $state['ver'] ?? '' ) !== IAR_TILE_VER ) {
		update_option( IAR_TILE_CRON, array( 'ver' => IAR_TILE_VER, 'after' => 0 ), false );
	}
	if ( ! wp_next_scheduled( IAR_TILE_CRON ) ) {
		wp_schedule_single_event( time() + 60, IAR_TILE_CRON );
	}
} );
