<?php
/**
 * Stories: ephemeral posts that disappear after a set time (24h by default).
 *
 * - Post type `instapress-story`: one photo or video, optional overlay text,
 *   link and display time. Not publicly queryable — they're only ever shown
 *   in the full-screen story viewer.
 * - Expiry: anything older than the window is hidden immediately on the
 *   front end, and an hourly cron moves it to a private "Expired" status
 *   (the archive) or deletes it, per Settings.
 * - Stories are published in /feed/insta/ as <insta:story> channel elements
 *   (not <item>s, so normal RSS readers ignore them) and read back from
 *   people you follow.
 */

defined( 'ABSPATH' ) || exit;

const IAR_STORY_TYPE   = 'instapress-story';
const IAR_STORY_STATUS = 'iar-expired';
const IAR_STORY_FIELDS = array(
	'media'    => 'story_media',     // file (image or video), attachment ID
	'text'     => 'story_text',      // overlay text
	'link'     => 'story_link',      // optional "link" sticker
	'duration' => 'story_duration',  // seconds an image stays up (videos use their length)
);
const IAR_STORY_CRON = 'iar_expire_stories';

/* -------------------------------------------------------------------------
 * Registration
 * ---------------------------------------------------------------------- */

add_action( 'init', function () {
	register_post_type( IAR_STORY_TYPE, array(
		'labels'             => array(
			'name'          => __( 'Stories', 'instapress-social' ),
			'singular_name' => __( 'Story', 'instapress-social' ),
			'add_new_item'  => __( 'Add story', 'instapress-social' ),
			'edit_item'     => __( 'Edit story', 'instapress-social' ),
			'all_items'     => __( 'Stories', 'instapress-social' ),
			'menu_name'     => __( 'Stories', 'instapress-social' ),
		),
		'public'             => false,
		'show_ui'            => true,
		'show_in_menu'       => 'edit.php?post_type=' . IAR_POST_TYPE,
		'show_in_rest'       => true,
		'publicly_queryable' => false,
		'exclude_from_search'=> true,
		'has_archive'        => false,
		'rewrite'            => false,
		'supports'           => array( 'title', 'thumbnail', 'custom-fields' ),
	) );

	register_post_status( IAR_STORY_STATUS, array(
		'label'                     => _x( 'Expired', 'story status', 'instapress-social' ),
		'public'                    => false,
		'internal'                  => false,
		'protected'                 => true,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		/* translators: %s: count */
		'label_count'               => _n_noop( 'Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'instapress-social' ),
	) );
} );

add_action( 'acf/include_fields', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}
	$f = IAR_STORY_FIELDS;
	acf_add_local_field_group( array(
		'key'      => 'group_instapress_story',
		'title'    => __( 'Story', 'instapress-social' ),
		'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => IAR_STORY_TYPE ) ) ),
		'position' => 'acf_after_title',
		'fields'   => array(
			array(
				'key'           => 'field_story_media',
				'name'          => $f['media'],
				'label'         => __( 'Photo or video', 'instapress-social' ),
				'type'          => 'file',
				'return_format' => 'id',
				'library'       => 'all',
				'mime_types'    => 'jpg,jpeg,png,webp,gif,mp4,mov,m4v,webm',
				'required'      => 1,
				'instructions'  => __( 'Shown full-screen at 9:16 — tall photos and videos fill it best.', 'instapress-social' ),
			),
			array(
				'key'          => 'field_story_text',
				'name'         => $f['text'],
				'label'        => __( 'Text', 'instapress-social' ),
				'type'         => 'text',
				'maxlength'    => 140,
				'instructions' => __( 'Optional, shown over the middle of the story.', 'instapress-social' ),
			),
			array(
				'key'          => 'field_story_link',
				'name'         => $f['link'],
				'label'        => __( 'Link', 'instapress-social' ),
				'type'         => 'url',
				'instructions' => __( 'Optional — adds a link button at the bottom.', 'instapress-social' ),
				'wrapper'      => array( 'width' => 70 ),
			),
			array(
				'key'           => 'field_story_duration',
				'name'          => $f['duration'],
				'label'         => __( 'Seconds on screen', 'instapress-social' ),
				'type'          => 'number',
				'default_value' => 5,
				'min'           => 2,
				'max'           => 15,
				'instructions'  => __( 'Photos only; videos play for their length (max 60s).', 'instapress-social' ),
				'wrapper'       => array( 'width' => 30 ),
			),
		),
	) );
} );

// Stories rarely need a title — default one so the admin list stays readable.
add_filter( 'wp_insert_post_data', function ( $data ) {
	if ( IAR_STORY_TYPE === $data['post_type'] && '' === trim( (string) $data['post_title'] ) && 'auto-draft' !== $data['post_status'] ) {
		$data['post_title'] = sprintf( __( 'Story — %s', 'instapress-social' ), wp_date( 'j M Y H:i' ) );
	}
	return $data;
} );

/* -------------------------------------------------------------------------
 * Expiry
 * ---------------------------------------------------------------------- */

function iar_story_lifetime() {
	return max( 1, (int) iar_setting( 'story_hours' ) ) * HOUR_IN_SECONDS;
}

/** Unix time a story stops showing. */
function iar_story_expires( $post ) {
	return (int) get_post_time( 'U', true, $post ) + iar_story_lifetime();
}

/** Published stories still inside the window, oldest first (viewing order). */
function iar_active_stories() {
	return get_posts( array(
		'post_type'      => IAR_STORY_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => 50,
		'orderby'        => 'date',
		'order'          => 'ASC',
		'date_query'     => array( array( 'after' => gmdate( 'Y-m-d H:i:s', time() - iar_story_lifetime() ), 'column' => 'post_date_gmt' ) ),
	) );
}

/** Archive (or delete) stories past their window. Runs hourly. */
function iar_expire_stories() {
	$expired = get_posts( array(
		'post_type'      => IAR_STORY_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => 100,
		'fields'         => 'ids',
		'date_query'     => array( array( 'before' => gmdate( 'Y-m-d H:i:s', time() - iar_story_lifetime() ), 'column' => 'post_date_gmt' ) ),
	) );
	foreach ( $expired as $id ) {
		if ( 'delete' === iar_setting( 'story_after' ) ) {
			wp_delete_post( $id, true ); // the media stays in the library
		} else {
			wp_update_post( array( 'ID' => $id, 'post_status' => IAR_STORY_STATUS ) );
		}
	}
	return count( $expired );
}
add_action( IAR_STORY_CRON, 'iar_expire_stories' );

add_action( 'init', function () {
	if ( ! wp_next_scheduled( IAR_STORY_CRON ) ) {
		wp_schedule_event( time() + 600, 'hourly', IAR_STORY_CRON );
	}
} );

/* -------------------------------------------------------------------------
 * Data for the viewer and the feed
 * ---------------------------------------------------------------------- */

/** A story in the shape the viewer and feed use, or null if it has no media. */
function iar_story_to_item( $post ) {
	$post  = get_post( $post );
	$media = (int) get_post_meta( $post->ID, IAR_STORY_FIELDS['media'], true );
	if ( ! $media ) {
		return null;
	}
	$video  = wp_attachment_is( 'video', $media );
	$src    = $video ? wp_get_attachment_url( $media ) : wp_get_attachment_image_url( $media, 'large' );
	$poster = $video ? ( get_the_post_thumbnail_url( $post, 'large' ) ?: '' ) : '';
	if ( ! $src ) {
		return null;
	}
	return array(
		'id'       => 'story-' . $post->ID . '@' . wp_parse_url( home_url(), PHP_URL_HOST ),
		'type'     => $video ? 'video' : 'image',
		'src'      => $src,
		'poster'   => $poster,
		'text'     => (string) get_post_meta( $post->ID, IAR_STORY_FIELDS['text'], true ),
		'link'     => esc_url_raw( (string) get_post_meta( $post->ID, IAR_STORY_FIELDS['link'], true ) ),
		'duration' => max( 2, min( 15, (int) get_post_meta( $post->ID, IAR_STORY_FIELDS['duration'], true ) ?: 5 ) ),
		'date'     => get_post_time( 'c', true, $post ),
		'expires'  => gmdate( 'c', iar_story_expires( $post ) ),
	);
}

function iar_own_story_items() {
	return array_values( array_filter( array_map( 'iar_story_to_item', iar_active_stories() ) ) );
}

/** A followed account's stories that haven't expired yet. */
function iar_follow_story_items( $profile ) {
	$now = time();
	return array_values( array_filter( (array) ( $profile['stories'] ?? array() ), function ( $s ) use ( $now ) {
		return ! empty( $s['src'] ) && strtotime( $s['expires'] ?? '' ) > $now;
	} ) );
}

/**
 * Story groups for the viewer: [ key => { user: {...}, stories: [...] } ].
 * "me" first, then followed accounts that have stories.
 */
function iar_story_groups( $include_following = true ) {
	$me     = iar_own_profile();
	$groups = array();
	$own    = iar_own_story_items();
	if ( $own ) {
		$groups['me'] = array(
			'user'    => array( 'username' => $me['username'], 'avatar' => $me['avatar'], 'profile' => $me['profile'] ),
			'stories' => $own,
		);
	}
	if ( $include_following ) {
		foreach ( iar_following() as $id => $f ) {
			$items = iar_follow_story_items( $f );
			if ( $items ) {
				$groups[ $id ] = array(
					'user'    => array( 'username' => $f['username'], 'avatar' => $f['avatar'], 'profile' => $f['profile'] ),
					'stories' => $items,
				);
			}
		}
	}
	return $groups;
}

/** Feed output: <insta:story> elements inside <channel>. */
function iar_feed_story_elements() {
	foreach ( iar_own_story_items() as $s ) {
		printf(
			"\t<insta:story id=\"%s\" type=\"%s\" url=\"%s\" poster=\"%s\" link=\"%s\" duration=\"%d\" date=\"%s\" expires=\"%s\">%s</insta:story>\n",
			esc_xml( $s['id'] ),
			esc_xml( $s['type'] ),
			esc_xml( $s['src'] ),
			esc_xml( $s['poster'] ),
			esc_xml( $s['link'] ),
			(int) $s['duration'],
			esc_xml( $s['date'] ),
			esc_xml( $s['expires'] ),
			esc_xml( $s['text'] )
		);
	}
}

/** Feed input: read <insta:story> elements from a followed account's channel. */
function iar_parse_feed_stories( $ns ) {
	$out = array();
	foreach ( $ns->story as $el ) {
		$a   = $el->attributes();
		$src = iar_clean_url( (string) $a->url );
		$exp = strtotime( (string) $a->expires );
		if ( ! $src || ! $exp || $exp <= time() ) {
			continue;
		}
		$out[] = array(
			'id'       => sanitize_text_field( (string) $a->id ) ?: $src,
			'type'     => 'video' === (string) $a->type ? 'video' : 'image',
			'src'      => $src,
			'poster'   => iar_clean_url( (string) $a->poster ),
			'text'     => sanitize_text_field( (string) $el ),
			'link'     => iar_clean_url( (string) $a->link ),
			'duration' => max( 2, min( 15, (int) $a->duration ?: 5 ) ),
			'date'     => gmdate( 'c', strtotime( (string) $a->date ) ?: time() ),
			'expires'  => gmdate( 'c', $exp ),
		);
		if ( count( $out ) >= 50 ) {
			break;
		}
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * Admin list: show the media and time left
 * ---------------------------------------------------------------------- */

add_filter( 'manage_' . IAR_STORY_TYPE . '_posts_columns', function ( $cols ) {
	$new = array();
	foreach ( $cols as $k => $v ) {
		if ( 'title' === $k ) {
			$new['iar_story_media'] = '';
		}
		$new[ $k ] = $v;
		if ( 'title' === $k ) {
			$new['iar_story_left'] = __( 'Visible', 'instapress-social' );
		}
	}
	return $new;
} );

add_action( 'manage_' . IAR_STORY_TYPE . '_posts_custom_column', function ( $col, $post_id ) {
	if ( 'iar_story_media' === $col ) {
		$media = (int) get_post_meta( $post_id, IAR_STORY_FIELDS['media'], true );
		if ( $media && wp_attachment_is_image( $media ) ) {
			echo wp_get_attachment_image( $media, array( 48, 48 ), false, array( 'style' => 'width:36px;height:64px;object-fit:cover;border-radius:4px;' ) );
		} elseif ( $media ) {
			echo '<span class="dashicons dashicons-video-alt3" style="font-size:28px;width:36px;height:36px;"></span>';
		}
	}
	if ( 'iar_story_left' === $col ) {
		$status = get_post_status( $post_id );
		$left   = iar_story_expires( $post_id ) - time();
		if ( 'publish' === $status && $left > 0 ) {
			/* translators: %s: time remaining */
			echo esc_html( sprintf( __( 'for %s more', 'instapress-social' ), human_time_diff( time(), time() + $left ) ) );
		} elseif ( 'publish' === $status || IAR_STORY_STATUS === $status ) {
			echo '<span style="color:#787c82;">' . esc_html__( 'Expired', 'instapress-social' ) . '</span>';
		} else {
			echo '—';
		}
	}
}, 10, 2 );

add_action( 'admin_head', function () {
	$screen = get_current_screen();
	if ( $screen && 'edit-' . IAR_STORY_TYPE === $screen->id ) {
		echo '<style>.column-iar_story_media{width:44px}.column-iar_story_left{width:140px}</style>';
	}
} );
