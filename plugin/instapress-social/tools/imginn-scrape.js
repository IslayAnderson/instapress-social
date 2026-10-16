/**
 * imginn → posts.json scraper
 * ---------------------------------------------------------------------------
 * imginn sits behind a Cloudflare check, so this runs in YOUR browser where
 * you have already passed it:
 *
 *   1. Open https://imginn.com/<your-username>/ and pass the "verify" check.
 *   2. Click the "imginn → posts.json" bookmarklet (instapress-social → Import
 *      in WordPress), or open DevTools → Console and paste this whole file.
 *   3. It scrolls/clicks "load more" until the grid is complete, then fetches
 *      each post page (same origin, so your clearance cookie is reused) and
 *      downloads posts.json. Put that file in the import/ folder.
 *
 * Progress is saved to localStorage, so if it gets rate-limited just run
 * it again and it resumes. Run `igScrapeReset()` to start from scratch.
 *
 * Media are recorded by FILENAME (e.g. 4856..._n.jpg, AQM...mp4) — the same
 * names as the files you already downloaded — so the importer can match them
 * to local files instead of re-downloading from expiring CDN URLs.
 */
(async function igScrape() {
  'use strict';

  // Only makes sense on an imginn profile page (one path segment: /username/).
  if (!/(^|\.)imginn\.com$/.test(location.hostname) || !/^\/[^\/]+\/?$/.test(location.pathname) || /^\/(p|stories|search|tagged)\b/.test(location.pathname)) {
    window.alert('instapress-social: open your profile on imginn.com first (e.g. https://imginn.com/your-username/), pass the "verify you are human" check, then run this again.');
    return;
  }
  if (window.__igScrapeRunning) {
    window.alert('instapress-social: the scraper is already running on this page.');
    return;
  }
  window.__igScrapeRunning = true;

  // On-page status box, so it's usable from a bookmarklet without DevTools.
  var box = document.createElement('div');
  box.setAttribute('role', 'status');
  box.style.cssText = 'position:fixed;right:16px;bottom:16px;z-index:2147483647;max-width:320px;padding:12px 14px;' +
    'border-radius:10px;background:#111;color:#fff;font:13px/1.4 -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;' +
    'box-shadow:0 4px 24px rgba(0,0,0,.35)';
  box.innerHTML = '<strong style="display:block;margin-bottom:4px">instapress-social scraper</strong><span></span>';
  document.body.appendChild(box);
  var status = box.querySelector('span');

  var DELAY_MS = 1500;              // pause between post fetches
  var STORE = 'igScrape:' + location.pathname;
  var IG_EPOCH = 1314220021721n;    // ms; media ids are (ms - epoch) << 23

  var sleep = function (ms) { return new Promise(function (r) { setTimeout(r, ms); }); };
  var log = function () {
    var args = [].slice.call(arguments);
    console.log.apply(console, ['%c[igScrape]', 'color:#d62976;font-weight:bold'].concat(args));
    status.textContent = args.join(' ');
  };

  window.igScrapeReset = function () { localStorage.removeItem(STORE); log('progress cleared'); };

  var state = {};
  try { state = JSON.parse(localStorage.getItem(STORE) || '{}'); } catch (e) {}
  // v2 keeps caption line breaks — re-fetch anything scraped by v1.
  if (state.v !== 2) state = { v: 2 };
  state.posts = state.posts || {};
  var save = function () { try { localStorage.setItem(STORE, JSON.stringify(state)); } catch (e) {} };

  /* ------------------------------------------------------------------ */
  /* 1. Load the whole grid                                              */
  /* ------------------------------------------------------------------ */
  function postLinks() {
    var seen = {}, out = [];
    document.querySelectorAll('a[href*="/p/"]').forEach(function (a) {
      var m = a.getAttribute('href').match(/\/p\/([A-Za-z0-9_-]+)/);
      if (!m || seen[m[1]]) return;
      seen[m[1]] = 1;
      var img = a.querySelector('img');
      out.push({ shortcode: m[1], alt: img ? (img.getAttribute('alt') || '') : '' });
    });
    return out;
  }

  // "123 posts" from the profile header, so we know when the grid is complete.
  function expectedCount() {
    var m = document.body.innerText.match(/([\d,.]+\s*[kK]?)\s*posts?\b/i);
    if (!m) return null;
    var n = parseFloat(m[1].replace(/,/g, ''));
    return /k/i.test(m[1]) ? Math.round(n * 1000) : n;
  }

  async function loadAll() {
    var expected = expectedCount();
    log('profile says', expected == null ? '(unknown)' : expected, 'posts');
    var stable = 0, last = -1;
    // Give up after 4 idle rounds — or 15 if we're still short of the header count.
    while (stable < ((expected && last < expected) ? 15 : 4)) {
      var btn = Array.prototype.find.call(
        document.querySelectorAll('button, a, .load-more, .more'),
        function (el) { return /load\s*more|show\s*more|more posts/i.test(el.textContent) && el.offsetParent !== null; }
      );
      if (btn) btn.click();
      // Nudge infinite scroll: up a little, then to the bottom.
      window.scrollBy(0, -600);
      await sleep(300);
      window.scrollTo(0, document.body.scrollHeight);
      await sleep(stable > 3 ? 4000 : 2000);
      var n = postLinks().length;
      log('grid posts found:', n + (expected ? ' / ' + expected : ''));
      stable = n === last ? stable + 1 : 0;
      last = n;
    }
    if (expected && last < expected) {
      log('⚠ grid stopped at', last, 'of', expected, '— scroll to the bottom manually, then run it again (already-scraped posts are skipped).');
    }
  }

  /* ------------------------------------------------------------------ */
  /* 2. Parse a post page                                                */
  /* ------------------------------------------------------------------ */
  var IMG_RE = /\d{5,}_\d{10,}_\d+_n\.(?:jpe?g|webp|png|heic)/gi;
  var VID_RE = /AQ[A-Za-z0-9_-]{30,}\.mp4/g;

  function mediaNames(html) {
    // Decode URL-encoded proxy links (…?url=https%3A%2F%2F…) before matching.
    var text = html;
    try { text = html + '\n' + decodeURIComponent(html.replace(/%(?![0-9a-f]{2})/gi, '%25')); } catch (e) {}
    var found = [], seen = {};
    // Keep first-appearance order (= carousel order). Scan images and videos in one pass.
    var re = new RegExp(IMG_RE.source + '|' + VID_RE.source, 'gi');
    var m;
    while ((m = re.exec(text))) {
      var name = m[0];
      if (seen[name]) continue;
      seen[name] = 1;
      found.push(name);
    }
    return found;
  }

  // The main post's media area — avoids picking up "more posts" thumbnails.
  var SCOPES = ['.swiper-wrapper', '.media-wrap', '.post-media', '.page-post .media', '.page-post .img', '.page-post', 'article', 'main'];

  // textContent drops <br>/<p> breaks, which glues caption lines together.
  function textWithBreaks(el) {
    var clone = el.cloneNode(true);
    clone.querySelectorAll('br').forEach(function (br) { br.replaceWith('\n'); });
    clone.querySelectorAll('p, div, li').forEach(function (b) { b.append('\n'); });
    return clone.textContent.replace(/[ \t]+\n/g, '\n').replace(/\n{3,}/g, '\n\n');
  }

  function text(doc, sels) {
    for (var i = 0; i < sels.length; i++) {
      var el = doc.querySelector(sels[i]);
      if (el && el.textContent.trim()) return textWithBreaks(el).trim();
    }
    return '';
  }

  function meta(doc, name) {
    var el = doc.querySelector('meta[property="' + name + '"], meta[name="' + name + '"]');
    return el ? el.getAttribute('content') || '' : '';
  }

  function num(str) {
    if (!str) return null;
    var m = String(str).replace(/,/g, '').match(/([\d.]+)\s*([kKmM]?)/);
    if (!m) return null;
    var n = parseFloat(m[1]);
    if (/k/i.test(m[2])) n *= 1e3;
    if (/m/i.test(m[2])) n *= 1e6;
    return Math.round(n);
  }

  function dateFromShortcode(code) {
    var abc = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
    var id = 0n;
    for (var c of code.slice(0, 11)) {
      var v = abc.indexOf(c);
      if (v < 0) return null;
      id = id * 64n + BigInt(v);
    }
    var ms = Number((id >> 23n) + IG_EPOCH);
    return ms > 1.3e12 && ms < Date.now() + 864e5 ? new Date(ms).toISOString() : null;
  }

  function findDate(doc, html) {
    var el = doc.querySelector('[data-created], [data-timestamp], time[datetime], [datetime]');
    if (el) {
      var v = el.getAttribute('data-created') || el.getAttribute('data-timestamp') || el.getAttribute('datetime');
      if (/^\d{10}$/.test(v)) return new Date(v * 1000).toISOString();
      if (/^\d{13}$/.test(v)) return new Date(+v).toISOString();
      if (v && !isNaN(Date.parse(v))) return new Date(v).toISOString();
    }
    var m = html.match(/"(?:taken_at(?:_timestamp)?|created_at|uploadDate|datePublished)"\s*:\s*"?([\d\-T:.+Z]+)"?/);
    if (m) {
      if (/^\d{10}$/.test(m[1])) return new Date(m[1] * 1000).toISOString();
      if (!isNaN(Date.parse(m[1]))) return new Date(m[1]).toISOString();
    }
    return null;
  }

  function parsePost(code, html, alt) {
    var doc = new DOMParser().parseFromString(html, 'text/html');

    var media = [], scope = 'document';
    for (var i = 0; i < SCOPES.length && !media.length; i++) {
      var el = doc.querySelector(SCOPES[i]);
      if (el) { media = mediaNames(el.outerHTML); scope = SCOPES[i]; }
    }
    if (!media.length) { media = mediaNames(html); scope = 'document'; }

    var caption = text(doc, ['.desc', '.post-desc', '.caption', '.page-post .text', '[itemprop="caption"]', '[itemprop="description"]'])
      || alt
      || meta(doc, 'og:description').replace(/^.*?:\s*["“]?/, '').replace(/["”]\s*$/, '');

    var likes = num(text(doc, ['.likes', '.like-count', '[class*="like"]']));
    var comments = num(text(doc, ['.comments', '.comment-count', '[class*="comment"] .count']));
    var location = text(doc, ['.location', '.post-location', 'a[href*="/locations/"]', 'a[href*="/explore/locations/"]']);

    var scraped = findDate(doc, html);
    var decoded = dateFromShortcode(code);

    return {
      shortcode: code,
      url: 'https://www.instagram.com/p/' + code + '/',
      caption: caption.replace(/\r/g, '').trim(),
      date: scraped || decoded,
      date_source: scraped ? 'page' : (decoded ? 'shortcode' : null),
      likes: likes,
      comments: comments,
      location: location || null,
      media: media,
      _scope: scope,
    };
  }

  /* ------------------------------------------------------------------ */
  /* 3. Run                                                              */
  /* ------------------------------------------------------------------ */
  log('loading full grid…');
  await loadAll();
  var links = postLinks();
  log('total posts:', links.length, '— already scraped:', Object.keys(state.posts).length);

  for (var i = 0; i < links.length; i++) {
    var p = links[i];
    if (state.posts[p.shortcode]) continue;

    var res, tries = 0;
    while (true) {
      res = await fetch('/p/' + p.shortcode + '/', { credentials: 'include' });
      if (res.ok || ++tries > 3) break;
      log('HTTP', res.status, 'on', p.shortcode, '— backing off', 10 * tries, 's');
      await sleep(10000 * tries);
    }
    if (!res.ok) { log('giving up on', p.shortcode, '(run it again later to resume)'); continue; }

    var html = await res.text();
    if (/Just a moment|challenge-platform/i.test(html) && !/\/p\//.test(html.slice(0, 2000))) {
      log('Cloudflare challenge returned — reload the page, pass the check, then run it again to resume.');
      break;
    }

    var post = parsePost(p.shortcode, html, p.alt);
    state.posts[p.shortcode] = post;
    save();

    if (i === 0 || !post.media.length || !post.caption) {
      // Show anything suspicious so selectors can be tuned.
      console.log('[igScrape] sample/suspect', post);
      if (!post.media.length) window.igLastHtml = html; // inspect with copy(igLastHtml)
    }
    log((i + 1) + '/' + links.length, p.shortcode, post.media.length + ' media', post.caption.slice(0, 40));
    await sleep(DELAY_MS);
  }

  /* ------------------------------------------------------------------ */
  /* 4. Clean up + download                                              */
  /* ------------------------------------------------------------------ */
  var order = links.map(function (l) { return l.shortcode; });
  var posts = order.filter(function (c) { return state.posts[c]; }).map(function (c) { return state.posts[c]; });

  // Drop filenames that show up on lots of posts (profile picture, sidebar thumbs).
  var freq = {};
  posts.forEach(function (p) { p.media.forEach(function (m) { freq[m] = (freq[m] || 0) + 1; }); });
  var limit = Math.max(2, Math.ceil(posts.length * 0.2));
  posts.forEach(function (p) { p.media = p.media.filter(function (m) { return freq[m] < limit; }); });

  var out = {
    source: location.href,
    username: location.pathname.replace(/\//g, ''),
    scraped_at: new Date().toISOString(),
    count: posts.length,
    posts: posts,
  };
  var blob = new Blob([JSON.stringify(out, null, 2)], { type: 'application/json' });
  var a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'posts.json';
  document.body.appendChild(a);
  a.click();
  a.remove();

  var noMedia = posts.filter(function (p) { return !p.media.length; }).length;
  var noCap = posts.filter(function (p) { return !p.caption; }).length;
  log('done —', posts.length, 'posts.', noMedia, 'without media,', noCap, 'without caption. posts.json downloaded.');
  window.__igScrapeRunning = false;
  var close = document.createElement('button');
  close.textContent = 'Close';
  close.style.cssText = 'display:block;margin-top:8px;padding:4px 10px;border:0;border-radius:6px;background:#0095f6;color:#fff;font:inherit;cursor:pointer';
  close.onclick = function () { box.remove(); };
  box.appendChild(close);
})();
