<?php
/**
 * Names this plugin used before it became instopress-social (it started as
 * "Insta Archive", then "instapress-social"). They are only used to recognise
 * and migrate old data, to send old addresses to the new ones, and to read
 * feeds from sites that haven't updated yet. Nothing new is written under them.
 */

defined( 'ABSPATH' ) || exit;

/** Old post type keys → IAR_POST_TYPE / IAR_STORY_TYPE (migrated once, see includes/post-type.php). */
const IAR_LEGACY_POST_TYPES  = array( 'instagram_post', 'instapress-social' );
const IAR_LEGACY_STORY_TYPES = array( 'instapress-story' );

/** Feed namespaces of older versions, still read from sites that haven't updated. */
const IAR_LEGACY_NAMESPACES = array( 'urn:instapress-social:1', 'urn:insta-archive:1' );

/** <link data-…> attributes older versions use to point at their feed. */
const IAR_LEGACY_DISCOVERY = array( 'instapress-social', 'insta-archive' );

/** The feed's old name: /feed/insta/ and ?feed=insta redirect to the current feed. */
const IAR_LEGACY_FEED = 'insta';

/** The old slug: settings page address and theme override folder. */
const IAR_LEGACY_SLUG = 'instapress-social';
