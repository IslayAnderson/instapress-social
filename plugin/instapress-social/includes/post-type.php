<?php
/**
 * instapress-social post type and its ACF field group.
 */

defined( 'ABSPATH' ) || exit;

function iar_register_post_type() {
	register_post_type( IAR_POST_TYPE, array(
		'labels'       => array(
			'name'          => __( 'instapress-social', 'instapress-social' ),
			'singular_name' => __( 'instapress-social post', 'instapress-social' ),
			'add_new_item'  => __( 'Add instapress-social post', 'instapress-social' ),
			'edit_item'     => __( 'Edit instapress-social post', 'instapress-social' ),
			'all_items'     => __( 'All Posts', 'instapress-social' ),
		),
		'public'       => true,
		// Archive at /gram/, posts at /p/<shortcode>/ — change with the filters.
		'has_archive'  => apply_filters( 'iar_archive_slug', 'gram' ),
		'rewrite'      => array( 'slug' => apply_filters( 'iar_post_slug', 'p' ), 'with_front' => false ),
		'menu_icon'    => 'dashicons-camera',
		'supports'     => array( 'title', 'thumbnail', 'custom-fields' ),
		'show_in_rest' => true,
	) );
}
add_action( 'init', 'iar_register_post_type' );

/**
 * One-time move from the old post type key. Posts keep their IDs, slugs,
 * dates, fields and media; menu items pointing at the archive are updated too.
 */
function iar_migrate_post_type() {
	global $wpdb;
	if ( get_option( 'iar_post_type_migrated' ) === IAR_POST_TYPE ) {
		return;
	}
	$moved = $wpdb->update( $wpdb->posts, array( 'post_type' => IAR_POST_TYPE ), array( 'post_type' => IAR_OLD_POST_TYPE ) );
	$wpdb->update( $wpdb->postmeta, array( 'meta_value' => IAR_POST_TYPE ), array( 'meta_key' => '_menu_item_object', 'meta_value' => IAR_OLD_POST_TYPE ) );
	if ( $moved ) {
		wp_cache_flush();
		update_option( 'iar_flush_rewrites', 1 );
	}
	update_option( 'iar_post_type_migrated', IAR_POST_TYPE );
}
add_action( 'init', 'iar_migrate_post_type', 5 );

add_action( 'acf/include_fields', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}
	$f = IAR_FIELDS;

	acf_add_local_field_group( array(
		'key'      => 'group_instapress_social',
		'title'    => 'instapress-social',
		'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => IAR_POST_TYPE ) ) ),
		'position' => 'acf_after_title',
		'fields'   => array(
			array( 'key' => 'field_ig_media', 'name' => $f['media'], 'label' => 'Media', 'type' => 'gallery', 'instructions' => 'Images/videos in carousel order.', 'return_format' => 'array', 'mime_types' => 'jpg,jpeg,png,webp,mp4,mov' ),
			array(
				'key'           => 'field_ig_aspect_ratio',
				'name'          => $f['ratio'],
				'label'         => 'Aspect ratio',
				'type'          => 'radio',
				'layout'        => 'horizontal',
				'instructions'  => 'How the post is framed. Every slide is cropped to this. Auto uses the first image, kept between 4:5 and 1.91:1.',
				'choices'       => iar_ratio_labels(),
				'default_value' => 'auto',
				'allow_null'    => 0,
			),
			array( 'key' => 'field_ig_caption', 'name' => $f['caption'], 'label' => 'Caption', 'type' => 'textarea', 'rows' => 6, 'new_lines' => '' ),
			array( 'key' => 'field_ig_media_type', 'name' => $f['media_type'], 'label' => 'Media type', 'type' => 'select', 'choices' => array( 'image' => 'Image', 'carousel' => 'Carousel', 'video' => 'Video' ), 'wrapper' => array( 'width' => 33 ) ),
			array( 'key' => 'field_ig_posted_at', 'name' => $f['posted_at'], 'label' => 'Posted at', 'type' => 'date_time_picker', 'display_format' => 'j F Y H:i', 'return_format' => 'Y-m-d H:i:s', 'wrapper' => array( 'width' => 33 ) ),
			array( 'key' => 'field_ig_shortcode', 'name' => $f['shortcode'], 'label' => 'Shortcode', 'type' => 'text', 'wrapper' => array( 'width' => 34 ) ),
			array( 'key' => 'field_ig_likes', 'name' => $f['likes'], 'label' => 'Likes on Instagram', 'instructions' => 'Imported count; likes here are added on top.', 'type' => 'number', 'wrapper' => array( 'width' => 33 ) ),
			array( 'key' => 'field_ig_comments', 'name' => $f['comments'], 'label' => 'Comments on Instagram', 'instructions' => 'Imported count; shown as "earlier comments".', 'type' => 'number', 'wrapper' => array( 'width' => 33 ) ),
			array( 'key' => 'field_ig_location', 'name' => $f['location'], 'label' => 'Location', 'type' => 'text', 'wrapper' => array( 'width' => 34 ) ),
			array( 'key' => 'field_ig_url', 'name' => $f['url'], 'label' => 'Original URL', 'instructions' => 'Where the post was first published, if it was imported.', 'type' => 'url' ),
		),
	) );
} );

/*
 * ACF 6.8.7+ treats gallery fields as image-only: its media picker refuses
 * videos and saving a post whose gallery holds a video fails with "File must
 * be a valid image." Our Media gallery is meant to hold photos *and* videos,
 * so opt this one field out (images and videos allowed, nothing else).
 */
add_filter( 'acf/is_image_field', function ( $is_image, $field ) {
	return ( is_array( $field ) && 'field_ig_media' === ( $field['key'] ?? '' ) ) ? false : $is_image;
}, 20, 2 );
add_filter( 'acf/is_image_field/type=gallery', function ( $is_image, $field ) {
	return ( is_array( $field ) && 'field_ig_media' === ( $field['key'] ?? '' ) ) ? false : $is_image;
}, 20, 2 );

add_filter( 'acf/validate_value/key=field_ig_media', function ( $valid, $value, $field ) {
	foreach ( (array) $value as $id ) {
		if ( is_numeric( $id ) && ! wp_attachment_is_image( $id ) && ! wp_attachment_is( 'video', $id ) ) {
			return __( 'Media must be photos or videos.', 'instapress-social' );
		}
	}
	// Replace ACF's image-only verdict; keep any other message (e.g. a minimum count).
	return ( true === $valid || __( 'File must be a valid image.', 'acf' ) === $valid ) ? true : $valid;
}, 20, 3 );

add_action( 'admin_notices', function () {
	if ( function_exists( 'acf_add_local_field_group' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( $screen && in_array( $screen->id, array( 'plugins', IAR_POST_TYPE, 'edit-' . IAR_POST_TYPE ), true ) ) {
		echo '<div class="notice notice-warning"><p><strong>instapress-social</strong> needs <a href="https://wordpress.org/plugins/advanced-custom-fields/">Advanced Custom Fields</a> to edit post fields. Templates and the importer still work without it.</p></div>';
	}
} );
