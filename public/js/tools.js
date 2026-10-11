/*
 * "Baholash plus" — calculators for the comparative, cost and income
 * approaches and the reconciliation of their results. Uses FV.parseNum
 * and FV.fmt from admin.js.
 */
(function () {
  'use strict';

  var parse = window.FV.parseNum;
  var fmt = window.FV.fmt;
  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
  var val = function (name) { var el = document.getElementsByName(name)[0]; return el ? parse(el.value) : 0; };
  // Fields typed in by hand are no longer overwritten by the other tabs.
  var setVal = function (name, v) { var el = document.getElementsByName(name)[0]; if (el && !el.dataset.manual && document.activeElement !== el) el.value = v ? fmt(Math.round(v)) : ''; };
  var out = function (key, text) { var el = $('[data-out="' + key + '"]'); if (el) el.textContent = text; };
  var money = function (v) { return isFinite(v) && v ? fmt(Math.round(v)) : '—'; };

  /* Tabs */
  $$('[data-tool-tab]').forEach(function (tab) {
    tab.addEventListener('click', function () {
      $$('[data-tool-tab]').forEach(function (t) {
        var active = t === tab;
        t.classList.toggle('btn-primary', active);
        t.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      $$('[data-tool-panel]').forEach(function (p) { p.hidden = p.dataset.toolPanel !== tab.dataset.toolTab; });
    });
  });

  function comparative() {
    var area = val('cmp_area');
    var sum = 0, weights = 0, perM2 = [];
    $$('[data-analog]').forEach(function (row) {
      var get = function (k) { return parse($('[data-a="' + k + '"]', row).value); };
      var price = get('price'), a = get('area'), weight = get('weight');
      var cell = $('[data-a-out]', row);
      if (!price || !a) { cell.textContent = '—'; return; }
      var k = (1 + get('bargain') / 100) * (1 + get('location') / 100) * (1 + get('condition') / 100) * (1 + get('other') / 100);
      var m2 = price / a * k;
      cell.textContent = fmt(Math.round(m2));
      perM2.push(m2);
      sum += m2 * weight;
      weights += weight;
    });
    var avg = weights ? sum / weights : 0;
    out('cmp_m2', money(avg));
    out('cmp_value', money(avg * area));
    if (perM2.length > 1) {
      var min = Math.min.apply(null, perM2), max = Math.max.apply(null, perM2);
      out('cmp_spread', fmt((max - min) / min * 100, 1) + ' %');
    } else {
      out('cmp_spread', '—');
    }
    return avg * area;
  }

  function cost() {
    var replacement = val('cost_area') * val('cost_rate') * (1 + val('cost_extra') / 100);
    var life = val('cost_life');
    var physical = life ? Math.min(1, val('cost_age') / life) : 0;
    var afterPhysical = replacement * (1 - physical);
    var value = afterPhysical * (1 - val('cost_func') / 100) * (1 - val('cost_ext') / 100) + val('cost_land');
    out('cost_new', money(replacement));
    out('cost_phys', fmt(physical * 100, 1) + ' % · ' + money(replacement - afterPhysical));
    out('cost_value', money(value));
    return value;
  }

  function income() {
    var pgi = val('inc_area') * val('inc_rent') * 12;
    var egi = pgi * (1 - val('inc_vacancy') / 100);
    var noi = egi * (1 - val('inc_opex') / 100);
    var cap = val('inc_cap') / 100;
    var value = cap ? noi / cap : 0;
    out('inc_pgi', money(pgi));
    out('inc_noi', money(noi));
    out('inc_value', money(value));
    return value;
  }

  function reconcile(results) {
    setVal('fin_cmp', results.cmp);
    setVal('fin_cost', results.cost);
    setVal('fin_inc', results.inc);
    var parts = [['fin_cmp', 'fin_cmp_w'], ['fin_cost', 'fin_cost_w'], ['fin_inc', 'fin_inc_w']];
    var w = 0, total = 0;
    parts.forEach(function (p) {
      var v = val(p[0]), weight = val(p[1]);
      if (v) { w += weight; total += v * weight / 100; }
    });
    out('fin_w', fmt(w, 0) + ' %');
    out('fin_value', money(w ? total / w * 100 : 0));
    // Round to thousands below a billion, otherwise to millions.
    var value = w ? total / w * 100 : 0;
    var step = value >= 1e9 ? 1e6 : 1e3;
    out('fin_round', money(Math.round(value / step) * step));
  }

  function currency() {
    var rate = val('cur_rate');
    out('cur_usd', rate ? fmt(val('cur_sum') / rate, 2) + ' $' : '—');
  }

  function run() {
    reconcile({ cmp: comparative(), cost: cost(), inc: income() });
    currency();
  }

  ['fin_cmp', 'fin_cost', 'fin_inc'].forEach(function (name) {
    var el = document.getElementsByName(name)[0];
    if (el) el.addEventListener('input', function () { el.dataset.manual = el.value.trim() ? '1' : ''; });
  });

  document.addEventListener('input', run);
  run();
})();
