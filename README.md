# instopress-social

**Download:** grab `instopress-social-<version>.zip` from [Releases](../../releases) and upload it in *Plugins → Add New → Upload Plugin*. From 1.7.0 the plugin updates itself from Releases — turn on *Enable auto-updates* on the Plugins screen to have it happen automatically.

A WordPress plugin for an instopress-social profile on your own site — your posts in a profile grid, plus a home feed of people you follow on other instopress-social sites.

```
plugin/instopress-social/        the plugin (install this)
dist/instopress-social-*.zip installable zip (git-ignored; see Releases)
ui/                          static HTML mockup the templates were built from
import/                      your posts.json + downloaded media (git-ignored)
```

## Features

- **Post type** `instopress-social` with ACF fields (`ig_shortcode`, `ig_caption`, `ig_media`, `ig_media_type`, `ig_aspect_ratio`, `ig_posted_at`, `ig_likes`, `ig_comments`, `ig_location`, `ig_url` = original URL).
- **Permalinks** `/p/DYLRvc7CC0h/`, with a **Generate shortcode** button on the edit screen.
- **Templates**: profile grid at `/social/` (Posts / Reels tabs, load more, `?hashtag=` filter), single post at `/p/<shortcode>/`, home feed at `/social/home/`. Override any template from your theme in `instopress-social/`.
- **Aspect ratios** per post (Auto, 1:1, 4:5, 3:4, 1.91:1, 9:16, Original) and grid tile shape.
- **Following**: the **Follow** button asks for your own site's address and opens your Following page in a new tab with the profile filled in, ready to confirm (or copies the feed link, `/social/feed/`, to paste into *instopress-social → Following* on your own site; their posts show in your home feed, refreshed hourly.
- **Likes & comments** on WordPress's own comment system: comment panel with replies (Discussion settings, moderation and Akismet apply), likes for visitors without accounts, double-tap to like.
- **Bot protection** (optional): Cloudflare Turnstile, Google reCAPTCHA v3 or reCAPTCHA v2 (invisible) on comments and likes, verified server-side; choose and add keys under *Settings → instopress-social*.
- **Mobile posting** at `/social/new/` (the **+** in the bottom bar): pick photos/videos, reorder, choose the shape, caption and share as a post or a story — no wp-admin needed. Logged-in authors only.
- **Text posts**: rich text on a coloured or gradient card (size, font, alignment, colours), drawn as SVG with real text. Compose them in the **Text** tab at `/social/new/` or in wp-admin.
- **Post menu**: logged in, the ⋯ on a post changes who can see it (Published / Private / Draft), bins it, or opens the editor.
- **Stories**: photos/videos that disappear after 24h, played full-screen from your profile picture and the home feed's stories row; followers get them through the same feed.
- **Get instopress-social**: a profile button that downloads the plugin version installed on the site (`/?download=instopress-social`).
- **Your own addresses**: change the profile folder (`/social/`) and post folder (`/p/`) in Settings; old addresses redirect.
- **Settings → instopress-social**: profile picture, username, bio, website, counts, grid, templates, sharing.

## Editions

One source tree, two plugins (both install as `instopress-social/`):

| | instopress-social Pro | instopress-social (WordPress.org) |
| --- | --- | --- |
| Where | GitHub Releases: `instopress-social-X.Y.Z.zip` | `instopress-social-wporg-X.Y.Z.zip`, for the WordPress.org SVN |
| Bot protection (Turnstile / reCAPTCHA) | ✓ | — |
| Updates | itself, from GitHub Releases | WordPress.org |
| Usage statistics | on by default | opt-in |
| Everything else | ✓ | ✓ |

Pro-only code lives in `plugin/instopress-social/includes/pro/`; the plugin loads it when it's there (`IAR_PRO`). Build both zips from a tag:

```bash
npm ci            # once: installs esbuild, used to minify the CSS/JS
bin/build.sh v1.29.0
```

The WordPress.org build drops `includes/pro/`, the `Update URI` header and readme lines ending in "(Pro)". Installed copies update from the asset named `instopress-social-X.Y.Z.zip`, so that name always carries the Pro build.

## Importing from imginn

1. In WordPress go to *instopress-social → Import* and drag the **imginn → posts.json** bookmarklet to your bookmarks bar. Open your profile on imginn.com, pass the check and click the bookmark — a progress box appears and `posts.json` downloads. (Fallback: paste `plugin/instopress-social/tools/imginn-scrape.js` into DevTools → Console.)
2. On the same page, upload `posts.json` with a `.zip` of the media folder (or give the path of a folder already on the server), check the preview and press **Start import**. Or from the command line:

```bash
wp instopress-social import import/posts.json import/islayanderson64-2026-10-16 --dry-run
```

Re-running updates captions/dates without duplicating anything. `wp instopress-social shortcode --date=2019-06-01` prints generated shortcodes.

## Mockup

```bash
python3 -m http.server 8765 --directory ui
```

## License

[MIT](LICENSE) © 2026 Islay Anderson.
