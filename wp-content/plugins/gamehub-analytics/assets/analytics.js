/* GameHub Analytics — dependency-free canvas chart.
 * Renders window.GHUB_SERIES { labels: [Y-m-d], values: [int], metric } as a
 * responsive area/line chart with hover tooltips. No external libraries.
 */
(function () {
	'use strict';

	var data = window.GHUB_SERIES;
	var canvas = document.getElementById('ghub-chart');
	if (!canvas || !data || !data.values || !data.values.length) {
		if (canvas) {
			var ctxEmpty = canvas.getContext('2d');
			canvas.height = 90;
			ctxEmpty.fillStyle = '#787c82';
			ctxEmpty.font = '14px -apple-system, sans-serif';
			ctxEmpty.fillText('No data in this range yet.', 12, 40);
		}
		return;
	}

	var labels = data.labels;
	var values = data.values.map(function (v) { return parseInt(v, 10) || 0; });
	var isTime = data.metric === 'session_seconds';

	function fmtVal(v) {
		if (isTime) {
			var m = Math.round(v / 60);
			return m >= 60 ? (Math.floor(m / 60) + 'h ' + (m % 60) + 'm') : (m + 'm');
		}
		if (v >= 1e6) { return (v / 1e6).toFixed(1).replace(/\.0$/, '') + 'M'; }
		if (v >= 1e3) { return (v / 1e3).toFixed(1).replace(/\.0$/, '') + 'K'; }
		return String(v);
	}

	var DPR = window.devicePixelRatio || 1;
	var pad = { l: 46, r: 12, t: 14, b: 26 };
	var hover = -1;

	function draw() {
		var cssW = canvas.clientWidth || 800;
		var cssH = 220;
		canvas.width = cssW * DPR;
		canvas.height = cssH * DPR;
		canvas.style.height = cssH + 'px';
		var ctx = canvas.getContext('2d');
		ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
		ctx.clearRect(0, 0, cssW, cssH);

		var w = cssW - pad.l - pad.r;
		var h = cssH - pad.t - pad.b;
		var max = Math.max.apply(null, values);
		max = max <= 0 ? 1 : max;
		var n = values.length;

		var accent = getComputedStyle(document.documentElement).getPropertyValue('--wp-admin-theme-color') || '#2271b1';
		accent = accent.trim() || '#2271b1';

		// Y gridlines + labels.
		ctx.strokeStyle = '#ececed';
		ctx.fillStyle = '#787c82';
		ctx.font = '11px -apple-system, sans-serif';
		ctx.textAlign = 'right';
		var ticks = 4;
		for (var t = 0; t <= ticks; t++) {
			var yv = max * (t / ticks);
			var y = pad.t + h - (yv / max) * h;
			ctx.beginPath();
			ctx.moveTo(pad.l, y);
			ctx.lineTo(pad.l + w, y);
			ctx.stroke();
			ctx.fillText(fmtVal(Math.round(yv)), pad.l - 8, y + 3);
		}

		function px(i) { return n === 1 ? pad.l + w / 2 : pad.l + (i / (n - 1)) * w; }
		function py(v) { return pad.t + h - (v / max) * h; }

		// Area fill.
		ctx.beginPath();
		ctx.moveTo(px(0), py(values[0]));
		for (var i = 1; i < n; i++) { ctx.lineTo(px(i), py(values[i])); }
		ctx.lineTo(px(n - 1), pad.t + h);
		ctx.lineTo(px(0), pad.t + h);
		ctx.closePath();
		var grad = ctx.createLinearGradient(0, pad.t, 0, pad.t + h);
		grad.addColorStop(0, hexA(accent, 0.28));
		grad.addColorStop(1, hexA(accent, 0.02));
		ctx.fillStyle = grad;
		ctx.fill();

		// Line.
		ctx.beginPath();
		ctx.moveTo(px(0), py(values[0]));
		for (var j = 1; j < n; j++) { ctx.lineTo(px(j), py(values[j])); }
		ctx.strokeStyle = accent;
		ctx.lineWidth = 2;
		ctx.stroke();

		// X labels (first, middle, last).
		ctx.fillStyle = '#787c82';
		ctx.textAlign = 'center';
		[0, Math.floor(n / 2), n - 1].forEach(function (idx) {
			if (labels[idx]) { ctx.fillText(shortDate(labels[idx]), px(idx), pad.t + h + 18); }
		});

		// Hover marker + tooltip.
		if (hover >= 0 && hover < n) {
			var hx = px(hover), hy = py(values[hover]);
			ctx.strokeStyle = hexA(accent, 0.5);
			ctx.beginPath(); ctx.moveTo(hx, pad.t); ctx.lineTo(hx, pad.t + h); ctx.stroke();
			ctx.fillStyle = accent;
			ctx.beginPath(); ctx.arc(hx, hy, 4, 0, Math.PI * 2); ctx.fill();

			var text = labels[hover] + ' · ' + fmtVal(values[hover]);
			ctx.font = '12px -apple-system, sans-serif';
			var tw = ctx.measureText(text).width + 16;
			var tx = Math.min(Math.max(hx - tw / 2, pad.l), pad.l + w - tw);
			ctx.fillStyle = 'rgba(29,35,39,.92)';
			roundRect(ctx, tx, pad.t - 2, tw, 22, 5);
			ctx.fill();
			ctx.fillStyle = '#fff';
			ctx.textAlign = 'left';
			ctx.fillText(text, tx + 8, pad.t + 13);
		}

		canvas._px = px;
	}

	function roundRect(ctx, x, y, w, h, r) {
		ctx.beginPath();
		ctx.moveTo(x + r, y);
		ctx.arcTo(x + w, y, x + w, y + h, r);
		ctx.arcTo(x + w, y + h, x, y + h, r);
		ctx.arcTo(x, y + h, x, y, r);
		ctx.arcTo(x, y, x + w, y, r);
		ctx.closePath();
	}
	function hexA(hex, a) {
		hex = hex.replace('#', '').trim();
		if (hex.length === 3) { hex = hex.replace(/(.)/g, '$1$1'); }
		var n = parseInt(hex, 16);
		return 'rgba(' + ((n >> 16) & 255) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + a + ')';
	}
	function shortDate(d) {
		var p = d.split('-');
		return p.length === 3 ? (p[1] + '/' + p[2]) : d;
	}

	canvas.addEventListener('mousemove', function (e) {
		var rect = canvas.getBoundingClientRect();
		var x = e.clientX - rect.left;
		var cssW = canvas.clientWidth;
		var w = cssW - pad.l - pad.r;
		var n = values.length;
		var rel = (x - pad.l) / (w || 1);
		var idx = Math.round(rel * (n - 1));
		idx = Math.max(0, Math.min(n - 1, idx));
		if (idx !== hover) { hover = idx; draw(); }
	});
	canvas.addEventListener('mouseleave', function () { hover = -1; draw(); });
	window.addEventListener('resize', draw);
	draw();
})();
