/**
 * Generate video posters in the browser: load each video, grab a frame just
 * in, upload it as the video's poster (wp_ajax_iar_save_video_poster).
 *
 * Markup (includes/video-posters.php):
 *   <span data-iar-posters data-videos="[…]" data-nonce="…">
 *     <button data-iar-posters-go> <span data-iar-posters-status> <span data-iar-posters-list>
 */
(function () {
	'use strict';

	// Same origin as this page, so the canvas isn't "tainted" (http vs https site URLs…).
	function sameOrigin(url) {
		try { var u = new URL(url, location.href); return u.hostname === location.hostname ? u.pathname + u.search : url; } catch (e) { return url; }
	}

	function frame(url, seek) {
		return new Promise(function (resolve, reject) {
			var v = document.createElement('video');
			v.muted = true; v.playsInline = true; v.preload = 'auto'; v.crossOrigin = 'anonymous';
			var done = false, timer = setTimeout(function () { finish(new Error('timed out')); }, 20000);
			function finish(err, blob) { if (done) return; done = true; clearTimeout(timer); v.removeAttribute('src'); v.load(); err ? reject(err) : resolve(blob); }
			// The chosen moment, or the middle of videos shorter than that.
			v.addEventListener('loadedmetadata', function () { v.currentTime = Math.min(seek, (v.duration || 1) / 2); });
			v.addEventListener('seeked', function () {
				var scale = Math.min(1, 1080 / Math.max(v.videoWidth, v.videoHeight));
				var c = document.createElement('canvas');
				c.width = Math.round(v.videoWidth * scale); c.height = Math.round(v.videoHeight * scale);
				try {
					c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
					c.toBlob(function (b) { b ? finish(null, b) : finish(new Error('could not encode')); }, 'image/jpeg', 0.86);
				} catch (e) { finish(e); }
			});
			v.addEventListener('error', function () { finish(new Error('video failed to load')); });
			v.src = sameOrigin(url);
		});
	}

	document.querySelectorAll('[data-iar-posters]').forEach(function (root) {
		var videos = JSON.parse(root.getAttribute('data-videos') || '[]');
		var nonce = root.getAttribute('data-nonce');
		var btn = root.querySelector('[data-iar-posters-go]');
		var status = root.querySelector('[data-iar-posters-status]');
		var list = root.querySelector('[data-iar-posters-list]');

		btn.addEventListener('click', async function () {
			var confirmText = root.getAttribute('data-confirm');
			if (confirmText && !window.confirm(confirmText)) return;
			var seekInput = root.getAttribute('data-seek-input') && document.getElementById(root.getAttribute('data-seek-input'));
			var seek = seekInput ? Math.max(0, parseFloat(seekInput.value) || 0) : 0.5;
			btn.disabled = true;
			list.innerHTML = '';
			var ok = 0, failed = 0, removed = 0;
			for (var i = 0; i < videos.length; i++) {
				var item = videos[i];
				status.textContent = videos.length > 1 ? (i + 1) + ' / ' + videos.length + ' — ' + item.title : 'Working…';
				var tile = document.createElement('span');
				tile.style.cssText = 'width:54px;height:96px;border-radius:4px;background:#f0f0f1;display:inline-flex;align-items:center;justify-content:center;font-size:11px;overflow:hidden;';
				tile.textContent = '…';
				list.appendChild(tile);
				try {
					var blob = await frame(item.url, seek);
					var body = new FormData();
					body.append('action', 'iar_save_video_poster');
					body.append('_ajax_nonce', nonce);
					body.append('video_id', item.video_id);
					body.append('post_id', item.post_id);
					body.append('poster', blob, 'poster.jpg');
					var res = await (await fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: body, credentials: 'same-origin' })).json();
					if (!res.success) throw new Error(res.data || 'upload failed');
					if (res.data.removed) removed++;
					tile.textContent = '';
					var img = new Image(); img.src = res.data.poster; img.style.cssText = 'width:100%;height:100%;object-fit:cover;';
					tile.appendChild(img);
					ok++;
				} catch (e) {
					tile.textContent = '✕'; tile.title = e.message; tile.style.color = '#b32d2e';
					failed++;
				}
			}
			status.textContent = ok + ' poster' + (ok === 1 ? '' : 's') + ' saved' + (removed ? ', ' + removed + ' old one' + (removed === 1 ? '' : 's') + ' removed' : '') + (failed ? ', ' + failed + ' failed (hover the ✕ for why)' : '') + '.';
			btn.disabled = false;
		});
	});
})();
