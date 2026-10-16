=== instapress-social ===
Contributors: islayanderson
Tags: instapress-social, archive, gallery, import, social
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.5.2
License: MIT
License URI: https://opensource.org/licenses/MIT

An instapress-social archive of your own posts — and a feed of the people you follow on other instapress-social sites.

== Description ==

* `instapress-social` post type with ACF fields (ig_shortcode, ig_caption, ig_media, ig_media_type, ig_aspect_ratio, ig_posted_at, ig_likes, ig_comments, ig_location, ig_url = original URL).
* Short-code permalinks — /p/DYLRvc7CC0h/ — with a "Generate shortcode" button on the edit screen. Generated codes encode the post date the same way real ones do.
* Profile grid at /gram/ (posts + reels tabs, hover counts, post modal with carousel, load more) and a single post template.
* Per-post aspect ratio — Auto, 1:1, 4:5, 3:4, 1.91:1, 9:16 or Original. Auto uses the first image, kept within 4:5…1.91:1; every carousel slide is cropped to the post's ratio.
* Grid tiles square (classic) or 3:4 / 4:5 portrait (Settings).
* Following: the Follow button on a profile copies its feed link (/feed/insta/). Paste it into instapress-social → Following on your own site and their posts appear in your home feed at /gram/home/, refreshed hourly.
* "Get instapress-social" button: visitors can download the exact plugin version installed on your site.
* Hashtags link to your own grid filtered by tag (/gram/?hashtag=film); @mentions link to people you follow.
* Settings → instapress-social for the profile header (picture, username, bio, website, follower counts).
* WP-CLI importer.

Requires Advanced Custom Fields (free or Pro) to edit fields in the admin.

== Importing ==

1. Go to instapress-social → Import and drag the "imginn → posts.json" bookmarklet to your bookmarks bar. Open your profile on imginn.com, pass the check, and click the bookmark — it downloads posts.json. (Or paste tools/imginn-scrape.js into the browser console.)
2. On the same Import page, upload posts.json with a .zip of your photos/videos (or point it at a folder already on the server), check the preview, and press Start import. Or from the command line:

    wp instapress-social import posts.json path/to/media --dry-run
    wp instapress-social import posts.json path/to/media

Re-running updates captions/dates without duplicating anything.

== Theme overrides ==

Copy any file from the plugin's templates/ folder into your theme at instapress-social/<same path> to customise it, e.g. instapress-social/parts/grid-item.php.

Filters: iar_archive_slug (default "gram"), iar_post_slug (default "p"), iar_use_templates.

== Changelog ==

= 1.5.2 =
* Now licensed under the MIT License (replaces the WTFPL).

= 1.5.1 =
* Now licensed under the WTFPL (LICENSE file included).

= 1.5.0 =
* Upload posts.json (plus a media .zip or a server folder) on the Import page, preview, then import in the browser with a progress bar.
* Re-importing without the media no longer drops files that are already in the media library.

= 1.4.0 =
* Import page with a one-click imginn bookmarklet (no DevTools needed) and an on-page progress box.

= 1.3.3 =
* Home feed moved into the profile tab bar (Posts · Reels · Home), desktop only.

= 1.3.2 =
* Home feed link on the desktop profile.

= 1.3.1 =
* Removed the left sidebar menu; pages are centred full-width.
* Profile buttons wrap on small screens.

= 1.3.0 =
* Plugin renamed to instapress-social (folder, main file, text domain, theme override folder instapress-social/). Settings, follows and posts carry over.
* Still follows feeds from sites on older versions.

= 1.2.0 =
* Following other instapress-social sites, home feed, /feed/insta/.
* "Get instapress-social" download button.
* Renamed to instapress-social; the post type key moves from instagram_post to instapress-social automatically.
* Hashtags and mentions link within your site.

= 1.1.0 =
* Aspect ratios per post and for grid tiles.

= 1.0.0 =
* First release.
