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
		var current = 0;
		var INTERVAL = 5000;

		window.setInterval(function () {
			if (document.hidden) return;
			// Both photos fade at once, same as Slick's fade mode did.
			slides[current].classList.remove('is-active');
			current = (current + 1) % slides.length;
			slides[current].classList.add('is-active');
		}, INTERVAL);
	}
})();
