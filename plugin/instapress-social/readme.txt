=== instapress-social ===
Contributors: islayanderson
Tags: instapress-social, archive, gallery, import, social
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.25.0
License: MIT
License URI: https://opensource.org/licenses/MIT

An instapress-social archive of your own posts — and a feed of the people you follow on other instapress-social sites.

== Description ==

* `instapress-social` post type with ACF fields (ig_shortcode, ig_caption, ig_media, ig_media_type, ig_aspect_ratio, ig_posted_at, ig_likes, ig_comments, ig_location, ig_url = original URL).
* Short-code permalinks — /p/DYLRvc7CC0h/ — with a "Generate shortcode" button on the edit screen. Generated codes encode the post date the same way real ones do.
* Profile grid at /social/ (posts + reels tabs, hover counts, load more) and a single post page with carousel.
* Per-post aspect ratio — Auto, 1:1, 4:5, 3:4, 1.91:1, 9:16 or Original. Auto uses the first image, kept within 4:5…1.91:1; every carousel slide is cropped to the post's ratio.
* Grid tiles square (classic) or 3:4 / 4:5 portrait (Settings).
* Following: the Follow button on a profile copies its feed link (/social/feed/). Paste it into instapress-social → Following on your own site and their posts appear in your home feed at /social/home/, refreshed hourly.
* Likes and comments on WordPress's own comment system: Instagram-style comment panel with replies, moderation/Akismet/notifications as usual; likes for visitors without accounts (one per browser), double-tap to like.
* Stories: photos/videos that disappear after 24 hours (configurable), shown in a full-screen viewer from your profile picture and the home feed's stories row. They're shared with followers through the same feed, and expired stories are archived privately (or deleted).
* "Get instapress-social" button: visitors can download the exact plugin version installed on your site.
* Hashtags link to your own grid filtered by tag (/social/?hashtag=film); @mentions link to people you follow.
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

== Privacy ==

The plugin shares anonymous usage statistics with its author through Google Analytics, to count installs and decide what to improve:

* When it's set up (activated, or first run after updating): one "plugin_setup" event.
* In wp-admin: which instapress-social screens are opened (posts, editor, stories, Import, Following, Settings) and which actions are used — settings saved (the names of the settings that changed, not their values), following or unfollowing a feed, starting or cancelling an import, checking for updates, publishing from the composer (post, text or story), and changing a post's status.

Every event carries this site's domain, the plugin and WordPress versions and the environment type (production, staging, local). Never post content, settings values, users or visitors. To send them, the plugin first reads the Analytics details from blog.islayanderson.co.uk (a plain request; nothing about your site is included).

Turn it off under Settings → instapress-social → Privacy ("Share anonymous usage statistics"), with `define( 'IAR_DISABLE_SETUP_EVENT', true );` in wp-config.php, or by returning false from the `iar_send_usage_stats` filter.

== Changelog ==

= 1.25.0 =
* Following is one step now: the Follow button on a profile asks for your own site's address (remembered in your browser for next time) and opens your site's Following page in a new tab, with that profile filled in and shown (picture, name, site). Press Follow to confirm — nothing is followed without that click. If you're logged out, your site asks you to log in first.
* Your site needs 1.25.0 or later for the link to land on its Following page. The dialog still offers "Copy the feed link instead", and a "Get instapress-social" link for visitors without a site.

= 1.24.1 =
* Fixed: the + on your story ring in the home feed stretched to the full width of the story instead of being a small round button.

= 1.24.0 =
* Your feed has moved inside your profile folder: /social/feed/ (or /gram/feed/, whatever your profile folder is) instead of /feed/insta/. It follows the folder if you change it.
* Nothing breaks: /feed/insta/ (and ?feed=insta) redirect permanently to the new address, and sites following you switch to it automatically on their next refresh once they're on 1.24.0.

= 1.23.0 =
* Anonymous usage statistics from the plugin's admin screens: which screens are opened and which actions are used (names only, never content or people), sent to the plugin author's Google Analytics alongside the existing setup event. A notice on the plugin's screens says so once, and Settings → instapress-social → Privacy has a "Share anonymous usage statistics" switch to turn all of it off. See Privacy.

= 1.22.0 =
* New defaults for new installs: profile at /social/ (was /gram/), the WordPress toolbar hidden on instapress-social pages, and the home feed private to you. Everything else is unchanged.
* Sites already running the plugin keep their current settings: if you never changed these, your profile stays at /gram/, the toolbar still shows and your home feed stays public. Change any of them under Settings → instapress-social.

= 1.21.0 =
* Privacy options under Settings → instapress-social → Templates → Privacy:
* "Only I can see my home feed": the home feed (posts and stories from everyone you follow) asks visitors to log in, tells logged-in people who can't edit posts that it's private, and its links are hidden from them.
* "Hide follower and following counts from visitors": the counts only show for you and anyone who can edit posts.
* Both are off by default.

= 1.20.0 =
* New option under Settings → instapress-social → Templates: "Hide the WordPress toolbar on these pages", for a cleaner, app-like look when you're logged in. It only affects instapress-social pages (profile, home feed, posts, New post); wp-admin and the rest of your site are unchanged. Off by default.

= 1.19.0 =
* Choose your own addresses: Settings → instapress-social now has a Profile folder (default /gram/, which also holds …/home/ and …/new/) and a Post folder (default /p/). Lowercase letters, numbers and dashes; names WordPress already uses and your existing pages are refused, with a message saying why.
* Changing a folder doesn't break links: the old addresses redirect permanently (301) to the new ones, including old post links and links on sites that follow you.

= 1.18.0 =
* Text posts: write a short post with rich text (bold, italic, underline, strikethrough, headings, quotes, lists, links) and give it a look: text size, font, alignment, text colour and a background from preset colours and gradients (or your own colour). Make them from the composer's new Text tab or in wp-admin (Media type: Text). They're ordinary posts (permalinks, grid, likes, comments, feeds) drawn as SVG with real text, not baked into an image. Followers' sites and RSS readers get the card as an .svg file.
* Post menu: when you're logged in, the ⋯ button on a post (post page and home feed) lets you make it Published, Private (only you) or a Draft (hidden), move it to the bin, or open it in the WordPress editor. Your drafts and private posts stay visible to you in the grid and on their pages, with a badge.

= 1.17.1 =
* Fixed: feed readers could be told /feed/insta/ hadn't changed (HTTP 304) after a new story, a like or comment, a story expiring, a deletion or a profile edit, because the feed's Last-Modified date only followed post edits. It now reflects all of these, and so does the feed's lastBuildDate.

= 1.17.0 =
* Posting from the phone composer is more reliable: photos are resized in the browser (to at most 2160 px, as JPEG) before uploading, so full-size camera photos no longer overload the server. A 5000 px photo goes up as about a quarter of the size.
* If the server still fails, the composer now says what went wrong and at which step (uploading, saving the video poster or publishing) instead of just "HTTP 500".
* Uploads from the composer get more memory and time on the server.

= 1.16.0 =
* Faster profile grid. The first row of tiles loads straight away (the first with high priority) instead of waiting as "lazy" images, so the page's main image appears sooner.
* Video tiles that don't have a poster yet only start loading once they scroll into view, so they no longer hold up the images above them. (Generating posters under instapress-social → Import → Video posters is still the best fix.)
* The grid loads all its photos, videos and posters in a handful of database queries instead of several per tile (449 → 39 on a 24-post page).

= 1.15.0 =
* The Google Analytics API secret for the setup event is no longer built into the plugin: it's read from blog.islayanderson.co.uk when needed and cached for 12 hours. If it can't be fetched, nothing is sent and the event is retried later (at most hourly).

= 1.14.0 =
* When the plugin is set up (activated, or first run after this update) it sends one "plugin_setup" event — the site's domain, plugin and WordPress versions and environment type — to the plugin author's Google Analytics, to count installs. See Privacy for how to turn it off.

= 1.13.0 =
* Removed the pop-up post viewer: tapping a post in the profile grid now opens the post's own page. Likes, comments and the carousel work as before there.

= 1.12.0 =
* New phone-friendly way to post: tap + in the bottom bar (or New post on your profile) to open /gram/new/. Pick photos and videos, reorder them, choose the shape, add a caption and location, and share — or switch to Story. Videos get a poster captured automatically. Only logged-in users who can publish posts can use it; files picked but never shared are tidied up after a day.

= 1.11.0 =
* "Regenerate all posters" on the Import page: recaptures a frame from every video (choose the moment, e.g. 1 second in) and replaces its poster. Posters made by the plugin before are deleted; cover images imported from Instagram are kept. Single-video posts' featured images follow the new poster.

= 1.10.0 =
* Bot protection can now use Google reCAPTCHA v3 (invisible, score-based, with a minimum-score setting) or reCAPTCHA v2 invisible, as well as Cloudflare Turnstile. Choose under Settings → instapress-social → Bot protection; each provider has its own keys. Existing Turnstile setups carry on unchanged.
* If the provider ever rejects your secret key during real use, an admin warning appears (a wrong secret would otherwise block every comment and like). Google can't confirm reCAPTCHA secrets when you save them, so this is how a mistake shows up.
* The bot-check script isn't loaded for comment moderators, who are never checked.

= 1.9.0 =
* Optional Cloudflare Turnstile bot protection for comments and likes (Settings → instapress-social → Bot protection). Usually invisible; a fresh single-use token is checked with Cloudflare for every comment and like, requiring the right action and this site's hostname, and requests are rejected if the check fails or Cloudflare can't be reached. Comment moderators are never challenged.
* The secret key is checked with Cloudflare when saved, never shown again, kept out of autoloaded options, or can be set as IAR_TURNSTILE_SECRET in wp-config.php.

= 1.8.0 =
* Comments: real WordPress comments on posts — shown beside the photo and in the grid pop-up, with replies. Visitors comment without an account: the first time, a pop-up asks for their email (required, never shown) and an optional name — blank posts as "Anonymous" — and can remember them on that device. Discussion settings, moderation, Akismet and notification emails all apply. Imported Instagram comments show as "N earlier comments on Instagram".
* Likes: visitors can like posts (no account needed, one like per browser; tap again to unlike; double-tap the photo). Stored as WordPress comments of type "Likes", filterable on the Comments screen and kept out of comment lists and counts. Totals add imported Instagram likes.
* Settings → Likes & comments to turn either off. Comments on existing posts are opened once.
* If the Disable Comments plugin is switching comments off everywhere, a notice explains how to allow them on instapress-social posts only.
* The feed now reports total likes and comments.

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
* Stories expire after 24 hours (Settings: 1–336h), then move to a private Expired archive or are deleted.
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
