/**
 * Insta-style theme — front-end behaviour.
 *
 * Grid items carry their post data as data-* attributes so the modal can be
 * built without an extra request. In WordPress, the loop prints the same
 * attributes (see wp/template-parts/grid-item.php notes in the README).
 *
 *   data-media     JSON array: [{ "type": "image"|"video", "src": "...", "poster": "..." }]
 *   data-caption   plain text caption
 *   data-date      ISO 8601 date
 *   data-likes     integer
 *   data-comments  integer
 *   data-location  optional string
 */
(function () {
  'use strict';

  var profile = document.querySelector('[data-profile]');
  var username = profile ? profile.dataset.username : '';
  var avatar = profile ? profile.dataset.avatar : '';

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
      .replace(/(^|\s)#([\p{L}\p{N}_]+)/gu, '$1<a class="hashtag" href="index.html?hashtag=$2">#$2</a>')
      .replace(/(^|\s)@([\w.]+)/g, '$1<span class="hashtag">@$2</span>');
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
    var like = e.target.closest('[data-action="like"]');
    if (like) {
      like.classList.toggle('is-liked');
      var counter = like.closest('.post').querySelector('[data-likes-count]');
      if (counter) {
        var n = parseInt(counter.dataset.likesCount, 10) + (like.classList.contains('is-liked') ? 1 : 0);
        counter.textContent = formatCount(n) + (n === 1 ? ' like' : ' likes');
      }
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
  var items = Array.prototype.slice.call(document.querySelectorAll('.grid__item[data-media]'));
  var current = -1;
  var returnUrl = null;

  function postHTML(item) {
    var d = item.dataset;
    var media = [];
    try { media = JSON.parse(d.media || '[]'); } catch (err) { media = []; }
    var likes = parseInt(d.likes, 10) || 0;

    return '<article class="post">' +
      '<div class="post__media">' + carouselHTML(media) + '</div>' +
      '<div class="post__panel">' +
        '<header class="post__header">' +
          '<img class="avatar" src="' + esc(avatar) + '" alt="">' +
          '<div><a class="post__user" href="index.html">' + esc(username) + '</a>' +
          (d.location ? '<span class="post__location">' + esc(d.location) + '</span>' : '') + '</div>' +
          '<button class="post__more icon-btn" aria-label="More options">' + icon('more') + '</button>' +
        '</header>' +
        '<div class="post__body">' +
          (d.caption
            ? '<div class="comment"><img class="avatar" src="' + esc(avatar) + '" alt="">' +
              '<div><p class="comment__text"><a class="post__user" href="index.html">' + esc(username) + '</a>' + formatCaption(d.caption) + '</p>' +
              '<div class="comment__meta"><time datetime="' + esc(d.date) + '">' + esc(formatDate(d.date)) + '</time></div></div></div>'
            : '') +
        '</div>' +
        '<footer class="post__footer">' +
          '<div class="post__actions">' +
            '<button class="icon-btn" data-action="like" aria-label="Like">' + icon('heart') + '</button>' +
            '<button class="icon-btn" aria-label="Comment">' + icon('comment') + '</button>' +
            '<button class="icon-btn" aria-label="Share">' + icon('share') + '</button>' +
            '<button class="icon-btn" data-action="save" aria-label="Save">' + icon('bookmark') + '</button>' +
          '</div>' +
          '<p class="post__likes" data-likes-count="' + likes + '">' + formatCount(likes) + (likes === 1 ? ' like' : ' likes') + '</p>' +
          '<a class="post__date" href="' + esc(item.getAttribute('href')) + '"><time datetime="' + esc(d.date) + '">' + esc(formatDate(d.date)) + '</time></a>' +
        '</footer>' +
      '</div>' +
    '</article>';
  }

  function open(i) {
    if (!modal || i < 0 || i >= items.length) return;
    current = i;
    var item = items[i];
    modal.querySelector('[data-modal-content]').innerHTML = postHTML(item);
    initCarousel(modal.querySelector('.carousel'));
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
    items.forEach(function (item, i) {
      item.addEventListener('click', function (e) {
        if (e.metaKey || e.ctrlKey || e.shiftKey) return; // allow open-in-new-tab
        e.preventDefault();
        open(i);
      });
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
     Single post page: render caption formatting + carousel
     ---------------------------------------------------------------------- */
  document.querySelectorAll('[data-format-caption]').forEach(function (el) {
    el.innerHTML = formatCaption(el.textContent);
  });
  document.querySelectorAll('.carousel').forEach(initCarousel);
})();
