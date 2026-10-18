<?php
/**
 * Text posts: short rich-text posts shown as a card, drawn as SVG (vector,
 * real text) rather than a baked-in image.
 *
 * A text post is an ordinary instopress-social post (same permalinks, grid,
 * likes, comments, feeds) whose media type is "text". Its words and look live
 * in the IAR_TEXT_FIELDS meta; iar_text_svg() draws it:
 *
 *   <svg viewBox="0 0 1080 H">  background rect (solid or gradient)
 *     <switch> <foreignObject> formatted HTML </foreignObject>
 *              <text> plain fallback for renderers without HTML support </text>
 *
 * Inline on this site's pages; as a standalone .svg file (?iar_text_svg=ID)
 * for the RSS feed and sites that follow you.
 */

defined( 'ABSPATH' ) || exit;

const IAR_TEXT_FIELDS = array(
	'text'     => 'ig_text',          // rich text (sanitised HTML)
	'scale'    => 'ig_text_scale',    // percent, 50–200
	'font'     => 'ig_text_font',     // key of iar_text_fonts()
	'align'    => 'ig_text_align',    // left | center | right
	'color'    => 'ig_text_color',    // #hex, or '' for automatic
	'bg'       => 'ig_text_bg',       // key of iar_text_backgrounds(), or 'custom'
	'bg_color' => 'ig_text_bg_color', // #hex when bg is 'custom'
);

/* -------------------------------------------------------------------------
 * Presets
 * ---------------------------------------------------------------------- */

/** Backgrounds: solid colours and gradients. 'ink' is the text colour that reads best on it. */
function iar_text_backgrounds() {
	return apply_filters( 'iar_text_backgrounds', array(
		'white'  => array( 'label' => __( 'White', 'instopress-social' ), 'colors' => array( '#ffffff' ), 'ink' => '#111111' ),
		'black'  => array( 'label' => __( 'Black', 'instopress-social' ), 'colors' => array( '#000000' ), 'ink' => '#ffffff' ),
		'cream'  => array( 'label' => __( 'Cream', 'instopress-social' ), 'colors' => array( '#f6efe3' ), 'ink' => '#2b2118' ),
		'red'    => array( 'label' => __( 'Red', 'instopress-social' ), 'colors' => array( '#e63946' ), 'ink' => '#ffffff' ),
		'orange' => array( 'label' => __( 'Orange', 'instopress-social' ), 'colors' => array( '#f77f00' ), 'ink' => '#ffffff' ),
		'yellow' => array( 'label' => __( 'Yellow', 'instopress-social' ), 'colors' => array( '#ffd60a' ), 'ink' => '#111111' ),
		'green'  => array( 'label' => __( 'Green', 'instopress-social' ), 'colors' => array( '#2a9d8f' ), 'ink' => '#ffffff' ),
		'blue'   => array( 'label' => __( 'Blue', 'instopress-social' ), 'colors' => array( '#3a86ff' ), 'ink' => '#ffffff' ),
		'purple' => array( 'label' => __( 'Purple', 'instopress-social' ), 'colors' => array( '#7b2cbf' ), 'ink' => '#ffffff' ),
		'pink'   => array( 'label' => __( 'Pink', 'instopress-social' ), 'colors' => array( '#ff70a6' ), 'ink' => '#ffffff' ),
		'sunset' => array( 'label' => __( 'Sunset', 'instopress-social' ), 'colors' => array( '#feda75', '#fa7e1e', '#d62976', '#962fbf' ), 'dir' => 'diagonal', 'ink' => '#ffffff' ),
		'ocean'  => array( 'label' => __( 'Ocean', 'instopress-social' ), 'colors' => array( '#00c6ff', '#0072ff' ), 'dir' => 'diagonal', 'ink' => '#ffffff' ),
		'mint'   => array( 'label' => __( 'Mint', 'instopress-social' ), 'colors' => array( '#43e97b', '#38f9d7' ), 'dir' => 'diagonal', 'ink' => '#0b3b2e' ),
		'peach'  => array( 'label' => __( 'Peach', 'instopress-social' ), 'colors' => array( '#ffecd2', '#fcb69f' ), 'dir' => 'vertical', 'ink' => '#4a2511' ),
		'candy'  => array( 'label' => __( 'Candy', 'instopress-social' ), 'colors' => array( '#ff9a9e', '#fad0c4', '#fbc2eb' ), 'dir' => 'diagonal', 'ink' => '#4a1630' ),
		'fire'   => array( 'label' => __( 'Fire', 'instopress-social' ), 'colors' => array( '#f12711', '#f5af19' ), 'dir' => 'vertical', 'ink' => '#ffffff' ),
		'dusk'   => array( 'label' => __( 'Dusk', 'instopress-social' ), 'colors' => array( '#2c3e50', '#4ca1af' ), 'dir' => 'vertical', 'ink' => '#ffffff' ),
		'night'  => array( 'label' => __( 'Night', 'instopress-social' ), 'colors' => array( '#0f2027', '#203a43', '#2c5364' ), 'dir' => 'diagonal', 'ink' => '#ffffff' ),
		'aurora' => array( 'label' => __( 'Aurora', 'instopress-social' ), 'colors' => array( '#a8edea', '#fed6e3' ), 'dir' => 'diagonal', 'ink' => '#1d2b3a' ),
		'lilac'  => array( 'label' => __( 'Lilac', 'instopress-social' ), 'colors' => array( '#c471f5', '#fa71cd' ), 'dir' => 'horizontal', 'ink' => '#ffffff' ),
	) );
}

/** Preset text colours (any other colour can be picked too). */
function iar_text_inks() {
	return apply_filters( 'iar_text_inks', array( '#ffffff', '#111111', '#f6efe3', '#ffd60a', '#ff70a6', '#4cc9f0', '#e63946', '#80ed99' ) );
}

function iar_text_fonts() {
	return apply_filters( 'iar_text_fonts', array(
		'sans'  => array( 'label' => __( 'Classic', 'instopress-social' ), 'stack' => 'system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif', 'weight' => 600 ),
		'serif' => array( 'label' => __( 'Serif', 'instopress-social' ), 'stack' => 'Georgia, "Times New Roman", Times, serif', 'weight' => 400 ),
		'mono'  => array( 'label' => __( 'Typewriter', 'instopress-social' ), 'stack' => 'ui-monospace, "SF Mono", Menlo, Consolas, "Courier New", monospace', 'weight' => 500 ),
		'bold'  => array( 'label' => __( 'Strong', 'instopress-social' ), 'stack' => '"Arial Black", "Helvetica Neue", Impact, system-ui, sans-serif', 'weight' => 900 ),
	) );
}

/** Everything the composer needs to draw the same card in the browser. */
function iar_text_presets() {
	return array(
		'backgrounds' => iar_text_backgrounds(),
		'inks'        => iar_text_inks(),
		'fonts'       => iar_text_fonts(),
	);
}

/* -------------------------------------------------------------------------
 * Data
 * ---------------------------------------------------------------------- */

function iar_is_text_post( $post_id = null ) {
	$post_id = $post_id ?: get_the_ID();
	return 'text' === get_post_meta( $post_id, IAR_FIELDS['media_type'], true );
}

function iar_text_hex( $value ) {
	$value = strtolower( trim( (string) $value ) );
	return preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $value ) ? $value : '';
}

/** Clean up a look: unknown values fall back to defaults. */
function iar_text_style_normalize( $raw ) {
	$raw   = (array) $raw;
	$bgs   = iar_text_backgrounds();
	$fonts = iar_text_fonts();
	$bg    = (string) ( $raw['bg'] ?? '' );
	$style = array(
		'scale'    => max( 50, min( 200, (int) ( $raw['scale'] ?? 100 ) ?: 100 ) ),
		'font'     => isset( $fonts[ $raw['font'] ?? '' ] ) ? $raw['font'] : 'sans',
		'align'    => in_array( $raw['align'] ?? '', array( 'left', 'center', 'right' ), true ) ? $raw['align'] : 'center',
		'color'    => iar_text_hex( $raw['color'] ?? '' ),
		'bg'       => ( 'custom' === $bg || isset( $bgs[ $bg ] ) ) ? $bg : 'sunset',
		'bg_color' => iar_text_hex( $raw['bg_color'] ?? '' ),
	);
	if ( 'custom' === $style['bg'] && ! $style['bg_color'] ) {
		$style['bg'] = 'sunset';
	}
	return $style;
}

function iar_text_style( $post_id ) {
	$raw = array();
	foreach ( array( 'scale', 'font', 'align', 'color', 'bg', 'bg_color' ) as $k ) {
		$raw[ $k ] = get_post_meta( $post_id, IAR_TEXT_FIELDS[ $k ], true );
	}
	return iar_text_style_normalize( $raw );
}

/** Black or white, whichever reads better on a colour. */
function iar_text_contrast( $hex ) {
	$hex = ltrim( iar_text_hex( $hex ) ?: '#000000', '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	list( $r, $g, $b ) = array_map( 'hexdec', str_split( $hex, 2 ) );
	return ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) > 160 ? '#111111' : '#ffffff';
}

/**
 * Rich text in, safe XHTML out (it lives inside SVG, which is XML).
 * Bold, italic, underline, strike, headings, quotes, lists and links.
 */
function iar_text_sanitize( $html ) {
	$allowed = array(
		'p' => array(), 'div' => array(), 'br' => array(),
		'strong' => array(), 'b' => array(), 'em' => array(), 'i' => array(),
		'u' => array(), 's' => array(), 'del' => array(), 'strike' => array(),
		'h2' => array(), 'h3' => array(), 'blockquote' => array(),
		'ul' => array(), 'ol' => array(), 'li' => array(),
		'a' => array( 'href' => true ),
	);
	$html = preg_replace( '#<(script|style|template)\b[^>]*>.*?</\1>#is', '', (string) $html );
	$html = wp_kses( $html, $allowed, array( 'http', 'https', 'mailto' ) );
	$html = trim( str_replace( "\r", '', $html ) );
	if ( '' === $html ) {
		return '';
	}
	// WordPress's editor saves paragraphs as blank lines; turn loose lines back into paragraphs.
	$html = wpautop( $html );

	$prev = libxml_use_internal_errors( true );
	$doc  = new DOMDocument();
	$doc->loadHTML( '<?xml encoding="utf-8"?><div id="iar-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );
	$root = $doc->getElementById( 'iar-root' );
	if ( ! $root ) {
		return '';
	}
	$out = '';
	foreach ( iterator_to_array( $root->childNodes ) as $node ) {
		$out .= $doc->saveXML( $node );
	}
	// Drop empty paragraphs the editor leaves behind.
	$out = preg_replace( '#<(p|div)>(\s|<br\s*/>|&\#160;|\xc2\xa0)*</\1>#u', '', $out );
	return trim( $out );
}

function iar_text_plain( $post_id ) {
	$html = (string) get_post_meta( $post_id, IAR_TEXT_FIELDS['text'], true );
	$text = wp_strip_all_tags( preg_replace( '#</(p|div|h2|h3|blockquote|li)>|<br\s*/?>#i', "\n", $html ) );
	return trim( preg_replace( "/\n{2,}/", "\n", html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) );
}

/** Font size (in card pixels, card is 1080 wide) for this much text — shorter text, bigger letters. */
function iar_text_base_size( $length, $height ) {
	$steps = array( 30 => 104, 80 => 84, 160 => 68, 300 => 54, 500 => 44 );
	$size  = 36;
	foreach ( $steps as $max => $px ) {
		if ( $length <= $max ) {
			$size = $px;
			break;
		}
	}
	// Taller cards have room for slightly bigger text.
	return $size * max( 0.85, min( 1.3, sqrt( $height / 1080 ) ) );
}

/* -------------------------------------------------------------------------
 * Drawing
 * ---------------------------------------------------------------------- */

/**
 * The card as SVG markup.
 *
 * @param int    $post_id
 * @param string $context 'full' (post page: links work), 'tile' (inside a
 *                        link, e.g. the grid), or 'file' (a standalone .svg).
 */
function iar_text_svg( $post_id, $context = 'full' ) {
	$style = iar_text_style( $post_id );
	$html  = iar_text_sanitize( get_post_meta( $post_id, IAR_TEXT_FIELDS['text'], true ) );
	$plain = iar_text_plain( $post_id );
	return iar_text_render_svg( $html, $plain, $style, iar_aspect_ratio( $post_id ), $context, 'iar-tg-' . $post_id );
}

function iar_text_render_svg( $html, $plain, $style, $ratio, $context = 'full', $id_base = 'iar-tg' ) {
	$bgs    = iar_text_backgrounds();
	$fonts  = iar_text_fonts();
	$w      = 1080;
	$h      = (int) round( $w / max( 0.5, min( 2, (float) $ratio ?: 1 ) ) );
	$font   = $fonts[ $style['font'] ];
	$custom = 'custom' === $style['bg'];
	$bg     = $custom ? array( 'colors' => array( $style['bg_color'] ), 'ink' => iar_text_contrast( $style['bg_color'] ) ) : $bgs[ $style['bg'] ];
	$ink    = $style['color'] ?: $bg['ink'];
	$size   = round( iar_text_base_size( mb_strlen( $plain ), $h ) * $style['scale'] / 100, 1 );
	$pad    = 96;
	$id     = $id_base . '-' . wp_rand( 1000, 999999 );

	if ( 'full' !== $context ) {
		// Links can't sit inside the grid's link, and do nothing in an image.
		$html = preg_replace( array( '#<a\b[^>]*>#i', '#</a>#i' ), array( '<span class="iar-tx-a">', '</span>' ), $html );
	}

	// Background: one colour, or a gradient.
	$colors = $bg['colors'];
	if ( count( $colors ) > 1 ) {
		$dirs  = array( 'diagonal' => 'x1="0" y1="0" x2="1" y2="1"', 'vertical' => 'x1="0" y1="0" x2="0" y2="1"', 'horizontal' => 'x1="0" y1="0" x2="1" y2="0"' );
		$stops = '';
		foreach ( $colors as $i => $c ) {
			$stops .= '<stop offset="' . round( $i / ( count( $colors ) - 1 ), 3 ) . '" stop-color="' . esc_attr( $c ) . '"/>';
		}
		$defs = '<defs><linearGradient id="' . esc_attr( $id ) . '" ' . ( $dirs[ $bg['dir'] ?? 'diagonal' ] ?? $dirs['diagonal'] ) . '>' . $stops . '</linearGradient></defs>';
		$fill = 'url(#' . esc_attr( $id ) . ')';
	} else {
		$defs = '';
		$fill = esc_attr( $colors[0] );
	}

	// Every rule is scoped to this card (several can share a page) and specific
	// enough to beat the site's own styles for p, h2 and so on.
	$c   = '.iar-tx.' . $id;
	$css = $c . '{box-sizing:border-box;width:' . $w . 'px;height:' . $h . 'px;padding:' . $pad . 'px;display:flex;flex-direction:column;justify-content:center;overflow:hidden;'
		. 'color:' . $ink . ';font-family:' . $font['stack'] . ';font-weight:' . (int) $font['weight'] . ';font-size:' . $size . 'px;line-height:1.3;text-align:' . $style['align'] . ';overflow-wrap:break-word;word-wrap:break-word}'
		. $c . ' *{font-family:inherit;color:inherit;letter-spacing:normal;text-transform:none;font-size:inherit;line-height:inherit;background:none}'
		. $c . '>*{margin:0 0 .45em}' . $c . '>*:last-child{margin-bottom:0}'
		. $c . ' h2{font-size:1.45em;line-height:1.12;font-weight:800}' . $c . ' h3{font-size:1.2em;line-height:1.15;font-weight:800}'
		. $c . ' strong,' . $c . ' b{font-weight:800}' . $c . ' a,' . $c . ' .iar-tx-a{color:inherit;text-decoration:underline}'
		. $c . ' blockquote{border-' . ( 'right' === $style['align'] ? 'right' : 'left' ) . ':.12em solid currentColor;padding:0 .6em;font-style:italic}'
		. $c . ' ul,' . $c . ' ol{padding-left:1.2em;text-align:left}' . $c . ' li{margin:.1em 0}';

	// For renderers that can't show HTML inside SVG: the first line, plainly.
	$fallback = wp_html_excerpt( strtok( $plain, "\n" ) ?: $plain, 60, '…' );

	$label = wp_html_excerpt( preg_replace( '/\s+/', ' ', $plain ), 200, '…' );
	$attrs = 'xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $h . '" class="iar-text-card" preserveAspectRatio="xMidYMid meet"';
	if ( 'full' !== $context ) {
		$attrs .= ' role="img" aria-label="' . esc_attr( $label ) . '"';
	}
	if ( 'tile' === $context ) {
		// Fill the grid's tile shape the way photos do (cropping the edges).
		$attrs = str_replace( 'xMidYMid meet', 'xMidYMid slice', $attrs );
	}

	$svg = '<svg ' . $attrs . '>'
		. ( 'file' === $context ? '<title>' . esc_html( $label ) . '</title>' : '' )
		. $defs
		. '<rect width="' . $w . '" height="' . $h . '" fill="' . $fill . '"/>'
		. '<switch>'
		. '<g requiredFeatures="http://www.w3.org/TR/SVG11/feature#Extensibility">'
		. '<foreignObject x="0" y="0" width="' . $w . '" height="' . $h . '">'
		. '<div xmlns="http://www.w3.org/1999/xhtml" class="iar-tx ' . esc_attr( $id ) . '"><style>' . $css . '</style>' . $html . '</div>'
		. '</foreignObject></g>'
		. '<text x="' . ( $w / 2 ) . '" y="' . ( $h / 2 ) . '" text-anchor="middle" dominant-baseline="middle" fill="' . esc_attr( $ink ) . '" font-family="' . esc_attr( $font['stack'] ) . '" font-size="' . min( 72, $size ) . '">' . esc_html( $fallback ) . '</text>'
		. '</switch>'
		. '</svg>';

	return 'file' === $context ? '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $svg : $svg;
}

/** Address of the standalone .svg (versioned, so it can be cached for long). */
function iar_text_svg_url( $post_id ) {
	return add_query_arg(
		array(
			'iar_text_svg' => (int) $post_id,
			'v'            => (int) get_post_modified_time( 'U', true, $post_id ),
		),
		home_url( '/' )
	);
}

add_action( 'template_redirect', function () {
	if ( empty( $_GET['iar_text_svg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$id = absint( $_GET['iar_text_svg'] ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( IAR_POST_TYPE !== get_post_type( $id ) || ! iar_is_text_post( $id ) || ( 'publish' !== get_post_status( $id ) && ! current_user_can( 'edit_post', $id ) ) ) {
		status_header( 404 );
		exit;
	}
	header( 'Content-Type: image/svg+xml; charset=utf-8' );
	header( 'X-Content-Type-Options: nosniff' );
	// Opened directly, it's a document: nothing in it may run or load.
	header( "Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox" );
	header( 'Cache-Control: public, max-age=86400' );
	echo iar_text_svg( $id, 'file' ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped/sanitised parts
	exit;
}, 1 );

/* -------------------------------------------------------------------------
 * Saving
 * ---------------------------------------------------------------------- */

/** Store a text post's words and look (from the composer or the admin). */
function iar_text_save( $post_id, $html, $style ) {
	$style = iar_text_style_normalize( $style );
	update_post_meta( $post_id, IAR_TEXT_FIELDS['text'], iar_text_sanitize( $html ) );
	foreach ( $style as $k => $v ) {
		update_post_meta( $post_id, IAR_TEXT_FIELDS[ $k ], $v );
	}
	foreach ( IAR_TEXT_FIELDS as $k => $meta ) {
		update_post_meta( $post_id, '_' . $meta, 'field_' . $meta );
	}
	update_post_meta( $post_id, IAR_FIELDS['media_type'], 'text' );
	update_post_meta( $post_id, '_' . IAR_FIELDS['media_type'], 'field_' . IAR_FIELDS['media_type'] );
}

// Admin edits go through ACF's wysiwyg; keep the stored HTML to what the card can show.
add_filter( 'acf/update_value/name=' . IAR_TEXT_FIELDS['text'], function ( $value ) {
	return iar_text_sanitize( $value );
} );

/* -------------------------------------------------------------------------
 * Admin fields (shown when Media type is "Text")
 * ---------------------------------------------------------------------- */

add_action( 'acf/include_fields', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}
	$t    = IAR_TEXT_FIELDS;
	$show = array( array( array( 'field' => 'field_ig_media_type', 'operator' => '==', 'value' => 'text' ) ) );
	$bgs  = array();
	foreach ( iar_text_backgrounds() as $key => $bg ) {
		$bgs[ $key ] = $bg['label'];
	}
	$bgs['custom'] = __( 'Custom colour', 'instopress-social' );
	$fonts         = array();
	foreach ( iar_text_fonts() as $key => $font ) {
		$fonts[ $key ] = $font['label'];
	}

	acf_add_local_field_group( array(
		'key'      => 'group_instopress_social_text',
		'title'    => __( 'Text post', 'instopress-social' ),
		'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => IAR_POST_TYPE ) ) ),
		'position' => 'acf_after_title',
		'menu_order' => 1,
		'fields'   => array(
			array( 'key' => 'field_' . $t['text'], 'name' => $t['text'], 'label' => __( 'Text', 'instopress-social' ), 'type' => 'wysiwyg', 'toolbar' => 'basic', 'media_upload' => 0, 'tabs' => 'visual', 'conditional_logic' => $show ),
			array( 'key' => 'field_' . $t['bg'], 'name' => $t['bg'], 'label' => __( 'Background', 'instopress-social' ), 'type' => 'select', 'choices' => $bgs, 'default_value' => 'sunset', 'conditional_logic' => $show, 'wrapper' => array( 'width' => 34 ) ),
			array( 'key' => 'field_' . $t['bg_color'], 'name' => $t['bg_color'], 'label' => __( 'Background colour', 'instopress-social' ), 'type' => 'color_picker', 'conditional_logic' => array( array( array( 'field' => 'field_ig_media_type', 'operator' => '==', 'value' => 'text' ), array( 'field' => 'field_' . $t['bg'], 'operator' => '==', 'value' => 'custom' ) ) ), 'wrapper' => array( 'width' => 33 ) ),
			array( 'key' => 'field_' . $t['color'], 'name' => $t['color'], 'label' => __( 'Text colour', 'instopress-social' ), 'instructions' => __( 'Empty: whatever reads best on the background.', 'instopress-social' ), 'type' => 'color_picker', 'conditional_logic' => $show, 'wrapper' => array( 'width' => 33 ) ),
			array( 'key' => 'field_' . $t['scale'], 'name' => $t['scale'], 'label' => __( 'Text size (%)', 'instopress-social' ), 'type' => 'range', 'min' => 50, 'max' => 200, 'step' => 5, 'default_value' => 100, 'append' => '%', 'conditional_logic' => $show, 'wrapper' => array( 'width' => 34 ) ),
			array( 'key' => 'field_' . $t['font'], 'name' => $t['font'], 'label' => __( 'Font', 'instopress-social' ), 'type' => 'select', 'choices' => $fonts, 'default_value' => 'sans', 'conditional_logic' => $show, 'wrapper' => array( 'width' => 33 ) ),
			array( 'key' => 'field_' . $t['align'], 'name' => $t['align'], 'label' => __( 'Alignment', 'instopress-social' ), 'type' => 'button_group', 'choices' => array( 'left' => __( 'Left', 'instopress-social' ), 'center' => __( 'Centre', 'instopress-social' ), 'right' => __( 'Right', 'instopress-social' ) ), 'default_value' => 'center', 'conditional_logic' => $show, 'wrapper' => array( 'width' => 33 ) ),
		),
	) );
}, 20 );

// "Text" joins the Media type choices.
add_filter( 'acf/load_field/key=field_ig_media_type', function ( $field ) {
	$field['choices']['text'] = __( 'Text', 'instopress-social' );
	return $field;
} );
