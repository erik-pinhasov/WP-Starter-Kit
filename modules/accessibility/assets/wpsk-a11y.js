/**
 * WPSK Accessibility Toolbar — Frontend JS.
 *
 * @package WPStarterKit
 * @since   1.0.0
 */
document.addEventListener("DOMContentLoaded", function () {
	"use strict";

	try {
		var KEY       = "wpsk-a11y-prefs",
		    html      = document.documentElement,
		    widget    = document.getElementById("wpsk-a11y-widget"),
		    panel     = document.getElementById("wpsk-a11y-panel"),
		    btnToggle = document.getElementById("wpsk-a11y-toggle"),
		    btnClose  = document.getElementById("wpsk-a11y-close"),
		    btnZoomIn = document.getElementById("wpsk-a11y-zoom-in"),
		    btnZoomOut= document.getElementById("wpsk-a11y-zoom-out"),
		    btnReset  = document.getElementById("wpsk-a11y-reset"),
		    toggleBtns= document.querySelectorAll(".wpsk-a11y-toggle-btn[data-class]");

		if (!widget || !panel || !btnToggle || !btnClose || !btnReset) return;

		// Move widget to documentElement to avoid CSS filter stacking issues.
		if (widget.parentNode !== html) html.appendChild(widget);

		// ── State ──
		var state;
		try {
			state = JSON.parse(localStorage.getItem(KEY)) || { zoom: 0, classes: [] };
		} catch (e) {
			state = { zoom: 0, classes: [] };
		}

		var ZOOM_LEVELS = { 0: 1, 1: 1.15, 2: 1.3 };
		var lastFocus   = null;
		var HC_ATTR     = "data-wpsk-hc-bg";

		// ── Helpers ──
		function findMain() {
			var el = document.getElementById("main-content");
			if (!el) {
				el = document.querySelector("main, #main, .main-page-wrapper, .site-content, [role='main']");
				if (el && !el.id) el.id = "main-content";
			}
			return el;
		}

		function parseRGB(str) {
			if (!str) return null;
			var m = str.match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/);
			return m ? { r: +m[1], g: +m[2], b: +m[3] } : null;
		}

		function luminance(c) {
			return (299 * c.r + 587 * c.g + 114 * c.b) / 2550;
		}

		// ── Apply state ──
		function apply() {
			// Font scaling.
			var scale = ZOOM_LEVELS[state.zoom] || 1;
			if (scale === 1) {
				document.querySelectorAll("[data-wpsk-base-fs]").forEach(function (el) {
					el.style.removeProperty("font-size");
					el.removeAttribute("data-wpsk-base-fs");
				});
			} else {
				var sel = "p,span,a,li,td,th,h1,h2,h3,h4,h5,h6,label,button,input,select,textarea,blockquote,figcaption,small,strong,em,b,dt,dd,summary,legend";
				document.querySelectorAll(sel).forEach(function (el) {
					if (el.closest("#wpsk-a11y-widget")) return;
					if (!el.hasAttribute("data-wpsk-base-fs")) {
						var fs = parseFloat(window.getComputedStyle(el).fontSize);
						if (!fs || isNaN(fs)) return;
						el.setAttribute("data-wpsk-base-fs", fs);
					}
					var base = parseFloat(el.getAttribute("data-wpsk-base-fs"));
					if (base && !isNaN(base)) {
						el.style.setProperty("font-size", Math.round(base * scale) + "px", "important");
					}
				});
			}

			// Clear old a11y classes.
			var cls = html.className.split(/\s+/);
			cls.forEach(function (c) {
				if (c.indexOf("wpsk-a11y-") === 0) html.classList.remove(c);
			});

			// Apply active classes.
			state.classes.forEach(function (c) { html.classList.add(c); });

			// Stop animations — also pause carousels.
			var noAnim = state.classes.indexOf("wpsk-a11y-no-animations") !== -1;
			document.querySelectorAll(".swiper-container, .swiper, .wd-carousel").forEach(function (el) {
				if (el.swiper && el.swiper.autoplay) {
					try { noAnim ? el.swiper.autoplay.stop() : el.swiper.autoplay.start(); } catch (e) {}
				}
			});

			// High contrast — scan backgrounds.
			if (state.classes.indexOf("wpsk-a11y-high-contrast") !== -1) {
				document.body.querySelectorAll("*").forEach(function (el) {
					if (el.closest("#wpsk-a11y-widget") || el.hasAttribute(HC_ATTR)) return;
					if (["IMG", "VIDEO", "IFRAME", "CANVAS", "SVG"].indexOf(el.tagName) !== -1) return;
					var bg = window.getComputedStyle(el).backgroundColor;
					if (bg && bg !== "transparent" && bg !== "rgba(0, 0, 0, 0)") {
						var c = parseRGB(bg);
						if (c && luminance(c) > 40) {
							el.setAttribute(HC_ATTR, "1");
							el.style.setProperty("background-color", "#000", "important");
							el.style.setProperty("background-image", "none", "important");
						}
					}
				});
			} else {
				document.querySelectorAll("[" + HC_ATTR + "]").forEach(function (el) {
					el.style.removeProperty("background-color");
					el.style.removeProperty("background-image");
					el.removeAttribute(HC_ATTR);
				});
			}

			// Update toggle button states.
			toggleBtns.forEach(function (btn) {
				var dc = btn.getAttribute("data-class");
				var active = state.classes.indexOf(dc) !== -1;
				btn.classList.toggle("active", active);
				btn.setAttribute("aria-pressed", active ? "true" : "false");
			});
		}

		function save() {
			try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) {}
			apply();
		}

		// ── Panel open/close ──
		function getFocusable(container) {
			return Array.from(container.querySelectorAll(
				'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])'
			)).filter(function (el) { return el.offsetParent !== null; });
		}

		function closePanel() {
			panel.classList.remove("open");
			panel.setAttribute("aria-hidden", "true");
			btnToggle.setAttribute("aria-expanded", "false");
			if (lastFocus && typeof lastFocus.focus === "function") lastFocus.focus();
			else btnToggle.focus();
		}

		function openPanel() {
			lastFocus = document.activeElement;
			panel.classList.add("open");
			panel.setAttribute("aria-hidden", "false");
			btnToggle.setAttribute("aria-expanded", "true");
			var focusable = getFocusable(panel);
			if (focusable.length) focusable[0].focus();
			else panel.focus();
		}

		btnToggle.addEventListener("click", function () {
			panel.classList.contains("open") ? closePanel() : openPanel();
		});

		btnClose.addEventListener("click", closePanel);

		// Keyboard: Escape closes, Tab traps inside panel.
		document.addEventListener("keydown", function (e) {
			if (e.key === "Escape" && panel.classList.contains("open")) {
				e.preventDefault();
				closePanel();
			}
			if (panel.classList.contains("open") && e.key === "Tab") {
				var focusable = getFocusable(panel);
				if (focusable.length) {
					var first = focusable[0], last = focusable[focusable.length - 1];
					if (e.shiftKey && document.activeElement === first) {
						e.preventDefault(); last.focus();
					} else if (!e.shiftKey && document.activeElement === last) {
						e.preventDefault(); first.focus();
					}
				}
			}
		});

		// Click outside closes panel.
		document.addEventListener("click", function (e) {
			if (panel.classList.contains("open") && !panel.contains(e.target) && !btnToggle.contains(e.target)) {
				closePanel();
			}
		});

		// ── Feature buttons ──
		toggleBtns.forEach(function (btn) {
			btn.addEventListener("click", function () {
				var dc = btn.getAttribute("data-class");
				if (!dc) return;
				var idx = state.classes.indexOf(dc);
				if (idx !== -1) state.classes.splice(idx, 1);
				else state.classes.push(dc);
				save();
			});
		});

		if (btnZoomIn) {
			btnZoomIn.addEventListener("click", function () {
				if (state.zoom < 2) { state.zoom += 1; save(); }
			});
		}
		if (btnZoomOut) {
			btnZoomOut.addEventListener("click", function () {
				if (state.zoom > 0) { state.zoom -= 1; save(); }
			});
		}

		btnReset.addEventListener("click", function () {
			state = { zoom: 0, classes: [] };
			save();
		});

		// ── Skip-to-content link ──
		if (!document.getElementById("wpsk-a11y-skip")) {
			var main = findMain();
			var skip = document.createElement("a");
			skip.id = "wpsk-a11y-skip";
			skip.className = "wpsk-a11y-skip";
			skip.href = main ? "#main-content" : "#";
			skip.textContent = (typeof wpskA11yConfig !== "undefined" && wpskA11yConfig.i18n)
				? wpskA11yConfig.i18n.skipToMain
				: "Skip to main content";
			skip.addEventListener("click", function (e) {
				var m = findMain();
				if (m) {
					e.preventDefault();
					m.setAttribute("tabindex", "-1");
					m.focus({ preventScroll: false });
					m.scrollIntoView({ behavior: "auto", block: "start" });
				}
			});
			document.body.insertBefore(skip, document.body.firstChild);
		}

		// ── Auto-fix missing alt attributes ──
		document.querySelectorAll("img:not([alt])").forEach(function (img) {
			img.setAttribute("alt", "");
		});

		// ── Auto-fix missing form labels ──
		document.querySelectorAll('input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="reset"]), textarea, select').forEach(function (el) {
			if (el.id && document.querySelector('label[for="' + CSS.escape(el.id) + '"]')) return;
			if (el.labels && el.labels.length) return;
			if (el.getAttribute("aria-label") || el.getAttribute("aria-labelledby")) return;
			var ph = (el.getAttribute("placeholder") || "").trim();
			var nm = (el.getAttribute("name") || "").trim();
			if (ph) el.setAttribute("aria-label", ph);
			else if (nm) el.setAttribute("aria-label", nm);
		});

		// Apply saved state on load.
		apply();

	} catch (err) {
		console.error("WPSK A11y:", err);
	}
});
