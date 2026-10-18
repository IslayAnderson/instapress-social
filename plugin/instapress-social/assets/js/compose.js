/**
 * Phone-first composer for /gram/new/ (templates/compose.php).
 *
 * Pick photos/videos → reorder → choose a shape → caption → Share.
 * Files upload one by one (XHR, for progress) to iar_compose_upload; each
 * video first gets a poster frame captured here and uploaded with it. Then
 * iar_compose_publish creates the post (or story) and we go to it.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-compose]');
  if (!root) return;

  var $ = function (s) { return root.querySelector(s); };
  var $$ = function (s) { return Array.prototype.slice.call(root.querySelectorAll(s)); };

  var AJAX = root.dataset.ajax;
  var NONCE = root.dataset.nonce;
  var MAX_UPLOAD = parseInt(root.dataset.maxUpload, 10) || 0;
  var MAX_ITEMS = 20;
  var RATIOS = { 'auto': null, '1:1': 1, '4:5': 0.8, '3:4': 0.75, '1.91:1': 1.91, '9:16': 0.5625 };

  var state = { kind: root.dataset.kind === 'story' ? 'story' : 'post', items: [], current: 0, ratio: 'auto', busy: false, done: false };
  var input = $('[data-file-input]');

  /* ---------------- helpers ---------------- */
  function fmtSize(b) { return b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.round(b / 1024) + ' KB'; }

  function naturalRatio(item) {
    return new Promise(function (resolve) {
      if (item.ratio) return resolve(item.ratio);
      if (item.type === 'video') {
        var v = document.createElement('video');
        v.preload = 'metadata'; v.muted = true; v.src = item.url;
        v.onloadedmetadata = function () { item.ratio = v.videoWidth && v.videoHeight ? v.videoWidth / v.videoHeight : 1; resolve(item.ratio); };
        v.onerror = function () { item.ratio = 1; resolve(1); };
      } else {
        var img = new Image();
        img.onload = function () { item.ratio = img.naturalWidth / img.naturalHeight || 1; resolve(item.ratio); };
        img.onerror = function () { item.ratio = 1; resolve(1); };
        img.src = item.url;
      }
    });
  }

  // The frame shape: story = 9:16; chosen ratio; or Auto from the first item (4:5…1.91:1, videos down to 9:16).
  function frameRatio() {
    if (state.kind === 'story') return 0.5625;
    if (RATIOS[state.ratio]) return RATIOS[state.ratio];
    var first = state.items[0];
    if (!first || !first.ratio) return 1;
    var min = first.type === 'video' ? 0.5625 : 0.8;
    return Math.max(min, Math.min(1.91, first.ratio));
  }

  /* ---------------- rendering ---------------- */
  function render() {
    var has = state.items.length > 0;
    $('[data-empty]').hidden = has;
    $('[data-editor]').hidden = !has;
    $('[data-share]').disabled = !has || state.busy;
    $$('[data-for]').forEach(function (el) { el.hidden = el.dataset.for !== state.kind; });
    $$('[data-kind-btn]').forEach(function (b) { b.setAttribute('aria-selected', b.dataset.kindBtn === state.kind ? 'true' : 'false'); });
    $('[data-hint-post]').hidden = state.kind !== 'post';
    $('[data-hint-story]').hidden = state.kind !== 'story';
    $('[data-pick-label]').textContent = state.kind === 'story' ? 'Choose a photo or video' : 'Choose photos & videos';
    input.multiple = state.kind === 'post';
    $$('[data-ratio]').forEach(function (c) { c.setAttribute('aria-pressed', c.dataset.ratio === state.ratio ? 'true' : 'false'); });
    if (!has) return;

    state.current = Math.min(state.current, state.items.length - 1);

    // Preview
    var preview = $('[data-preview]');
    preview.style.setProperty('--ar', frameRatio());
    var item = state.items[state.current];
    preview.innerHTML = '';
    var el;
    if (item.type === 'video') {
      el = document.createElement('video');
      el.src = item.url; el.muted = true; el.playsInline = true; el.loop = true; el.autoplay = true; el.controls = false;
    } else {
      el = document.createElement('img');
      el.src = item.url; el.alt = '';
    }
    preview.appendChild(el);
    if (state.items.length > 1) {
      var dots = document.createElement('div');
      dots.className = 'carousel__dots';
      state.items.forEach(function (_, n) { var d = document.createElement('span'); d.className = 'carousel__dot' + (n === state.current ? ' is-active' : ''); dots.appendChild(d); });
      preview.appendChild(dots);
    }

    // Thumbnails
    var strip = $('[data-strip]');
    strip.innerHTML = '';
    state.items.forEach(function (it, n) {
      var li = document.createElement('li');
      li.className = 'compose__thumb' + (n === state.current ? ' is-current' : '');
      li.innerHTML =
        '<button type="button" class="compose__thumb-pick" data-select="' + n + '" aria-label="Show item ' + (n + 1) + '"></button>' +
        (state.items.length > 1 ? '<span class="compose__num">' + (n + 1) + '</span>' : '') +
        '<button type="button" class="compose__x" data-remove="' + n + '" aria-label="Remove">×</button>' +
        (state.kind === 'post' && state.items.length > 1
          ? '<span class="compose__move">' +
              (n > 0 ? '<button type="button" data-move="' + n + '" data-dir="-1" aria-label="Move earlier">‹</button>' : '<span></span>') +
              (n < state.items.length - 1 ? '<button type="button" data-move="' + n + '" data-dir="1" aria-label="Move later">›</button>' : '<span></span>') +
            '</span>'
          : '');
      var media = it.type === 'video' ? document.createElement('video') : document.createElement('img');
      media.src = it.url;
      if (it.type === 'video') { media.muted = true; media.preload = 'metadata'; } else { media.alt = ''; }
      li.querySelector('.compose__thumb-pick').appendChild(media);
      if (it.type === 'video') li.insertAdjacentHTML('beforeend', '<span class="compose__vid">▶</span>');
      strip.appendChild(li);
    });
    if (state.kind === 'post' && state.items.length < MAX_ITEMS) {
      var add = document.createElement('li');
      add.className = 'compose__thumb compose__thumb--add';
      add.innerHTML = '<button type="button" data-pick aria-label="Add more">+</button>';
      strip.appendChild(add);
    }
  }

  /* ---------------- picking ---------------- */
  function addFiles(files) {
    var skipped = [];
    Array.prototype.forEach.call(files, function (file) {
      var type = /^video\//.test(file.type) ? 'video' : (/^image\//.test(file.type) ? 'image' : '');
      if (!type) { skipped.push(file.name + ' (not a photo or video)'); return; }
      if (type === 'video' && MAX_UPLOAD && file.size > MAX_UPLOAD) { skipped.push(file.name + ' (' + fmtSize(file.size) + ' — this site accepts up to ' + fmtSize(MAX_UPLOAD) + ')'); return; }
      if (state.kind === 'story') { state.items.forEach(function (it) { URL.revokeObjectURL(it.url); }); state.items = []; }
      if (state.items.length >= MAX_ITEMS) { skipped.push(file.name + ' (' + MAX_ITEMS + ' max)'); return; }
      state.items.push({ file: file, type: type, url: URL.createObjectURL(file), ratio: null });
    });
    if (skipped.length) window.alert('Skipped:\n' + skipped.join('\n'));
    Promise.all(state.items.map(naturalRatio)).then(render);
    render();
  }

  input.addEventListener('change', function () { addFiles(input.files); input.value = ''; });

  root.addEventListener('click', function (e) {
    var t;
    if ((t = e.target.closest('[data-pick]'))) { input.click(); return; }
    if ((t = e.target.closest('[data-select]'))) { state.current = +t.dataset.select; render(); return; }
    if ((t = e.target.closest('[data-remove]'))) {
      var n = +t.dataset.remove;
      URL.revokeObjectURL(state.items[n].url);
      state.items.splice(n, 1);
      if (state.current >= n && state.current > 0) state.current--;
      render();
      return;
    }
    if ((t = e.target.closest('[data-move]'))) {
      var i = +t.dataset.move, j = i + (+t.dataset.dir);
      var tmp = state.items[i]; state.items[i] = state.items[j]; state.items[j] = tmp;
      state.current = j;
      render();
      return;
    }
    if ((t = e.target.closest('[data-ratio]'))) { state.ratio = t.dataset.ratio; render(); return; }
    if ((t = e.target.closest('[data-kind-btn]'))) {
      state.kind = t.dataset.kindBtn;
      if (state.kind === 'story' && state.items.length > 1) {
        var keep = state.items[state.current];
        state.items.forEach(function (it) { if (it !== keep) URL.revokeObjectURL(it.url); });
        state.items = [keep];
        state.current = 0;
      }
      history.replaceState(null, '', state.kind === 'story' ? '?type=story' : location.pathname);
      render();
      return;
    }
    if (e.target.closest('[data-share]')) share();
  });

  // Swipe the preview to step through a carousel.
  var startX = null;
  $('[data-preview]').addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
  $('[data-preview]').addEventListener('touchend', function (e) {
    if (startX === null) return;
    var dx = e.changedTouches[0].clientX - startX;
    if (Math.abs(dx) > 40) { state.current = Math.max(0, Math.min(state.items.length - 1, state.current + (dx < 0 ? 1 : -1))); render(); }
    startX = null;
  });

  window.addEventListener('beforeunload', function (e) {
    if (state.items.length && !state.done) { e.preventDefault(); e.returnValue = ''; }
  });

  /* ---------------- sharing ---------------- */
  var MAX_EDGE = 2160; // px — twice Instagram's 1080, plenty for any screen

  // Full-size camera photos (12–48 MP, sometimes HEIC) can be too heavy for the
  // server to resize. Send a JPEG no bigger than MAX_EDGE instead, unless the
  // original is already small. Animated GIFs and anything we can't decode go as they are.
  async function shrinkImage(file) {
    if (file.type === 'image/gif' || !window.createImageBitmap) return file;
    var bmp;
    try { bmp = await createImageBitmap(file, { imageOrientation: 'from-image' }); } catch (e) { return file; }
    var scale = Math.min(1, MAX_EDGE / Math.max(bmp.width, bmp.height));
    var plainJpeg = /^image\/jpe?g$/.test(file.type);
    if (scale === 1 && plainJpeg && file.size < 4 * 1048576) { bmp.close && bmp.close(); return file; }
    var c = document.createElement('canvas');
    c.width = Math.round(bmp.width * scale); c.height = Math.round(bmp.height * scale);
    c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
    bmp.close && bmp.close();
    var blob = await new Promise(function (r) { c.toBlob(r, 'image/jpeg', 0.9); });
    if (!blob) return file;
    var name = (file.name || 'photo').replace(/\.[^.]+$/, '') + '.jpg';
    try { return new File([blob], name, { type: 'image/jpeg' }); } catch (e) { blob.name = name; return blob; }
  }

  function posterFrame(url) {
    return new Promise(function (resolve) {
      var v = document.createElement('video');
      v.muted = true; v.playsInline = true; v.preload = 'auto';
      var t = setTimeout(function () { resolve(null); }, 15000);
      v.onloadedmetadata = function () { v.currentTime = Math.min(0.5, (v.duration || 1) / 2); };
      v.onseeked = function () {
        var scale = Math.min(1, 1080 / Math.max(v.videoWidth, v.videoHeight));
        var c = document.createElement('canvas');
        c.width = Math.round(v.videoWidth * scale); c.height = Math.round(v.videoHeight * scale);
        try {
          c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
          c.toBlob(function (b) { clearTimeout(t); resolve(b); }, 'image/jpeg', 0.86);
        } catch (err) { clearTimeout(t); resolve(null); }
      };
      v.onerror = function () { clearTimeout(t); resolve(null); };
      v.src = url;
    });
  }

  function post(fields, onProgress, what) {
    return new Promise(function (resolve, reject) {
      var fd = new FormData();
      fd.append('_ajax_nonce', NONCE);
      Object.keys(fields).forEach(function (k) {
        var v = fields[k];
        if (Array.isArray(v)) v.forEach(function (x) { fd.append(k + '[]', x); });
        else if (v && v.blob) fd.append(k, v.blob, v.name);
        else fd.append(k, v);
      });
      var xhr = new XMLHttpRequest();
      xhr.open('POST', AJAX);
      xhr.withCredentials = true;
      if (onProgress) xhr.upload.onprogress = function (e) { if (e.lengthComputable) onProgress(e.loaded / e.total); };
      xhr.onload = function () {
        var res = null;
        try { res = JSON.parse(xhr.responseText); } catch (err) {}
        if (res && res.success) resolve(res.data);
        else reject(new Error((res && res.data) || (xhr.status === 413 ? 'That file is too big for this site.' : (what || 'Upload') + ' failed (HTTP ' + xhr.status + ').')));
      };
      xhr.onerror = function () { reject(new Error('Network error — check your connection and try again.')); };
      xhr.send(fd);
    });
  }

  function progress(text, fraction) {
    $('[data-progress]').hidden = false;
    $('[data-progress-text]').textContent = text;
    $('[data-progress-fill]').style.width = Math.round(Math.max(0, Math.min(1, fraction)) * 100) + '%';
  }

  async function share() {
    if (state.busy || !state.items.length) return;
    state.busy = true;
    render();
    var items = state.kind === 'story' ? state.items.slice(0, 1) : state.items.slice();
    var total = items.reduce(function (s, it) { return s + it.file.size; }, 0) || 1;
    var done = 0, ids = [];
    try {
      for (var i = 0; i < items.length; i++) {
        var it = items[i];
        var label = items.length > 1 ? 'Uploading ' + (i + 1) + ' of ' + items.length + '…' : 'Uploading…';
        var poster = it.type === 'video' ? await posterFrame(it.url) : null;
        var file = it.type === 'image' ? await shrinkImage(it.file) : it.file;
        if (MAX_UPLOAD && file.size > MAX_UPLOAD) throw new Error((it.file.name || 'A file') + ' is ' + fmtSize(file.size) + ' — this site accepts up to ' + fmtSize(MAX_UPLOAD) + '.');
        var up = await post({ action: 'iar_compose_upload', file: { blob: file, name: file.name || ('upload.' + (it.type === 'video' ? 'mp4' : 'jpg')) } }, function (f) {
          progress(label, (done + f * it.file.size) / total * 0.95);
        }, 'Uploading ' + (it.file.name || 'a file'));
        if (poster) {
          await post({ action: 'iar_compose_upload', poster_for: up.id, file: { blob: poster, name: 'poster.jpg' } }, null, 'Saving the video poster');
        }
        ids.push(up.id);
        done += it.file.size;
        progress(label, done / total * 0.95);
      }
      progress('Publishing…', 0.97);
      var fields = { action: 'iar_compose_publish', kind: state.kind, media: ids };
      if (state.kind === 'post') {
        fields.caption = root.querySelector('[name="caption"]').value;
        fields.location = root.querySelector('[name="location"]').value;
        fields.ratio = state.ratio;
      } else {
        fields.story_text = root.querySelector('[name="story_text"]').value;
        fields.story_link = root.querySelector('[name="story_link"]').value;
      }
      var res = await post(fields, null, 'Publishing');
      progress('Shared!', 1);
      state.done = true;
      location.href = res.redirect;
    } catch (err) {
      $('[data-progress]').hidden = true;
      state.busy = false;
      render();
      window.alert(err.message || 'Something went wrong — please try again.');
    }
  }

  render();
})();
