/**
 * Phone-first composer for /gram/new/ (templates/compose.php).
 *
 * Pick photos/videos → reorder → choose a shape → caption → Share.
 * Files upload one by one (XHR, for progress) to iar_compose_upload; each
 * video first gets a poster frame captured here and uploaded with it. Then
 * iar_compose_publish creates the post (or story) and we go to it.
 * Text posts upload nothing: the words and their look are sent with publish,
 * and the card is drawn here exactly as includes/text-posts.php draws it.
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

  var PRESETS = JSON.parse(root.dataset.textPresets || '{}');
  var KINDS = ['post', 'text', 'story'];
  var state = {
    kind: KINDS.indexOf(root.dataset.kind) >= 0 ? root.dataset.kind : 'post', items: [], current: 0, ratio: 'auto', busy: false, done: false,
    text: { scale: 100, font: 'sans', align: 'center', color: '', bg: 'sunset', bg_color: '' }
  };
  var editor = $('[data-text-input]');
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

  /* ---------------- text cards ---------------- */
  function textPlain() { return (editor.innerText || '').replace(/\u00a0/g, ' ').trim(); }
  function hasText() { return textPlain() !== ''; }

  function contrast(hex) {
    var h = hex.replace('#', '');
    if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
    var r = parseInt(h.substr(0, 2), 16), g = parseInt(h.substr(2, 2), 16), b = parseInt(h.substr(4, 2), 16);
    return (0.299 * r + 0.587 * g + 0.114 * b) > 160 ? '#111111' : '#ffffff';
  }

  // Same steps as iar_text_base_size(): shorter text, bigger letters; taller cards a little bigger.
  function baseSize(length, height) {
    var steps = [[30, 104], [80, 84], [160, 68], [300, 54], [500, 44]], size = 36;
    for (var i = 0; i < steps.length; i++) { if (length <= steps[i][0]) { size = steps[i][1]; break; } }
    return size * Math.max(0.85, Math.min(1.3, Math.sqrt(height / 1080)));
  }

  function textRatio() { return RATIOS[state.ratio] || 1; }

  // Mirrors iar_text_render_svg() (context "full").
  function cardSVG(html, plain, st, ratio) {
    var w = 1080, h = Math.round(w / Math.max(0.5, Math.min(2, ratio || 1)));
    var font = PRESETS.fonts[st.font] || PRESETS.fonts.sans;
    var bg = st.bg === 'custom' ? { colors: [st.bg_color], ink: contrast(st.bg_color) } : PRESETS.backgrounds[st.bg];
    var ink = st.color || bg.ink;
    var size = Math.round(baseSize(plain.length, h) * st.scale / 100 * 10) / 10;
    var id = 'iar-tg-preview', c = '.iar-tx.' + id, defs = '', fill = bg.colors[0];
    if (bg.colors.length > 1) {
      var dirs = { diagonal: 'x1="0" y1="0" x2="1" y2="1"', vertical: 'x1="0" y1="0" x2="0" y2="1"', horizontal: 'x1="0" y1="0" x2="1" y2="0"' };
      var stops = bg.colors.map(function (col, i) { return '<stop offset="' + Math.round(i / (bg.colors.length - 1) * 1000) / 1000 + '" stop-color="' + col + '"/>'; }).join('');
      defs = '<defs><linearGradient id="' + id + '" ' + (dirs[bg.dir] || dirs.diagonal) + '>' + stops + '</linearGradient></defs>';
      fill = 'url(#' + id + ')';
    }
    var css = c + '{box-sizing:border-box;width:' + w + 'px;height:' + h + 'px;padding:96px;display:flex;flex-direction:column;justify-content:center;overflow:hidden;' +
      'color:' + ink + ';font-family:' + font.stack + ';font-weight:' + font.weight + ';font-size:' + size + 'px;line-height:1.3;text-align:' + st.align + ';overflow-wrap:break-word;word-wrap:break-word}' +
      c + ' *{font-family:inherit;color:inherit;letter-spacing:normal;text-transform:none;font-size:inherit;line-height:inherit;background:none}' +
      c + '>*{margin:0 0 .45em}' + c + '>*:last-child{margin-bottom:0}' +
      c + ' h2{font-size:1.45em;line-height:1.12;font-weight:800}' + c + ' h3{font-size:1.2em;line-height:1.15;font-weight:800}' +
      c + ' strong,' + c + ' b{font-weight:800}' + c + ' a{color:inherit;text-decoration:underline}' +
      c + ' blockquote{border-' + (st.align === 'right' ? 'right' : 'left') + ':.12em solid currentColor;padding:0 .6em;font-style:italic}' +
      c + ' ul,' + c + ' ol{padding-left:1.2em;text-align:left}' + c + ' li{margin:.1em 0}';
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + w + ' ' + h + '" class="iar-text-card">' + defs +
      '<rect width="' + w + '" height="' + h + '" fill="' + fill + '"/>' +
      '<foreignObject x="0" y="0" width="' + w + '" height="' + h + '"><div xmlns="http://www.w3.org/1999/xhtml" class="iar-tx ' + id + '"><style>' + css + '</style>' + html + '</div></foreignObject></svg>';
  }

  function renderText() {
    var st = state.text, preview = $('[data-text-preview]');
    preview.style.setProperty('--ar', textRatio());
    var html = hasText() ? editor.innerHTML : '<p style="opacity:.55">' + (editor.dataset.placeholder || '') + '</p>';
    preview.innerHTML = cardSVG(html, textPlain() || editor.dataset.placeholder || '', st, textRatio());
    $$('[data-text-bg]').forEach(function (b) { b.setAttribute('aria-pressed', st.bg === b.dataset.textBg ? 'true' : 'false'); });
    root.querySelector('[data-text-bg-custom]').parentNode.setAttribute('aria-pressed', st.bg === 'custom' ? 'true' : 'false');
    $$('[data-text-ink]').forEach(function (b) { b.setAttribute('aria-pressed', st.color === b.dataset.textInk ? 'true' : 'false'); });
    var customInk = st.color && !$$('[data-text-ink]').some(function (b) { return b.dataset.textInk === st.color; });
    root.querySelector('[data-text-ink-custom]').parentNode.setAttribute('aria-pressed', customInk ? 'true' : 'false');
    $$('[data-text-font]').forEach(function (b) { b.setAttribute('aria-pressed', st.font === b.dataset.textFont ? 'true' : 'false'); });
    $$('[data-text-align]').forEach(function (b) { b.setAttribute('aria-pressed', st.align === b.dataset.textAlign ? 'true' : 'false'); });
    $('[data-text-scale]').value = st.scale;
    $('[data-text-scale-label]').textContent = st.scale + '%';
  }

  /* ---------------- rendering ---------------- */
  function render() {
    var isText = state.kind === 'text';
    var has = state.items.length > 0;
    $('[data-empty]').hidden = isText || has;
    $('[data-editor]').hidden = !isText && !has;
    $('[data-share]').disabled = state.busy || (isText ? !hasText() : !has);
    $$('[data-for]').forEach(function (el) { el.hidden = el.dataset.for.split(' ').indexOf(state.kind) < 0; });
    $$('[data-kind-btn]').forEach(function (b) { b.setAttribute('aria-selected', b.dataset.kindBtn === state.kind ? 'true' : 'false'); });
    $('[data-hint-post]').hidden = state.kind !== 'post';
    $('[data-hint-story]').hidden = state.kind !== 'story';
    $('[data-pick-label]').textContent = state.kind === 'story' ? 'Choose a photo or video' : 'Choose photos & videos';
    input.multiple = state.kind === 'post';
    $$('[data-ratio]').forEach(function (c) { c.setAttribute('aria-pressed', c.dataset.ratio === state.ratio ? 'true' : 'false'); });
    if (isText) { renderText(); return; }
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
    if ((t = e.target.closest('[data-text-bg]'))) { state.text.bg = t.dataset.textBg; renderText(); return; }
    if ((t = e.target.closest('[data-text-ink]'))) { state.text.color = t.dataset.textInk; renderText(); return; }
    if ((t = e.target.closest('[data-text-font]'))) { state.text.font = t.dataset.textFont; renderText(); return; }
    if ((t = e.target.closest('[data-text-align]'))) { state.text.align = t.dataset.textAlign; renderText(); return; }
    if ((t = e.target.closest('[data-cmd]'))) { format(t.dataset.cmd); return; }
    if ((t = e.target.closest('[data-kind-btn]'))) {
      state.kind = t.dataset.kindBtn;
      if (state.kind === 'story' && state.items.length > 1) {
        var keep = state.items[state.current];
        state.items.forEach(function (it) { if (it !== keep) URL.revokeObjectURL(it.url); });
        state.items = [keep];
        state.current = 0;
      }
      history.replaceState(null, '', state.kind === 'post' ? location.pathname : '?type=' + state.kind);
      render();
      return;
    }
    if (e.target.closest('[data-share]')) share();
  });

  /* ---------------- rich text ---------------- */
  try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (err) {}

  function currentBlock() {
    var sel = window.getSelection();
    var n = sel && sel.anchorNode;
    while (n && n !== editor) { if (n.nodeType === 1 && /^(H2|BLOCKQUOTE|P|DIV|LI)$/.test(n.nodeName)) return n.nodeName; n = n.parentNode; }
    return '';
  }

  function format(cmd) {
    editor.focus();
    if (cmd === 'h2' || cmd === 'blockquote') {
      // Toggle: applying it again goes back to a normal paragraph.
      document.execCommand('formatBlock', false, currentBlock() === cmd.toUpperCase() ? 'p' : cmd);
    } else if (cmd === 'createLink') {
      var url = window.prompt('Link address', 'https://');
      if (!url || url === 'https://') return;
      if (!/^(https?:|mailto:)/i.test(url)) url = 'https://' + url;
      document.execCommand('createLink', false, url);
    } else if (cmd === 'removeFormat') {
      document.execCommand('removeFormat');
      document.execCommand('unlink');
      document.execCommand('formatBlock', false, 'p');
    } else {
      document.execCommand(cmd);
    }
    render();
  }

  // Keep the selection when pressing toolbar buttons.
  root.querySelector('.compose__toolbar').addEventListener('mousedown', function (e) { if (e.target.closest('[data-cmd]')) e.preventDefault(); });
  editor.addEventListener('input', render);
  // Pasted text arrives plain (no outside fonts, colours or sizes).
  editor.addEventListener('paste', function (e) {
    e.preventDefault();
    var text = (e.clipboardData || window.clipboardData).getData('text/plain');
    document.execCommand('insertText', false, text);
  });
  editor.addEventListener('keydown', function (e) {
    if ((e.metaKey || e.ctrlKey) && /^[biu]$/i.test(e.key)) { e.preventDefault(); format({ b: 'bold', i: 'italic', u: 'underline' }[e.key.toLowerCase()]); }
  });
  $('[data-text-scale]').addEventListener('input', function (e) { state.text.scale = parseInt(e.target.value, 10) || 100; renderText(); });
  root.querySelector('[data-text-bg-custom]').addEventListener('input', function (e) { state.text.bg = 'custom'; state.text.bg_color = e.target.value; renderText(); });
  root.querySelector('[data-text-ink-custom]').addEventListener('input', function (e) { state.text.color = e.target.value; renderText(); });

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
    if ((state.items.length || hasText()) && !state.done) { e.preventDefault(); e.returnValue = ''; }
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
    if (state.busy) return;
    if (state.kind === 'text') return shareText();
    if (!state.items.length) return;
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

  async function shareText() {
    if (!hasText()) return;
    state.busy = true;
    render();
    try {
      progress('Publishing…', 0.5);
      var res = await post({
        action: 'iar_compose_publish', kind: 'text',
        text_html: editor.innerHTML,
        text_scale: state.text.scale, text_font: state.text.font, text_align: state.text.align,
        text_color: state.text.color, text_bg: state.text.bg, text_bg_color: state.text.bg_color,
        ratio: state.ratio,
        caption: root.querySelector('[name="caption"]').value,
        location: root.querySelector('[name="location"]').value
      }, null, 'Publishing');
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
