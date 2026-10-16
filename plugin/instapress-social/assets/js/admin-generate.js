/**
 * "Generate shortcode" button on the instapress-social edit screen.
 * Fills the slug, the ig_shortcode field and (if empty, or ticked) the title.
 */
(function () {
	'use strict';

	var ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';

	// Same algorithm as iar_generate_shortcode() in PHP.
	function generate(ms) {
		var id = (BigInt(Math.max(ms - IGP.epoch, 0)) << 23n) | BigInt(Math.floor(Math.random() * (1 << 23)));
		var code = '';
		do { code = ALPHABET[Number(id & 63n)] + code; id >>= 6n; } while (id > 0n);
		return code;
	}

	// The post date from the Publish box (aa/mm/jj/hh/mn), or now.
	function postTime() {
		var v = function (id) { var el = document.getElementById(id); return el ? parseInt(el.value, 10) : NaN; };
		var d = new Date(v('aa'), v('mm') - 1, v('jj'), v('hh'), v('mn'));
		return isNaN(d) ? Date.now() : d.getTime();
	}

	function setValue(el, value) {
		if (!el) return;
		el.value = value;
		el.dispatchEvent(new Event('input', { bubbles: true }));
		el.dispatchEvent(new Event('change', { bubbles: true }));
	}

	document.addEventListener('click', function (e) {
		if (!e.target.closest('#igp-generate-btn')) return;

		// Imported posts already have their real code — don't replace it by accident.
		var field = document.querySelector('.acf-field[data-name="' + IGP.field + '"] input');
		if (field && field.value.trim() && !window.confirm(IGP.confirm.replace('%s', field.value.trim()))) {
			return;
		}

		var code = generate(postTime());

		// Slug: the hidden field WordPress submits, plus the visible permalink preview.
		setValue(document.getElementById('post_name'), code);
		var short = document.getElementById('editable-post-name');
		var full = document.getElementById('editable-post-name-full');
		if (short) short.textContent = code;
		if (full) full.textContent = code;
		var link = document.querySelector('#sample-permalink a');
		if (link) link.href = IGP.base + code + '/';

		// Title: fill when empty, or when the box is ticked.
		var title = document.getElementById('title');
		if (title && (!title.value.trim() || document.getElementById('igp-fill-title').checked)) {
			setValue(title, code);
			var prompt = document.getElementById('title-prompt-text');
			if (prompt) prompt.classList.add('screen-reader-text');
		}

		setValue(field, code);

		var preview = document.getElementById('igp-preview');
		preview.textContent = IGP.base + code + '/';
		preview.style.display = '';
	});
})();
