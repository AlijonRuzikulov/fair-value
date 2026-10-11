/* Fair Value — public website. Plain JavaScript, no build step. */
(function () {
  'use strict';

  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  /* Header shadow after scrolling */
  var header = document.querySelector('[data-header]');
  if (header) {
    var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 40); };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* Mobile menu */
  var menuBtn = document.querySelector('[data-menu]');
  var nav = document.getElementById('site-nav');
  if (menuBtn && nav) {
    var setOpen = function (open) {
      nav.classList.toggle('is-open', open);
      menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    };
    menuBtn.addEventListener('click', function () { setOpen(!nav.classList.contains('is-open')); });
    nav.addEventListener('click', function (e) { if (e.target.closest('a')) setOpen(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
  }

  /* Reveal sections when they enter the viewport */
  var items = $$('.reveal');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) { entry.target.classList.add('is-visible'); io.unobserve(entry.target); }
      });
    }, { rootMargin: '0px 0px -8% 0px' });
    items.forEach(function (el) { io.observe(el); });
  } else {
    items.forEach(function (el) { el.classList.add('is-visible'); });
  }

  /* Verification code: upper case and "FV-XXXX-XXXX" grouping while typing */
  $$('[data-verify-input]').forEach(function (input) {
    input.addEventListener('input', function () {
      var raw = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
      if (raw.indexOf('FV') === 0) raw = raw.slice(2);
      raw = raw.slice(0, 8);
      input.value = raw ? 'FV-' + raw.slice(0, 4) + (raw.length > 4 ? '-' + raw.slice(4) : '') : '';
    });
  });

  /* QR codes (sample certificate on the home page) */
  if (typeof window.qrcode === 'function') {
    $$('[data-qr]').forEach(function (el) {
      var qr = window.qrcode(0, 'M');
      qr.addData(el.dataset.qr);
      qr.make();
      el.innerHTML = qr.createSvgTag({ cellSize: 3, margin: 0, scalable: true });
    });
  }

  /* Phone field: keep only digits, spaces, +, -, () */
  $$('input[type=tel]').forEach(function (input) {
    input.addEventListener('input', function () { input.value = input.value.replace(/[^\d\s()+\-]/g, ''); });
  });
})();
