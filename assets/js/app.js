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

	/* Learn page: count seconds actually watched; enough % -> lesson done -> next unlocks.
	 * Seeking ahead does not count. Server re-checks access and minimum time. */
	var lp = d.querySelector('.kh-learn-player[data-track]');
	var nextBtn = d.querySelector('[data-next-wait]');
	if (nextBtn) {
		nextBtn.addEventListener('click', function (e) {
			if (!nextBtn.hasAttribute('data-next-wait')) return;
			e.preventDefault();
			var st = d.querySelector('[data-status]');
			if (st) { st.classList.remove('kh-shake'); void st.offsetWidth; st.classList.add('kh-shake'); }
		});
	}
	if (lp) {
		var cfg = lp.dataset, need = parseFloat(cfg.need) || 0.8, sent = cfg.done === '1';
		var seen = {}, dur = 0, statusBox = d.querySelector('[data-status]');
		var stText = d.querySelector('[data-status-text]'), stBar = d.querySelector('[data-status-bar]');
		var setDone = function () {
			sent = true;
			if (statusBox) {
				statusBox.classList.add('is-done');
				statusBox.innerHTML = '<span>✓ Bạn đã học xong bài này.' + (nextBtn ? ' Bài tiếp theo đã mở.' : '') + '</span>';
			}
			if (nextBtn) {
				nextBtn.removeAttribute('data-next-wait');
				nextBtn.removeAttribute('aria-disabled');
				nextBtn.removeAttribute('title');
				nextBtn.classList.remove('is-disabled');
				var lock = nextBtn.querySelector('.kh-svg');
				if (lock && nextBtn.firstElementChild === lock) lock.remove();
			}
		};
		/* Lesson list on the right: tick this lesson, unlock the next one, update the count. */
		var syncSide = function (data) {
			var cur = d.querySelector('.kh-learn-list .kh-lesson.is-current');
			if (!cur) return;
			var items = [].slice.call(d.querySelectorAll('.kh-learn-list .kh-lesson'));
			var nxt = items[items.indexOf(cur) + 1];
			var icon = cur.querySelector('.kh-lesson-icon');
			if (nxt && nxt.classList.contains('is-locked') && icon) {
				var lock = nxt.querySelector('.kh-lesson-icon');
				if (lock) lock.replaceWith(icon.cloneNode(true));
				nxt.classList.remove('is-locked');
				nxt.classList.add('is-open');
				var a = nxt.querySelector('a');
				if (a) a.removeAttribute('title');
			}
			cur.classList.add('is-done');
			if (icon) icon.innerHTML = '<path d="M20 6 9 17l-5-5"/>';
			var head = d.querySelector('.kh-learn-side-head p'), bar = d.querySelector('.kh-learn-side-head .kh-learn-bar span');
			var total = head && head.textContent.match(/\/(\d+)/);
			if (head && total && data && data.done) {
				head.textContent = 'Đã học xong ' + data.done + '/' + total[1] + ' bài';
				if (bar) bar.style.width = Math.round(data.done / total[1] * 100) + '%';
			}
		};
		var send = function () {
			if (sent) return;
			sent = true;
			var fd = new FormData();
			fd.append('action', 'kh_lesson_done');
			fd.append('course', cfg.course);
			fd.append('lesson', cfg.lesson);
			fd.append('nonce', cfg.nonce);
			fetch(cfg.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (r) {
					if (r && r.success) { setDone(); return syncSide(r.data); }
					sent = false;
					var wait = r && r.data && r.data.wait;
					if (wait) setTimeout(send, (wait + 1) * 1000);
				})
				.catch(function () { sent = false; setTimeout(send, 5000); });
		};
		var tick = function (cur, total) {
			if (total > 0) dur = total;
			if (!dur || cur < 0) return;
			seen[Math.floor(cur)] = 1;
			var pct = Math.min(1, Object.keys(seen).length / Math.max(1, Math.floor(dur)));
			if (stBar) stBar.style.width = Math.round(pct * 100) + '%';
			if (stText && !sent) stText.textContent = 'Đã xem ' + Math.round(pct * 100) + '% · cần xem ' + Math.round(need * 100) + '% để mở bài tiếp theo.';
			if (pct >= need) send();
		};
		var loadScript = function (src, cb) {
			var sc = d.createElement('script'); sc.src = src; sc.async = true; sc.onload = cb; d.head.appendChild(sc);
		};
		if (cfg.track === 'yt') {
			var startYT = function () {
				var p = new window.YT.Player('kh-yt-player', {
					events: {
						onReady: function () {
							setInterval(function () {
								if (p.getPlayerState && p.getPlayerState() === 1) tick(p.getCurrentTime(), p.getDuration());
							}, 250);
						},
						onStateChange: function (e) { if (e.data === 0) tick(p.getDuration() - 0.5, p.getDuration()); }
					}
				});
			};
			if (window.YT && window.YT.Player) startYT();
			else {
				var prevReady = window.onYouTubeIframeAPIReady;
				window.onYouTubeIframeAPIReady = function () { if (prevReady) prevReady(); startYT(); };
				loadScript('https://www.youtube.com/iframe_api');
			}
		} else if (cfg.track === 'vimeo') {
			loadScript('https://player.vimeo.com/api/player.js', function () {
				var vp = new window.Vimeo.Player(lp.querySelector('iframe'));
				vp.on('timeupdate', function (e) { tick(e.seconds, e.duration); });
			});
		} else if (cfg.track === 'video') {
			var v = lp.querySelector('video');
			if (v) v.addEventListener('timeupdate', function () { if (!v.paused) tick(v.currentTime, v.duration); });
		} else {
			/* Drive/Bunny/other embeds and documents: button unlocks after the minimum time. */
			var btn = d.querySelector('[data-done-btn]'), left = parseInt(cfg.min, 10) || 0;
			if (btn) {
				var label = btn.textContent;
				var count = function () {
					if (left <= 0) { btn.disabled = false; btn.textContent = label; return; }
					btn.textContent = label + ' (' + Math.floor(left / 60) + ':' + ('0' + (left % 60)).slice(-2) + ')';
					left--; setTimeout(count, 1000);
				};
				count();
				btn.addEventListener('click', function () { btn.disabled = true; send(); });
			}
		}
	}

	/* FAQ: keep one answer open at a time */
	d.querySelectorAll('.kh-faq').forEach(function (faq) {
		faq.addEventListener('toggle', function (e) {
			if (!e.target.open) return;
			faq.querySelectorAll('details[open]').forEach(function (o) { if (o !== e.target) o.open = false; });
		}, true);
	});
})();
