/**
 * Fyndable Loader — animated Fyndable logo used for all AI loading states.
 *
 * Ports the standalone preloader (logo draw animation + "Loading" dots) to
 * vanilla JS. Responsibilities:
 *  - Fullscreen #sseo-ai-loader-overlay, shown via window.sseoShowLoader() /
 *    sseoHideLoader() (kept API — many features call these directly).
 *  - Auto-triggers: jQuery ajaxSend/ajaxComplete (sseo_ai_* / action=ai_seo*),
 *    wp.apiFetch and window.fetch calls to sseo-ai/v1 (except support-assistant,
 *    which has its own inline typing UI).
 *  - MutationObserver that upgrades every .spinner / .fyndable-spinner element
 *    inside config.scopeSelector (empty = whole document) to the same logo.
 *
 * Config via wp_localize_script → window.fyndableLoaderConfig:
 *  { text: string, scopeSelector: string, colors: [c1, c2] | [c1, c2, c3] | null }
 */
(function () {
	'use strict';

	var CFG = window.fyndableLoaderConfig || {};
	var BRAND_COLORS = ['#34A9DD', '#5B57A0', '#8C3793'];
	var COLORS = normalizeColors(CFG.colors);
	var LABEL = CFG.text || 'AI is generating';
	var SCOPE = CFG.scopeSelector || '';
	var SHOW_DELAY = 300;

	var uid = 0;
	var instances = [];      // { el, wedge, dots, start, active }
	var rafId = null;
	var overlay = null;
	var overlayInst = null;
	var pending = 0;
	var showTimer = null;
	var stylesInjected = false;

	function normalizeColors(colors) {
		if (!colors || colors.length < 2) return BRAND_COLORS;
		if (colors.length >= 3) return colors.slice(0, 3);
		return [colors[0], mixHex(colors[0], colors[1]), colors[1]];
	}

	function mixHex(a, b) {
		var pa = a.replace('#', ''), pb = b.replace('#', '');
		var out = '#';
		for (var i = 0; i < 3; i++) {
			var v = Math.round((parseInt(pa.substr(i * 2, 2), 16) + parseInt(pb.substr(i * 2, 2), 16)) / 2);
			out += ('0' + v.toString(16)).slice(-2);
		}
		return out;
	}

	/* ------------------------------------------------------------------ */
	/* Logo draw animation (port of the preloader component)               */
	/* ------------------------------------------------------------------ */

	var CX = 80.52, CY = 77.71, R = 260, ANCHOR = 337;
	var DRAW = 1200, HOLD = 450, ERASE = 500, GAP = 250;
	var CYCLE = DRAW + HOLD + ERASE + GAP;

	function ease(t) {
		return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
	}

	function wedgePath(p) {
		var sweep = 354 * p;
		if (sweep <= 0.05) return 'M ' + CX + ',' + CY + ' Z';
		var a0 = ANCHOR * Math.PI / 180;
		var a1 = (ANCHOR - sweep) * Math.PI / 180;
		var x0 = (CX + R * Math.cos(a0)).toFixed(2);
		var y0 = (CY + R * Math.sin(a0)).toFixed(2);
		var x1 = (CX + R * Math.cos(a1)).toFixed(2);
		var y1 = (CY + R * Math.sin(a1)).toFixed(2);
		return 'M ' + CX + ',' + CY + ' L ' + x0 + ',' + y0 +
			' A ' + R + ',' + R + ' 0 ' + (sweep > 180 ? 1 : 0) + ' 0 ' + x1 + ',' + y1 + ' Z';
	}

	function tick(now) {
		var anyActive = false;
		for (var i = 0; i < instances.length; i++) {
			var inst = instances[i];
			if (!inst.active) continue;
			if (inst.start === null) inst.start = now;
			anyActive = true;

			var elapsed = (now - inst.start) % CYCLE;
			var p;
			if (elapsed < DRAW) p = ease(elapsed / DRAW);
			else if (elapsed < DRAW + HOLD) p = 1;
			else if (elapsed < DRAW + HOLD + ERASE) p = 1 - ease((elapsed - DRAW - HOLD) / ERASE);
			else p = 0;

			inst.wedge.setAttribute('d', wedgePath(p));
			if (inst.dots) {
				inst.dots.textContent = new Array(1 + Math.floor((now - inst.start) / 450) % 3 + 1).join('.');
			}
		}
		rafId = anyActive ? requestAnimationFrame(tick) : null;
	}

	function ensureRaf() {
		if (rafId === null) rafId = requestAnimationFrame(tick);
	}

	/* ------------------------------------------------------------------ */
	/* SVG markup                                                          */
	/* ------------------------------------------------------------------ */

	function logoSvg() {
		uid++;
		var g = 'fynGrad-' + uid, m = 'fynRing-' + uid;
		return '<svg viewBox="0 0 175.82 174.85" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
			+ '<defs>'
			+ '<linearGradient id="' + g + '" x1="0" y1="0" x2="175.82" y2="174.85" gradientUnits="userSpaceOnUse">'
			+ '<stop offset="0%" stop-color="' + COLORS[0] + '"/>'
			+ '<stop offset="50%" stop-color="' + COLORS[1] + '"/>'
			+ '<stop offset="100%" stop-color="' + COLORS[2] + '"/>'
			+ '</linearGradient>'
			+ '<mask id="' + m + '" maskUnits="userSpaceOnUse" x="0" y="0" width="175.82" height="174.85">'
			+ '<path class="fyn-wedge" d="M ' + CX + ',' + CY + ' Z" fill="white"/>'
			+ '</mask>'
			+ '</defs>'
			+ '<polygon points="97.46 53.6 97.46 42.43 66.29 42.43 62.94 42.43 53.63 42.43 53.63 107.85 66.29 107.85 66.29 81.05 95.88 81.05 95.88 69.88 66.29 69.88 66.29 53.6 97.46 53.6" fill="url(#' + g + ')"/>'
			+ '<path d="M153.25,171.14l-21.63-21.54c-4.23-4.22.69-8.54-1.59-10.36l-3.36-2.68-6.02,4.87c-7.2,4.58-14.83,8.45-23.63,10.2,6.7-4.53,12.47-9.05,17.83-14.87,9.39-10.19,18.08-20.68,26.69-31.61l5.57,5.2c-2.77,5.51-6.05,10.4-9.7,15.58.66,1.82,2.06,3.32,3.69,4.27,3.16-1.75,7.11-1.99,9.72.58l15.82,15.62c2.76,2.72,5.69,5.1,7.61,8.52,2.6,4.61,1.89,10.15-1.23,14.36-4.67,6.31-13.89,7.67-19.75,1.84Z" fill="url(#' + g + ')"/>'
			+ '<path d="M125.75,86.86l-14.53-7.49,47.82-23.34-4.95,52.8-13.4-11.44-30.68,36.24c-4.86,5.74-10.71,10.18-17.14,13.92-7.95,4.61-16.67,6.33-25.84,5.76-6.7-.42-12.85-2.53-18.75-5.01C11,132.57-7.31,90.54,6.7,52.48,18.04,21.68,47.23.98,80.67,2.04c31.92,1.01,59.91,21.78,69.71,52.29l-11.12,5.79c-3.51-11.71-10.09-21.98-19.29-30.19-10.8-9.63-24.33-14.81-38.79-15.58-18.2-.96-36.77,6.83-49.08,20.36-11.75,12.92-18.05,30.08-16.93,47.71,1.32,20.72,13.68,41.81,32.52,50.87,16.31,7.83,35.73,3.28,47.21-10.21l30.84-36.22Z" fill="url(#' + g + ')" mask="url(#' + m + ')"/>'
			+ '</svg>';
	}

	function injectStyles() {
		if (stylesInjected) return;
		stylesInjected = true;
		var style = document.createElement('style');
		style.id = 'fyndable-loader-styles';
		style.textContent =
			'#sseo-ai-loader-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:100001;' +
			'justify-content:center;align-items:center;flex-direction:column;backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px)}' +
			'#sseo-ai-loader-overlay.active{display:flex!important}' +
			'#sseo-ai-loader-overlay .sseo-loader-logo{width:120px;height:119px}' +
			'#sseo-ai-loader-overlay .sseo-loader-logo svg{width:100%;height:100%;display:block;overflow:visible}' +
			'#sseo-ai-loader-overlay .sseo-loader-text{color:#fff;margin-top:20px;font-size:16px;font-weight:600;letter-spacing:.02em;' +
			'font-family:system-ui,-apple-system,"Segoe UI",sans-serif;display:flex;align-items:baseline;justify-content:center;' +
			'max-width:80vw;text-align:center;line-height:1.5}' +
			'#sseo-ai-loader-overlay .sseo-loader-dots{display:inline-block;width:1.6em;text-align:left}' +
			'.fyn-upgraded{background-image:none!important;border:none!important;border-radius:0!important;' +
			'animation:none!important;-webkit-animation:none!important}' +
			'.fyn-upgraded svg{display:block;width:100%;height:100%;overflow:visible}';
		document.head.appendChild(style);
	}

	/* ------------------------------------------------------------------ */
	/* Fullscreen overlay                                                  */
	/* ------------------------------------------------------------------ */

	function ensureOverlay() {
		if (overlay && overlay.isConnected) return overlay;
		overlay = document.getElementById('sseo-ai-loader-overlay');
		if (!overlay) {
			overlay = document.createElement('div');
			overlay.id = 'sseo-ai-loader-overlay';
			overlay.setAttribute('role', 'alert');
			overlay.setAttribute('aria-busy', 'true');
			document.body.appendChild(overlay);
		}
		if (!overlayInst || overlayInst.el !== overlay) {
			overlay.innerHTML = '<div class="sseo-loader-logo">' + logoSvg() + '</div>'
				+ '<div class="sseo-loader-text"><span class="sseo-loader-label"></span>'
				+ '<span class="sseo-loader-dots"></span></div>';
			overlay.querySelector('.sseo-loader-label').textContent = LABEL;
			overlayInst = {
				el: overlay,
				wedge: overlay.querySelector('.fyn-wedge'),
				dots: overlay.querySelector('.sseo-loader-dots'),
				start: null,
				active: false
			};
			instances.push(overlayInst);
		}
		return overlay;
	}

	window.sseoShowLoader = function () {
		pending++;
		ensureOverlay();
		if (showTimer) return;
		showTimer = setTimeout(function () {
			showTimer = null;
			if (pending > 0 && overlay) {
				overlay.classList.add('active');
				refreshActive();
			}
		}, SHOW_DELAY);
	};

	window.sseoHideLoader = function () {
		pending = Math.max(0, pending - 1);
		if (pending === 0) {
			if (showTimer) { clearTimeout(showTimer); showTimer = null; }
			if (overlay) overlay.classList.remove('active');
			refreshActive();
		}
	};

	/* ------------------------------------------------------------------ */
	/* Spinner upgrading                                                   */
	/* ------------------------------------------------------------------ */

	var SPINNER_SEL = '.spinner,.fyndable-spinner';

	function inScope(el) {
		return !SCOPE || !!el.closest(SCOPE);
	}

	function isVisible(el) {
		if (!el.isConnected || el.closest('.hidden')) return false;
		var cs = window.getComputedStyle(el);
		return cs.visibility !== 'hidden' && cs.display !== 'none' && cs.opacity !== '0'
			&& el.getClientRects().length > 0;
	}

	function upgrade(el) {
		if (el.nodeType !== 1 || el.__fynUpgraded) return;
		if (!el.matches || !el.matches(SPINNER_SEL) || !inScope(el)) return;
		if (el.closest('#sseo-ai-loader-overlay')) return;
		el.__fynUpgraded = true;
		el.classList.add('fyn-upgraded');
		el.innerHTML = logoSvg();
		instances.push({
			el: el,
			wedge: el.querySelector('.fyn-wedge'),
			dots: null,
			start: null,
			active: false
		});
	}

	function scan(root) {
		if (root.nodeType !== 1) return;
		upgrade(root);
		var found = root.querySelectorAll(SPINNER_SEL);
		for (var i = 0; i < found.length; i++) upgrade(found[i]);
	}

	function refreshActive() {
		instances = instances.filter(function (inst) { return inst.el.isConnected; });
		var now = null;
		for (var i = 0; i < instances.length; i++) {
			var inst = instances[i];
			var vis = isVisible(inst.el);
			if (vis && !inst.active) {
				inst.active = true;
				if (now === null) now = performance.now();
				inst.start = now; // restart draw cycle
			} else if (!vis && inst.active) {
				inst.active = false;
				inst.start = null;
			}
		}
		ensureRaf();
	}

	function startObserver() {
		scan(document.body);
		refreshActive();
		new MutationObserver(function (mutations) {
			var rescan = false;
			for (var i = 0; i < mutations.length; i++) {
				var m = mutations[i];
				if (m.type === 'childList') {
					for (var j = 0; j < m.addedNodes.length; j++) scan(m.addedNodes[j]);
					rescan = rescan || m.addedNodes.length > 0 || m.removedNodes.length > 0;
				} else {
					rescan = true; // class/style mutation — visibility may have changed
				}
			}
			if (rescan) refreshActive();
		}).observe(document.body, {
			childList: true,
			subtree: true,
			attributes: true,
			attributeFilter: ['class', 'style']
		});
	}

	/* ------------------------------------------------------------------ */
	/* Auto-triggers                                                       */
	/* ------------------------------------------------------------------ */

	function isAiAjax(settings) {
		var data = settings && settings.data;
		if (typeof data !== 'string') {
			try { data = String(data || ''); } catch (e) { data = ''; }
		}
		return data.indexOf('sseo_ai_') !== -1 || data.indexOf('action=ai_seo') !== -1;
	}

	if (window.jQuery) {
		window.jQuery(document).ajaxSend(function (e, xhr, settings) {
			if (isAiAjax(settings)) {
				xhr.__fynLoader = true;
				window.sseoShowLoader();
			}
		});
		window.jQuery(document).on('ajaxComplete ajaxError', function (e, xhr) {
			if (xhr && xhr.__fynLoader) {
				xhr.__fynLoader = false;
				window.sseoHideLoader();
			}
		});
	}

	var apiFetchWrapped = false;
	function wrapApiFetch() {
		if (apiFetchWrapped) return;
		if (typeof wp === 'undefined' || !wp.apiFetch) return;
		apiFetchWrapped = true;
		var original = wp.apiFetch;
		var wrapped = function (options) {
			var path = (options && (options.path || options.url)) || '';
			if (typeof path === 'string'
				&& path.indexOf('sseo-ai/v1') !== -1
				&& path.indexOf('support-assistant') === -1) {
				window.sseoShowLoader();
				var result = original(options);
				if (result && typeof result.then === 'function') {
					result.then(window.sseoHideLoader, window.sseoHideLoader);
				} else {
					window.sseoHideLoader();
				}
				return result;
			}
			return original(options);
		};
		for (var key in original) {
			if (original.hasOwnProperty(key)) wrapped[key] = original[key];
		}
		wp.apiFetch = wrapped;
	}

	function wrapFetch() {
		if (!window.fetch || window.fetch.__fynWrapped) return;
		var original = window.fetch;
		var wrapped = function () {
			var url = '';
			try {
				var arg = arguments[0];
				url = typeof arg === 'string' ? arg : (arg && arg.url) || '';
			} catch (e) { /* ignore */ }
			if (url.indexOf('/sseo-ai/v1/') === -1 || url.indexOf('/support-assistant/') !== -1) {
				return original.apply(this, arguments);
			}
			window.sseoShowLoader();
			var done = window.sseoHideLoader;
			try {
				var p = original.apply(this, arguments);
				if (p && typeof p.then === 'function') p.then(done, done);
				else done();
				return p;
			} catch (err) {
				done();
				throw err;
			}
		};
		wrapped.__fynWrapped = true;
		window.fetch = wrapped;
	}

	/* ------------------------------------------------------------------ */
	/* Background Task Notifications                                       */
	/* When AI calls complete after navigation, show a toast notification.  */
	/* ------------------------------------------------------------------ */

	var BG_POLL_INTERVAL = 10000; // 10 seconds
	var bgPollTimer = null;
	var bgToastContainer = null;

	function injectBgStyles() {
		var existing = document.getElementById('fyndable-bg-task-styles');
		if (existing) return;
		var s = document.createElement('style');
		s.id = 'fyndable-bg-task-styles';
		s.textContent =
			'#fyn-bg-toasts{position:fixed;bottom:20px;right:20px;z-index:100002;display:flex;flex-direction:column-reverse;gap:10px;max-width:400px;pointer-events:none}' +
			'.fyn-bg-toast{pointer-events:auto;background:#1e293b;color:#fff;border-radius:10px;padding:14px 18px;box-shadow:0 8px 32px rgba(0,0,0,.3);' +
			'font-family:system-ui,-apple-system,"Segoe UI",sans-serif;font-size:13px;line-height:1.5;display:flex;align-items:flex-start;gap:12px;' +
			'animation:fynToastIn .3s ease-out;transition:opacity .3s,transform .3s}' +
			'.fyn-bg-toast.leaving{opacity:0;transform:translateX(40px)}' +
			'.fyn-bg-toast-icon{flex-shrink:0;width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;margin-top:1px}' +
			'.fyn-bg-toast-icon.success{background:#22c55e}' +
			'.fyn-bg-toast-icon.failed{background:#ef4444}' +
			'.fyn-bg-toast-icon.processing{background:#3b82f6}' +
			'.fyn-bg-toast-body{flex:1;min-width:0}' +
			'.fyn-bg-toast-label{font-weight:600;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}' +
			'.fyn-bg-toast-status{color:#94a3b8;font-size:12px}' +
			'.fyn-bg-toast-actions{display:flex;gap:8px;margin-top:8px}' +
			'.fyn-bg-toast-btn{background:rgba(255,255,255,.1);border:none;color:#fff;padding:4px 12px;border-radius:6px;cursor:pointer;font-size:12px;transition:background .15s}' +
			'.fyn-bg-toast-btn:hover{background:rgba(255,255,255,.2)}' +
			'.fyn-bg-toast-btn.primary{background:#34A9DD}' +
			'.fyn-bg-toast-btn.primary:hover{background:#2898cc}' +
			'@keyframes fynToastIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}';
		document.head.appendChild(s);
	}

	function ensureToastContainer() {
		if (bgToastContainer && bgToastContainer.isConnected) return bgToastContainer;
		bgToastContainer = document.getElementById('fyn-bg-toasts');
		if (!bgToastContainer) {
			bgToastContainer = document.createElement('div');
			bgToastContainer.id = 'fyn-bg-toasts';
			document.body.appendChild(bgToastContainer);
		}
		return bgToastContainer;
	}

	function showBgToast(task) {
		injectBgStyles();
		var container = ensureToastContainer();

		// Don't show duplicate toast for same task
		if (container.querySelector('[data-task-id="' + task.id + '"]')) return;

		var isSuccess = task.status === 'completed';
		var isFailed = task.status === 'failed';
		var iconClass = isSuccess ? 'success' : (isFailed ? 'failed' : 'processing');
		var iconChar = isSuccess ? '\u2713' : (isFailed ? '\u2717' : '\u2026');
		var statusText = isSuccess
			? 'Completed in the background'
			: (isFailed ? (task.error || 'Failed') : 'Still processing\u2026');

		var toast = document.createElement('div');
		toast.className = 'fyn-bg-toast';
		toast.setAttribute('data-task-id', task.id);
		toast.innerHTML =
			'<div class="fyn-bg-toast-icon ' + iconClass + '">' + iconChar + '</div>' +
			'<div class="fyn-bg-toast-body">' +
			'<div class="fyn-bg-toast-label">' + escHtml(task.label) + '</div>' +
			'<div class="fyn-bg-toast-status">' + escHtml(statusText) + '</div>' +
			(isSuccess && task.result && task.result.edit_url
				? '<div class="fyn-bg-toast-actions"><a class="fyn-bg-toast-btn primary" href="' + escAttr(task.result.edit_url) + '">Open</a>' +
				  '<button class="fyn-bg-toast-btn dismiss-btn">Dismiss</button></div>'
				: '<div class="fyn-bg-toast-actions"><button class="fyn-bg-toast-btn dismiss-btn">OK</button></div>') +
			'</div>';

		// Dismiss handler
		var dismissBtn = toast.querySelector('.dismiss-btn');
		dismissBtn.addEventListener('click', function () {
			dismissTask(task.id);
			toast.classList.add('leaving');
			setTimeout(function () { toast.remove(); }, 300);
		});

		container.appendChild(toast);

		// Auto-dismiss after 30s for completed tasks
		if (isSuccess || isFailed) {
			setTimeout(function () {
				if (toast.isConnected) {
					dismissTask(task.id);
					toast.classList.add('leaving');
					setTimeout(function () { toast.remove(); }, 300);
				}
			}, 30000);
		}
	}

	function dismissTask(taskId) {
		var fetchFn = window.fetch && window.fetch.__fynWrapped ? Function.prototype : null;
		// Use the original fetch if wrapped, otherwise use wp.apiFetch
		if (typeof wp !== 'undefined' && wp.apiFetch) {
			wp.apiFetch({
				path: '/sseo-ai/v1/tasks/' + taskId + '/dismiss',
				method: 'POST'
			}).catch(function () {});
		}
	}

	function pollBackgroundTasks() {
		if (typeof wp === 'undefined' || !wp.apiFetch) return;

		wp.apiFetch({ path: '/sseo-ai/v1/tasks/pending' }).then(function (res) {
			var tasks = (res && res.tasks) || [];
			var hasProcessing = false;

			for (var i = 0; i < tasks.length; i++) {
				var task = tasks[i];
				if (task.status === 'completed' || task.status === 'failed') {
					showBgToast(task);
				} else if (task.status === 'processing') {
					hasProcessing = true;
				}
			}

			// Keep polling if there are still processing tasks
			if (hasProcessing) {
				bgPollTimer = setTimeout(pollBackgroundTasks, BG_POLL_INTERVAL);
			}
		}).catch(function () {
			// Silently fail — non-critical
		});
	}

	function startBgTaskPolling() {
		// Initial check after a short delay (let the page settle)
		setTimeout(function () {
			pollBackgroundTasks();
		}, 2000);
	}

	function escHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str || ''));
		return div.innerHTML;
	}

	function escAttr(str) {
		return (str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
	}

	/* ------------------------------------------------------------------ */
	/* Boot                                                                */
	/* ------------------------------------------------------------------ */

	function init() {
		injectStyles();
		wrapApiFetch();
		wrapFetch();
		startObserver();
		startBgTaskPolling();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
