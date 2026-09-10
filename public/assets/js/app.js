/* ======================================================================
   ViretaDev Student Portal - App scripts
   Sticky nav, mobile menu, scroll reveal, animated counters.
   ====================================================================== */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        /* ---------- Sticky navbar ---------- */
        var nav = document.getElementById('site-nav');
        var onScroll = function () {
            if (!nav) return;
            nav.classList.toggle('nav-scrolled', window.scrollY > 24);
        };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        /* ---------- Mobile menu ---------- */
        var burger = document.getElementById('nav-toggle');
        var menu = document.getElementById('nav-menu');
        var overlay = document.getElementById('sidebar-overlay');
        if (burger && menu) {
            var closeMenu = function () {
                menu.classList.add('hidden');
                menu.classList.remove('flex');
                burger.classList.remove('open');
                overlay && overlay.classList.add('hidden');
            };
            burger.addEventListener('click', function () {
                var willOpen = menu.classList.contains('hidden');
                menu.classList.toggle('hidden', !willOpen);
                menu.classList.toggle('flex', willOpen);
                overlay && overlay.classList.toggle('hidden', !willOpen);
            });
            overlay && overlay.addEventListener('click', closeMenu);
            menu.querySelectorAll('a, button[type=submit]').forEach(function (el) {
                el.addEventListener('click', closeMenu);
            });
        }

        /* ---------- Active nav highlight ---------- */
        var current = window.location.pathname.replace(/\/+$/, '');
        document.querySelectorAll('.nav-link').forEach(function (link) {
            var href = (link.getAttribute('href') || '').replace(/\/+$/, '');
            if (href && (current === href || (href !== '' && current.indexOf(href) === 0))) {
                link.classList.add('active');
            }
        });

        /* ---------- Scroll reveal ---------- */
        var revealEls = document.querySelectorAll('.reveal');
        if ('IntersectionObserver' in window && revealEls.length) {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    if (e.isIntersecting) {
                        e.target.classList.add('revealed');
                        io.unobserve(e.target);
                    }
                });
            }, { threshold: 0.12 });
            revealEls.forEach(function (el) { io.observe(el); });
        } else {
            revealEls.forEach(function (el) { el.classList.add('revealed'); });
        }

        /* ---------- Animated counters ---------- */
        var counters = document.querySelectorAll('[data-count]');
        function animateCount(el) {
            var target = parseFloat(el.getAttribute('data-count')) || 0;
            var suffix = el.getAttribute('data-suffix') || '';
            var decimals = el.getAttribute('data-decimals') === 'true' ? 1 : 0;
            var duration = 1500;
            var start = null;
            function step(ts) {
                if (!start) start = ts;
                var p = Math.min((ts - start) / duration, 1);
                var eased = 1 - Math.pow(1 - p, 3);
                el.textContent = (target * eased).toFixed(decimals) + suffix;
                if (p < 1) requestAnimationFrame(step);
            }
            requestAnimationFrame(step);
        }
        if ('IntersectionObserver' in window && counters.length) {
            var cio = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    if (e.isIntersecting) {
                        animateCount(e.target);
                        cio.unobserve(e.target);
                    }
                });
            }, { threshold: 0.5 });
            counters.forEach(function (el) { cio.observe(el); });
        } else {
            counters.forEach(animateCount);
        }
    });
})();