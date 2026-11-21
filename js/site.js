/*
	Site behaviors. Vanilla JS, no jQuery, no build step. Enqueued in the
	footer with `defer`, so the DOM is parsed by the time this runs.

	What used to be JavaScript and no longer is:
	- Smooth scrolling for in-page links: CSS `scroll-behavior` (scss/layout).
	- rel="external" new-tab links: plain target="_blank" in the templates.
*/
(function () {
	'use strict';

	/*
		Mobile nav drawer. The open state is body.nav-overlay-open (styled in
		scss/header/_nav-overlay-open.scss; js/search.js also clears it when the
		search palette opens). Closes on outside click and on Escape.
	*/
	var trigger = document.querySelector('.js-nav-trigger');
	var nav = document.querySelector('.site-navigation');

	function setNavOpen(open) {
		document.body.classList.toggle('nav-overlay-open', open);
		if (trigger) {
			trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
		}
	}

	function navIsOpen() {
		return document.body.classList.contains('nav-overlay-open');
	}

	if (trigger) {
		trigger.addEventListener('click', function (e) {
			e.preventDefault();
			setNavOpen(!navIsOpen());
		});

		document.addEventListener('click', function (e) {
			if (!navIsOpen()) return;
			if (trigger.contains(e.target) || (nav && nav.contains(e.target))) return;
			setNavOpen(false);
		});

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && navIsOpen()) {
				setNavOpen(false);
				trigger.focus();
			}
		});

		// search.js removes the body class directly; keep aria-expanded honest.
		new MutationObserver(function () {
			trigger.setAttribute('aria-expanded', navIsOpen() ? 'true' : 'false');
		}).observe(document.body, { attributes: true, attributeFilter: ['class'] });
	}

	/*
		Home hero crossfade. Every .photo sits in the same grid cell; the one
		with .is-active is opaque and CSS fades between them. The first photo is
		marked active in the template, so with no JS (or one photo) it just
		shows that. Pauses while the tab is hidden, and never starts
		when the visitor prefers reduced motion.
	*/
	var slider = document.querySelector('.hero-slider');
	var slides = slider ? slider.querySelectorAll('.photo') : [];
	var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	if (slides.length > 1 && !reduceMotion) {
		// The template holds back every photo but the first (data-src/data-srcset), so they
		// don't compete with it for bandwidth. Fill them in once the page has loaded.
		var hydrate = function (img) {
			if (!img || !img.dataset.src) return;
			if (img.dataset.srcset) img.srcset = img.dataset.srcset;
			img.src = img.dataset.src;
			img.removeAttribute('data-src');
			img.removeAttribute('data-srcset');
		};
		var hydrateAll = function () {
			slider.querySelectorAll('img[data-src]').forEach(hydrate);
		};
		if (document.readyState === 'complete') hydrateAll();
		else window.addEventListener('load', hydrateAll);

		// A photo is ready once it has fully downloaded and decoded.
		var isReady = function (slide) {
			var img = slide.querySelector('img');
			return !img || (img.complete && img.naturalWidth > 0 && !img.dataset.src);
		};

		var current = 0;
		var INTERVAL = 5000;

		window.setInterval(function () {
			if (document.hidden) return;
			var next = (current + 1) % slides.length;
			// Never fade to a photo that hasn't arrived yet (a slow first visit): ask for it
			// now if it's still held back, keep the current one up, and try again next tick.
			if (!isReady(slides[next])) {
				hydrate(slides[next].querySelector('img[data-src]'));
				return;
			}
			// Both photos fade at once, same as Slick's fade mode did.
			slides[current].classList.remove('is-active');
			current = next;
			slides[current].classList.add('is-active');
		}, INTERVAL);
	}
})();
