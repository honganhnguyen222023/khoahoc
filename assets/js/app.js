/* KhoaHoc Academy – front-end (no dependencies, ~2KB). */
(function () {
	'use strict';
	var d = document;
	var root = d.documentElement;
	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	root.classList.add('kh-js');

	/* Header shadow on scroll */
	var header = d.querySelector('[data-header]');
	if (header) {
		var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
		onScroll();
		window.addEventListener('scroll', onScroll, { passive: true });
	}

	/* Mobile menu */
	var toggle = d.querySelector('[data-menu-toggle]');
	var drawer = d.getElementById('kh-drawer');
	if (toggle && drawer) {
		var setOpen = function (open) {
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			drawer.hidden = !open;
			root.style.overflow = open ? 'hidden' : '';
		};
		toggle.addEventListener('click', function () { setOpen(drawer.hidden); });
		drawer.addEventListener('click', function (e) { if (e.target.closest('a')) setOpen(false); });
		d.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && !drawer.hidden) { setOpen(false); toggle.focus(); }
		});
		window.addEventListener('resize', function () { if (window.innerWidth >= 1024 && !drawer.hidden) setOpen(false); });
	}

	/* Scroll reveal for section headings and card groups */
	var targets = d.querySelectorAll('.kh-section-head, .kh-grid, .kh-course-featured, .kh-about, .kh-faq, .kh-masonry, .kh-cta-box, .kh-panel');
	if ('IntersectionObserver' in window && !reduce) {
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (en) {
				if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); }
			});
		}, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
		targets.forEach(function (el) {
			if (el.getBoundingClientRect().top > window.innerHeight) { el.classList.add('kh-reveal'); io.observe(el); }
		});
	}

	/* Count-up numbers ("6.000+", "5+ năm") */
	var counters = d.querySelectorAll('[data-count]');
	var fmt = function (n) { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.'); };
	var run = function (el) {
		var text = el.textContent;
		var m = text.match(/^(\D*)([\d.]+)(.*)$/);
		if (!m) return;
		var target = parseInt(m[2].replace(/\./g, ''), 10);
		if (!target || target < 10) return;
		var start = null;
		var dur = 1200;
		var step = function (t) {
			if (!start) start = t;
			var p = Math.min(1, (t - start) / dur);
			var eased = 1 - Math.pow(1 - p, 3);
			el.textContent = m[1] + fmt(Math.round(target * eased)) + m[3];
			if (p < 1) requestAnimationFrame(step); else el.textContent = text;
		};
		requestAnimationFrame(step);
	};
	if (counters.length && 'IntersectionObserver' in window && !reduce) {
		var co = new IntersectionObserver(function (entries) {
			entries.forEach(function (en) { if (en.isIntersecting) { run(en.target); co.unobserve(en.target); } });
		}, { threshold: 0.6 });
		counters.forEach(function (el) { co.observe(el); });
	}

	/* YouTube: load the player only when clicked (fast page) */
	d.addEventListener('click', function (e) {
		var btn = e.target.closest('.kh-yt');
		if (!btn) return;
		var id = btn.getAttribute('data-yt');
		var f = d.createElement('iframe');
		f.src = 'https://www.youtube.com/embed/' + encodeURIComponent(id) + '?autoplay=1&rel=0&playsinline=1';
		f.title = btn.getAttribute('aria-label') || 'Video';
		f.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
		f.referrerPolicy = 'strict-origin-when-cross-origin';
		f.allowFullscreen = true;
		btn.replaceWith(f);
	});

	/* Course list: filters apply on change; sidebar always open on desktop */
	d.querySelectorAll('form[data-autosubmit]').forEach(function (form) {
		form.addEventListener('change', function () {
			// Drop empty fields so URLs stay short (e.g. no "?sap_xep=").
			form.querySelectorAll('select').forEach(function (s) { s.disabled = !s.value; });
			form.submit();
		});
	});
	var box = d.querySelector('[data-filterbox]');
	if (box && window.matchMedia) {
		var desk = window.matchMedia('(min-width: 1024px)');
		var syncBox = function () { if (desk.matches) box.open = true; };
		syncBox();
		desk.addEventListener ? desk.addEventListener('change', syncBox) : desk.addListener(syncBox);
		box.querySelector('summary').addEventListener('click', function (e) { if (desk.matches) e.preventDefault(); });
	}

	/* Trial lessons: YouTube in a popup player */
	var modal;
	d.addEventListener('click', function (e) {
		var link = e.target.closest('[data-yt-modal]');
		if (!link || !window.HTMLDialogElement) return;
		e.preventDefault();
		if (!modal) {
			modal = d.createElement('dialog');
			modal.className = 'kh-vmodal';
			modal.innerHTML = '<div class="kh-vmodal-box"><div class="kh-vmodal-head"><p class="kh-vmodal-title"></p>' +
				'<button type="button" class="kh-vmodal-close" aria-label="Đóng video">&times;</button></div><div class="kh-vmodal-frame"></div></div>';
			d.body.appendChild(modal);
			modal.querySelector('.kh-vmodal-close').addEventListener('click', function () { modal.close(); });
			modal.addEventListener('click', function (ev) { if (ev.target === modal) modal.close(); });
			modal.addEventListener('close', function () { modal.querySelector('.kh-vmodal-frame').innerHTML = ''; });
		}
		var f = d.createElement('iframe');
		f.src = 'https://www.youtube.com/embed/' + encodeURIComponent(link.getAttribute('data-yt-modal')) + '?autoplay=1&rel=0&playsinline=1';
		f.title = link.textContent;
		f.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
		f.referrerPolicy = 'strict-origin-when-cross-origin';
		f.allowFullscreen = true;
		modal.querySelector('.kh-vmodal-title').textContent = 'Học thử: ' + link.textContent;
		modal.querySelector('.kh-vmodal-frame').appendChild(f);
		modal.showModal();
	});

	/* FAQ: keep one answer open at a time */
	d.querySelectorAll('.kh-faq').forEach(function (faq) {
		faq.addEventListener('toggle', function (e) {
			if (!e.target.open) return;
			faq.querySelectorAll('details[open]').forEach(function (o) { if (o !== e.target) o.open = false; });
		}, true);
	});
})();
