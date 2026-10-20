/* Valuation report: QR codes, page layout with Paged.js and the toolbar. */
(function () {
  'use strict';

  // Loaded in <head>: shown while the pages are laid out (outside <body>, so it is not paginated).
  var loading = document.createElement('div');
  loading.className = 'report-loading';
  loading.textContent = document.documentElement.getAttribute('data-preparing') || '…';
  document.documentElement.appendChild(loading);

  function renderQr() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-qr]'), function (el) {
      if (!window.qrcode || el.firstChild) return;
      var qr = window.qrcode(0, 'M');
      qr.addData(el.getAttribute('data-qr'));
      qr.make();
      el.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
    });
  }

  function link(label, href, cls) {
    var a = document.createElement('a');
    a.href = href;
    a.textContent = label;
    if (cls) a.className = cls;
    return a;
  }

  function buildToolbar() {
    var toolbar = {};
    try { toolbar = JSON.parse(document.body.getAttribute('data-toolbar') || '{}'); } catch (e) { toolbar = {}; }
    var bar = document.createElement('div');
    bar.className = 'report-toolbar';
    var print = document.createElement('button');
    print.type = 'button';
    print.className = 'primary';
    print.textContent = toolbar.print || 'Print';
    print.addEventListener('click', function () { window.print(); });
    bar.appendChild(print);
    if (toolbar.download) bar.appendChild(link(toolbar.download.label, toolbar.download.url, 'primary'));
    (toolbar.scripts || []).forEach(function (s) { bar.appendChild(link(s.label, s.url, s.active ? 'on' : '')); });
    if (toolbar.edit) bar.appendChild(link(toolbar.edit.label, toolbar.edit.url));
    if (toolbar.back) bar.appendChild(link(toolbar.back.label, toolbar.back.url));
    document.body.appendChild(bar);
  }

  window.PagedConfig = {
    auto: true,
    before: function () { renderQr(); },
    after: function () {
      loading.remove();
      buildToolbar();
      document.body.setAttribute('data-paged', 'done');
      // Opened by a PDF button while the server cannot make the file: the print dialog saves it.
      if (/[?&]autoprint=1(&|$)/.test(window.location.search)) window.setTimeout(function () { window.print(); }, 300);
    }
  };
})();
