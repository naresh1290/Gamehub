/* GameHub theme front-end: layout (sidebar + instant search), player,
 * metrics beacons, votes, theme toggle, and recently-played.
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
		fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body || {}), keepalive: true }).catch(function () {});
	}
	function lsGet(k) { try { return JSON.parse(localStorage.getItem(k)); } catch (e) { return null; } }
	function lsSet(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} }
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function shortNum(n) {
		n = parseInt(n, 10) || 0;
		if (n >= 1e6) { return (n / 1e6).toFixed(1).replace(/\.0$/, '') + 'M'; }
		if (n >= 1e3) { return (n / 1e3).toFixed(1).replace(/\.0$/, '') + 'K'; }
		return String(n);
	}

	/* ---- Sidebar toggle (mobile) ---- */
	(function sidebar() {
		var app = document.querySelector('.gh-app');
		if (!app) { return; }
		document.addEventListener('click', function (e) {
			if (e.target.closest('[data-gh-sidebar-open]')) { app.classList.add('gh-sidebar-open'); }
			else if (e.target.closest('[data-gh-sidebar-close]')) { app.classList.remove('gh-sidebar-open'); }
		});
	})();

	/* ---- Theme toggle (persist) ---- */
	(function theme() {
		var saved = lsGet('gh-theme');
		if (saved === 'dark' || saved === 'light') { document.documentElement.setAttribute('data-theme', saved); }
		document.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-gh-theme-toggle]');
			if (!btn) { return; }
			var cur = document.documentElement.getAttribute('data-theme') ||
				(window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
			var next = cur === 'dark' ? 'light' : 'dark';
			document.documentElement.setAttribute('data-theme', next);
			lsSet('gh-theme', next);
		});
	})();

	/* ---- Instant search (games + categories, navigate on click) ---- */
	(function search() {
		var wrap = document.querySelector('[data-gh-search]');
		if (!wrap) { return; }
		var input = wrap.querySelector('.gh-search-input');
		var panel = wrap.querySelector('.gh-search-panel');
		var timer = null, seq = 0, items = [], active = -1;

		function close() { panel.hidden = true; panel.innerHTML = ''; active = -1; items = []; }
		function open() { panel.hidden = false; }

		function render(data) {
			var html = '';
			if (data.categories && data.categories.length) {
				html += '<div class="gh-search-group-label">Categories</div>';
				data.categories.forEach(function (c) {
					html += '<a class="gh-search-row" href="' + esc(c.url) + '">' +
						'<span class="gh-search-thumb">#</span>' +
						'<span><span class="gh-search-row-name">' + esc(c.name) + '</span>' +
						'<span class="gh-search-row-sub">' + shortNum(c.count) + ' games</span></span></a>';
				});
			}
			if (data.games && data.games.length) {
				html += '<div class="gh-search-group-label">Games</div>';
				data.games.forEach(function (g) {
					var thumb = g.icon ? '<img src="' + esc(g.icon) + '" alt="" loading="lazy">' : '<span class="gh-search-thumb">' + esc((g.name || '?')[0]) + '</span>';
					html += '<a class="gh-search-row" href="' + esc(g.url) + '">' + thumb +
						'<span class="gh-search-row-name">' + esc(g.name) + '</span></a>';
				});
			}
			if (!html) { html = '<div class="gh-search-empty">No games or categories found.</div>'; }
			panel.innerHTML = html;
			items = Array.prototype.slice.call(panel.querySelectorAll('.gh-search-row'));
			active = -1;
			open();
		}

		function run(q) {
			var mine = ++seq;
			fetch(REST + '/search?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (data) { if (mine === seq) { render(data); } })
				.catch(function () {});
		}

		input.addEventListener('input', function () {
			var q = input.value.trim();
			clearTimeout(timer);
			if (q.length < 1) { close(); return; }
			timer = setTimeout(function () { run(q); }, 160);
		});
		input.addEventListener('focus', function () { if (input.value.trim() && panel.innerHTML) { open(); } });
		input.addEventListener('keydown', function (e) {
			if (panel.hidden || !items.length) { return; }
			if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(active + 1, items.length - 1); }
			else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(active - 1, 0); }
			else if (e.key === 'Enter') { if (active >= 0) { e.preventDefault(); window.location.href = items[active].getAttribute('href'); } return; }
			else if (e.key === 'Escape') { close(); return; }
			items.forEach(function (el, i) { el.classList.toggle('is-active', i === active); });
			if (items[active]) { items[active].scrollIntoView({ block: 'nearest' }); }
		});
		document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) { close(); } });
	})();

	/* ---- Recently played ---- */
	function recordRecent() {
		var head = document.querySelector('.gh-game-head');
		if (!head) { return; }
		var name = (head.querySelector('h1') || {}).textContent || document.title;
		var img = head.querySelector('img');
		var entry = { name: name.trim(), url: location.pathname, icon: img ? img.src : '' };
		var list = lsGet('gh-recent') || [];
		list = list.filter(function (x) { return x.url !== entry.url; });
		list.unshift(entry);
		lsSet('gh-recent', list.slice(0, 12));
	}
	function renderRecent() {
		var sec = document.querySelector('[data-gh-recent]');
		var grid = document.querySelector('[data-gh-recent-grid]');
		if (!sec || !grid) { return; }
		var list = (lsGet('gh-recent') || []).slice(0, 12);
		if (!list.length) { return; }
		grid.innerHTML = list.map(function (g) {
			var thumb = g.icon ? '<img src="' + esc(g.icon) + '" alt="" loading="lazy">' : '';
			return '<a class="gh-card" href="' + esc(g.url) + '"><div class="gh-card-thumb">' + thumb +
				'</div><div class="gh-card-body"><p class="gh-card-title">' + esc(g.name) + '</p></div></a>';
		}).join('');
		sec.hidden = false;
	}

	/* ---- Player: auto-run (desktop/tablet), immersive fullscreen, share ---- */
	(function player() {
		var wrap = document.querySelector('.gh-player');
		if (!wrap) { renderRecent(); return; }
		var gid = parseInt(wrap.getAttribute('data-game-id'), 10);
		if (!gid) { return; }

		recordRecent();
		beacon('/games/' + gid + '/visit', {});

		var stage = wrap.querySelector('.gh-player-stage');
		var launch = wrap.querySelector('.gh-player-launch');
		var started = false, sessionStart = 0, buffered = 0;

		function inject() {
			if (started) { return; }
			started = true;
			var iframe = document.createElement('iframe');
			iframe.src = wrap.getAttribute('data-iframe');
			iframe.title = wrap.getAttribute('data-title') || 'game';
			iframe.allow = 'autoplay; fullscreen; gamepad; accelerometer; gyroscope; clipboard-write; cross-origin-isolated';
			iframe.allowFullscreen = true;
			if (launch) { launch.style.display = 'none'; }
			stage.appendChild(iframe);
			post('/games/' + gid + '/play', {}).catch(function () {});
			sessionStart = Date.now();
		}
		function enterImmersive() { inject(); wrap.classList.add('is-immersive'); document.body.classList.add('gh-noscroll'); }
		function exitImmersive() { wrap.classList.remove('is-immersive'); document.body.classList.remove('gh-noscroll'); }

		// Tablet/desktop: run the game directly. Phones: tap to launch fullscreen.
		var isDesktop = window.innerWidth >= 768;
		if (isDesktop) { inject(); }
		if (launch) {
			launch.addEventListener('click', function () { inject(); if (!isDesktop) { enterImmersive(); } });
			launch.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); launch.click(); } });
		}

		var fsEnter = wrap.querySelector('[data-gh-fs-enter]');
		if (fsEnter) { fsEnter.addEventListener('click', enterImmersive); }
		var fsExit = wrap.querySelector('[data-gh-fs-exit]');
		if (fsExit) { fsExit.addEventListener('click', exitImmersive); }
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { exitImmersive(); } });

		var shareBtn = wrap.querySelector('[data-gh-share]');
		if (shareBtn) {
			shareBtn.addEventListener('click', function () {
				var url = location.href, title = wrap.getAttribute('data-title') || document.title;
				if (navigator.share) { navigator.share({ title: title, url: url }).catch(function () {}); }
				else if (navigator.clipboard) {
					navigator.clipboard.writeText(url).then(function () {
						shareBtn.classList.add('is-active');
						setTimeout(function () { shareBtn.classList.remove('is-active'); }, 1200);
					});
				}
			});
		}

		function accrue() { if (started && sessionStart) { buffered += Math.round((Date.now() - sessionStart) / 1000); sessionStart = 0; } }
		function resume() { if (started && !sessionStart && document.visibilityState === 'visible') { sessionStart = Date.now(); } }
		function flush() { accrue(); if (buffered >= 3) { beacon('/games/' + gid + '/session', { seconds: buffered }); buffered = 0; } }
		document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') { flush(); } else { resume(); } });
		window.addEventListener('pagehide', flush);
		setInterval(flush, 60000);
	})();

	/* ---- Like / dislike (+ derived rating) ---- */
	(function votes() {
		var bar = document.querySelector('.gh-actions');
		if (!bar) { return; }
		var gid = parseInt(bar.getAttribute('data-game-id'), 10);
		if (!gid) { return; }
		var voteKey = 'gh-vote-' + gid;
		var likeBtn = bar.querySelector('.gh-like');
		var dislikeBtn = bar.querySelector('.gh-dislike');
		var ratingValueEl = document.querySelector('.gh-rating-value');
		var voteTotalEl = document.querySelector('.gh-vote-total');

		function paint(vote) {
			if (likeBtn) { likeBtn.classList.toggle('is-active', vote === 'like'); }
			if (dislikeBtn) { dislikeBtn.classList.toggle('is-active', vote === 'dislike'); }
		}
		paint(lsGet(voteKey));

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
})();
