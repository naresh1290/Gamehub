/* GameHub theme front-end.
 * Talks to the GameHub Engine REST API (gamehub/v1) for plays, visits,
 * likes/dislikes, ratings, and play-session durations.
 */
(function () {
	'use strict';

	var CFG = window.GAMEHUB || {};
	var REST = (CFG.restBase || '').replace(/\/$/, '');
	var NONCE = CFG.nonce || '';

	function post(path, body) {
		return fetch(REST + path, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
			credentials: 'same-origin',
			body: body ? JSON.stringify(body) : null
		}).then(function (r) { return r.ok ? r.json() : Promise.reject(r); });
	}

	function beacon(path, body) {
		var url = REST + path + (NONCE ? ('?_wpnonce=' + encodeURIComponent(NONCE)) : '');
		var data = new Blob([JSON.stringify(body || {})], { type: 'application/json' });
		if (navigator.sendBeacon && navigator.sendBeacon(url, data)) { return; }
		// Fallback: keepalive fetch.
		fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body || {}), keepalive: true }).catch(function () {});
	}

	/* ---- Local UI memory for a visitor's own votes/ratings ---- */
	function lsGet(k) { try { return JSON.parse(localStorage.getItem(k)); } catch (e) { return null; } }
	function lsSet(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} }

	/* ---- Theme toggle ---- */
	(function theme() {
		var saved = lsGet('gh-theme');
		if (saved === 'dark' || saved === 'light') {
			document.documentElement.setAttribute('data-theme', saved);
		}
		document.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-gh-theme-toggle]');
			if (!btn) { return; }
			var cur = document.documentElement.getAttribute('data-theme');
			if (!cur) {
				cur = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
			}
			var next = cur === 'dark' ? 'light' : 'dark';
			document.documentElement.setAttribute('data-theme', next);
			lsSet('gh-theme', next);
		});
	})();

	/* ---- Player + session tracking ---- */
	(function player() {
		var wrap = document.querySelector('.gh-player');
		if (!wrap) { return; }
		var gid = parseInt(wrap.getAttribute('data-game-id'), 10);
		if (!gid) { return; }

		// Count a page visit once per pageview.
		beacon('/games/' + gid + '/visit', {});

		var cover = wrap.querySelector('.gh-player-cover');
		var started = false;
		var sessionStart = 0;
		var buffered = 0; // seconds already accumulated but not yet flushed

		function launch() {
			if (started) { return; }
			started = true;
			var src = wrap.getAttribute('data-iframe');
			var title = wrap.getAttribute('data-title') || 'game';
			var iframe = document.createElement('iframe');
			iframe.src = src;
			iframe.title = title;
			iframe.allow = 'autoplay; fullscreen; gamepad; accelerometer; gyroscope; clipboard-write; cross-origin-isolated';
			iframe.allowFullscreen = true;
			iframe.setAttribute('loading', 'eager');
			if (cover) { cover.remove(); }
			wrap.appendChild(iframe);

			post('/games/' + gid + '/play', {}).catch(function () {});
			sessionStart = Date.now();
		}

		if (cover) {
			cover.addEventListener('click', launch);
			cover.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); launch(); }
			});
		}

		function accrue() {
			if (started && sessionStart) {
				buffered += Math.round((Date.now() - sessionStart) / 1000);
				sessionStart = 0;
			}
		}
		function resume() {
			if (started && !sessionStart && document.visibilityState === 'visible') {
				sessionStart = Date.now();
			}
		}
		function flush() {
			accrue();
			if (buffered >= 3) {
				beacon('/games/' + gid + '/session', { seconds: buffered });
				buffered = 0;
			}
		}

		document.addEventListener('visibilitychange', function () {
			if (document.visibilityState === 'hidden') { flush(); } else { resume(); }
		});
		window.addEventListener('pagehide', flush);
		// Periodic flush for long sessions so data isn't lost on a crash.
		setInterval(flush, 60000);
	})();

	/* ---- Like / dislike ---- */
	(function votes() {
		var bar = document.querySelector('.gh-actions');
		if (!bar) { return; }
		var gid = parseInt(bar.getAttribute('data-game-id'), 10);
		if (!gid) { return; }
		var voteKey = 'gh-vote-' + gid;
		var likeBtn = bar.querySelector('.gh-like');
		var dislikeBtn = bar.querySelector('.gh-dislike');

		function paint(vote) {
			if (likeBtn) { likeBtn.classList.toggle('is-active', vote === 'like'); }
			if (dislikeBtn) { dislikeBtn.classList.toggle('is-active', vote === 'dislike'); }
		}
		paint(lsGet(voteKey));

		// The derived rating (★ x.x) recomputes from likes/dislikes on the server.
		var ratingValueEl = document.querySelector('.gh-rating-value');
		var voteTotalEl = document.querySelector('.gh-vote-total');

		function cast(action) {
			post('/games/' + gid + '/' + action, {}).then(function (res) {
				if (bar.querySelector('.gh-like-count')) { bar.querySelector('.gh-like-count').textContent = shortNum(res.likes); }
				if (bar.querySelector('.gh-dislike-count')) { bar.querySelector('.gh-dislike-count').textContent = shortNum(res.dislikes); }
				if (ratingValueEl && typeof res.rating !== 'undefined') {
					ratingValueEl.textContent = res.rating_count > 0 ? ('★ ' + (Math.round(res.rating * 10) / 10)) : '';
				}
				if (voteTotalEl && typeof res.rating_count !== 'undefined') { voteTotalEl.textContent = shortNum(res.rating_count); }
				lsSet(voteKey, res.user_vote);
				paint(res.user_vote);
			}).catch(function () {});
		}
		if (likeBtn) { likeBtn.addEventListener('click', function () { cast('like'); }); }
		if (dislikeBtn) { dislikeBtn.addEventListener('click', function () { cast('dislike'); }); }
	})();

	function shortNum(n) {
		n = parseInt(n, 10) || 0;
		if (n >= 1e6) { return (n / 1e6).toFixed(1).replace(/\.0$/, '') + 'M'; }
		if (n >= 1e3) { return (n / 1e3).toFixed(1).replace(/\.0$/, '') + 'K'; }
		return String(n);
	}
})();
