/* Owambe Connect — Frontend JS
 * Lightweight progressive enhancements; site works fully without JS. */
(function () {
	'use strict';

	function init() {
		// Auto-submit directory category filter when the select changes (mobile-friendly).
		document.querySelectorAll('.oc-filters select#oc-f-cat').forEach(function (sel) {
			sel.addEventListener('change', function () {
				var form = sel.closest('form');
				if (form) form.submit();
			});
		});

		// Live image-size validation on upload fields.
		document.querySelectorAll('.oc-form input[type="file"]').forEach(function (input) {
			input.addEventListener('change', function () {
				var max = input.name === 'logo' ? 2 * 1024 * 1024 : 5 * 1024 * 1024;
				var file = input.files && input.files[0];
				if (!file) return;
				var note = input.parentNode.querySelector('.oc-file-note');
				if (note) note.remove();
				if (file.size > max) {
					var span = document.createElement('span');
					span.className = 'oc-file-note';
					span.style.cssText = 'display:block;color:#B0354F;font-size:.85rem;margin-top:6px';
					span.textContent = 'This file is larger than the allowed limit. Please choose a smaller image.';
					input.parentNode.appendChild(span);
					input.value = '';
				}
			});
		});

		// Strip leading "@" from Instagram handle as user types.
		var ig = document.querySelector('input[name="instagram"]');
		if (ig) {
			ig.addEventListener('blur', function () {
				ig.value = ig.value.replace(/^@+/, '').trim();
			});
		}

		/* ── Phase 2: contact-click beacons ─────────────────────────
		 * Anchors carrying data-oc-track="whatsapp|email|instagram|
		 * facebook|website" + data-vendor="{id}" ping the (nonce-less,
		 * rate-limited) oc_track endpoint via sendBeacon so navigation
		 * is never delayed. Views are recorded server-side, not here. */
		document.addEventListener('click', function (e) {
			var el = e.target && e.target.closest ? e.target.closest('[data-oc-track]') : null;
			if (!el || typeof OC_DATA === 'undefined' || !OC_DATA.ajax_url) return;
			var vendor = parseInt(el.getAttribute('data-vendor'), 10);
			var metric = 'click_' + String(el.getAttribute('data-oc-track') || '');
			if (!vendor) return;
			var data = new FormData();
			data.append('action', 'oc_track');
			data.append('vendor_id', String(vendor));
			data.append('metric', metric);
			// Logged-in clients: attach the same-origin nonce so the endpoint
			// may update their "recently contacted" list (CSRF-gated). Anonymous
			// stats writes need no nonce (cached pages) and ignore its absence.
			if (OC_DATA.saved_nonce) { data.append('nonce', OC_DATA.saved_nonce); }
			if (navigator.sendBeacon) {
				navigator.sendBeacon(OC_DATA.ajax_url, data);
			} else {
				try { fetch(OC_DATA.ajax_url, { method: 'POST', body: data, keepalive: true }); } catch (err) {}
			}
		}, true);

		/* ── Phase 2: save-vendor heart toggle (logged-in only) ───── */
		document.addEventListener('click', function (e) {
			var btn = e.target && e.target.closest ? e.target.closest('button[data-oc-save]') : null;
			if (!btn || typeof OC_DATA === 'undefined' || !OC_DATA.saved_nonce) return;
			e.preventDefault();
			e.stopPropagation();
			if (btn.__ocBusy) return;
			btn.__ocBusy = true;
			var data = new FormData();
			data.append('action', 'oc_toggle_saved');
			data.append('vendor_id', btn.getAttribute('data-oc-save'));
			data.append('nonce', OC_DATA.saved_nonce);
			fetch(OC_DATA.ajax_url, { method: 'POST', body: data, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (res && res.success) {
						var saved = !!(res.data && res.data.saved);
						btn.classList.toggle('is-saved', saved);
						btn.setAttribute('aria-pressed', saved ? 'true' : 'false');
						ocToast(saved ? 'Saved to your list.' : 'Removed from your list.', 'success');
					} else if (res && res.data && res.data.message) {
						ocToast(res.data.message, 'error');
					}
				})
				.catch(function () {})
				.finally(function () { btn.__ocBusy = false; });
		}, true);

		/* ── Phase 2: copy-link buttons (share my business, review link) ── */
		document.addEventListener('click', function (e) {
			var btn = e.target && e.target.closest ? e.target.closest('[data-oc-copy-link]') : null;
			if (!btn) return;
			e.preventDefault();
			var url = btn.getAttribute('data-oc-copy-link');
			var done = function () { ocToast('Link copied — share it anywhere.', 'success'); };
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(url).then(done).catch(function () { ocCopyFallback(url); done(); });
			} else {
				ocCopyFallback(url);
				done();
			}
		});

		function ocCopyFallback(text) {
			var ta = document.createElement('textarea');
			ta.value = text;
			ta.style.cssText = 'position:fixed;opacity:0;';
			document.body.appendChild(ta);
			ta.select();
			try { document.execCommand('copy'); } catch (err) {}
			ta.remove();
		}

		initProfileNav();
	}

	/* ── Global post-submit toast renderer (moved from the FAB template) ──
	 * Reads ?oc_notice= / ?oc_error= on every public page and shows
	 * slide-in toasts, then strips the params so refresh doesn't repeat. */
	function ocToast(message, type) {
		if (!message) return;
		var holder = document.querySelector('.oc-toast-stack');
		if (!holder) {
			holder = document.createElement('div');
			holder.className = 'oc-toast-stack';
			holder.style.cssText = 'position:fixed;right:20px;bottom:20px;z-index:9050;display:flex;flex-direction:column;gap:10px;max-width:360px;';
			document.body.appendChild(holder);
		}
		var toast = document.createElement('div');
		toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
		toast.style.cssText =
			'background:' + (type === 'error' ? '#B0354F' : '#2E7D5B') + ';' +
			'color:#fff;padding:12px 16px;border-radius:8px;font-size:14px;' +
			'box-shadow:0 6px 18px rgba(0,0,0,.18);display:flex;align-items:flex-start;' +
			'gap:10px;line-height:1.4;opacity:0;transform:translateY(8px);' +
			'transition:opacity .2s ease, transform .2s ease;font-family:inherit;';
		toast.innerHTML =
			'<span style="flex-shrink:0;">' + (type === 'error' ? '⚠️' : '✓') + '</span>' +
			'<span class="oc-gt__msg" style="flex:1;"></span>' +
			'<button type="button" aria-label="Dismiss" style="background:transparent;border:0;color:rgba(255,255,255,.85);font-size:18px;line-height:1;cursor:pointer;padding:0 0 0 4px;">×</button>';
		toast.querySelector('.oc-gt__msg').textContent = message;
		holder.appendChild(toast);
		requestAnimationFrame(function () {
			toast.style.opacity = '1';
			toast.style.transform = 'translateY(0)';
		});
		var dismiss = function () {
			toast.style.opacity = '0';
			toast.style.transform = 'translateY(8px)';
			setTimeout(function () { toast.remove(); }, 220);
		};
		toast.querySelector('button').addEventListener('click', dismiss);
		setTimeout(dismiss, 6000);
	}

	function bootQueryToasts() {
		if (window.__ocGlobalToastBooted) return;
		window.__ocGlobalToastBooted = true;
		var params = new URLSearchParams(window.location.search);
		var notice = params.get('oc_notice');
		var error  = params.get('oc_error');
		if (!notice && !error) return;
		params.delete('oc_notice');
		params.delete('oc_error');
		var clean = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + window.location.hash;
		try { history.replaceState(null, '', clean); } catch (e) {}
		if (notice) ocToast(decodeURIComponent(notice), 'success');
		if (error)  ocToast(decodeURIComponent(error),  'error');
	}

	/* ── Vendor profile: IntersectionObserver scroll-spy for the section nav ──
	 * Highlights the pill of the section currently in view. Sections inside a
	 * sticky/fixed container (the desktop sidebar Contact card) are excluded so
	 * the always-pinned sidebar can't hijack the highlight. Pairs with the
	 * template header-offset script, which sets the nav's sticky top. */
	function initProfileNav() {
		var nav = document.querySelector('.oc-vp-nav');
		if (!nav || !('IntersectionObserver' in window)) return;
		var items = Array.prototype.slice.call(nav.querySelectorAll('.oc-vp-nav__link')).map(function (a) {
			var id = (a.getAttribute('href') || '').replace(/^#/, '');
			return { link: a, sec: id && document.getElementById(id) };
		}).filter(function (o) { return o.sec; });
		if (!items.length) return;

		var setActive = function (link) {
			items.forEach(function (o) { o.link.classList.toggle('is-active', o.link === link); });
		};
		// A section inside a sticky/fixed ancestor (e.g. the desktop sidebar) stays in
		// view regardless of scroll, so it must not drive the highlight.
		var isPinnedChain = function (el) {
			for (var n = el; n && !(n.classList && n.classList.contains('oc-vp')); n = n.parentElement) {
				var pos = getComputedStyle(n).position;
				if (pos === 'sticky' || pos === 'fixed') return true;
			}
			return false;
		};
		var inFlowPool = function () {
			var pool = items.filter(function (o) { return !isPinnedChain(o.sec); });
			return pool.length ? pool : items;
		};
			var byId = {};
			items.forEach(function (o) { byId[o.sec.id] = o; });

			// Trigger line = the pinned nav's bottom edge: header offset (set inline by the
			// template) + the floating bubble's own margin + its height, nudged a few px down
			// so a section counts as active once its top clears the bar.
			var lineY = function () {
				var top = parseInt(nav.style.top, 10);
				if (isNaN(top)) top = Math.round(nav.getBoundingClientRect().top);
				var mt = parseFloat(getComputedStyle(nav).marginTop) || 0;
				return Math.max(0, Math.round(top + mt)) + nav.offsetHeight + 6;
			};

			// A 1px observer band sitting on that line means exactly ONE section — the one
			// currently under the bar — intersects. Fixes the earlier "topmost visible" bug
			// where a tall section still peeking into a wide band kept winning.
			var observer = null;
			var build = function () {
				if (observer) observer.disconnect();
				var T = Math.round(lineY());
				var vh = window.innerHeight || document.documentElement.clientHeight;
				var bottom = Math.max(0, vh - T - 1);
				observer = new IntersectionObserver(function (entries) {
					entries.forEach(function (e) {
						if (e.isIntersecting && byId[e.target.id]) setActive(byId[e.target.id].link);
					});
				}, { rootMargin: '-' + T + 'px 0px -' + bottom + 'px 0px', threshold: 0 });
				inFlowPool().forEach(function (o) { observer.observe(o.sec); });
			};
			build();

		var raf;
		window.addEventListener('resize', function () {
			if (raf) cancelAnimationFrame(raf);
			raf = requestAnimationFrame(build);
		}, { passive: true });

		// Bottom-of-page guard: light the last in-flow pill when scrolling can't go further.
		window.addEventListener('scroll', function () {
			if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2) {
				var pool = inFlowPool();
				setActive(pool[pool.length - 1].link);
			}
		}, { passive: true });

		// Snappy feedback: highlight immediately on click; the observer refines after scroll.
		items.forEach(function (o) {
			o.link.addEventListener('click', function () { setActive(o.link); });
		});
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { init(); bootQueryToasts(); });
	} else {
		init();
		bootQueryToasts();
	}
})();
