/**
 * Insta-style theme — front-end behaviour.
 *
 * Grid items carry their post data as data-* attributes so the modal can be
 * built without an extra request. In WordPress, the loop prints the same
 * attributes (templates/parts/grid-item.php).
 *
 *   data-media     JSON array: [{ "type": "image"|"video", "src": "...", "poster": "..." }]
 *   data-caption   plain text caption
 *   data-date      ISO 8601 date
 *   data-likes     integer
 *   data-comments  integer
 *   data-location  optional string
 *   data-ratio     frame width / height (e.g. 0.8 for 4:5)
 */
(function () {
  'use strict';

  var CFG = window.IAR || { hashtagBase: location.pathname, people: {} };
  var profile = document.querySelector('[data-profile]');
  var username = profile ? profile.dataset.username : '';
  var avatar = profile ? profile.dataset.avatar : '';
  var profileUrl = profile ? (profile.dataset.profileUrl || '#') : '#';

  /* ----------------------------------------------------------------------
     Helpers
     ---------------------------------------------------------------------- */
  function esc(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  // Escape, then link #hashtags and @mentions.
  function formatCaption(text) {
    return esc(text)
      .replace(/(^|\s)#([\p{L}\p{N}_]+)/gu, function (m, pre, tag) {
        var url = CFG.hashtagBase + (CFG.hashtagBase.indexOf('?') < 0 ? '?' : '&') + 'hashtag=' + encodeURIComponent(tag);
        return pre + '<a class="hashtag" href="' + esc(url) + '">#' + tag + '</a>';
      })
      .replace(/(^|\s)@([\w.]+)/g, function (m, pre, name) {
        var url = CFG.people[name.replace(/\.$/, '').toLowerCase()];
        return url ? pre + '<a class="hashtag" href="' + esc(url) + '">@' + name + '</a>' : m;
      });
  }

  function formatDate(iso) {
    var d = new Date(iso);
    if (isNaN(d)) return '';
    return d.toLocaleDateString(undefined, { day: 'numeric', month: 'long', year: 'numeric' });
  }

  function formatCount(n) {
    n = parseInt(n, 10) || 0;
    return n.toLocaleString();
  }

  function icon(id) {
    return '<svg class="icon" aria-hidden="true"><use href="#i-' + id + '"></use></svg>';
  }

  /* ----------------------------------------------------------------------
     Carousel — works on any .carousel in the page (modal + single.html)
     ---------------------------------------------------------------------- */
  function initCarousel(root) {
    if (!root || root.dataset.ready) return;
    root.dataset.ready = '1';

    var track = root.querySelector('.carousel__track');
    var slides = root.querySelectorAll('.carousel__slide');
    var prev = root.querySelector('.carousel__nav--prev');
    var next = root.querySelector('.carousel__nav--next');
    var dots = root.querySelectorAll('.carousel__dot');
    var index = 0;

    function go(i) {
      index = Math.max(0, Math.min(slides.length - 1, i));
      track.style.transform = 'translateX(' + (-100 * index) + '%)';
      if (prev) prev.hidden = index === 0;
      if (next) next.hidden = index === slides.length - 1;
      dots.forEach(function (d, n) { d.classList.toggle('is-active', n === index); });
      slides.forEach(function (s, n) {
        var v = s.querySelector('video');
        if (v && n !== index) v.pause();
      });
    }

    if (prev) prev.addEventListener('click', function (e) { e.stopPropagation(); go(index - 1); });
    if (next) next.addEventListener('click', function (e) { e.stopPropagation(); go(index + 1); });

    // Touch swipe
    var startX = null;
    root.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
    root.addEventListener('touchend', function (e) {
      if (startX === null) return;
      var dx = e.changedTouches[0].clientX - startX;
      if (Math.abs(dx) > 40) go(index + (dx < 0 ? 1 : -1));
      startX = null;
    });

    root.carousel = { go: go, get index() { return index; }, count: slides.length };
    go(0);
  }

  function carouselHTML(media) {
    var slides = media.map(function (m) {
      if (m.type === 'video') {
        return '<div class="carousel__slide"><video src="' + esc(m.src) + '"' +
          (m.poster ? ' poster="' + esc(m.poster) + '"' : '') +
          ' controls playsinline preload="metadata"></video></div>';
      }
      return '<div class="carousel__slide"><img src="' + esc(m.src) + '" alt="" loading="lazy"></div>';
    }).join('');

    var multi = media.length > 1;
    return '<div class="carousel">' +
      '<div class="carousel__track">' + slides + '</div>' +
      (multi
        ? '<button class="carousel__nav carousel__nav--prev" aria-label="Previous">' + icon('chevron-left') + '</button>' +
          '<button class="carousel__nav carousel__nav--next" aria-label="Next">' + icon('chevron-right') + '</button>' +
          '<div class="carousel__dots">' + media.map(function () { return '<span class="carousel__dot"></span>'; }).join('') + '</div>'
        : '') +
      '</div>';
  }

  /* ----------------------------------------------------------------------
     Like / save toggles (purely visual)
     ---------------------------------------------------------------------- */
  document.addEventListener('click', function (e) {
    var like = e.target.closest('[data-action="like"][data-post-id]');
    if (like) toggleLike(like);
    var commentBtn = e.target.closest('[data-action="comment"]');
    if (commentBtn) {
      var input = commentBtn.closest('.post, .card') && commentBtn.closest('.post, .card').querySelector('.comment-form__input');
      if (input) input.focus();
    }
    var share = e.target.closest('[data-action="share"]');
    if (share) {
      var link = share.closest('.post, .card').querySelector('.post__date');
      var url = share.dataset.url || (link ? link.href : location.href);
      if (navigator.share) navigator.share({ url: url }).catch(function () {});
      else if (navigator.clipboard) navigator.clipboard.writeText(url).then(function () { share.style.opacity = '.4'; setTimeout(function () { share.style.opacity = ''; }, 600); });
    }
    var save = e.target.closest('[data-action="save"]');
    if (save) {
      save.classList.toggle('is-saved');
      save.innerHTML = icon(save.classList.contains('is-saved') ? 'bookmark-filled' : 'bookmark');
    }
  });

  /* ----------------------------------------------------------------------
     Modal
     ---------------------------------------------------------------------- */
  var modal = document.querySelector('[data-modal]');
  function getItems() { return Array.prototype.slice.call(document.querySelectorAll('[data-grid] .grid__item[data-media], .grid .grid__item[data-media]')); }
  var items = getItems();
  var current = -1;
  var returnUrl = null;

  function postHTML(item) {
    var d = item.dataset;
    var media = [];
    try { media = JSON.parse(d.media || '[]'); } catch (err) { media = []; }
    var likes = parseInt(d.likes, 10) || 0;

    var ratio = parseFloat(d.ratio) || 1;

    return '<article class="post" data-post-id="' + esc(d.id || '') + '" style="--ar: ' + ratio + '">' +
      '<div class="post__media">' + carouselHTML(media) + '</div>' +
      '<div class="post__panel">' +
        '<header class="post__header">' +
          '<img class="avatar" src="' + esc(avatar) + '" alt="">' +
          '<div><a class="post__user" href="' + esc(profileUrl) + '">' + esc(username) + '</a>' +
          (d.location ? '<span class="post__location">' + esc(d.location) + '</span>' : '') + '</div>' +
          '<button class="post__more icon-btn" aria-label="More options">' + icon('more') + '</button>' +
        '</header>' +
        '<div class="post__body">' +
          (d.caption
            ? '<div class="comment"><img class="avatar" src="' + esc(avatar) + '" alt="">' +
              '<div><p class="comment__text"><a class="post__user" href="' + esc(profileUrl) + '">' + esc(username) + '</a>' + formatCaption(d.caption) + '</p>' +
              '<div class="comment__meta"><time datetime="' + esc(d.date) + '">' + esc(formatDate(d.date)) + '</time></div></div></div>'
            : '') +
          '<div class="comments" data-comments></div>' +
        '</div>' +
        '<footer class="post__footer">' +
          '<div class="post__actions">' +
            (CFG.likes && d.id ? '<button class="icon-btn" data-action="like" data-post-id="' + esc(d.id) + '" aria-label="Like">' + icon('heart') + '</button>' : '') +
            '<button class="icon-btn" data-action="comment" aria-label="Comment">' + icon('comment') + '</button>' +
            '<button class="icon-btn" data-action="share" aria-label="Share">' + icon('share') + '</button>' +
            '<button class="icon-btn" data-action="save" aria-label="Save">' + icon('bookmark') + '</button>' +
          '</div>' +
          '<p class="post__likes" data-likes-count="' + likes + '">' + formatCount(likes) + (likes === 1 ? ' like' : ' likes') + '</p>' +
          '<a class="post__date" href="' + esc(item.getAttribute('href')) + '"><time datetime="' + esc(d.date) + '">' + esc(formatDate(d.date)) + '</time></a>' +
          '<div data-comment-form-slot></div>' +
        '</footer>' +
      '</div>' +
    '</article>';
  }

  function open(i) {
    items = getItems();
    if (!modal || i < 0 || i >= items.length) return;
    current = i;
    var item = items[i];
    modal.querySelector('[data-modal-content]').innerHTML = postHTML(item);
    initCarousel(modal.querySelector('.carousel'));
    markLiked(modal);
    loadComments(modal.querySelector('.post'));
    modal.querySelector('.modal__step--prev').hidden = i === 0;
    modal.querySelector('.modal__step--next').hidden = i === items.length - 1;

    if (!modal.classList.contains('is-open')) {
      returnUrl = location.href;
      modal.classList.add('is-open');
      document.body.classList.add('modal-open');
      history.pushState({ modal: i }, '', item.getAttribute('href'));
    } else {
      history.replaceState({ modal: i }, '', item.getAttribute('href'));
    }
    modal.querySelector('.modal__close').focus();
  }

  function close(fromPopstate) {
    if (!modal || !modal.classList.contains('is-open')) return;
    modal.classList.remove('is-open');
    document.body.classList.remove('modal-open');
    modal.querySelector('[data-modal-content]').innerHTML = '';
    if (!fromPopstate && returnUrl) history.pushState(null, '', returnUrl);
    if (items[current]) items[current].focus();
    current = -1;
  }

  if (modal) {
    document.addEventListener('click', function (e) {
      var item = e.target.closest('.grid__item[data-media]');
      if (!item || e.metaKey || e.ctrlKey || e.shiftKey) return; // allow open-in-new-tab
      e.preventDefault();
      open(getItems().indexOf(item));
    });

    modal.querySelector('.modal__close').addEventListener('click', function () { close(); });
    modal.querySelector('.modal__step--prev').addEventListener('click', function () { open(current - 1); });
    modal.querySelector('.modal__step--next').addEventListener('click', function () { open(current + 1); });
    modal.addEventListener('click', function (e) { if (e.target === modal) close(); });

    document.addEventListener('keydown', function (e) {
      if (!modal.classList.contains('is-open')) return;
      var c = modal.querySelector('.carousel');
      if (e.key === 'Escape') close();
      else if (e.key === 'ArrowRight') {
        if (c && c.carousel && c.carousel.index < c.carousel.count - 1) c.carousel.go(c.carousel.index + 1);
        else open(current + 1);
      } else if (e.key === 'ArrowLeft') {
        if (c && c.carousel && c.carousel.index > 0) c.carousel.go(c.carousel.index - 1);
        else open(current - 1);
      }
    });

    window.addEventListener('popstate', function () { close(true); });
  }

  /* ----------------------------------------------------------------------
     Load more
     ---------------------------------------------------------------------- */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-load-more]');
    if (!btn) return;
    e.preventDefault();
    if (btn.getAttribute('aria-busy') === 'true') return;
    btn.setAttribute('aria-busy', 'true');

    fetch(btn.href, { credentials: 'same-origin' })
      .then(function (r) { return r.text(); })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var target = document.querySelector(btn.dataset.target || '[data-grid]');
        doc.querySelectorAll(btn.dataset.items || '[data-grid] .grid__item').forEach(function (el) {
          var node = document.importNode(el, true);
          target.appendChild(node);
          node.querySelectorAll('.carousel').forEach(initCarousel);
        });
        var next = doc.querySelector('[data-load-more]');
        if (next) {
          btn.href = next.href;
          btn.removeAttribute('aria-busy');
        } else {
          btn.closest('.load-more').remove();
        }
      })
      .catch(function () { location.href = btn.href; });
  });

  /* ----------------------------------------------------------------------
     Bot check: Cloudflare Turnstile, Google reCAPTCHA v3 or v2 (invisible).
     captchaToken(action) resolves with a fresh single-use token for each
     protected request ("comment", "like"), or '' when no check is on.
     A visible challenge only appears if the provider asks for one.
     ---------------------------------------------------------------------- */
  var CAP = CFG.captcha || null;
  var capWidgets = {}, capPending = {}, capBox = null;
  var CAP_FAIL = 'Couldn\'t confirm you\'re human — please try again.';

  function waitFor(test, what) {
    return new Promise(function (resolve, reject) {
      var t0 = Date.now();
      (function poll() {
        var v = test();
        if (v) return resolve(v);
        if (Date.now() - t0 > 15000) return reject(new Error(what + ' didn\'t load — check your connection and try again.'));
        setTimeout(poll, 100);
      })();
    });
  }

  function challengeBox() {
    if (!capBox) {
      capBox = document.createElement('div');
      capBox.className = 'ts-challenge';
      document.body.appendChild(capBox);
    }
    return capBox;
  }

  // Turnstile and reCAPTCHA v2 (invisible): one widget per key, reset + execute per request.
  function widgetToken(api, key, renderOpts) {
    return new Promise(function (resolve, reject) {
      var timer = setTimeout(function () { fail(new Error('The bot check timed out — please try again.')); }, 120000);
      function done(token) { clearTimeout(timer); delete capPending[key]; resolve(token); }
      function fail(err) { clearTimeout(timer); delete capPending[key]; reject(err); }
      capPending[key] = { done: done, fail: fail };
      if (capWidgets[key] === undefined) {
        var el = document.createElement('div');
        challengeBox().appendChild(el);
        capWidgets[key] = api.render(el, Object.assign({
          callback: function (token) { capPending[key] && capPending[key].done(token); },
          'error-callback': function () { capPending[key] && capPending[key].fail(new Error(CAP_FAIL)); return true; },
          'expired-callback': function () {},
        }, renderOpts));
      } else {
        api.reset(capWidgets[key]);
      }
      api.execute(capWidgets[key]);
    });
  }

  function captchaToken(action) {
    if (!CAP || !CAP[action]) return Promise.resolve('');
    if (CAP.provider === 'turnstile') {
      return waitFor(function () { return window.turnstile && window.turnstile.render && window.turnstile; }, 'The bot check')
        .then(function (ts) {
          return widgetToken(ts, 'ts-' + action, { sitekey: CAP.sitekey, action: action, execution: 'execute', appearance: 'interaction-only' });
        });
    }
    if (CAP.provider === 'recaptcha_v3') {
      return waitFor(function () { return window.grecaptcha && window.grecaptcha.execute && window.grecaptcha; }, 'reCAPTCHA')
        .then(function (g) {
          return new Promise(function (resolve, reject) {
            g.ready(function () { g.execute(CAP.sitekey, { action: action }).then(resolve, function () { reject(new Error(CAP_FAIL)); }); });
          });
        });
    }
    if (CAP.provider === 'recaptcha_v2') {
      // v2 has no actions, so one invisible widget serves both comments and likes.
      return waitFor(function () { return window.grecaptcha && window.grecaptcha.render && window.grecaptcha; }, 'reCAPTCHA')
        .then(function (g) {
          return widgetToken(g, 'rc2', { sitekey: CAP.sitekey, size: 'invisible', badge: 'bottomright' });
        });
    }
    return Promise.resolve('');
  }

  /* ----------------------------------------------------------------------
     Likes (stored as WordPress comments of type "iar_like")
     ---------------------------------------------------------------------- */
  var LIKED_KEY = 'iar_liked';

  function likedSet() {
    try { return new Set(JSON.parse(localStorage.getItem(LIKED_KEY) || '[]').map(String)); } catch (e) { return new Set(); }
  }
  function saveLiked(set) {
    try { localStorage.setItem(LIKED_KEY, JSON.stringify(Array.from(set).slice(-2000))); } catch (e) {}
  }
  // A random ID for this browser, so a visitor's like counts once (stored hashed by WordPress).
  function visitorId() {
    try {
      var id = localStorage.getItem('iar_vid');
      if (!id) {
        id = (crypto.randomUUID ? crypto.randomUUID() : (Date.now().toString(36) + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2)));
        localStorage.setItem('iar_vid', id);
      }
      return id;
    } catch (e) { return ''; }
  }
  function setLikes(scope, n) {
    var counter = scope && scope.querySelector('[data-likes-count]');
    if (!counter) return;
    counter.dataset.likesCount = n;
    counter.textContent = formatCount(n) + (n === 1 ? ' like' : ' likes');
  }
  function markLiked(root) {
    var set = likedSet();
    (root || document).querySelectorAll('[data-action="like"][data-post-id]').forEach(function (b) {
      var on = set.has(String(b.dataset.postId));
      b.classList.toggle('is-liked', on);
      b.innerHTML = icon(on ? 'heart-filled' : 'heart');
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  function toggleLike(btn, onlyLike) {
    var id = String(btn.dataset.postId);
    var set = likedSet();
    var was = set.has(id);
    if (onlyLike && was) return;
    var scope = btn.closest('.post, .card');
    var counter = scope && scope.querySelector('[data-likes-count]');
    var before = counter ? parseInt(counter.dataset.likesCount, 10) || 0 : 0;

    // Optimistic update, then the server's count.
    was ? set.delete(id) : set.add(id);
    saveLiked(set);
    markLiked(scope || document);
    setLikes(scope, before + (was ? -1 : 1));
    if (!was) { btn.classList.remove('is-pop'); void btn.offsetWidth; btn.classList.add('is-pop'); }

    captchaToken('like')
      .then(function (token) {
        var body = new FormData();
        body.append('action', 'iar_like');
        body.append('post_id', id);
        body.append('vid', visitorId());
        if (token) body.append('captcha_token', token);
        return fetch(CFG.ajax, { method: 'POST', body: body, credentials: 'same-origin' });
      })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.success) throw new Error(res.data);
        var s = likedSet();
        res.data.liked ? s.add(id) : s.delete(id);
        saveLiked(s);
        markLiked(scope || document);
        setLikes(scope, res.data.likes);
      })
      .catch(function () {
        var s = likedSet();
        was ? s.add(id) : s.delete(id);
        saveLiked(s);
        markLiked(scope || document);
        setLikes(scope, before);
      });
  }

  // Double-tap a photo to like it, like Instagram.
  document.addEventListener('dblclick', function (e) {
    var media = e.target.closest('.post__media, .card__media');
    if (!media) return;
    var scope = media.closest('.post, .card');
    var btn = scope && scope.querySelector('[data-action="like"][data-post-id]');
    if (!btn) return;
    e.preventDefault();
    toggleLike(btn, true);
    var heart = document.createElement('span');
    heart.className = 'like-burst';
    heart.innerHTML = icon('heart-filled');
    media.appendChild(heart);
    setTimeout(function () { heart.remove(); }, 900);
  });

  markLiked(document);

  /* ----------------------------------------------------------------------
     Comments (WordPress comments)
     ---------------------------------------------------------------------- */
  function loadComments(post) {
    if (!post || !post.dataset.postId) return;
    var list = post.querySelector('[data-comments]'), slot = post.querySelector('[data-comment-form-slot]');
    fetch(CFG.ajax + '?action=iar_comments&post_id=' + encodeURIComponent(post.dataset.postId), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.success) return;
        if (list) list.innerHTML = res.data.comments;
        if (slot) slot.innerHTML = res.data.form;
        showCommentingAs(post);
        setLikes(post, res.data.likes);
      })
      .catch(function () {});
  }

  document.addEventListener('input', function (e) {
    var input = e.target.closest('.comment-form__input');
    if (!input) return;
    input.form.querySelector('.comment-form__post').disabled = !input.value.trim();
  });

  document.addEventListener('click', function (e) {
    var reply = e.target.closest('[data-reply-to]');
    if (!reply) return;
    var post = reply.closest('.post, .card');
    var form = post && post.querySelector('[data-comment-form]');
    if (!form) return;
    form.elements.comment_parent.value = reply.dataset.replyTo;
    var input = form.querySelector('.comment-form__input');
    input.value = '@' + reply.dataset.replyName.replace(/\s+/g, '') + ' ';
    input.placeholder = 'Reply to ' + reply.dataset.replyName + '…';
    input.focus();
    form.querySelector('.comment-form__post').disabled = false;
  });

  /* Visitors comment without an account: the first time, a pop-up asks for an
     email (required, never shown) and an optional name ("Anonymous" if blank).
     Remembered in this browser if they tick "Remember me". */
  var IDENTITY_KEY = 'iar_commenter';
  var sessionIdentity = null;

  function getIdentity() {
    if (sessionIdentity) return sessionIdentity;
    try { var v = JSON.parse(localStorage.getItem(IDENTITY_KEY) || 'null'); return v && v.email ? v : null; } catch (e) { return null; }
  }
  function setIdentity(id, remember) {
    sessionIdentity = id;
    try { remember ? localStorage.setItem(IDENTITY_KEY, JSON.stringify(id)) : localStorage.removeItem(IDENTITY_KEY); } catch (e) {}
    showCommentingAs();
  }
  function clearIdentity() {
    sessionIdentity = null;
    try { localStorage.removeItem(IDENTITY_KEY); } catch (e) {}
    showCommentingAs();
  }
  function showCommentingAs(root) {
    var id = getIdentity();
    (root || document).querySelectorAll('[data-comment-form][data-guest] [data-comment-as]').forEach(function (p) {
      p.hidden = !id;
      if (id) p.querySelector('span').textContent = (id.name || 'Anonymous') + ' (' + id.email + ')';
    });
  }

  var emailModal = null, pendingForm = null;
  function buildEmailModal() {
    if (emailModal) return emailModal;
    emailModal = document.createElement('div');
    emailModal.className = 'email-modal';
    emailModal.hidden = true;
    emailModal.setAttribute('role', 'dialog');
    emailModal.setAttribute('aria-modal', 'true');
    emailModal.setAttribute('aria-labelledby', 'iar-email-title');
    emailModal.innerHTML =
      '<form class="email-modal__box" novalidate>' +
        '<h2 id="iar-email-title">Add your email to comment</h2>' +
        '<p class="email-modal__note">You don’t need an account. Your email isn’t shown — it’s only used to show your Gravatar picture and if the site owner needs to reach you.</p>' +
        '<label class="email-modal__field">Email<input type="email" name="email" required autocomplete="email" placeholder="you@example.com"></label>' +
        '<label class="email-modal__field">Name <span>(optional)</span><input type="text" name="name" autocomplete="name" maxlength="60" placeholder="Anonymous"></label>' +
        '<label class="email-modal__check"><input type="checkbox" name="remember" checked> Remember me on this device</label>' +
        '<p class="email-modal__error" hidden></p>' +
        '<div class="email-modal__actions">' +
          '<button type="button" class="btn" data-email-cancel>Cancel</button>' +
          '<button type="submit" class="btn btn--primary" data-email-ok>Post comment</button>' +
        '</div>' +
      '</form>';
    document.body.appendChild(emailModal);

    var box = emailModal.querySelector('form');
    emailModal.addEventListener('click', function (e) { if (e.target === emailModal || e.target.closest('[data-email-cancel]')) closeEmailModal(); });
    box.addEventListener('submit', function (e) {
      e.preventDefault();
      var email = box.elements.email, err = box.querySelector('.email-modal__error');
      if (!email.value.trim() || !email.checkValidity()) {
        err.textContent = 'Please enter a valid email address.';
        err.hidden = false;
        email.focus();
        return;
      }
      setIdentity({ email: email.value.trim(), name: box.elements.name.value.trim() }, box.elements.remember.checked);
      var form = pendingForm;
      closeEmailModal();
      if (form) sendComment(form);
    });
    document.addEventListener('keydown', function (e) { if (!emailModal.hidden && e.key === 'Escape') { e.stopPropagation(); closeEmailModal(); } }, true);
    return emailModal;
  }
  function openEmailModal(form, editing) {
    var m = buildEmailModal(), box = m.querySelector('form'), id = getIdentity() || {};
    pendingForm = editing ? null : form;
    box.elements.email.value = id.email || '';
    box.elements.name.value = id.name || '';
    box.querySelector('.email-modal__error').hidden = true;
    box.querySelector('[data-email-ok]').textContent = editing ? 'Save' : 'Post comment';
    m.hidden = false;
    document.body.classList.add('email-modal-open');
    box.elements.email.focus();
  }
  function closeEmailModal() {
    if (!emailModal) return;
    emailModal.hidden = true;
    document.body.classList.remove('email-modal-open');
    var form = pendingForm;
    pendingForm = null;
    if (form) form.querySelector('.comment-form__post').disabled = !form.querySelector('.comment-form__input').value.trim();
  }

  document.addEventListener('click', function (e) {
    var change = e.target.closest('[data-comment-change]');
    if (change) openEmailModal(change.closest('[data-comment-form]'), true);
  });

  function sendComment(form) {
    var msg = form.querySelector('[data-comment-msg]');
    var btn = form.querySelector('.comment-form__post');
    if (form.hasAttribute('data-guest')) {
      var id = getIdentity();
      if (!id) { openEmailModal(form); return; }
      form.elements.author.value = id.name || '';
      form.elements.email.value = id.email;
    }
    btn.disabled = true;
    msg.hidden = true;
    captchaToken('comment')
      .then(function (token) {
        var body = new FormData(form);
        body.append('action', 'iar_post_comment');
        if (token) body.append('captcha_token', token);
        return fetch(CFG.ajax, { method: 'POST', body: body, credentials: 'same-origin' });
      })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.success) {
          if (res.data && res.data.code === 'need_email') { clearIdentity(); openEmailModal(form); return; }
          throw new Error((res.data && res.data.message) || res.data || 'Couldn\'t post your comment.');
        }
        var post = form.closest('.post, .card');
        var list = post.querySelector('[data-comments]');
        var holder = document.createElement('div');
        holder.innerHTML = res.data.html;
        var node = holder.firstElementChild;
        var parent = res.data.parent && list && list.querySelector('[data-comment-id="' + res.data.parent + '"] > div');
        if (parent) {
          var replies = parent.querySelector('.comment__replies');
          if (!replies) { replies = document.createElement('div'); replies.className = 'comment__replies'; parent.appendChild(replies); }
          replies.appendChild(node);
        } else if (list) {
          list.appendChild(node);
        }
        if (node) node.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        var input = form.querySelector('.comment-form__input');
        input.value = '';
        input.placeholder = 'Add a comment…';
        form.elements.comment_parent.value = '0';
        if (!res.data.approved) { msg.textContent = 'Thanks — your comment will appear once it\'s approved.'; msg.hidden = false; }
      })
      .catch(function (err) {
        msg.textContent = String(err.message || err).replace(/^Error:\s*/, '');
        msg.hidden = false;
        btn.disabled = false;
      });
  }

  document.addEventListener('submit', function (e) {
    var form = e.target.closest('[data-comment-form]');
    if (!form) return;
    e.preventDefault();
    if (!form.querySelector('.comment-form__input').value.trim()) return;
    sendComment(form);
  });

  showCommentingAs();

  /* ----------------------------------------------------------------------
     Follow: copy this profile's feed link for the visitor to paste into
     Following on their own site.
     ---------------------------------------------------------------------- */
  function toast(message) {
    var el = document.createElement('div');
    el.className = 'toast';
    el.setAttribute('role', 'status');
    el.textContent = message;
    document.body.appendChild(el);
    requestAnimationFrame(function () { el.classList.add('is-shown'); });
    setTimeout(function () {
      el.classList.remove('is-shown');
      setTimeout(function () { el.remove(); }, 300);
    }, 4000);
  }

  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }
    // http:// pages can't use the async clipboard API.
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    var ok = false;
    try { ok = document.execCommand('copy'); } catch (err) {}
    ta.remove();
    return ok ? Promise.resolve() : Promise.reject();
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-follow]');
    if (!btn) return;
    var url = btn.dataset.follow;
    copyText(url).then(function () {
      toast(btn.dataset.copied);
      btn.classList.add('is-copied');
    }, function () {
      window.prompt('Copy this link and paste it into Following on your site:', url);
    });
  });

  // Long captions in the home feed: "… more".
  document.addEventListener('click', function (e) {
    var more = e.target.closest('[data-action="expand"]');
    if (!more) return;
    var cap = more.previousElementSibling;
    if (cap) cap.classList.remove('is-clamped');
    more.remove();
  });

  /* ----------------------------------------------------------------------
     Single post page: render caption formatting + carousel
     ---------------------------------------------------------------------- */
  document.querySelectorAll('[data-format-caption]').forEach(function (el) {
    el.innerHTML = formatCaption(el.textContent);
  });
  document.querySelectorAll('.carousel').forEach(initCarousel);
})();
