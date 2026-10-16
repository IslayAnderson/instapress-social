# instapress-social

**Download:** grab `instapress-social-<version>.zip` from [Releases](../../releases) and upload it in *Plugins → Add New → Upload Plugin*. From 1.7.0 the plugin updates itself from Releases — turn on *Enable auto-updates* on the Plugins screen to have it happen automatically.

A WordPress plugin for an instapress-social profile on your own site — your posts in a profile grid, plus a home feed of people you follow on other instapress-social sites.

```
plugin/instapress-social/        the plugin (install this)
dist/instapress-social-*.zip installable zip (git-ignored; see Releases)
ui/                          static HTML mockup the templates were built from
import/                      your posts.json + downloaded media (git-ignored)
```

## Features

- **Post type** `instapress-social` with ACF fields (`ig_shortcode`, `ig_caption`, `ig_media`, `ig_media_type`, `ig_aspect_ratio`, `ig_posted_at`, `ig_likes`, `ig_comments`, `ig_location`, `ig_url` = original URL).
- **Permalinks** `/p/DYLRvc7CC0h/`, with a **Generate shortcode** button on the edit screen.
- **Templates**: profile grid at `/gram/` (Posts / Reels tabs, modal, load more, `?hashtag=` filter), single post at `/p/<shortcode>/`, home feed at `/gram/home/`. Override any template from your theme in `instapress-social/`.
- **Aspect ratios** per post (Auto, 1:1, 4:5, 3:4, 1.91:1, 9:16, Original) and grid tile shape.
- **Following**: the **Follow** button copies the profile's feed link (`/feed/insta/`). Paste it into *instapress-social → Following* on your own site; their posts show in your home feed, refreshed hourly.
- **Likes & comments** on WordPress's own comment system: comment panel with replies (Discussion settings, moderation and Akismet apply), likes for visitors without accounts, double-tap to like.
- **Bot protection** (optional): Cloudflare Turnstile, Google reCAPTCHA v3 or reCAPTCHA v2 (invisible) on comments and likes, verified server-side; choose and add keys under *Settings → instapress-social*.
- **Stories**: photos/videos that disappear after 24h, played full-screen from your profile picture and the home feed's stories row; followers get them through the same feed.
- **Get instapress-social**: a profile button that downloads the plugin version installed on the site (`/?download=instapress-social`).
- **Settings → instapress-social**: profile picture, username, bio, website, counts, grid, templates, sharing.

## Importing from imginn

1. In WordPress go to *instapress-social → Import* and drag the **imginn → posts.json** bookmarklet to your bookmarks bar. Open your profile on imginn.com, pass the check and click the bookmark — a progress box appears and `posts.json` downloads. (Fallback: paste `plugin/instapress-social/tools/imginn-scrape.js` into DevTools → Console.)
2. On the same page, upload `posts.json` with a `.zip` of the media folder (or give the path of a folder already on the server), check the preview and press **Start import**. Or from the command line:

```bash
wp instapress-social import import/posts.json import/islayanderson64-2026-10-16 --dry-run
```

Re-running updates captions/dates without duplicating anything. `wp instapress-social shortcode --date=2019-06-01` prints generated shortcodes.

## Mockup

```bash
python3 -m http.server 8765 --directory ui
```

## License

[MIT](LICENSE) © 2026 Islay Anderson.
