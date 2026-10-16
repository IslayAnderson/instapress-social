=== instapress-social ===
Contributors: islayanderson
Tags: instapress-social, archive, gallery, import, social
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.7.3
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
* Stories: photos/videos that disappear after 24 hours (configurable), shown in a full-screen viewer from your profile picture and the home feed's stories row. They're shared with followers through the same feed, and expired stories are archived privately (or deleted).
* "Get instapress-social" button: visitors can download the exact plugin version installed on your site.
* Hashtags link to your own grid filtered by tag (/gram/?hashtag=film); @mentions link to people you follow.
* Updates from GitHub Releases: "Update now" and "Enable auto-updates" on the Plugins screen work as for any plugin (WordPress 5.8+).
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

= 1.7.3 =
* Media type now follows the gallery automatically on save (one video = Video, several items = Carousel), and existing posts are corrected once — hand-made video posts now show in Reels and get poster checks.
* Video posters tool finds every video without a poster, whatever the post's media type.
* Edit screen: posts whose video has no poster get a "Generate poster now" button.
* Fixed: with ACF 6.8.7+ a post containing a video couldn't be saved ("File must be a valid image") and the media picker refused videos. The gallery accepts photos and videos again.

= 1.7.2 =
* Video posters no longer pick up a default image from plugins like "Default Featured Image" — only a video's own poster (or a single-video post's own featured image) is used.
* Videos without a poster show their first frame in the grid instead of a broken image.
* New "Video posters" tool on the Import page: generates a poster for every video that lacks one, from a frame of the video.

= 1.7.1 =
* imginn scraper: reels (/reel/, /tv/) are collected too; stops as soon as the grid ends instead of chasing the profile's post count (which includes reels imginn doesn't list); "Stop & save" button; an error no longer leaves it stuck on "already running".

= 1.7.0 =
* Updates come from GitHub Releases: new versions show on the Plugins screen with "Update now", the auto-updates toggle works, and "View details" shows the release notes. Only zips attached to releases of IslayAnderson/instapress-social are accepted.
* "Check for updates" and "Releases" links under the plugin.

= 1.6.0 =
* Stories: new Story post type (instapress-social → Stories) with photo/video, overlay text, link and display time.
* Full-screen story viewer: progress bars, auto-advance, tap to skip, hold to pause, swipe down / Esc to close, video sound toggle; seen stories turn the ring grey.
* Stories expire after 24 hours (Settings: 1–168h), then move to a private Expired archive or are deleted.
* Followers receive stories through /feed/insta/; the home feed shows rings for accounts with live stories and plays them one after another.

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
