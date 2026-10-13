/*
 * Fair Value — admin panel behaviour. Plain JavaScript, no build step.
 * Every block looks for its own data-* hooks and does nothing when the
 * page has none, so the file is safe to include everywhere.
 */
(function () {
  'use strict';

  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  /* ---------- Numbers ------------------------------------------------- */

  // "1 250 000,50" → 1250000.5
  function parseNum(value) {
    if (value === null || value === undefined) return 0;
    var clean = String(value).replace(/[\s  ']/g, '');
    clean = clean.indexOf('.') !== -1 ? clean.replace(/,/g, '') : clean.replace(',', '.');
    var n = parseFloat(clean);
    return isFinite(n) ? n : 0;
  }

  // 1250000.5 → "1 250 000,50"
  function fmt(n, decimals) {
    if (!isFinite(n)) return '—';
    if (decimals === undefined) decimals = Math.abs(n % 1) > 0.0001 ? 2 : 0;
    var neg = n < 0;
    var fixed = Math.abs(n).toFixed(decimals).split('.');
    var int = fixed[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    return (neg ? '−' : '') + int + (fixed[1] ? ',' + fixed[1] : '');
  }

  window.FV = { parseNum: parseNum, fmt: fmt };

  function formatMoneyInput(input) {
    if (input.value.trim() === '') return;
    var n = parseNum(input.value);
    input.value = fmt(n);
  }

  document.addEventListener('focusout', function (e) {
    if (e.target.matches && e.target.matches('[data-money]')) formatMoneyInput(e.target);
  });

  /* ---------- Sidebar ------------------------------------------------- */

  var shell = $('[data-shell]');
  if (shell) {
    document.addEventListener('click', function (e) {
      if (e.target.closest('[data-sidebar-toggle]')) shell.classList.toggle('is-open');
      else if (e.target.closest('[data-sidebar-close]')) shell.classList.remove('is-open');
    });
  }

  /* ---------- Dropdowns (<details class="dropdown">) ------------------ */

  var dropdowns = $$('details.dropdown');
  dropdowns.forEach(function (d) {
    d.addEventListener('toggle', function () {
      if (d.open) dropdowns.forEach(function (o) { if (o !== d) o.open = false; });
    });
  });
  document.addEventListener('click', function (e) {
    dropdowns.forEach(function (d) { if (d.open && !d.contains(e.target)) d.open = false; });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      dropdowns.forEach(function (d) { d.open = false; });
      if (shell) shell.classList.remove('is-open');
    }
  });

  /* ---------- Toasts and confirmations -------------------------------- */

  $$('[data-toast]').forEach(function (t) {
    setTimeout(function () { t.classList.add('is-hidden'); }, 5000);
    setTimeout(function () { t.remove(); }, 5400);
  });

  $$('[data-print]').forEach(function (btn) {
    btn.addEventListener('click', function () { window.print(); });
  });

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form.dataset && form.dataset.confirm && !window.confirm(form.dataset.confirm)) e.preventDefault();
  }, true);

  /* ---------- Filters on small screens -------------------------------- */

  $$('[data-filter-toggle]').forEach(function (btn) {
    var filters = $('[data-filters]', btn.form);
    if (!filters) return;
    // Keep the panel open when any filter besides the search box is in use.
    var used = $$('input, select', filters).some(function (el) {
      return el.name && el.name !== 'q' && el.type !== 'hidden' && el.value;
    });
    if (used) filters.classList.add('is-open');
    btn.addEventListener('click', function () { filters.classList.toggle('is-open'); });
  });

  /* ---------- Region → district --------------------------------------- */

  $$('[data-region-select]').forEach(function (region) {
    var form = region.form || document;
    var district = $('[data-district-select]', form);
    if (!district) return;
    var url = (document.querySelector('meta[name="districts-url"]') || {}).content || '/admin/districts';

    region.addEventListener('change', function () {
      var placeholder = district.options[0] && district.options[0].value === '' ? district.options[0].text : '—';
      district.innerHTML = '';
      district.add(new Option(placeholder, ''));
      if (!region.value) return;
      fetch(url + '?region_id=' + encodeURIComponent(region.value), { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (rows) { rows.forEach(function (row) { district.add(new Option(row.name, row.id)); }); });
    });
  });

  /* ---------- Combobox (client search) -------------------------------- */

  function debounce(fn, ms) {
    var t;
    return function () {
      var args = arguments, self = this;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(self, args); }, ms);
    };
  }

  function escapeHtml(s) {
    return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /**
   * Generic search-as-you-type list under an input.
   * options: { input, fetchItems(q) → Promise<items>, render(item) → html, onPick(item), emptyText }
   */
  function attachSearchList(options) {
    var input = options.input;
    var wrap = input.parentElement;
    var list = null, items = [], focused = -1;

    function close() { if (list) { list.remove(); list = null; } focused = -1; }

    function open(results) {
      close();
      items = results;
      list = document.createElement('div');
      list.className = 'combo-list';
      list.setAttribute('role', 'listbox');
      if (!items.length) {
        list.innerHTML = '<div class="combo-empty">' + escapeHtml(options.emptyText || '—') + '</div>';
      } else {
        list.innerHTML = items.map(function (item, i) {
          return '<button type="button" class="combo-option" role="option" data-i="' + i + '">' + options.render(item) + '</button>';
        }).join('');
      }
      wrap.appendChild(list);
    }

    function pick(i) {
      var item = items[i];
      close();
      if (item) options.onPick(item);
    }

    var search = debounce(function () {
      var q = input.value.trim();
      options.fetchItems(q).then(function (results) {
        if (document.activeElement === input) open(results);
      });
    }, 220);

    input.addEventListener('input', search);
    input.addEventListener('focus', search);
    input.addEventListener('keydown', function (e) {
      if (!list) return;
      var opts = $$('.combo-option', list);
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        focused = (focused + (e.key === 'ArrowDown' ? 1 : -1) + opts.length) % Math.max(opts.length, 1);
        opts.forEach(function (o, i) { o.classList.toggle('is-focused', i === focused); });
      } else if (e.key === 'Enter') {
        if (focused >= 0) { e.preventDefault(); pick(focused); }
      } else if (e.key === 'Escape') {
        close();
      }
    });
    wrap.addEventListener('mousedown', function (e) {
      var opt = e.target.closest('.combo-option');
      if (opt) { e.preventDefault(); pick(+opt.dataset.i); }
    });
    input.addEventListener('blur', function () { setTimeout(close, 150); });
  }

  function getJson(url) {
    return fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : []; })
      .catch(function () { return []; });
  }

  /* ---------- Persons editor ("Шахслар") ------------------------------ */

  $$('[data-persons]').forEach(function (box) {
    var rows = $('[data-person-rows]', box);
    var template = $('[data-person-template]', box);
    var next = +box.dataset.nextIndex || 0;
    var emptyText = ($('[data-combo-empty-text]', box) || {}).textContent;

    function initRow(row) {
      var combo = $('[data-combo]', row);
      var input = $('[data-combo-input]', combo);
      var value = $('[data-combo-value]', combo);
      var selected = $('[data-combo-selected]', combo);

      attachSearchList({
        input: input,
        emptyText: emptyText,
        fetchItems: function (q) { return getJson(box.dataset.searchUrl + '?q=' + encodeURIComponent(q)); },
        render: function (c) { return '<strong>' + escapeHtml(c.title) + '</strong><small>' + escapeHtml(c.sub) + '</small>'; },
        onPick: function (c) {
          value.value = c.id;
          $('strong', selected).textContent = c.title;
          $('small', selected).textContent = c.sub;
          selected.hidden = false;
          input.hidden = true;
          input.value = '';
        }
      });

      $('[data-combo-clear]', combo).addEventListener('click', function () {
        value.value = '';
        selected.hidden = true;
        input.hidden = false;
        input.focus();
      });
      $('[data-person-remove]', row).addEventListener('click', function () { row.remove(); });
    }

    $$('[data-person-row]', rows).forEach(initRow);

    $('[data-person-add]', box).addEventListener('click', function () {
      var html = template.innerHTML.replace(/__i__/g, String(next++));
      var holder = document.createElement('div');
      holder.innerHTML = html.trim();
      var row = holder.firstElementChild;
      rows.appendChild(row);
      initRow(row);
      $('[data-combo-input]', row).focus();
    });
  });

  /* ---------- Approaches and kind toggles ----------------------------- */

  $$('[data-approach]').forEach(function (block) {
    var toggle = $('[data-approach-toggle]', block);
    var input = $('[data-approach-value]', block);
    if (!toggle || !input) return;
    function sync() { input.disabled = !toggle.checked; if (!toggle.checked) input.value = ''; }
    toggle.addEventListener('change', sync);
    input.disabled = !toggle.checked;
  });

  $$('[data-kind-toggle]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      $$('[data-kind-only]').forEach(function (el) { el.hidden = el.dataset.kindOnly !== radio.value; });
    });
  });

  /* ---------- Attachments --------------------------------------------- */

  $$('[data-upload]').forEach(function (form) {
    var input = $('[data-upload-input]', form);
    var zone = $('[data-dropzone]', form);
    input.addEventListener('change', function () { if (input.files.length) form.submit(); });
    ['dragenter', 'dragover'].forEach(function (ev) {
      zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('is-over'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
      zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.remove('is-over'); });
    });
    zone.addEventListener('drop', function (e) {
      if (e.dataTransfer && e.dataTransfer.files.length) {
        input.files = e.dataTransfer.files;
        form.submit();
      }
    });
  });

  /* ---------- QR codes ------------------------------------------------ */

  function renderQr() {
    if (typeof window.qrcode !== 'function') return;
    $$('[data-qr]').forEach(function (el) {
      var qr = window.qrcode(0, 'M');
      qr.addData(el.dataset.qr);
      qr.make();
      el.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', renderQr);
  else renderQr();
  window.addEventListener('load', renderQr);

  /* ---------- Chart tooltips ------------------------------------------ */

  var tip = null;
  function showTip(target) {
    if (!tip) {
      tip = document.createElement('div');
      tip.style.cssText = 'position:fixed;z-index:60;pointer-events:none;padding:8px 10px;border-radius:8px;background:#172031;color:#fff;font-size:12px;font-weight:600;line-height:1.5;white-space:pre-line;box-shadow:0 8px 24px rgba(0,0,0,.2)';
      document.body.appendChild(tip);
    }
    tip.textContent = target.getAttribute('data-tip');
    var r = target.getBoundingClientRect();
    tip.style.display = 'block';
    var left = Math.min(window.innerWidth - tip.offsetWidth - 8, Math.max(8, r.left + r.width / 2 - tip.offsetWidth / 2));
    tip.style.left = left + 'px';
    tip.style.top = Math.max(8, r.top - tip.offsetHeight - 8) + 'px';
  }
  function hideTip() { if (tip) tip.style.display = 'none'; }
  $$('[data-tip]').forEach(function (el) {
    el.addEventListener('mouseenter', function () { showTip(el); });
    el.addEventListener('focus', function () { showTip(el); });
    el.addEventListener('mouseleave', hideTip);
    el.addEventListener('blur', hideTip);
  });

  /* ---------- Damage calculator --------------------------------------- */

  var calc = $('[data-damage-calc]');
  if (calc) {
    var body = $('[data-calc-rows]', calc);
    var tpl = $('[data-calc-template]', calc);
    var form = calc.closest('form');
    var carSelect = form ? $('[data-car-model]', form) : null;
    var nextLine = $$('[data-calc-row]', body).length + 1000;

    var wearInput = $('[data-calc-wear]', calc);
    var rateInput = $('[data-calc-rate]', calc);
    var utsInput = $('[data-calc-uts]', calc);

    function field(row, name) { return $('[data-line="' + name + '"]', row); }

    function syncRowType(row) {
      var type = field(row, 'type').value;
      var hours = field(row, 'hours');
      hours.disabled = type !== 'labor';
      if (type !== 'labor') hours.value = '';
      field(row, 'operation').disabled = type !== 'labor' && type !== 'paint';
      if (type === 'labor' && !field(row, 'unit_price').value) field(row, 'unit_price').value = fmt(parseNum(rateInput.value));
    }

    function recalc() {
      var totals = { part: 0, small_part: 0, labor: 0, paint: 0, other: 0 };
      $$('[data-calc-row]', body).forEach(function (row) {
        var type = field(row, 'type').value;
        var qty = parseNum(field(row, 'quantity').value) || 1;
        var price = parseNum(field(row, 'unit_price').value);
        var amount = type === 'labor' ? parseNum(field(row, 'hours').value) * price * qty : qty * price;
        field(row, 'amount').textContent = fmt(amount);
        totals[type] = (totals[type] || 0) + amount;
      });
      var wear = totals.part * parseNum(wearInput.value) / 100;
      var uts = parseNum(utsInput.value);
      var grand = totals.part - wear + totals.small_part + totals.labor + totals.paint + totals.other + uts;
      var set = function (key, v) { var el = $('[data-total="' + key + '"]', calc); if (el) el.textContent = fmt(v); };
      Object.keys(totals).forEach(function (k) { set(k, totals[k]); });
      set('wear', -wear);
      set('uts', uts);
      set('grand', grand);
    }

    function addLine(values) {
      var holder = document.createElement('tbody');
      holder.innerHTML = tpl.innerHTML.replace(/__i__/g, String(nextLine++)).trim();
      var row = holder.firstElementChild;
      body.appendChild(row);
      Object.keys(values || {}).forEach(function (key) {
        var el = field(row, key);
        if (!el || values[key] === undefined || values[key] === null) return;
        el.value = key === 'unit_price' ? fmt(values[key]) : values[key];
      });
      syncRowType(row);
      recalc();
      return row;
    }

    $$('[data-calc-row]', body).forEach(syncRowType);

    calc.addEventListener('input', recalc);
    calc.addEventListener('change', function (e) {
      var row = e.target.closest('[data-calc-row]');
      if (row && e.target.matches('[data-line="type"]')) syncRowType(row);
      recalc();
    });
    calc.addEventListener('click', function (e) {
      var add = e.target.closest('[data-calc-add]');
      if (add) {
        var row = addLine({ type: add.dataset.calcAdd });
        field(row, 'name').focus();
      }
      var remove = e.target.closest('[data-calc-remove]');
      if (remove) { remove.closest('[data-calc-row]').remove(); recalc(); }
    });

    // When the labour rate changes, labour lines follow it.
    rateInput.addEventListener('change', function () {
      $$('[data-calc-row]', body).forEach(function (row) {
        if (field(row, 'type').value === 'labor') field(row, 'unit_price').value = fmt(parseNum(rateInput.value));
      });
      recalc();
    });

    if (carSelect) {
      carSelect.addEventListener('change', function () {
        var opt = carSelect.selectedOptions[0];
        var name = $('[data-vehicle-name]', form);
        if (opt && opt.value && name && !name.value) name.value = opt.dataset.title;
        if (opt && +opt.dataset.rate > 0) { rateInput.value = fmt(+opt.dataset.rate); rateInput.dispatchEvent(new Event('change')); }
      });
    }

    // Search in the three catalogues at once.
    var picker = $('[data-catalog-input]', calc);
    if (picker) {
      attachSearchList({
        input: picker,
        emptyText: '—',
        fetchItems: function (q) {
          var model = carSelect && carSelect.value ? '&car_model_id=' + carSelect.value : '';
          var query = '?q=' + encodeURIComponent(q) + model;
          return Promise.all([
            getJson(calc.dataset.partsUrl + query),
            getJson(calc.dataset.smallUrl + '?q=' + encodeURIComponent(q)),
            getJson(calc.dataset.normsUrl + query)
          ]).then(function (r) { return r[0].slice(0, 8).concat(r[1].slice(0, 5), r[2].slice(0, 8)); });
        },
        render: function (i) {
          var price = i.kind === 'labor' ? '' : ' · ' + fmt(i.price);
          return '<strong>' + escapeHtml(i.name) + '</strong><small>' + escapeHtml(i.sub) + price + '</small>';
        },
        onPick: function (i) {
          if (i.kind === 'labor') {
            addLine({ type: 'labor', name: i.name, operation: i.operation, hours: i.hours, unit_price: parseNum(rateInput.value), quantity: 1 });
          } else {
            addLine({ type: i.kind, name: i.name, unit_price: i.price, quantity: 1 });
          }
          picker.value = '';
        }
      });
    }

    recalc();
  }
})();
