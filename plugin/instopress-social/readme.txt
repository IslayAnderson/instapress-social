=== instopress-social ===
Contributors: islayanderson
Tags: instopress-social, archive, gallery, import, social
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.29.0
License: MIT
License URI: https://opensource.org/licenses/MIT

An instopress-social archive of your own posts — and a feed of the people you follow on other instopress-social sites.

== Description ==

* `instopress-social` post type with ACF fields (ig_shortcode, ig_caption, ig_media, ig_media_type, ig_aspect_ratio, ig_posted_at, ig_likes, ig_comments, ig_location, ig_url = original URL).
* Short-code permalinks — /p/DYLRvc7CC0h/ — with a "Generate shortcode" button on the edit screen. Generated codes encode the post date the same way real ones do.
* Profile grid at /social/ (posts + reels tabs, hover counts, load more) and a single post page with carousel.
* Per-post aspect ratio — Auto, 1:1, 4:5, 3:4, 1.91:1, 9:16 or Original. Auto uses the first image, kept within 4:5…1.91:1; every carousel slide is cropped to the post's ratio.
* Grid tiles square (classic) or 3:4 / 4:5 portrait (Settings).
* Following: the Follow button on someone's profile asks for your own site's address and opens your Following page with them filled in, ready to confirm (or copies their feed link, /social/feed/). Their posts then appear in your home feed at /social/home/, refreshed hourly.
* Likes and comments on WordPress's own comment system: a comment panel beside each post, with replies, moderation/Akismet/notifications as usual; likes for visitors without accounts (one per browser), double-tap to like.
* Stories: photos/videos that disappear after 24 hours (configurable), shown in a full-screen viewer from your profile picture and the home feed's stories row. They're shared with followers through the same feed, and expired stories are archived privately (or deleted).
* "Get instopress-social" button: visitors can download the exact plugin version installed on your site.
* Hashtags link to your own grid filtered by tag (/social/?hashtag=film); @mentions link to people you follow.
* Updates from GitHub Releases: "Update now" and "Enable auto-updates" on the Plugins screen work as for any plugin (WordPress 5.8+). (Pro)
* Bot protection: Cloudflare Turnstile, Google reCAPTCHA v3 or v2 (invisible) on comments and likes, verified on the server. (Pro)
* Settings → instopress-social for the profile header (picture, username, bio, website, follower counts).
* WP-CLI importer.

Requires Advanced Custom Fields (free or Pro) to edit fields in the admin.

instopress-social comes in two editions: the free edition on WordPress.org, and instopress-social Pro from GitHub, which adds the features marked (Pro) and updates itself from GitHub Releases.

== Importing ==

1. Go to instopress-social → Import and drag the "imginn → posts.json" bookmarklet to your bookmarks bar. Open your profile on imginn.com, pass the check, and click the bookmark — it downloads posts.json. (Or paste tools/imginn-scrape.js into the browser console.)
2. On the same Import page, upload posts.json with a .zip of your photos/videos (or point it at a folder already on the server), check the preview, and press Start import. Or from the command line:

    wp instopress-social import posts.json path/to/media --dry-run
    wp instopress-social import posts.json path/to/media

Re-running updates captions/dates without duplicating anything.

== Theme overrides ==

Copy any file from the plugin's templates/ folder into your theme at instopress-social/<same path> to customise it, e.g. instopress-social/parts/grid-item.php.

Filters: iar_archive_slug (default "social"), iar_post_slug (default "p"), iar_use_templates.

== Privacy ==

With usage statistics switched on — off by default in the WordPress.org edition, on by default in instopress-social Pro — the plugin shares anonymous usage statistics with its author through Google Analytics, to count installs and decide what to improve:

* When it's set up (activated, or first run after updating): one "plugin_setup" event.
* In wp-admin: which instopress-social screens are opened (posts, editor, stories, Import, Following, Settings) and which actions are used — settings saved (the names of the settings that changed, not their values), following or unfollowing a feed, starting or cancelling an import, checking for updates, publishing from the composer (post, text or story), and changing a post's status.

Every event carries this site's domain, the plugin and WordPress versions and the environment type (production, staging, local). Never post content, settings values, users or visitors. To send them, the plugin first reads the Analytics details from blog.islayanderson.co.uk (a plain request; nothing about your site is included).

Turn it on or off under Settings → instopress-social → Privacy ("Share anonymous usage statistics"), with `define( 'IAR_DISABLE_SETUP_EVENT', true );` in wp-config.php, or by returning false from the `iar_send_usage_stats` filter.

== External services ==

* Google Analytics (Measurement Protocol) — only while usage statistics are switched on (Settings → instopress-social → Privacy). Sends the events listed under Privacy: this site's domain, plugin/WordPress versions, environment type, and names of admin screens and actions. Terms: https://marketingplatform.google.com/about/analytics/terms/us/ — Privacy policy: https://policies.google.com/privacy
* blog.islayanderson.co.uk (the plugin author's site) — only while usage statistics are switched on: the plugin reads the Google Analytics details from https://blog.islayanderson.co.uk/wp-json/instopress/v1/ga-secret before sending. Nothing about your site is included in that request. Privacy policy: https://blog.islayanderson.co.uk/
* Other instopress-social sites you follow — your site fetches their public feeds (e.g. https://their-site.com/social/feed/) hourly, and when you follow someone. Only their public posts and profile are read.
* imginn.com — the Import page's bookmarklet runs in your own browser on imginn.com when you click it there, to save your public posts as posts.json. The plugin itself never contacts imginn. Terms and privacy: https://imginn.com/
* Gravatar (Automattic), through WordPress — comment avatars, as on any WordPress site. Privacy policy: https://automattic.com/privacy/
* Cloudflare Turnstile — only if you choose it for bot protection: loads https://challenges.cloudflare.com/turnstile/v0/api.js for visitors who comment or like, and checks their token with Cloudflare. Terms: https://www.cloudflare.com/website-terms/ — Privacy policy: https://www.cloudflare.com/privacypolicy/ (Pro)
* Google reCAPTCHA — only if you choose it for bot protection: loads https://www.google.com/recaptcha/api.js and checks visitors' tokens with Google. Terms: https://policies.google.com/terms — Privacy policy: https://policies.google.com/privacy (Pro)
* GitHub — instopress-social Pro checks https://api.github.com/repos/IslayAnderson/instopress-social/releases/latest for updates and downloads them from GitHub. Terms: https://docs.github.com/site-policy/github-terms/github-terms-of-service — Privacy policy: https://docs.github.com/site-policy/privacy-policies/github-general-privacy-statement (Pro)

== Changelog ==

= 1.29.0 =
* Smaller downloads: release builds include minified copies of the plugin's CSS and JavaScript (about 40% smaller before compression), which the plugin serves automatically. With SCRIPT_DEBUG on, or when running from source without a build, the readable files are used instead.

= 1.28.0 =
* Faster profile grid on phones: the first tile that shows a picture loads first (text posts and videos without a poster are skipped), the first three rows load straight away, and tiles carry srcset/sizes so phones download a small copy. A new 480px "tile" image size fills the gap between 300px and 768px; new uploads get it automatically and existing pictures are filled in a few at a time in the background, or all at once with `wp instopress-social tiles`.
* Faster pages on the server: the home feed and RSS feed load their posts' media in a few queries instead of one per image, each post's media is worked out once per page, tiles only build their first slide, and image/video checks use the attachment's type instead of slower lookups. On a test site the home feed and RSS feed render about 30% faster, and the plugin's own work on them dropped by more than half. Pages look exactly the same.

= 1.27.0 =
* New name: instopress-social (instopress-social Pro on GitHub, at github.com/IslayAnderson/instopress-social). The plugin, its folder, post types, feed and settings now use the new name; descriptions no longer refer to other services' brand names.
* Updating from 1.26.1 or later is automatic: the plugin moves to its new folder and stays active and auto-updating; your posts, stories, likes, comments, settings and follows carry over. Old addresses (settings page, feed) redirect, theme overrides in the old folder still work, and feeds from sites that haven't updated yet are still read.
* Imported likes and comment counts are now labelled "imported".

= 1.26.2 =
* The move to the new name keeps the plugin switched on however the update is run (from the Plugins screen, the Updates screen, automatically, or with WP-CLI).

= 1.26.1 =
* Getting ready for the plugin's new name, instopress-social: updates are also accepted from the renamed GitHub repository and zips, and when the renamed version installs it moves to its new folder and stays active and auto-updating. Please update to this version before 1.27.0.
* Follows sites already on the new name: their feeds and Follow links are recognised.

= 1.26.0 =
* Two editions: instopress-social (WordPress.org) and instopress-social Pro (GitHub). Pro adds bot protection (Cloudflare Turnstile / Google reCAPTCHA) and updates itself from GitHub Releases. Everything else is in both.
* Usage statistics are opt-in in the WordPress.org edition (off until you switch them on under Settings → Privacy) and stay on by default in Pro.
* Sites already running the plugin are on Pro: this update keeps their bot protection, settings and automatic updates as they are.

= 1.25.2 =
* Plugin Check clean-up: translator comments on every translatable string with placeholders; the RSS feed escapes through esc_xml() directly; file operations (import jobs, the download package, imported media dates) use WordPress's filesystem API instead of PHP's unlink/rmdir/rename/touch/move_uploaded_file; request values are sanitised where they're read; templates/parts/sprite.php blocks direct access; readme gains "Tested up to".
* The plugin no longer borrows ACF's text domain to recognise its "valid image" message; the media gallery is validated from the files themselves.
* Release zips and the "Get instopress-social" download no longer include hidden files (an IDE settings folder had slipped in).

= 1.25.1 =
* Fixed: Unfollow could look like it did nothing. Following someone again through their new feed address (/social/feed/) added them a second time, so unfollowing removed one copy and they stayed listed. Following the same person again now updates the existing follow, Unfollow removes every copy, and any duplicates already in your list are merged.
* The Following page now confirms what happened after Unfollow, Refresh and Refresh all.

= 1.25.0 =
* Following is one step now: the Follow button on a profile asks for your own site's address (remembered in your browser for next time) and opens your site's Following page in a new tab, with that profile filled in and shown (picture, name, site). Press Follow to confirm — nothing is followed without that click. If you're logged out, your site asks you to log in first.
* Your site needs 1.25.0 or later for the link to land on its Following page. The dialog still offers "Copy the feed link instead", and a "Get instopress-social" link for visitors without a site.

= 1.24.1 =
* Fixed: the + on your story ring in the home feed stretched to the full width of the story instead of being a small round button.

= 1.24.0 =
* Your feed has moved inside your profile folder: /social/feed/ (or /gram/feed/, whatever your profile folder is) instead of the old feed address. It follows the folder if you change it.
* Nothing breaks: the old feed address redirects permanently to the new one, and sites following you switch to it automatically on their next refresh once they're on 1.24.0.

= 1.23.0 =
* Anonymous usage statistics from the plugin's admin screens: which screens are opened and which actions are used (names only, never content or people), sent to the plugin author's Google Analytics alongside the existing setup event. A notice on the plugin's screens says so once, and Settings → instopress-social → Privacy has a "Share anonymous usage statistics" switch to turn all of it off. See Privacy.

= 1.22.0 =
* New defaults for new installs: profile at /social/ (was /gram/), the WordPress toolbar hidden on instopress-social pages, and the home feed private to you. Everything else is unchanged.
* Sites already running the plugin keep their current settings: if you never changed these, your profile stays at /gram/, the toolbar still shows and your home feed stays public. Change any of them under Settings → instopress-social.

= 1.21.0 =
* Privacy options under Settings → instopress-social → Templates → Privacy:
* "Only I can see my home feed": the home feed (posts and stories from everyone you follow) asks visitors to log in, tells logged-in people who can't edit posts that it's private, and its links are hidden from them.
* "Hide follower and following counts from visitors": the counts only show for you and anyone who can edit posts.
* Both are off by default.

= 1.20.0 =
* New option under Settings → instopress-social → Templates: "Hide the WordPress toolbar on these pages", for a cleaner, app-like look when you're logged in. It only affects instopress-social pages (profile, home feed, posts, New post); wp-admin and the rest of your site are unchanged. Off by default.

= 1.19.0 =
* Choose your own addresses: Settings → instopress-social now has a Profile folder (default /gram/, which also holds …/home/ and …/new/) and a Post folder (default /p/). Lowercase letters, numbers and dashes; names WordPress already uses and your existing pages are refused, with a message saying why.
* Changing a folder doesn't break links: the old addresses redirect permanently (301) to the new ones, including old post links and links on sites that follow you.

= 1.18.0 =
* Text posts: write a short post with rich text (bold, italic, underline, strikethrough, headings, quotes, lists, links) and give it a look: text size, font, alignment, text colour and a background from preset colours and gradients (or your own colour). Make them from the composer's new Text tab or in wp-admin (Media type: Text). They're ordinary posts (permalinks, grid, likes, comments, feeds) drawn as SVG with real text, not baked into an image. Followers' sites and RSS readers get the card as an .svg file.
* Post menu: when you're logged in, the ⋯ button on a post (post page and home feed) lets you make it Published, Private (only you) or a Draft (hidden), move it to the bin, or open it in the WordPress editor. Your drafts and private posts stay visible to you in the grid and on their pages, with a badge.

= 1.17.1 =
* Fixed: feed readers could be told your feed hadn't changed (HTTP 304) after a new story, a like or comment, a story expiring, a deletion or a profile edit, because the feed's Last-Modified date only followed post edits. It now reflects all of these, and so does the feed's lastBuildDate.

= 1.17.0 =
* Posting from the phone composer is more reliable: photos are resized in the browser (to at most 2160 px, as JPEG) before uploading, so full-size camera photos no longer overload the server. A 5000 px photo goes up as about a quarter of the size.
* If the server still fails, the composer now says what went wrong and at which step (uploading, saving the video poster or publishing) instead of just "HTTP 500".
* Uploads from the composer get more memory and time on the server.

= 1.16.0 =
* Faster profile grid. The first row of tiles loads straight away (the first with high priority) instead of waiting as "lazy" images, so the page's main image appears sooner.
* Video tiles that don't have a poster yet only start loading once they scroll into view, so they no longer hold up the images above them. (Generating posters under instopress-social → Import → Video posters is still the best fix.)
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
* "Regenerate all posters" on the Import page: recaptures a frame from every video (choose the moment, e.g. 1 second in) and replaces its poster. Posters made by the plugin before are deleted; imported cover images are kept. Single-video posts' featured images follow the new poster.

= 1.10.0 =
* Bot protection can now use Google reCAPTCHA v3 (invisible, score-based, with a minimum-score setting) or reCAPTCHA v2 invisible, as well as Cloudflare Turnstile. Choose under Settings → instopress-social → Bot protection; each provider has its own keys. Existing Turnstile setups carry on unchanged.
* If the provider ever rejects your secret key during real use, an admin warning appears (a wrong secret would otherwise block every comment and like). Google can't confirm reCAPTCHA secrets when you save them, so this is how a mistake shows up.
* The bot-check script isn't loaded for comment moderators, who are never checked.

= 1.9.0 =
* Optional Cloudflare Turnstile bot protection for comments and likes (Settings → instopress-social → Bot protection). Usually invisible; a fresh single-use token is checked with Cloudflare for every comment and like, requiring the right action and this site's hostname, and requests are rejected if the check fails or Cloudflare can't be reached. Comment moderators are never challenged.
* The secret key is checked with Cloudflare when saved, never shown again, kept out of autoloaded options, or can be set as IAR_TURNSTILE_SECRET in wp-config.php.

= 1.8.0 =
* Comments: real WordPress comments on posts — shown beside the photo and in the grid pop-up, with replies. Visitors comment without an account: the first time, a pop-up asks for their email (required, never shown) and an optional name — blank posts as "Anonymous" — and can remember them on that device. Discussion settings, moderation, Akismet and notification emails all apply. Imported comment counts show as "N earlier comments (imported)".
* Likes: visitors can like posts (no account needed, one like per browser; tap again to unlike; double-tap the photo). Stored as WordPress comments of type "Likes", filterable on the Comments screen and kept out of comment lists and counts. Totals add imported likes.
* Settings → Likes & comments to turn either off. Comments on existing posts are opened once.
* If the Disable Comments plugin is switching comments off everywhere, a notice explains how to allow them on instopress-social posts only.
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
* Updates come from GitHub Releases: new versions show on the Plugins screen with "Update now", the auto-updates toggle works, and "View details" shows the release notes. Only zips attached to releases of IslayAnderson/instopress-social are accepted.
* "Check for updates" and "Releases" links under the plugin.

= 1.6.0 =
* Stories: new Story post type (instopress-social → Stories) with photo/video, overlay text, link and display time.
* Full-screen story viewer: progress bars, auto-advance, tap to skip, hold to pause, swipe down / Esc to close, video sound toggle; seen stories turn the ring grey.
* Stories expire after 24 hours (Settings: 1–336h), then move to a private Expired archive or are deleted.
* Followers receive stories through your feed; the home feed shows rings for accounts with live stories and plays them one after another.

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
* Plugin renamed to instopress-social (folder, main file, text domain, theme override folder instopress-social/). Settings, follows and posts carry over.
* Still follows feeds from sites on older versions.

= 1.2.0 =
* Following other instopress-social sites, home feed, and a feed for your followers.
* "Get instopress-social" download button.
* Renamed; the post type key moves to the new name automatically.
* Hashtags and mentions link within your site.

= 1.1.0 =
* Aspect ratios per post and for grid tiles.

= 1.0.0 =
* First release.
