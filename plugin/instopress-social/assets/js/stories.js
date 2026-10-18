/**
 * Story viewer.
 *
 * Groups come from <script id="iar-stories-data"> (templates/parts/story-viewer.php):
 *   { key: { user: { username, avatar, profile }, stories: [ { id, type, src, poster, text, link, duration, date } ] } }
 * Anything with [data-story-open="key"] opens that group; when a group ends the
 * next ring on the page plays. Seen stories are remembered in
 * localStorage so rings turn grey.
 */
(function () {
  'use strict';

  var dataEl = document.getElementById('iar-stories-data');
  var viewer = document.querySelector('[data-story-viewer]');
  if (!dataEl || !viewer) return;

  var groups = {};
  try { groups = JSON.parse(dataEl.textContent) || {}; } catch (e) { return; }

  var SEEN_KEY = 'iar_seen_stories';
  var HOLD_MS = 220;
  var MAX_VIDEO = 60;

  var $ = function (sel) { return viewer.querySelector(sel); };
  var bars = $('[data-story-bars]'), media = $('[data-story-media]');
  var userLink = $('[data-story-user]'), textEl = $('[data-story-text]'), linkEl = $('[data-story-link]');
  var muteBtn = $('[data-story-mute]'), pauseBtn = $('[data-story-pause]');

  var state = { order: [], g: -1, i: 0, started: 0, elapsed: 0, length: 5000, paused: false, raf: 0, video: null, muted: true, holdTimer: 0, held: false };

  /* ---------------- seen state ---------------- */
  function seenSet() {
    try { return new Set(JSON.parse(localStorage.getItem(SEEN_KEY) || '[]')); } catch (e) { return new Set(); }
  }
  function markSeen(id) {
    try {
      var s = Array.from(seenSet());
      if (s.indexOf(id) < 0) s.push(id);
      localStorage.setItem(SEEN_KEY, JSON.stringify(s.slice(-500)));
    } catch (e) {}
  }
  function refreshRings() {
    var seen = seenSet();
    document.querySelectorAll('[data-story-open]').forEach(function (el) {
      var ids = (el.getAttribute('data-story-ids') || '').split(' ').filter(Boolean);
      var all = ids.length && ids.every(function (id) { return seen.has(id); });
      var ring = el.classList.contains('has-story') ? el : el.querySelector('.has-story');
      if (ring) ring.classList.toggle('is-seen', all);
    });
  }

  /* ---------------- helpers ---------------- */
  function ago(iso) {
    var s = (Date.now() - new Date(iso).getTime()) / 1000;
    if (s < 3600) return Math.max(1, Math.floor(s / 60)) + 'm';
    return Math.floor(s / 3600) + 'h';
  }
  function hostOf(url) {
    try { return new URL(url).hostname.replace(/^www\./, ''); } catch (e) { return url; }
  }
  function current() {
    var g = groups[state.order[state.g]];
    return g ? { group: g, story: g.stories[state.i] } : null;
  }

  /* ---------------- playback ---------------- */
  function setBar(i, fraction) {
    var fill = bars.children[i] && bars.children[i].firstChild;
    if (fill) fill.style.transform = 'scaleX(' + Math.max(0, Math.min(1, fraction)) + ')';
  }

  function tick() {
    if (!state.paused) {
      var now = performance.now();
      var t = state.video ? state.video.currentTime * 1000 : state.elapsed + (now - state.started);
      setBar(state.i, t / state.length);
      if (t >= state.length) { next(); return; }
    }
    state.raf = requestAnimationFrame(tick);
  }

  function startClock(lengthMs) {
    state.length = lengthMs;
    state.elapsed = 0;
    state.started = performance.now();
    cancelAnimationFrame(state.raf);
    state.raf = requestAnimationFrame(tick);
  }

  function pause(on) {
    if (on === state.paused) return;
    state.paused = on;
    if (on) {
      state.elapsed += performance.now() - state.started;
      if (state.video) state.video.pause();
    } else {
      state.started = performance.now();
      if (state.video) state.video.play().catch(function () {});
    }
    pauseBtn.textContent = on ? '▶' : '❚❚';
    viewer.classList.toggle('is-paused', on);
  }

  function show() {
    var c = current();
    if (!c) { close(); return; }
    var g = c.group, s = c.story;

    // Progress bars for this group
    bars.innerHTML = '';
    g.stories.forEach(function (_, n) {
      var bar = document.createElement('span');
      bar.className = 'story-viewer__bar';
      var fill = document.createElement('span');
      fill.style.transform = 'scaleX(' + (n < state.i ? 1 : 0) + ')';
      bar.appendChild(fill);
      bars.appendChild(bar);
    });

    userLink.href = g.user.profile || '#';
    userLink.querySelector('img').src = g.user.avatar || '';
    userLink.querySelector('.story-viewer__name').textContent = g.user.username || '';
    userLink.querySelector('.story-viewer__time').textContent = ago(s.date);

    textEl.hidden = !s.text;
    textEl.textContent = s.text || '';
    linkEl.hidden = !s.link;
    if (s.link) { linkEl.href = s.link; linkEl.querySelector('span').textContent = hostOf(s.link); }

    // Media
    cancelAnimationFrame(state.raf);
    if (state.video) { state.video.pause(); state.video = null; }
    media.innerHTML = '';
    state.paused = false;
    viewer.classList.remove('is-paused');
    pauseBtn.textContent = '❚❚';
    viewer.classList.add('is-loading');

    if (s.type === 'video') {
      var v = document.createElement('video');
      v.src = s.src;
      if (s.poster) v.poster = s.poster;
      v.playsInline = true;
      v.muted = state.muted;
      v.autoplay = true;
      v.preload = 'auto';
      v.addEventListener('loadedmetadata', function () {
        viewer.classList.remove('is-loading');
        startClock(Math.min(v.duration || 15, MAX_VIDEO) * 1000);
      }, { once: true });
      v.addEventListener('error', function () { viewer.classList.remove('is-loading'); startClock(3000); }, { once: true });
      media.appendChild(v);
      state.video = v;
      muteBtn.hidden = false;
      muteBtn.textContent = state.muted ? '🔇' : '🔊';
      v.play().catch(function () {});
    } else {
      var img = new Image();
      img.alt = '';
      img.decoding = 'async';
      img.onload = function () { viewer.classList.remove('is-loading'); startClock((s.duration || 5) * 1000); };
      img.onerror = function () { viewer.classList.remove('is-loading'); startClock(2000); };
      img.src = s.src;
      media.appendChild(img);
      muteBtn.hidden = true;
    }

    markSeen(s.id);
    preloadNext();
  }

  function preloadNext() {
    var g = groups[state.order[state.g]];
    var nxt = g && g.stories[state.i + 1];
    if (!nxt && state.order[state.g + 1]) nxt = groups[state.order[state.g + 1]].stories[0];
    if (nxt && nxt.type === 'image') { var p = new Image(); p.src = nxt.src; }
  }

  function next() {
    var g = groups[state.order[state.g]];
    if (g && state.i < g.stories.length - 1) { state.i++; show(); return; }
    if (state.g < state.order.length - 1) { state.g++; state.i = firstUnseen(state.order[state.g]); show(); return; }
    close();
  }

  function prev() {
    if (state.i > 0) { state.i--; show(); return; }
    if (state.g > 0) { state.g--; state.i = 0; show(); return; }
    show(); // restart the first story
  }

  function firstUnseen(key) {
    var seen = seenSet(), list = groups[key].stories;
    for (var n = 0; n < list.length; n++) if (!seen.has(list[n].id)) return n;
    return 0;
  }

  /* ---------------- open / close ---------------- */
  var lastFocus = null;

  function open(key) {
    // Play in the order the rings appear on the page, starting from this one.
    var keys = [];
    document.querySelectorAll('[data-story-open]').forEach(function (el) {
      var k = el.getAttribute('data-story-open');
      if (groups[k] && keys.indexOf(k) < 0) keys.push(k);
    });
    if (keys.indexOf(key) < 0) keys.unshift(key);
    state.order = keys;
    state.g = keys.indexOf(key);
    state.i = firstUnseen(key);
    lastFocus = document.activeElement;
    viewer.hidden = false;
    document.body.classList.add('story-open');
    show();
    $('[data-story-close]').focus();
  }

  function close() {
    cancelAnimationFrame(state.raf);
    if (state.video) { state.video.pause(); state.video = null; }
    media.innerHTML = '';
    viewer.hidden = true;
    document.body.classList.remove('story-open');
    refreshRings();
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  /* ---------------- input ---------------- */
  document.addEventListener('click', function (e) {
    var opener = e.target.closest('[data-story-open]');
    if (opener && groups[opener.getAttribute('data-story-open')]) {
      e.preventDefault();
      open(opener.getAttribute('data-story-open'));
    }
  });

  $('[data-story-close]').addEventListener('click', close);
  pauseBtn.addEventListener('click', function () { pause(!state.paused); });
  muteBtn.addEventListener('click', function () {
    state.muted = !state.muted;
    if (state.video) state.video.muted = state.muted;
    muteBtn.textContent = state.muted ? '🔇' : '🔊';
  });

  // Tap left/right; press and hold anywhere on the story to pause.
  ['prev', 'next'].forEach(function (dir) {
    var zone = $('[data-story-' + dir + ']');
    zone.addEventListener('pointerdown', function () {
      state.held = false;
      state.holdTimer = setTimeout(function () { state.held = true; pause(true); }, HOLD_MS);
    });
    zone.addEventListener('pointerup', function () {
      clearTimeout(state.holdTimer);
      if (state.held) { pause(false); return; }
      dir === 'prev' ? prev() : next();
    });
    zone.addEventListener('pointerleave', function () {
      clearTimeout(state.holdTimer);
      if (state.held) { state.held = false; pause(false); }
    });
    zone.addEventListener('contextmenu', function (e) { e.preventDefault(); });
  });

  // Swipe down to close.
  var touchY = null;
  viewer.addEventListener('touchstart', function (e) { touchY = e.touches[0].clientY; }, { passive: true });
  viewer.addEventListener('touchend', function (e) {
    if (touchY !== null && e.changedTouches[0].clientY - touchY > 90) close();
    touchY = null;
  });

  // Click on the dark backdrop (outside the frame) closes on desktop.
  viewer.addEventListener('click', function (e) { if (e.target === viewer) close(); });

  document.addEventListener('keydown', function (e) {
    if (viewer.hidden) return;
    if (e.key === 'Escape') close();
    else if (e.key === 'ArrowRight') next();
    else if (e.key === 'ArrowLeft') prev();
    else if (e.key === ' ') { e.preventDefault(); pause(!state.paused); }
  });

  document.addEventListener('visibilitychange', function () {
    if (!viewer.hidden && document.hidden) pause(true);
  });

  refreshRings();
})();
