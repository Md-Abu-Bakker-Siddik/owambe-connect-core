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
		initSmoothAnchors();
		initCarousels(document);
		bootDynamicCarousels();
	}

	/* ── Global post-submit toast renderer (moved from the FAB template) ──
	 * The renderer remains below; shared carousel mechanics are defined first. */
	/* Shared carousel controller.
	 *
	 * Markup:
	 *   [data-oc-carousel]
	 *     [data-oc-carousel-prev]
	 *     .oc-carousel__track > cards
	 *     [data-oc-carousel-next]
	 *     [data-oc-carousel-dots] (optional)
	 *
	 * Configuration:
	 *   data-oc-carousel-autoplay="yes|no" (default: yes)
	 *   data-oc-carousel-interval="milliseconds" (default: 5500)
	 *   data-oc-carousel-step="card|group" (default: card)
	 *   data-oc-carousel-loop="yes|no" (default: yes)
	 *
	 * Touch scrolling stays native to the overflow track. JavaScript only
	 * pauses autoplay for the touch lifecycle; it never prevents a swipe.
	 */
	function initCarousels(scope) {
		var root = scope && scope.querySelectorAll ? scope : document;
		var carousels = [];

		if (root.nodeType === 1 && root.matches && root.matches('[data-oc-carousel]')) {
			carousels.push(root);
		}
		Array.prototype.forEach.call(root.querySelectorAll('[data-oc-carousel]'), function (carousel) {
			carousels.push(carousel);
		});

		carousels.forEach(function (carousel) {
			if (carousel.__ocCarouselController) {
				carousel.__ocCarouselController.refresh();
				return;
			}
			createCarouselController(carousel);
		});
	}

	function createCarouselController(carousel) {
		var track = carousel.querySelector('.oc-carousel__track');
		if (!track) return;

		var prev = carousel.querySelector('[data-oc-carousel-prev]');
		var next = carousel.querySelector('[data-oc-carousel-next]');
		var dots = findCarouselDots(carousel);
		var reduceQuery = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
		var autoplayValue = String(carousel.getAttribute('data-oc-carousel-autoplay') || 'yes').toLowerCase();
		var autoplay = [ 'no', 'false', '0', 'off' ].indexOf(autoplayValue) === -1;
		var interval = parseInt(carousel.getAttribute('data-oc-carousel-interval'), 10);
		var loopValue = String(carousel.getAttribute('data-oc-carousel-loop') || 'yes').toLowerCase();
		var loop = [ 'no', 'false', '0', 'off' ].indexOf(loopValue) === -1;
		var groupStep = 'group' === String(carousel.getAttribute('data-oc-carousel-step') || 'card').toLowerCase();
		var state = {
			stops: [ 0 ],
			index: 0,
			overflow: false,
			hovered: false,
			focused: false,
			touching: false,
			inView: true,
			timer: 0,
			touchTimer: 0,
			scrollFrame: 0,
			resizeTimer: 0
		};

		interval = isFinite(interval) ? Math.max(2500, interval) : 5500;
		carousel.__ocCarouselController = {
			refresh: refresh
		};

		function reducedMotion() {
			return !!(reduceQuery && reduceQuery.matches);
		}

		function maxScroll() {
			return Math.max(0, track.scrollWidth - track.clientWidth);
		}

		function uniqueStop(stops, value) {
			value = Math.max(0, Math.min(maxScroll(), Math.round(value)));
			if (!stops.length || Math.abs(stops[stops.length - 1] - value) > 2) {
				stops.push(value);
			}
		}

		function buildStops() {
			var items = Array.prototype.slice.call(track.children);
			var maximum = maxScroll();
			var stops = [];

			if (!items.length || maximum <= 2) {
				return [ 0 ];
			}

			var firstOffset = items[0].offsetLeft;
			if (groupStep) {
				var cardStep = items.length > 1
					? Math.max(1, items[1].offsetLeft - items[0].offsetLeft)
					: Math.max(1, items[0].getBoundingClientRect().width);
				var cardsPerGroup = Math.max(1, Math.floor((track.clientWidth + 1) / cardStep));
				items.forEach(function (item, itemIndex) {
					if (itemIndex % cardsPerGroup === 0) {
						uniqueStop(stops, item.offsetLeft - firstOffset);
					}
				});
			} else {
				items.forEach(function (item) {
					uniqueStop(stops, item.offsetLeft - firstOffset);
				});
			}

			uniqueStop(stops, maximum);
			return stops.length ? stops : [ 0 ];
		}

		function nearestIndex() {
			var left = track.scrollLeft;
			var best = 0;
			var distance = Infinity;
			state.stops.forEach(function (stop, index) {
				var nextDistance = Math.abs(stop - left);
				if (nextDistance < distance) {
					distance = nextDistance;
					best = index;
				}
			});
			return best;
		}

		function setControlState(control, disabled, hidden) {
			if (!control) return;
			control.hidden = !!hidden;
			control.classList.toggle('is-disabled', !!disabled);
			control.setAttribute('aria-disabled', disabled ? 'true' : 'false');
			if ('disabled' in control) control.disabled = !!disabled;
		}

		function syncDots() {
			if (!dots) return;
			var dotButtons = dots.querySelectorAll('.oc-carousel__dot');
			Array.prototype.forEach.call(dotButtons, function (dot, index) {
				var active = index === state.index;
				dot.classList.toggle('is-active', active);
				if (active) {
					dot.setAttribute('aria-current', 'true');
				} else {
					dot.removeAttribute('aria-current');
				}
			});
		}

		function sync() {
			var maximum = maxScroll();
			state.overflow = maximum > 2 && state.stops.length > 1;
			state.index = nearestIndex();

			var atStart = track.scrollLeft <= 2;
			var atEnd = track.scrollLeft >= maximum - 2;
			carousel.classList.toggle('is-static', !state.overflow);
			carousel.classList.toggle('is-at-start', atStart);
			carousel.classList.toggle('is-at-end', atEnd);

			setControlState(prev, !state.overflow || (!loop && atStart), !state.overflow);
			setControlState(next, !state.overflow || (!loop && atEnd), !state.overflow);
			if (dots) dots.hidden = !state.overflow;
			syncDots();
			syncAutoplay();
		}

		function buildDots() {
			if (!dots) return;
			dots.innerHTML = '';
			state.stops.forEach(function (stop, index) {
				var dot = document.createElement('button');
				dot.type = 'button';
				dot.className = 'oc-carousel__dot';
				dot.setAttribute('aria-label', 'Go to slide group ' + (index + 1));
				dot.addEventListener('click', function () {
					goTo(index);
					restartAutoplay();
				});
				dots.appendChild(dot);
			});
		}

		function refresh() {
			window.clearTimeout(state.resizeTimer);
			state.resizeTimer = window.setTimeout(function () {
				state.stops = buildStops();
				buildDots();
				sync();
			}, 40);
		}

		function goTo(index) {
			if (!state.overflow || !state.stops.length) return;
			var last = state.stops.length - 1;
			if (loop) {
				if (index < 0) index = last;
				if (index > last) index = 0;
			} else {
				index = Math.max(0, Math.min(last, index));
			}
			state.index = index;
			track.scrollTo({
				left: state.stops[index],
				behavior: reducedMotion() ? 'auto' : 'smooth'
			});
			syncDots();
		}

		function move(direction, manual) {
			state.index = nearestIndex();
			goTo(state.index + direction);
			if (manual) restartAutoplay();
		}

		function canAutoplay() {
			return autoplay &&
				state.overflow &&
				state.inView &&
				!document.hidden &&
				!state.hovered &&
				!state.focused &&
				!state.touching &&
				!reducedMotion() &&
				carousel.isConnected;
		}

		function stopAutoplay() {
			if (state.timer) {
				window.clearTimeout(state.timer);
				state.timer = 0;
			}
		}

		function syncAutoplay() {
			if (!canAutoplay()) {
				stopAutoplay();
				return;
			}
			if (!state.timer) {
				state.timer = window.setTimeout(function () {
					state.timer = 0;
					move(1, false);
					syncAutoplay();
				}, interval);
			}
		}

		function restartAutoplay() {
			stopAutoplay();
			syncAutoplay();
		}

		if (prev) {
			prev.addEventListener('click', function () {
				move(-1, true);
			});
		}
		if (next) {
			next.addEventListener('click', function () {
				move(1, true);
			});
		}

		track.addEventListener('scroll', function () {
			if (state.scrollFrame) return;
			state.scrollFrame = window.requestAnimationFrame(function () {
				state.scrollFrame = 0;
				sync();
			});
		}, { passive: true });

		carousel.addEventListener('mouseenter', function () {
			state.hovered = true;
			syncAutoplay();
		});
		carousel.addEventListener('mouseleave', function () {
			state.hovered = false;
			restartAutoplay();
		});
		carousel.addEventListener('focusin', function () {
			state.focused = true;
			syncAutoplay();
		});
		carousel.addEventListener('focusout', function (event) {
			if (event.relatedTarget && carousel.contains(event.relatedTarget)) return;
			state.focused = false;
			restartAutoplay();
		});

		track.addEventListener('touchstart', function () {
			window.clearTimeout(state.touchTimer);
			state.touching = true;
			syncAutoplay();
		}, { passive: true });

		function finishTouch() {
			window.clearTimeout(state.touchTimer);
			state.touchTimer = window.setTimeout(function () {
				state.touching = false;
				restartAutoplay();
			}, 1400);
		}
		track.addEventListener('touchend', finishTouch, { passive: true });
		track.addEventListener('touchcancel', finishTouch, { passive: true });

		document.addEventListener('visibilitychange', function () {
			syncAutoplay();
		});

		if (reduceQuery) {
			var onMotionChange = function () {
				syncAutoplay();
			};
			if (reduceQuery.addEventListener) {
				reduceQuery.addEventListener('change', onMotionChange);
			} else if (reduceQuery.addListener) {
				reduceQuery.addListener(onMotionChange);
			}
		}

		if ('IntersectionObserver' in window) {
			var visibilityObserver = new IntersectionObserver(function (entries) {
				entries.forEach(function (entry) {
					state.inView = entry.isIntersecting && entry.intersectionRatio > 0;
					syncAutoplay();
				});
			}, { threshold: 0.01 });
			visibilityObserver.observe(carousel);
		}

		if ('ResizeObserver' in window) {
			var resizeObserver = new ResizeObserver(refresh);
			resizeObserver.observe(track);
		} else {
			window.addEventListener('resize', refresh, { passive: true });
		}

		state.stops = buildStops();
		buildDots();
		sync();
	}

	function findCarouselDots(carousel) {
		var internal = carousel.querySelector('[data-oc-carousel-dots]');
		if (internal) return internal;

		var target = carousel.getAttribute('data-oc-carousel-dots');
		if (target && target !== 'yes' && target !== 'true') {
			try {
				return document.querySelector(target);
			} catch (error) {}
		}

		if (carousel.id) {
			var candidates = document.querySelectorAll('[data-oc-carousel-dots-for]');
			for (var i = 0; i < candidates.length; i++) {
				if (candidates[i].getAttribute('data-oc-carousel-dots-for') === carousel.id) {
					return candidates[i];
				}
			}
		}
		return null;
	}

	/* Elementor replaces widget DOM during editing. Its frontend hook handles
	 * that path; the small observer covers other AJAX/dynamic insertions too.
	 * createCarouselController() is property-guarded, so either path is safe. */
	function bootDynamicCarousels() {
		if (window.__ocDynamicCarouselsBooted) return;
		window.__ocDynamicCarouselsBooted = true;

		var hookElementor = function () {
			if (!window.elementorFrontend || !window.elementorFrontend.hooks || window.__ocElementorCarouselHooked) return;
			window.__ocElementorCarouselHooked = true;
			window.elementorFrontend.hooks.addAction('frontend/element_ready/global', function (scope) {
				initCarousels(scope && scope[0] ? scope[0] : scope);
			});
		};

		hookElementor();
		window.addEventListener('elementor/frontend/init', hookElementor);
		if (window.jQuery) {
			window.jQuery(window).on('elementor/frontend/init.ocCarousel', hookElementor);
		}

		if ('MutationObserver' in window && document.body) {
			var observer = new MutationObserver(function (mutations) {
				mutations.forEach(function (mutation) {
					Array.prototype.forEach.call(mutation.addedNodes, function (node) {
						if (node.nodeType !== 1) return;
						if ((node.matches && node.matches('[data-oc-carousel]')) || node.querySelector('[data-oc-carousel]')) {
							initCarousels(node);
						}
					});
				});
			});
			observer.observe(document.body, { childList: true, subtree: true });
		}
	}

	/* Global post-submit toast renderer (moved from the FAB template).
	 * Reads ?oc_notice= / ?oc_error= on every public page and shows
	 * slide-in toasts, then strips the params so refresh does not repeat. */
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
	/* ── Smooth in-page scrolling for tab links & section anchors ──
	 * Within vendor profiles (.oc-vp) and dashboards (.oc-vd/.oc-cd), same-page hash
	 * links scroll smoothly to their target instead of jumping. scrollIntoView honours
	 * each target's scroll-margin-top, so sections land below the sticky header/nav.
	 * Respects prefers-reduced-motion; cross-page links are left untouched. */
	function initSmoothAnchors() {
		var SCOPES = '.oc-vp, .oc-vd, .oc-cd';
		document.addEventListener('click', function (e) {
			var a = e.target && e.target.closest ? e.target.closest('a[href*="#"]') : null;
			if (!a) return;
			var hash = a.hash;
			if (!hash || hash === '#') return;
			if (!a.closest(SCOPES)) return;
			// Same-page only — let links to other pages navigate normally.
			var here = window.location.pathname;
			var path = a.pathname || here;
			if (path !== here && path + '/' !== here && path !== here + '/') return;
			var target = document.getElementById(decodeURIComponent(hash.slice(1)));
			if (!target) return;
			e.preventDefault();
			var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
			target.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
			// Move focus for keyboard / assistive tech, without a second jump.
			if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
			target.focus({ preventScroll: true });
			// Reflect the section in the URL without a native jump.
			if (window.history && history.replaceState) history.replaceState(null, '', hash);
		});
	}
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


/* ── Typeahead component (H3) — shared by the hero, directory filters and
   any future search input. Two modes on the same markup:
     [data-oc-typeahead]                    → static list from data-suggestions JSON
     [data-oc-typeahead-remote="action"]   → debounced admin-ajax suggestions
   Matching uses the same normalization as the server (H2): lowercase, strip
   apostrophes, punctuation → spaces — so "stoke-on" matches "Stoke on Trent".
   Keyboard (arrows/Enter/Escape), ARIA combobox semantics, loading state,
   free typing always allowed. data-oc-typeahead-submit="no" disables the
   submit-on-pick behaviour. */
(function () {
	'use strict';

	function ocNorm(s) {
		return (s || '').toString().toLowerCase()
			.replace(/['’`´]/g, '')
			.replace(/[^\p{L}\p{N}]+/gu, ' ')
			.replace(/\s+/g, ' ').trim();
	}

	function initTypeahead(box) {
		if (box.getAttribute('data-oc-ta-ready')) { return; }
		box.setAttribute('data-oc-ta-ready', '1');

		var input = box.querySelector('.oc-typeahead__input');
		var list  = box.querySelector('.oc-typeahead__list');
		var clear = box.querySelector('[data-oc-typeahead-clear]');
		if (!input || !list) { return; }

		var remote  = box.getAttribute('data-oc-typeahead-remote') || '';
		var ajaxUrl = box.getAttribute('data-ajax-url') || (window.OC_DATA && OC_DATA.ajax_url) || '/wp-admin/admin-ajax.php';
		var submitOnPick = box.getAttribute('data-oc-typeahead-submit') !== 'no';

		var suggestions = [];
		try { suggestions = JSON.parse(input.getAttribute('data-suggestions') || '[]'); } catch (e) {}

		var active = -1, matches = [], debounceTimer = null, controller = null;

		function render(items, note) {
			list.innerHTML = '';
			matches = items;
			active = -1;
			if (!items.length && !note) {
				list.hidden = true;
				input.setAttribute('aria-expanded', 'false');
				return;
			}
			var frag = document.createDocumentFragment();
			if (note) {
				var li = document.createElement('li');
				li.className = 'oc-typeahead__item oc-typeahead__note';
				li.setAttribute('aria-disabled', 'true');
				li.textContent = note;
				frag.appendChild(li);
			}
			items.forEach(function (label, i) {
				var li = document.createElement('li');
				li.className = 'oc-typeahead__item';
				li.setAttribute('role', 'option');
				li.setAttribute('data-i', i);
				li.textContent = label;
				frag.appendChild(li);
			});
			list.appendChild(frag);
			list.hidden = false;
			input.setAttribute('aria-expanded', 'true');
		}

		var defaults = ['London', 'Manchester', 'Birmingham', 'Edinburgh', 'Cardiff', 'Belfast']
			.filter(function (label) { return suggestions.indexOf(label) !== -1; });

		function filterStatic() {
			var q = ocNorm(input.value);
			if (!q) { render(defaults); return; }
			var prefix = [], sub = [];
			for (var i = 0; i < suggestions.length; i++) {
				var nv = ocNorm(suggestions[i]);
				if (nv === q) { continue; }
				if (nv.indexOf(q) === 0) { prefix.push(suggestions[i]); }
				else if (nv.indexOf(q) > 0) { sub.push(suggestions[i]); }
			}
			render(prefix.concat(sub).slice(0, 10));
		}

		function fetchRemote() {
			var term = input.value.trim();
			if (term.length < 2) { render([]); return; }
			if (controller) { controller.abort(); }
			controller = ('AbortController' in window) ? new AbortController() : null;
			render([], '…');
			fetch(ajaxUrl + '?action=' + encodeURIComponent(remote) + '&term=' + encodeURIComponent(term), {
				credentials: 'same-origin',
				signal: controller ? controller.signal : undefined
			})
				.then(function (r) { return r.json(); })
				.then(function (res) {
					var items = (res && res.success && Array.isArray(res.data)) ? res.data : [];
					render(items.slice(0, 8));
				})
				.catch(function (err) { if (!err || err.name !== 'AbortError') { render([]); } });
		}

		function refresh() {
			if (clear) { clear.hidden = !input.value; }
			if (remote) {
				window.clearTimeout(debounceTimer);
				debounceTimer = window.setTimeout(fetchRemote, 250);
			} else {
				filterStatic();
			}
		}

		function pick(value) {
			input.value = value;
			render([]);
			if (clear) { clear.hidden = false; }
			if (submitOnPick) {
				var form = input.closest('form');
				if (form) { form.requestSubmit ? form.requestSubmit() : form.submit(); }
			} else {
				input.focus();
			}
		}

		function highlight(idx) {
			list.querySelectorAll('.oc-typeahead__item[role="option"]').forEach(function (el, i) {
				el.classList.toggle('is-active', i === idx);
			});
			active = idx;
		}

		input.addEventListener('input', refresh);
		input.addEventListener('focus', refresh);
		input.addEventListener('keydown', function (e) {
			if (list.hidden || !matches.length) { return; }
			if (e.key === 'ArrowDown')      { e.preventDefault(); highlight((active + 1) % matches.length); }
			else if (e.key === 'ArrowUp')   { e.preventDefault(); highlight((active - 1 + matches.length) % matches.length); }
			else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); pick(matches[active]); }
			else if (e.key === 'Escape')    { render([]); }
		});
		list.addEventListener('mousedown', function (e) {
			var el = e.target.closest('.oc-typeahead__item[role="option"]');
			if (!el) { return; }
			e.preventDefault();
			var i = parseInt(el.getAttribute('data-i'), 10);
			if (!isNaN(i) && matches[i]) { pick(matches[i]); }
		});
		document.addEventListener('click', function (e) {
			if (!box.contains(e.target)) { render([]); }
		});
		if (clear) {
			clear.addEventListener('click', function () {
				input.value = '';
				clear.hidden = true;
				render([]);
				input.focus();
			});
			if (input.value) { clear.hidden = false; }
		}
	}

	function bootTypeaheads() {
		document.querySelectorAll('[data-oc-typeahead]').forEach(initTypeahead);
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bootTypeaheads);
	} else {
		bootTypeaheads();
	}
})();
