/**
 * WPSK Accessibility Toolbar — Frontend JS.
 * Handles: font zoom (body.zoom), class toggles, state persistence.
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		try {
			var widget    = document.getElementById('wpsk-a11y-widget');
			var toggleBtn = document.getElementById('wpsk-a11y-toggle');
			var panel     = document.getElementById('wpsk-a11y-panel');
			var closeBtn  = panel ? panel.querySelector('.wpsk-a11y-close') : null;
			var zoomIn    = document.getElementById('wpsk-a11y-zoom-in');
			var zoomOut   = document.getElementById('wpsk-a11y-zoom-out');
			var resetBtn  = document.getElementById('wpsk-a11y-reset');
			var toggleBtns = panel ? panel.querySelectorAll('.wpsk-a11y-toggle-btn') : [];

			if (!widget || !toggleBtn || !panel) return;

			var STORAGE_KEY = 'wpsk_a11y_prefs';
			var MAX_ZOOM    = 2;
			var prefs       = loadState();

			function loadState() {
				try {
					var raw = localStorage.getItem(STORAGE_KEY);
					if (raw) {
						var data = JSON.parse(raw);
						return { zoom: data.zoom || 0, classes: data.classes || [] };
					}
				} catch (e) {}
				return { zoom: 0, classes: [] };
			}

			function saveState() {
				try {
					localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
				} catch (e) {}
				applyState();
			}

			function applyState() {
				// Zoom via body.style.zoom (works across all browsers including Chrome).
				var zoomLevel = 1 + (prefs.zoom * 0.15);
				document.body.style.zoom = zoomLevel;

				// Toggle classes on <html>.
				var allClasses = [];
				toggleBtns.forEach(function (btn) {
					var cls = btn.getAttribute('data-class');
					if (cls) allClasses.push(cls);
				});

				allClasses.forEach(function (cls) {
					if (prefs.classes.indexOf(cls) !== -1) {
						document.documentElement.classList.add(cls);
					} else {
						document.documentElement.classList.remove(cls);
					}
				});

				// Update button active states.
				toggleBtns.forEach(function (btn) {
					var cls = btn.getAttribute('data-class');
					if (cls && prefs.classes.indexOf(cls) !== -1) {
						btn.classList.add('active');
					} else {
						btn.classList.remove('active');
					}
				});

				// Update zoom button disabled states.
				if (zoomIn) zoomIn.disabled = prefs.zoom >= MAX_ZOOM;
				if (zoomOut) zoomOut.disabled = prefs.zoom <= 0;
			}

			// Panel open/close.
			var lastFocused = null;

			function openPanel() {
				lastFocused = document.activeElement;
				panel.style.display = 'block';
				panel.setAttribute('aria-hidden', 'false');
				toggleBtn.setAttribute('aria-expanded', 'true');
				// Focus first interactive element.
				var first = panel.querySelector('button, a, input, select');
				if (first) first.focus();
			}

			function closePanel() {
				panel.style.display = 'none';
				panel.setAttribute('aria-hidden', 'true');
				toggleBtn.setAttribute('aria-expanded', 'false');
				if (lastFocused) lastFocused.focus();
				else toggleBtn.focus();
			}

			toggleBtn.addEventListener('click', function () {
				if (panel.getAttribute('aria-hidden') === 'false') {
					closePanel();
				} else {
					openPanel();
				}
			});

			if (closeBtn) {
				closeBtn.addEventListener('click', closePanel);
			}

			// Escape key.
			document.addEventListener('keydown', function (e) {
				if (e.key === 'Escape' && panel.getAttribute('aria-hidden') === 'false') {
					e.preventDefault();
					closePanel();
				}
			});

			// Click outside.
			document.addEventListener('click', function (e) {
				if (panel.getAttribute('aria-hidden') === 'false') {
					if (!panel.contains(e.target) && !toggleBtn.contains(e.target)) {
						closePanel();
					}
				}
			});

			// Feature toggles.
			toggleBtns.forEach(function (btn) {
				btn.addEventListener('click', function () {
					var cls = btn.getAttribute('data-class');
					if (!cls) return;
					var idx = prefs.classes.indexOf(cls);
					if (idx !== -1) {
						prefs.classes.splice(idx, 1);
					} else {
						prefs.classes.push(cls);
					}
					saveState();
				});
			});

			// Zoom controls.
			if (zoomIn) {
				zoomIn.addEventListener('click', function () {
					if (prefs.zoom < MAX_ZOOM) {
						prefs.zoom += 1;
						saveState();
					}
				});
			}

			if (zoomOut) {
				zoomOut.addEventListener('click', function () {
					if (prefs.zoom > 0) {
						prefs.zoom -= 1;
						saveState();
					}
				});
			}

			// Reset.
			if (resetBtn) {
				resetBtn.addEventListener('click', function () {
					prefs = { zoom: 0, classes: [] };
					document.body.style.zoom = 1;
					saveState();
				});
			}

			// Apply saved state on load.
			applyState();

		} catch (err) {
			console.error('WPSK A11y setup error:', err);
		}
	});
})();
