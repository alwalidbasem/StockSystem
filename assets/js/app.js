/* ==========================================================================
   NovaCell — Phone Shop Manager
   jQuery front-end for the PHP API (api/*.php).
   Styling follows mockup/index.html + mockup/assets/styles.css.
   ========================================================================== */
(function ($) {
  'use strict';

  var APP  = window.APP || {};
  var API  = APP.api || 'api/';
  var SYMS = { JOD: 'د.أ ', USD: '$', EUR: '\u20AC', GBP: '\u00A3', INR: '\u20B9', AED: 'AED ' };

  /* ======================================================================
     state
     ====================================================================== */
  var state = {
    view: 'dashboard',
    settings: APP.settings || {},
    categories: APP.categories || [],
    items: [],
    filters: {
      dash: { range: '30' },
      inv:  { q: '', cat: 'all', status: 'all', range: '30' },
      bill: { q: '', status: 'all', range: '30' },
      mnt:  { q: '', cat: 'all', range: '30' },
      exp:  { q: '', kind: 'all', range: '30' },
      sup:  { q: '', sq: '', supplier: 'all', status: 'all', range: '30' }
    },
    ledger: { rows: [], summary: null },
    backup: null,
    billDraft: [],        // invoice lines: [{uid, item_id, qty, price}]
    billLineSeq: 1,
    billSearch: '',
    stockType: 'in',
    cache: { invoices: [], maintenance: [], billsExp: [], dashboard: null, suppliers: [], supParts: [] }
  };

  var charts = {};

  /* ======================================================================
     small helpers
     ====================================================================== */
  function sym()      { return SYMS[state.settings.currency] || '$'; }
  function num(v)     { v = parseFloat(v); return isNaN(v) ? 0 : v; }
  function money(v)   { return sym() + num(v).toLocaleString('en-US', { maximumFractionDigits: 2 }); }
  function money0(v)  { return sym() + Math.round(num(v)).toLocaleString('en-US'); }
  function esc(s)     {
    return String(s === null || s === undefined ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  function initials(n) {
    var parts = String(n || '?').trim().split(/\s+/);
    return ((parts[0] || '?')[0] + (parts[1] ? parts[1][0] : '')).toUpperCase();
  }
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function iso(d)  { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function todayStr() { return iso(new Date()); }
  function shiftDay(day, n) {
    var p = String(day).split('-');
    var d = new Date(+p[0], (+p[1]) - 1, +p[2]);
    d.setDate(d.getDate() + n);
    return iso(d);
  }
  var AR_MONTHS = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
                   'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
  var AR_DAYS = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
  var STATUS_AR = { Paid: 'مدفوعة', Pending: 'قيد الانتظار', Overdue: 'متأخرة' };

  function parseDay(v) {
    var p = String(v || '').substring(0, 10).split('-');
    var d = new Date(+p[0], (+p[1]) - 1, +p[2]);
    return isNaN(d.getTime()) ? null : d;
  }
  function fmtDate(v) {
    var d = parseDay(v);
    return d ? d.getDate() + ' ' + AR_MONTHS[d.getMonth()] + ' ' + d.getFullYear() : (v ? String(v) : '—');
  }
  function fmtDay(v) {
    var d = parseDay(v);
    return d ? d.getDate() + ' ' + AR_MONTHS[d.getMonth()] : String(v || '');
  }
  function statusLabel(s) { return STATUS_AR[s] || s; }
  function catById(id) {
    for (var i = 0; i < state.categories.length; i++) {
      if (Number(state.categories[i].id) === Number(id)) { return state.categories[i]; }
    }
    return null;
  }

  /* ---- category departments: المخزون / الصيانة / المصروفات -----------------
     Every category carries a `sections` list coming from the checkboxes in the
     categories screen; the selects of each section show only its own. */
  var SECTIONS   = ['inventory', 'maintenance', 'expense'];
  var SEC_LABELS = { inventory: 'المخزون', maintenance: 'الصيانة', expense: 'المصروفات' };

  function catSections(c) {
    return (c && c.sections && c.sections.length !== undefined) ? c.sections : SECTIONS;
  }
  function catInSection(c, section) {
    return !section || catSections(c).indexOf(section) >= 0;
  }
  function catByName(name) {
    var found = null;
    state.categories.forEach(function (c) { if (c.name === String(name)) { found = c; } });
    return found;
  }
  /** Tag color of an expense type: the category color when it is an expense category. */
  function kindColor(kind) {
    var c = catByName(kind);
    return (c && catInSection(c, 'expense')) ? (c.color || '#ef4444') : '#ef4444';
  }
  /** Checkbox states of the category form (default: المخزون + الصيانة). */
  function catSectionInputs(checked) {
    var keys = checked || ['inventory', 'maintenance'];
    $('#catSecInventory').prop('checked', keys.indexOf('inventory') >= 0);
    $('#catSecMaintenance').prop('checked', keys.indexOf('maintenance') >= 0);
    $('#catSecExpense').prop('checked', keys.indexOf('expense') >= 0);
  }
  function catCheckedSections() {
    var keys = [];
    if ($('#catSecInventory').is(':checked')) { keys.push('inventory'); }
    if ($('#catSecMaintenance').is(':checked')) { keys.push('maintenance'); }
    if ($('#catSecExpense').is(':checked')) { keys.push('expense'); }
    return keys;
  }
  function catColor(id, fallback) {
    var c = catById(id);
    return (c && c.color) || fallback || '#6153f4';
  }
  function firstChar(name) {
    var t = String(name || '?').replace(/^\s+/, '');
    return t.charAt(0).toUpperCase() || '?';
  }
  function tile(name, color, size) {
    return '<div class="row-ic" style="background:' + (color || '#6153f4') + '22;color:' + (color || '#6153f4') +
      ';' + (size ? 'width:' + size + 'px;height:' + size + 'px;font-size:13px' : 'font-size:14px') + '">' +
      esc(firstChar(name)) + '</div>';
  }
  function statusOf(item) {
    if (item.state === 'out' || item.qty <= 0) { return ['غير متوفر', 'b-red']; }
    if (item.state === 'low' || item.qty <= item.low_stock) { return ['مخزون منخفض', 'b-amber']; }
    return ['متوفر', 'b-green'];
  }
  function emptyRow(cols, msg) {
    return '<tr><td colspan="' + cols + '" class="empty">' + esc(msg) + '</td></tr>';
  }
  function statBox(value, label, tone) {
    return '<div class="res-box' + (tone ? ' ' + tone : '') + '"><b>' + value + '</b><span>' + esc(label) + '</span></div>';
  }
  /** Paper/report variant of the mini stat box (matches the mockup's .rp-box). */
  function paperStat(value, label) {
    return '<div class="rp-box"><b>' + value + '</b><span>' + esc(label) + '</span></div>';
  }
  function paintCurrencyTags() {
    $('.cur').text(sym());
    $('#billTaxRate').text(num(state.settings.tax));
  }

  /* ======================================================================
     ajax
     ====================================================================== */
  function api(file, data) {
    return $.ajax({ url: API + file, type: 'POST', dataType: 'json', data: data || {} })
      .fail(function (xhr) {
        var msg = 'تعذّر الاتصال بالخادم (' + xhr.status + ')';
        try {
          var j = $.parseJSON(xhr.responseText);
          if (j && j.error) { msg = j.error; }
        } catch (e) {}
        toast(msg, 'warn');
      });
  }
  function call(file, data, done) {
    return api(file, data).done(function (res) {
      if (!res || res.ok !== true) {
        toast((res && res.error) || 'حدث خطأ غير متوقع', 'warn');
        return;
      }
      if (done) { done(res); }
    });
  }

  /* ======================================================================
     overlays / toasts / theme
     ====================================================================== */
  function openOverlay(sel)  { $(sel).addClass('open'); $('body').css('overflow', 'hidden'); }
  function closeOverlay(sel) { $(sel).removeClass('open'); $('body').css('overflow', ''); }

  function toast(msg, type) {
    var t = $('<div class="toast' + (type === 'warn' ? ' warn' : '') + '">' +
      '<svg class="ic"><use href="#i-' + (type === 'warn' ? 'alert' : 'check') + '"/></svg>' + esc(msg) + '</div>');
    $('#toasts').append(t);
    setTimeout(function () { t.addClass('out'); setTimeout(function () { t.remove(); }, 350); }, 2800);
  }

  function setTheme(t) {
    $('html').attr('data-theme', t);
    localStorage.setItem('nc-theme', t);
    $('#btnTheme').html('<svg class="ic"><use href="#i-' + (t === 'dark' ? 'sun' : 'moon') + '"/></svg>');
    if (state.view === 'dashboard' && state.cache.dashboard) { paintDashboard(state.cache.dashboard, state.cache.dashRange || resolveRange('dash', '#dashFrom', '#dashTo')); }
  }
  function toggleTheme() { setTheme($('html').attr('data-theme') === 'dark' ? 'light' : 'dark'); }

  /* ======================================================================
     date range controller (inventory / bills / maintenance / dashboard)
     ====================================================================== */
  function resolveRange(key, fromSel, toSel) {
    var f = state.filters[key], t = todayStr(), d, a, b;
    switch (String(f.range)) {
      case 'all':   return { from: '', to: '', label: 'كل الفترات' };
      case 'today': return { from: t, to: t, label: 'اليوم | ' };
      case '7':     return { from: shiftDay(t, -6), to: t, label: 'آخر 7 أيام | ' };
      case '30':    return { from: shiftDay(t, -29), to: t, label: 'آخر 30 يوم | ' };
      case '90':    return { from: shiftDay(t, -89), to: t, label: 'آخر 90 يوم | ' };
      case 'month':
        d = new Date();
        return { from: iso(new Date(d.getFullYear(), d.getMonth(), 1)), to: t, label: 'هذا الشهر' };
      default:
        a = $(fromSel).val() || shiftDay(t, -29);
        b = $(toSel).val() || t;
        return { from: a, to: b, label: fmtDate(a) + ' ← ' + fmtDate(b) };
    }
  }
  function bindRange(chips, custom, fromSel, toSel, key, after) {
    $(chips).on('click', 'button', function () {
      $(chips).find('button').removeClass('active');
      $(this).addClass('active');
      state.filters[key].range = String($(this).data('r'));
      $(custom).css('display', state.filters[key].range === 'custom' ? 'flex' : 'none');
      if (state.filters[key].range === 'custom') {
        if (!$(fromSel).val()) { $(fromSel).val(shiftDay(todayStr(), -29)); }
        if (!$(toSel).val()) { $(toSel).val(todayStr()); }
      }
      after();
    });
    $(document).on('change', fromSel + ',' + toSel, after);
  }

  /* ======================================================================
     navigation
     ====================================================================== */
  var VIEWS = {
    dashboard:   function () { renderDashboard(); },
    inventory:   function () { renderInventory(); },
    invoices:    function () { renderBills(); },
    maintenance: function () { renderMaintenance(); },
    expenses:    function () { renderExpenses(); },
    suppliers:   function () { renderSuppliers(); },
    categories:  function () { renderCategories(); },
    settings:    function () { renderSettings(); }
  };

  function go(v) {
    state.view = v;
    $('.view').removeClass('active');
    $('#view-' + v).addClass('active');
    $('[data-nav]').each(function () {
      $(this).toggleClass('active', String($(this).data('nav')) === v);
    });
    closeNotif();
    if (VIEWS[v]) { VIEWS[v](); }
  }

  /** Re-draw the view that is currently on screen (used after every data change). */
  function rerender() {
    if (VIEWS[state.view]) { VIEWS[state.view](); }
  }

  function navCounts(o) {
    if (o.units !== undefined)       { $('#cntStock').text(o.units + ' u'); }
    if (o.invoices !== undefined)    { $('#cntInv').text(o.invoices); }
    if (o.maintenance !== undefined) { $('#cntMnt').text(o.maintenance); }
    if (o.expenses !== undefined)    { $('#cntExp').text(o.expenses); }
    if (o.suppliers !== undefined)   { $('#cntSup').text(o.suppliers); }
    if (o.categories !== undefined)  { $('#cntCat').text(o.categories); }
    if (o.low !== undefined)         { $('#bellDot').css('display', num(o.low) > 0 ? 'block' : 'none'); }
  }

  /* ======================================================================
     dashboard
     ====================================================================== */
  function countUp(el, val, opts) {
    opts = opts || {};
    var prefix = opts.prefix || '', suffix = opts.suffix || '';
    var t0 = performance.now(), dur = opts.dur || 800;
    function step(t) {
      var k = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - k, 3);
      el.textContent = prefix + Math.round(num(val) * e).toLocaleString() + suffix;
      if (k < 1) { requestAnimationFrame(step); }
    }
    requestAnimationFrame(step);
  }

  function statCard(icon, color, label, valueHtml, delta, cls, nav) {
    return '<div class="card stat' + (nav ? ' kpi-click' : '') + '"' + (nav ? ' onclick="' + nav + '"' : '') + '>' +
      '<div class="top">' +
      '<div class="tile" style="background:' + color + '22;color:' + color + '">' +
      '<svg class="ic" style="width:20px;height:20px"><use href="#i-' + icon + '"/></svg></div>' +
      '<span class="delta ' + (cls || 'mut') + '">' + esc(delta) + '</span></div>' +
      '<div>' + valueHtml + '<div class="lbl">' + esc(label) + '</div></div></div>';
  }

  function renderDashboard() {
    var d = new Date();
    var h = d.getHours();
    $('#greetWord').text(h < 12 ? 'صباح الخير' : 'مساء الخير');
    $('#greetDate').text(AR_DAYS[d.getDay()] + '، ' + d.getDate() + ' ' + AR_MONTHS[d.getMonth()] + ' ' + d.getFullYear());

    var r = resolveRange('dash', '#dashFrom', '#dashTo');
    var chartFrom = shiftDay(todayStr(), -13), chartTo = todayStr();
    $('#topRangeLbl').text(r.label);

    /* Guard against out-of-order responses when the user clicks filters fast:
       only the latest request is allowed to paint. */
    state.dashSeq = (state.dashSeq || 0) + 1;
    var seq = state.dashSeq;
    call('dashboard.php', {
      action: 'stats', from: r.from, to: r.to, chart_from: chartFrom, chart_to: chartTo
    }, function (res) {
      if (seq !== state.dashSeq) { return; }
      state.cache.dashboard = res;
      state.cache.dashRange = r;
      paintDashboard(res, r);
    });
  }

  function paintDashboard(res, r) {
    var k = res.kpis;

    /* الكاش: الإيرادات اليومية − المصروفات، + كاش نافذة الفلتر بعد دفع المستحقات */
    var cashRange = (k.cash_range !== undefined) ? k.cash_range : k.cash_30;
    var cashRangeAfter = (k.cash_range_after_suppliers !== undefined) ? k.cash_range_after_suppliers : k.cash_30_after_suppliers;
    var cashRangeIncome = (k.cash_range_income !== undefined) ? k.cash_range_income : k.cash_30_income;
    var cashRangeExp = (k.cash_range_expenses !== undefined) ? k.cash_range_expenses : k.cash_30_expenses;
    var cashAfterCls = num(cashRangeAfter) >= 0 ? 'up' : 'warn';
    var cashTodayCls = num(k.cash_today) >= 0 ? 'up' : 'warn';
    $('#cashCard').html(
      '<div class="cash-top">' +
      '<div class="tile cash-tile"><svg class="ic" style="width:22px;height:22px"><use href="#i-card"/></svg></div>' +
      '<div class="cash-title"><b>الكاش</b><small>الإيرادات اليومية − المصروفات</small></div>' +
      '<span class="delta ' + cashTodayCls + '">اليوم</span>' +
      '</div>' +
      '<div class="cash-grid">' +
      '<div class="cash-main"><b class="v" data-v="' + num(k.cash_today) + '" data-pre="' + sym() + '">' + sym() + '0</b>' +
      '<small>إيرادات اليوم ' + money0(k.cash_today_income) + ' · مصروفات اليوم ' + money0(k.cash_today_expenses) + '</small></div>' +
      '<div class="cash-side">' +
      '<div class="cash-row"><span>الكاش · ' + esc(r.label) + '<small class="cash-sub">إيرادات ' + money0(cashRangeIncome) + ' · مصروفات ' + money0(cashRangeExp) + '</small></span><b class="v" data-v="' + num(cashRange) + '" data-pre="' + sym() + '">' + sym() + '0</b></div>' +
      '<div class="cash-row hi"><span>بعد دفع المستحقات للموردين : ' + money0(k.supplier_due) + '</span>' +
      '<span class="cash-after"><b class="v" data-v="' + num(cashRangeAfter) + '" data-pre="' + sym() + '">' + sym() + '0</b>' +
      '<span class="delta ' + cashAfterCls + '">' + (num(cashRangeAfter) >= 0 ? 'متاح' : 'عجز') + '</span></span></div>' +
      '</div></div>'
    );
    $('#cashCard .v').each(function () {
      countUp(this, $(this).data('v'), { prefix: $(this).data('pre') || '' });
    });

    $('#statCards').html(
      statCard('card', '#0ea5e9', 'الإيرادات · ' + r.label,
        '<b class="v" data-v="' + num(k.income) + '" data-pre="' + sym() + '">' + sym() + '0</b>',
        k.bills + ' فاتورة', 'mut', 'go(\'invoices\')') +
      statCard('trend', '#10b981', 'الأرباح · ' + r.label,
        '<b class="v" data-v="' + num(k.profit) + '" data-pre="' + sym() + '">' + sym() + '0</b>',
        'داخلي فقط', 'up', 'go(\'inventory\')') +
      statCard('bolt', '#ef4444', 'المصروفات · ' + r.label,
        '<b class="v" data-v="' + num(k.expenses) + '" data-pre="' + sym() + '">' + sym() + '0</b>',
        k.expenses_count + ' مصروف مسجّل', 'warn', 'go(\'expenses\')') +
      statCard('up', '#8b5cf6', 'صافي الربح · ' + r.label,
        '<b class="v" data-v="' + num(k.net_profit) + '" data-pre="' + sym() + '">' + sym() + '0</b>',
        'الأرباح − المصروفات', 'mut', 'go(\'expenses\')') +
      statCard('truck', '#f59e0b', 'مستحق للموردين',
        '<b class="v" data-v="' + num(k.supplier_due) + '" data-pre="' + sym() + '">' + sym() + '0</b>',
        (k.supplier_due_parts ? k.supplier_due_parts + ' قطعة مستحقة · للعلم فقط' : 'لا مستحقات · للعلم فقط'), 'warn', 'go(\'suppliers\')') +
      statCard('phone', '#6153f4', 'وحدات في المخزون · ' + k.items + ' صنف',
        '<b class="v" data-v="' + num(k.units_in_stock) + '">0</b>',
        money0(k.stock_value) + ' قيمة المخزون', 'mut', 'go(\'inventory\')') +
      statCard('box', '#0ea5e9', 'قيمة المخزون الإجمالية',
        '<b class="v" data-v="' + num(k.stock_value) + '" data-pre="' + sym() + '">' + sym() + '0</b>',
        (k.units_in_stock ? num(k.units_in_stock) + ' وحدة في المخزون' : 'لا وحدات في المخزون'), 'up', 'go(\'inventory\')') +
      statCard('alert', '#f59e0b', 'تنبيهات المخزون',
        '<b class="v" data-v="' + num(k.low_stock) + '">0</b>',
        k.low_stock ? 'يحتاج متابعة' : 'لا مشاكل', k.low_stock ? 'warn' : 'up', 'kpiLowStock()')
    );
    $('#statCards .v').each(function () {
      countUp(this, $(this).data('v'), { prefix: $(this).data('pre') || '' });
    });

    var total = res.series.bills.reduce(function (a, b) { return a + num(b); }, 0) +
                res.series.maintenance.reduce(function (a, b) { return a + num(b); }, 0);
    $('#revTotal').text(money0(total) + ' · 14 يوم');

    paintIncomeChart(res.series);
    paintCategoryChart(res.categories);

    /* الأكثر بيعاً */
    var top = res.top_items || [], max = top.length ? top[0].units : 0;
    $('#topItems').html(top.length ? top.map(function (t) {
      return '<div class="bar-row"><div class="bar-top"><span style="color:var(--text)">' + esc(t.name) + '</span>' +
        '<span>' + t.units + ' مباع · ' + money0(t.value) + '</span></div>' +
        '<div class="bar-track"><div class="bar-fill" data-w="' + (max ? Math.round(t.units / max * 100) : 0) + '"></div></div></div>';
    }).join('') : '<div class="empty-sm">لا مبيعات في هذه الفترة</div>');
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        $('#topItems .bar-fill').each(function () { this.style.width = $(this).data('w') + '%'; });
      });
    });

    /* أحدث الفواتير */
    var recent = res.recent_invoices || [];
    $('#recentBills').html(recent.length ? recent.map(function (v, i) {
      var cols = ['#6153f4', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];
      return '<div class="row-item" onclick="openBillPaper(' + v.id + ')">' +
        '<div class="row-ic" style="background:' + cols[i % 6] + '22;color:' + cols[i % 6] + '">' + esc(initials(v.customer)) + '</div>' +
        '<div class="ri-t"><b>' + esc(v.code) + ' · ' + esc(v.customer) + '</b><small>' + v.units + ' قطعة · ' + fmtDate(v.entry_date) + '</small></div>' +
        '<div class="ri-r">' + money(v.total) + '<small>' + esc(statusLabel(v.status)) + '</small></div></div>';
    }).join('') : '<div class="empty-sm">لا توجد فواتير بعد</div>');

    /* مخزون منخفض */
    var low = res.low_stock_items || [];
    $('#lowCnt').text(low.length + ' صنف');
    $('#lowStockList').html(low.length ? low.map(function (p) {
      return '<div class="row-item" style="cursor:default">' + tile(p.name, '#f59e0b') +
        '<div class="ri-t"><b>' + esc(p.name) + '</b><small>' + esc(p.category) + ' · ' +
        (p.qty === 0 ? 'غير متوفر' : 'بقي ' + p.qty) + '</small></div>' +
        '<button class="btn btn-soft" style="padding:7px 12px;font-size:12px" onclick="openStock(' + p.id + ',\'in\')">إضافة كمية</button></div>';
    }).join('') : '<div class="empty-sm">المخزون بحالة جيدة 🎉</div>');

    /* الصيانة */
    var mnt = res.recent_maintenance || [];
    $('#recentMaintenance').html(mnt.length ? mnt.map(function (m) {
      return '<div class="row-item" onclick="openMntPaper(' + m.id + ')">' + tile(m.name, m.category_color) +
        '<div class="ri-t"><b>' + esc(m.code) + ' · ' + esc(m.name) + '</b><small>' + esc(m.category) + ' · ' + fmtDate(m.entry_date) + '</small></div>' +
        '<div class="ri-r">' + money(m.price) + '<small>' + esc(m.note || 'صيانة') + '</small></div></div>';
    }).join('') : '<div class="empty-sm">لا توجد فواتير صيانة بعد</div>');

    navCounts({ units: k.units_in_stock, invoices: k.bills, maintenance: k.maintenance_count, low: k.low_stock });
  }

  /** KPI shortcut: jump to the inventory list filtered by "low stock". */
  function kpiLowStock() {
    state.filters.inv.status = 'low';
    $('#invStatus button').removeClass('active');
    $('#invStatus button[data-s="low"]').addClass('active');
    go('inventory');
  }

  /* ----------------------------------------------------------------------
     charts (Chart.js, data straight from the database)
     ---------------------------------------------------------------------- */
  function cssVar(name) { return getComputedStyle(document.documentElement).getPropertyValue(name).trim(); }
  function hexA(hex, a) {
    hex = String(hex).replace('#', '');
    if (hex.length === 3) { hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2]; }
    return 'rgba(' + parseInt(hex.substring(0, 2), 16) + ',' + parseInt(hex.substring(2, 4), 16) + ',' +
      parseInt(hex.substring(4, 6), 16) + ',' + a + ')';
  }
  function chartFallback(canvasSel, msg) {
    var $wrap = $(canvasSel).closest('.chart-wrap');
    $wrap.find('canvas').hide();
    $wrap.find('.no-chart').remove();
    $wrap.append('<div class="no-chart">' + esc(msg) + '</div>');
  }
  function clearChartFallback(canvasSel) {
    $(canvasSel).show().closest('.chart-wrap').find('.no-chart').remove();
  }

  function paintIncomeChart(series) {
    if (charts.income) { charts.income.destroy(); charts.income = null; }
    if (!window.Chart) { chartFallback('#incomeChart', 'مكتبة الرسوم البيانية غير متوفرة'); return; }
    if (!series || !series.labels.length) { chartFallback('#incomeChart', 'لا توجد بيانات بعد'); return; }
    clearChartFallback('#incomeChart');

    var prim = cssVar('--primary') || '#6153f4';
    var muted = cssVar('--muted'), border = cssVar('--border');
    Chart.defaults.font.family = "'Cairo','Plus Jakarta Sans',sans-serif";
    Chart.defaults.color = muted;

    var ctx = document.getElementById('incomeChart');
    var g = ctx.getContext('2d').createLinearGradient(0, 0, 0, 280);
    g.addColorStop(0, hexA(prim, 0.32));
    g.addColorStop(1, hexA(prim, 0));

    charts.income = new Chart(ctx, {
      type: 'line',
      data: {
        labels: series.labels.map(fmtDay),
        datasets: [
          { label: 'المبيعات', data: series.bills, tension: 0.42, borderColor: prim, borderWidth: 3, fill: true,
            backgroundColor: g, pointRadius: 0, pointHoverRadius: 5, pointHoverBackgroundColor: prim,
            pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2 },
          { label: 'الصيانة', data: series.maintenance, tension: 0.42, borderColor: '#0ea5e9', borderWidth: 2.5,
            borderDash: [6, 5], fill: false, pointRadius: 0, pointHoverRadius: 5, pointHoverBackgroundColor: '#0ea5e9',
            pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2 },
          { label: 'الأرباح', data: series.profit, tension: 0.42, borderColor: '#10b981', borderWidth: 2,
            fill: false, pointRadius: 0, pointHoverRadius: 5, pointHoverBackgroundColor: '#10b981',
            pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2 },
          { label: 'المصروفات', data: series.expenses || [], tension: 0.42, borderColor: '#ef4444', borderWidth: 2,
            fill: false, pointRadius: 0, pointHoverRadius: 5, pointHoverBackgroundColor: '#ef4444',
            pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2 }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { intersect: false, mode: 'index' },
        plugins: {
          legend: { display: true, position: 'top', align: 'end', rtl: true,
            labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 14, font: { size: 11.5, weight: 600 } } },
          tooltip: { backgroundColor: '#171a2c', padding: 10, cornerRadius: 10, rtl: true, textDirection: 'rtl',
            callbacks: { label: function (c) { return ' ' + c.dataset.label + ': ' + money(c.parsed.y); } } }
        },
        scales: {
          x: { grid: { display: false }, border: { display: false }, ticks: { maxTicksLimit: 8, font: { size: 11 } } },
          y: { position: 'right', border: { display: false }, grid: { color: border }, ticks: { maxTicksLimit: 5, font: { size: 11 },
            callback: function (v) { return sym() + Number(v).toLocaleString(); } } }
        }
      }
    });
  }

  function paintCategoryChart(cats) {
    if (charts.cat) { charts.cat.destroy(); charts.cat = null; }
    cats = cats || [];
    var units = cats.reduce(function (a, c) { return a + num(c.units); }, 0);
    $('#catUnits').text(units + ' وحدة مباعة');
    if (!window.Chart) { chartFallback('#catChart', 'مكتبة الرسوم البيانية غير متوفرة'); return; }
    if (!cats.length) { chartFallback('#catChart', 'لا مبيعات في هذه الفترة'); return; }
    clearChartFallback('#catChart');

    var surface = cssVar('--surface') || '#fff';
    charts.cat = new Chart(document.getElementById('catChart'), {
      type: 'doughnut',
      data: {
        labels: cats.map(function (c) { return c.name; }),
        datasets: [{
          data: cats.map(function (c) { return num(c.value); }),
          backgroundColor: cats.map(function (c) { return c.color || '#6153f4'; }),
          borderWidth: 3, borderColor: surface, hoverOffset: 8
        }]
      },
      options: {
        cutout: '68%', maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', rtl: true,
            labels: { usePointStyle: true, pointStyle: 'circle', padding: 13, font: { size: 11.5, weight: 600 } } },
          tooltip: { backgroundColor: '#171a2c', padding: 10, cornerRadius: 10, rtl: true, textDirection: 'rtl',
            callbacks: { label: function (c) { return ' ' + cats[c.dataIndex].units + ' وحدة · ' +
              money(c.parsed); } } }
        }
      }
    });
  }

  /* ======================================================================
     inventory (stock items + dated ledger)
     ====================================================================== */
  function renderInventory() {
    var f = state.filters.inv;
    call('items.php', { action: 'list', q: f.q, category_id: f.cat, status: f.status }, function (res) {
      state.items = res.items;
      paintItems(res.summary);
      paintLedger();
    });
  }

  function paintItems(summary) {
    $('#invCount').text(summary.models + ' صنف · ' + summary.units + ' وحدة · ' +
      money0(summary.value) + ' قيمة المخزون');
    $('#invSummaryLbl').text(summary.low + ' صنف يحتاج متابعة');
    navCounts({ units: summary.units, low: summary.low });

    var list = state.items;
    $('#invBody').html(list.length ? list.map(function (p) {
      var st = statusOf(p);
      return '<tr>' +
        '<td class="no-label"><div class="p-cell">' + tile(p.name, p.category_color) +
          '<div><b>' + esc(p.name) + '</b><small>ربح الوحدة ' + money(p.profit) + ' · التنبيه عند ' + p.low_stock + '</small></div></div></td>' +
        '<td data-l="التصنيف"><span class="tag" style="color:' + (p.category_color || '#6153f4') + '"><i></i>' + esc(p.category) + '</span></td>' +
        '<td data-l="سعر البيع" class="right"><b>' + money(p.price) + '</b></td>' +
        '<td data-l="سعر الجملة" class="right">' + (num(p.wholesale) > 0 ? money(p.wholesale) : '—') + '</td>' +
        '<td data-l="ربح الوحدة" class="right"><span class="profit">' + money(p.profit) + '</span></td>' +
        '<td data-l="الكمية"><span class="stp">' +
          '<button onclick="quickStock(' + p.id + ',\'out\')" aria-label="خصم واحدة">−</button><b>' + p.qty + '</b>' +
          '<button onclick="quickStock(' + p.id + ',\'in\')" aria-label="إضافة واحدة">+</button></span></td>' +
        '<td data-l="الحالة"><span class="badge ' + st[1] + '">' + st[0] + '</span></td>' +
        '<td data-l="" class="right"><div class="row-actions">' +
          '<button class="icon-btn sm" title="بيع سريع" onclick="quickSell(' + p.id + ')"><svg class="ic" style="width:15px;height:15px"><use href="#i-cart"/></svg></button>' +
          '<button class="icon-btn sm" title="إضافة / خصم كمية" onclick="openStock(' + p.id + ',\'in\')"><svg class="ic" style="width:15px;height:15px"><use href="#i-down"/></svg></button>' +
          '<button class="icon-btn sm" title="تعديل الصنف" onclick="openItem(' + p.id + ')"><svg class="ic" style="width:15px;height:15px"><use href="#i-edit"/></svg></button>' +
          '<button class="icon-btn sm" title="حذف الصنف" onclick="deleteItem(' + p.id + ')"><svg class="ic" style="width:15px;height:15px"><use href="#i-trash"/></svg></button>' +
        '</div></td></tr>';
    }).join('') : emptyRow(8, 'لا توجد أصناف مطابقة للفلاتر'));
  }

  function paintLedger() {
    var r = resolveRange('inv', '#invFrom', '#invTo');
    $('#invRangeLbl').text(r.label);
    call('items.php', { action: 'movements', from: r.from, to: r.to, q: state.filters.inv.q,
      category_id: state.filters.inv.cat }, function (res) {
      state.ledger = res;
      var s = res.summary;
      $('#invRangeStats').html(
        statBox(s.entries, 'عدد الحركات') +
        statBox(s.units_in, 'وحدات مضافة') +
        statBox(money0(s.in_value), 'قيمة مضافة') +
        statBox(s.units_out, 'وحدات مصروفة') +
        statBox(money0(s.profit), 'أرباح الفترة', 'green')
      );

      $('#invMoveBody').html(res.movements.length ? res.movements.map(function (m) {
        var isIn = m.type === 'in';
        return '<tr>' +
          '<td class="no-label"><span class="mono">' + fmtDate(m.entry_date) + '</span></td>' +
          '<td data-l="الصنف"><div class="p-cell">' + tile(m.item, m.category_color, 32) +
            '<div><b style="font-size:13px">' + esc(m.item) + '</b><small>' + esc(m.category) + ' · ' + esc(m.note || '') + '</small></div></div></td>' +
          '<td data-l="الحركة"><span class="delta-pill ' + (isIn ? 'in' : 'out') + '">' +
            (isIn ? 'إضافة' : 'خصم') + '</span></td>' +
          '<td data-l="الكمية" class="right"><b>' + m.qty + '</b></td>' +
          '<td data-l="السعر" class="right">' + money(m.price) + '</td>' +
          '<td data-l="القيمة" class="right"><span class="amount">' + money(m.value) + '</span></td>' +
          '<td data-l="الربح" class="right">' + (isIn ? '<span class="mono">—</span>'
            : '<span class="profit">' + money(m.profit_total) + '</span>') + '</td>' +
          '<td data-l="" class="right"><div class="row-actions">' +
            '<button class="icon-btn sm" title="تعديل التاريخ أو الكمية" onclick="openLedger(' + m.id + ')"><svg class="ic" style="width:15px;height:15px"><use href="#i-edit"/></svg></button>' +
            '<button class="icon-btn sm" title="حذف الحركة" onclick="deleteLedger(' + m.id + ')"><svg class="ic" style="width:15px;height:15px"><use href="#i-trash"/></svg></button>' +
          '</div></td></tr>';
      }).join('') : emptyRow(8, 'لا توجد حركات مخزون في هذه الفترة'));
    });
  }

  /* ----------------------------------------------------------------------
     inventory: category selects, item modal, stock ledger modal
     ---------------------------------------------------------------------- */
  function catOptions(section, selected, emptyLabel) {
    var html = emptyLabel ? '<option value="">' + esc(emptyLabel) + '</option>' : '';
    state.categories.forEach(function (c) {
      // only the categories switched on for this department (the one already
      // selected is always kept so editing an older record cannot lose it)
      if (!catInSection(c, section) && Number(selected) !== Number(c.id)) { return; }
      html += '<option value="' + c.id + '"' + (Number(selected) === Number(c.id) ? ' selected' : '') + '>' + esc(c.name) + '</option>';
    });
    return html;
  }

  /** Filter select for one department: «كل التصنيفات» + its own categories. */
  function fillCatFilter(sel, section, filter, key) {
    var $sel    = $(sel);
    var current = String(filter[key]);
    $sel.html('<option value="all">كل التصنيفات</option>' + catOptions(section));
    if ($sel.find('option[value="' + current + '"]').length) {
      $sel.val(current);
    } else {
      $sel.val('all');
      filter[key] = 'all';   // the selected category left this department
    }
  }

  function loadCategories(cb) {
    call('categories.php', { action: 'list' }, function (res) {
      state.categories = res.categories;
      navCounts({ categories: res.categories.length });
      fillCatFilter('#invCat', 'inventory', state.filters.inv, 'cat');
      fillCatFilter('#mntCat', 'maintenance', state.filters.mnt, 'cat');
      if (cb) { cb(); }
    });
  }

  /**
   * ربح الوحدة = سعر البيع − سعر الجملة؛ يُحسب تلقائياً (ويُقفل الحقل) بمجرد
   * إدخال سعر الجملة، ويبقى يدوياً إذا تُرك سعر الجملة فارغاً.
   */
  function paintItemProfit() {
    var price = num($('#itemPrice').val());
    var whole = num($('#itemWholesale').val());
    var auto  = whole > 0;
    $('#itemProfitTag').text(auto ? 'تلقائي = البيع − الجملة' : 'يدوي');
    $('#itemProfit').prop('readonly', auto).toggleClass('auto', auto);
    if (auto) {
      $('#itemProfit').val((Math.max(price - whole, 0)).toFixed(2));
    }
  }

  function openItem(id) {
    var p = null;
    if (id) {
      for (var i = 0; i < state.items.length; i++) {
        if (Number(state.items[i].id) === Number(id)) { p = state.items[i]; }
      }
    }
    $('#itemModalTitle').text(p ? 'تعديل صنف مخزون' : 'صنف مخزون جديد');
    $('#itemId').val(p ? p.id : '');
    $('#itemName').val(p ? p.name : '');
    $('#itemCat').html(catOptions('inventory', p ? p.category_id : '', '— بدون تصنيف —'));
    $('#itemQty').val(p ? p.qty : 0);
    $('#itemPrice').val(p ? p.price : '');
    $('#itemWholesale').val(p && num(p.wholesale) > 0 ? p.wholesale : '');
    $('#itemProfit').val(p ? p.profit : '');
    $('#itemLow').val(p ? p.low_stock : num(state.settings.low_stock));
    $('#itemDate').val(todayStr());
    paintItemProfit();
    openOverlay('#ovItem');
  }

  function saveItem() {
    var id = $('#itemId').val();
    call('items.php', {
      action: 'save', id: id || '', name: $('#itemName').val(), category_id: $('#itemCat').val(),
      price: $('#itemPrice').val(), wholesale: $('#itemWholesale').val(), profit: $('#itemProfit').val(),
      qty: $('#itemQty').val(), low_stock: $('#itemLow').val(), entry_date: $('#itemDate').val()
    }, function (res) {
      state.items = res.items;
      closeOverlay('#ovItem');
      toast(res.message);
      rerender();
    });
  }

  function quickStock(id, type) {
    var p = null;
    for (var i = 0; i < state.items.length; i++) {
      if (Number(state.items[i].id) === Number(id)) { p = state.items[i]; }
    }
    if (!p) { return; }
    if (type === 'out' && p.qty <= 0) { toast('لا توجد كمية لخصمها', 'warn'); return; }
    call('items.php', {
      action: 'adjust', item_id: id, type: type, qty: 1, entry_date: todayStr(),
      note: type === 'in' ? 'إضافة سريعة' : 'خصم سريع'
    }, function (res) {
      state.items = res.items;
      toast(res.message);
      rerender();
    });
  }

  /** Quick sale: open a new bill with this item already on a line of its own. */
  function quickSell(id) {
    function add() {
      billAdd(id);
      setTimeout(function () { $('#billCustomer').focus(); }, 120);
    }
    if (state.items.length) {
      openBill();
      add();
      return;
    }
    call('items.php', { action: 'list' }, function (res) { state.items = res.items; openBill(); add(); });
  }

  function deleteItem(id) {
    var p = null;
    for (var i = 0; i < state.items.length; i++) {
      if (Number(state.items[i].id) === Number(id)) { p = state.items[i]; }
    }
    if (!p) { return; }
    if (!window.confirm('هل تريد حذف «' + p.name + '» وكل حركات مخزونه؟')) { return; }
    call('items.php', { action: 'delete', id: id }, function (res) {
      state.items = res.items;
      toast(res.message);
      rerender();
    });
  }

  function setStockType(t) {
    state.stockType = t;
    $('#stockSeg button').removeClass('on');
    $('#stockSeg button[data-t="' + t + '"]').addClass('on');
  }

  function openStock(itemId, type) {
    function withItems() {
      $('#stockItem').html(state.items.map(function (p) {
        return '<option value="' + p.id + '"' + (Number(itemId) === Number(p.id) ? ' selected' : '') + '>' +
          esc(p.name) + ' · ' + p.qty + ' متوفر</option>';
      }).join('') || '<option value="">— لا توجد أصناف بعد —</option>');
      if (itemId) { $('#stockItem').val(String(itemId)); }
      setStockType(type || 'in');
      $('#stockQty').val(1);
      $('#stockDate').val(todayStr());
      $('#stockNote').val('');
      fillStockPriceProfit();
      openOverlay('#ovStock');
    }
    if (state.items.length) {
      withItems();
    } else {
      call('items.php', { action: 'list' }, function (res) {
        state.items = res.items;
        withItems();
      });
    }
  }

  function fillStockPriceProfit() {
    var id = $('#stockItem').val();
    for (var i = 0; i < state.items.length; i++) {
      if (String(state.items[i].id) === String(id)) {
        $('#stockPrice').val(state.items[i].price);
        $('#stockProfit').val(state.items[i].profit);
        return;
      }
    }
  }

  function saveStock() {
    call('items.php', {
      action: 'adjust', item_id: $('#stockItem').val(), type: state.stockType, qty: $('#stockQty').val(),
      entry_date: $('#stockDate').val(), note: $('#stockNote').val(),
      price: $('#stockPrice').val(), profit: $('#stockProfit').val()
    }, function (res) {
      state.items = res.items;
      closeOverlay('#ovStock');
      toast(res.message);
      rerender();
    });
  }

  function openLedger(id) {
    var m = null;
    (state.ledger.movements || []).forEach(function (r) { if (Number(r.id) === Number(id)) { m = r; } });
    if (!m) { return; }
    $('#ledgerId').val(m.id);
    $('#ledgerItem').val(m.item + ' — ' + (m.type === 'in' ? 'إضافة' : 'خصم') + ' · ' + fmtDate(m.entry_date));
    $('#ledgerQty').val(m.qty);
    $('#ledgerDate').val(m.entry_date);
    $('#ledgerPrice').val(m.price);
    $('#ledgerProfit').val(m.profit);
    $('#ledgerNote').val(m.note);
    openOverlay('#ovLedger');
  }

  function saveLedger() {
    call('items.php', {
      action: 'movement_save', id: $('#ledgerId').val(), qty: $('#ledgerQty').val(),
      entry_date: $('#ledgerDate').val(), note: $('#ledgerNote').val(),
      price: $('#ledgerPrice').val(), profit: $('#ledgerProfit').val()
    }, function (res) {
      state.items = res.items;
      closeOverlay('#ovLedger');
      toast(res.message);
      rerender();
    });
  }

  function deleteLedger(id) {
    if (!window.confirm('هل تريد حذف هذه الحركة؟ سيتم إعادة حساب كمية الصنف تلقائياً.')) { return; }
    call('items.php', { action: 'movement_delete', id: id }, function (res) {
      state.items = res.items;
      toast(res.message);
      rerender();
    });
  }

  /* ======================================================================
     bills (invoices)
     NOTE: profit is deliberately never rendered in this view, in the paper
     preview or on the printed bill — it only feeds dashboard/inventory stats.
     ====================================================================== */
  function itemById(id) {
    for (var i = 0; i < state.items.length; i++) {
      if (Number(state.items[i].id) === Number(id)) { return state.items[i]; }
    }
    return null;
  }

  function renderBills() {
    var f = state.filters.bill, r = resolveRange('bill', '#billFrom', '#billTo');
    $('#billRangeLbl').text(r.label);
    call('invoices.php', { action: 'list', q: f.q, status: f.status, from: r.from, to: r.to }, function (res) {
      state.cache.invoices = res.invoices;
      var s = res.summary;
      $('#billCount').text(s.count + ' فاتورة · ' + s.units + ' قطعة مباعة · ' + money0(s.revenue) + ' إجمالي');
      $('#billStats').html(
        statBox(s.count, 'عدد الفواتير') +
        statBox(s.units, 'القطع المباعة') +
        statBox(money0(s.revenue), 'الإيرادات')
      );
      navCounts({ invoices: s.count });

      $('#billBody').html(res.invoices.length ? res.invoices.map(function (v) {
        var cls = { Paid: 'b-green', Pending: 'b-amber', Overdue: 'b-red' }[v.status] || 'b-gray';
        return '<tr class="rowlink" onclick="openBillPaper(' + v.id + ')">' +
          '<td class="no-label"><b>' + esc(v.code) + '</b><small style="display:block;color:var(--muted);font-size:11.5px">' + v.lines + ' بند</small></td>' +
          '<td data-l="العميل"><div class="p-cell" style="min-width:0"><div class="row-ic" style="width:32px;height:32px;font-size:11px;background:#6153f422;color:#6153f4">' +
            esc(initials(v.customer)) + '</div><div style="min-width:0"><b style="font-size:13px">' + esc(v.customer) + '</b>' +
            '<small class="num" dir="ltr">' + esc(v.phone || '—') + '</small></div></div></td>' +
          '<td data-l="التاريخ"><span class="mono">' + fmtDate(v.entry_date) + '</span></td>' +
          '<td data-l="القطع">' + v.units + '</td>' +
          '<td data-l="الإجمالي"><b>' + money(v.total) + '</b></td>' +
          '<td data-l="الحالة"><span class="badge ' + cls + '">' + esc(statusLabel(v.status)) + '</span></td>' +
          '<td data-l="" class="right"><div class="row-actions">' +
            '<button class="icon-btn sm" title="عرض الفاتورة" onclick="event.stopPropagation();openBillPaper(' + v.id + ')"><svg class="ic" style="width:15px;height:15px"><use href="#i-eye"/></svg></button>' +
            '<button class="icon-btn sm" title="طباعة" onclick="event.stopPropagation();printBill(' + v.id + ')"><svg class="ic" style="width:15px;height:15px"><use href="#i-printer"/></svg></button>' +
            '<button class="icon-btn sm" title="تعليم كمدفوعة" onclick="event.stopPropagation();setBillStatus(' + v.id + ')"><svg class="ic" style="width:15px;height:15px"><use href="#i-check"/></svg></button>' +
            '<button class="icon-btn sm" title="حذف الفاتورة وإرجاع الكميات" onclick="event.stopPropagation();deleteBill(' + v.id + ')"><svg class="ic" style="width:15px;height:15px"><use href="#i-trash"/></svg></button>' +
          '</div></td></tr>';
      }).join('') : emptyRow(7, 'لا توجد فواتير في هذه الفترة'));
    });
  }

  function openBill() {
    state.billDraft = [];
    state.billLineSeq = 1;
    state.billSearch = '';
    $('#billCustomer').val('');
    $('#billPhone').val('');
    $('#billDate').val(todayStr());
    $('#billStatusSel').val('Paid');
    $('#billPay').val('Cash');
    $('#billDiscount').val('');
    $('#billItemQ').val('');
    paintCurrencyTags();
    function ready() { paintBillItems(); paintBillLines(); openOverlay('#ovBill'); }
    if (state.items.length) {
      ready();
    } else {
      call('items.php', { action: 'list' }, function (res) { state.items = res.items; ready(); });
    }
  }

  /* ---- invoice lines (cart) ---------------------------------------------
     Every click on «إضافة» pushes a line of its own, so the same item can be
     sold twice at two different prices and the printed invoice shows both. */

  function billLineByUid(uid) {
    var found = null;
    state.billDraft.forEach(function (ln) { if (Number(ln.uid) === Number(uid)) { found = ln; } });
    return found;
  }

  /** Units of one item already added to the invoice. */
  function billUsedQty(itemId) {
    var sum = 0;
    state.billDraft.forEach(function (ln) {
      if (Number(ln.item_id) === Number(itemId)) { sum += num(ln.qty); }
    });
    return sum;
  }

  /** Units still free to sell: stock minus what is already on the invoice. */
  function billAvailable(itemId) {
    var p = itemById(itemId);
    return p ? num(p.qty) - billUsedQty(itemId) : 0;
  }

  /** The price used for a line: an emptied field falls back to the listed price. */
  function billLinePriceValue(ln) {
    if (ln.price !== '' && ln.price !== null && ln.price !== undefined) { return num(ln.price); }
    var p = itemById(ln.item_id);
    return num(p ? p.price : 0);
  }

  /**
   * Unit profit of a line = the item profit + whatever was added to (or taken
   * from) its listed price: سعر الإضافة يُضاف على الربح.
   */
  function billLineProfit(ln) {
    var p = itemById(ln.item_id);
    if (!p) { return 0; }
    return Math.round((num(p.profit) + (billLinePriceValue(ln) - num(p.price))) * 100) / 100;
  }

  /** Add one unit of a stock item as a new line (price defaults to the listed one). */
  function billAdd(itemId) {
    var p = itemById(itemId);
    if (!p) { return; }
    if (billAvailable(itemId) <= 0) {
      toast('المتوفر من «' + p.name + '» هو ' + p.qty + ' وحدة — كلها مضافة للفاتورة', 'warn');
      return;
    }
    state.billDraft.push({ uid: state.billLineSeq++, item_id: Number(itemId), qty: 1, price: num(p.price) });
    paintBillLines();
    paintBillItems();
  }

  function billLineStep(uid, d) {
    var ln = billLineByUid(uid);
    if (!ln) { return; }
    if (d > 0 && billAvailable(ln.item_id) <= 0) {
      var p = itemById(ln.item_id);
      toast('المتوفر ' + (p ? p.qty : 0) + ' وحدة فقط', 'warn');
      return;
    }
    ln.qty = Math.max(0, num(ln.qty) + d);
    if (ln.qty === 0) { billLineRemove(uid); return; }
    paintBillLines();
    paintBillItems();
  }

  /** Price typed for one line: no full repaint, so the caret stays in place. */
  function billLinePrice(uid, value) {
    var ln = billLineByUid(uid);
    if (!ln) { return; }
    ln.price = value === '' ? '' : num(value);
    $('#blAmt' + uid).text(money(billLinePriceValue(ln) * num(ln.qty)));
    $('#blNote' + uid).text('ربح البند ' + money(billLineProfit(ln)) + ' للوحدة');
    billTotals();
  }

  function billLineRemove(uid) {
    state.billDraft = state.billDraft.filter(function (ln) { return Number(ln.uid) !== Number(uid); });
    paintBillLines();
    paintBillItems();
  }

  function paintBillItems() {
    var q = state.billSearch.toLowerCase();
    var list = state.items.filter(function (p) {
      return !q || (p.name + ' ' + p.category).toLowerCase().indexOf(q) >= 0;
    });
    $('#billItems').html(list.length ? list.map(function (p) {
      var used = billUsedQty(p.id);
      var free = num(p.qty) - used;
      return '<div class="ni-row">' + tile(p.name, p.category_color) +
        '<div class="ni-info"><b>' + esc(p.name) + '</b><small>' + esc(p.category) + ' · ' + money(p.price) +
        (num(p.wholesale) > 0 ? ' · جملة ' + money(p.wholesale) : '') +
        ' · متوفر ' + (free > 0 ? free : 0) + (used > 0 ? ' (في الفاتورة ' + used + ')' : '') +
        '</small></div>' +
        '<button class="btn btn-soft ni-add" onclick="billAdd(' + p.id + ')">' +
          '<svg class="ic" style="width:14px;height:14px"><use href="#i-plus"/></svg>إضافة</button></div>';
    }).join('') : '<div class="empty-sm">لا توجد أصناف مطابقة للبحث</div>');
  }

  function paintBillLines() {
    var lines = state.billDraft;
    var units = 0;
    lines.forEach(function (ln) { units += num(ln.qty); });
    $('#billLinesCount').text(lines.length ? lines.length + ' بند · ' + units + ' قطعة' : 'لا بنود بعد');
    $('#billLines').html(lines.length ? lines.map(function (ln) {
      var p = itemById(ln.item_id) || { name: '—' };
      return '<div class="bl-row">' +
        '<div class="bl-t"><b>' + esc(p.name) + '</b>' +
          '<small id="blNote' + ln.uid + '">ربح البند ' + money(billLineProfit(ln)) + ' للوحدة</small></div>' +
        '<div class="bl-f">' +
          '<span class="stp"><button onclick="billLineStep(' + ln.uid + ',-1)" aria-label="خصم">−</button>' +
            '<b>' + ln.qty + '</b>' +
            '<button onclick="billLineStep(' + ln.uid + ',1)" aria-label="إضافة">+</button></span>' +
          '<input class="inp bl-price" type="number" min="0" step="0.01" value="' + billLinePriceValue(ln) + '" ' +
            'oninput="billLinePrice(' + ln.uid + ',this.value)" aria-label="سعر الوحدة">' +
          '<b class="bl-amt" id="blAmt' + ln.uid + '">' + money(billLinePriceValue(ln) * num(ln.qty)) + '</b>' +
          '<button class="icon-btn sm" title="حذف البند" onclick="billLineRemove(' + ln.uid + ')">' +
            '<svg class="ic" style="width:15px;height:15px"><use href="#i-trash"/></svg></button>' +
        '</div></div>';
    }).join('') : '<div class="empty-sm">لم تُضف أي قطعة بعد — اختر من قائمة المخزون بالأعلى</div>');
    billTotals();
  }

  /* The discount never lowers the amount due (total = subtotal + tax); it only
     cuts the profit kept for the internal reports (see api/bootstrap.php). */
  function billTotals() {
    var sub = 0, profit = 0;
    state.billDraft.forEach(function (ln) {
      sub    += billLinePriceValue(ln) * num(ln.qty);
      profit += billLineProfit(ln) * num(ln.qty);
    });
    var disc = Math.min(num($('#billDiscount').val()), sub);
    var tax = sub * num(state.settings.tax) / 100;
    $('#billSub').text(money(sub));
    $('#billDisc').text(money(disc));
    $('#billTax').text(money(tax));
    $('#billTotal').text(money(sub + tax));
    // the discount comes out of the profit only (see api/bootstrap.php)
    $('#billProfitHint').text('الربح المتوقع: ' + money(profit - disc));
  }

  function createBill() {
    var rows = state.billDraft.map(function (ln) {
      return {
        item_id: Number(ln.item_id), qty: num(ln.qty),
        price: billLinePriceValue(ln), profit: billLineProfit(ln)
      };
    });
    var customer = $.trim($('#billCustomer').val());
    if (!rows.length) { toast('أضف صنفاً واحداً على الأقل إلى الفاتورة', 'warn'); return; }
    if (!customer) { toast('اسم العميل مطلوب', 'warn'); return; }

    call('invoices.php', {
      action: 'create', customer: customer, phone: $('#billPhone').val(), entry_date: $('#billDate').val(),
      status: $('#billStatusSel').val(), pay_method: $('#billPay').val(), discount: $('#billDiscount').val(),
      tax_rate: num(state.settings.tax), items: JSON.stringify(rows)
    }, function (res) {
      state.items = res.items;
      closeOverlay('#ovBill');
      toast(res.message);
      openPaperInvoice(res.invoice);
      rerender();
    });
  }

  function setBillStatus(id) {
    call('invoices.php', { action: 'set_status', id: id, status: 'Paid', pay_method: 'Cash' }, function (res) {
      toast(res.message);
      rerender();
    });
  }

  function deleteBill(id) {
    if (!window.confirm('هل تريد حذف هذه الفاتورة؟ ستُعاد الكميات المباعة إلى المخزون.')) { return; }
    call('invoices.php', { action: 'delete', id: id }, function (res) {
      state.items = res.items;
      toast(res.message);
      rerender();
    });
  }

  /* ======================================================================
     paper previews + printing
     ====================================================================== */
  function openPaper(title, bodyHtml, footerHtml) {
    $('#paperModal').html(
      '<div class="modal-h"><b>' + esc(title) + '</b>' +
        '<button class="icon-btn" onclick="closeOverlay(\'#ovPaper\')" aria-label="Close"><svg class="ic"><use href="#i-x"/></svg></button></div>' +
      '<div class="modal-b">' + bodyHtml + '</div>' +
      '<div class="modal-f">' + footerHtml + '</div>');
    openOverlay('#ovPaper');
  }

  function billPaperHtml(inv) {
    var s = state.settings;
    var stc = { Paid: ['#16a34a', '#e8f7ee'], Pending: ['#d97706', '#fdf1df'], Overdue: ['#e5484d', '#fdeaea'] }[inv.status] ||
              ['#697089', '#f1f2f6'];
    var rows = (inv.items || []).map(function (l) {
      return '<tr><td><b>' + esc(l.name) + '</b>' +
        (l.category ? '<div class="p-row-note">' + esc(l.category) + '</div>' : '') + '</td>' +
        '<td style="text-align:center">' + l.qty + '</td>' +
        '<td class="r">' + money(l.price) + '</td>' +
        '<td class="r"><b>' + money(l.amount) + '</b></td></tr>';
    }).join('');

    return '<div class="pv-head">' +
      '<div class="pv-shop"><div style="display:flex;gap:10px;align-items:center;margin-bottom:8px">' +
        '<div class="logo-tile" style="width:34px;height:34px;border-radius:10px"><svg class="ic" style="width:17px;height:17px"><use href="#i-phone"/></svg></div>' +
        '<b>' + esc(s.shop) + '</b></div>' +
        '<span>' + esc(s.addr) + '</span><span class="num" dir="ltr">' + esc(s.phone) + '</span><span>' + esc(s.email) + '</span></div>' +
      '<div class="pv-meta"><div class="inword">فاتورة</div>' +
        '<b style="font-size:14px">' + esc(inv.code) + '</b>' +
        '<div style="color:#9aa0b5;font-size:12px;margin-top:2px">' + fmtDate(inv.entry_date) + '</div><br>' +
        '<span class="pv-stamp" style="color:' + stc[0] + ';background:' + stc[1] + '">' + esc(statusLabel(inv.status)) + '</span></div>' +
      '</div>' +
      '<div class="pv-grid">' +
        '<div><div class="pv-lbl">العميل</div><b>' + esc(inv.customer) + '</b>' +
          '<div class="num" style="color:#697089;font-size:12.5px" dir="ltr">' + esc(inv.phone || '—') + '</div></div>' +
        '<div style="text-align:left"><div class="pv-lbl">طريقة الدفع</div><b>' + esc(payLabel(inv.pay_method)) + '</b>' +
          '<div style="color:#697089;font-size:12.5px">يُستحق فور الاستلام</div></div>' +
      '</div>' +
      '<table class="pv-items"><thead><tr><th>الصنف</th><th style="text-align:center">الكمية</th>' +
        '<th class="r">السعر</th><th class="r">الإجمالي</th></tr></thead><tbody>' +
        (rows || '<tr><td colspan="4" style="color:#9aa0b5">لا توجد بنود</td></tr>') + '</tbody></table>' +
      '<div class="pv-tot">' +
        '<div class="trow"><span>المجموع</span><b>' + money(inv.subtotal) + '</b></div>' +
        (num(inv.discount) > 0
          ? '<div class="trow"><span>الخصم (لا يُخصم من الإجمالي)</span><b>' + money(inv.discount) + '</b></div>'
          : '') +
        '<div class="trow"><span>الضريبة (' + num(inv.tax_rate) + '%)</span><b>' + money(inv.tax) + '</b></div>' +
        '<div class="trow grand"><span>الإجمالي المستحق</span><span>' + money(inv.total) + '</span></div>' +
      '</div>' +
      '<div class="pv-foot"><div><div class="pv-lbl">ملاحظات</div>' +
        '<div style="color:#697089;font-size:12px">' + esc(s.invoice_note || '') + '</div></div>' +
        '<div style="text-align:left"><div class="barcode"></div>' +
        '<div style="font-size:10.5px;letter-spacing:.2em;color:#9aa0b5;margin-top:5px">' + esc(inv.code) + '</div></div></div>';
  }

  function payLabel(p) {
    return { Cash: 'نقدي', Card: 'بطاقة', Transfer: 'تحويل' }[p] || p || '—';
  }

  function openPaperInvoice(inv) {
    openPaper('فاتورة ' + inv.code, '<div class="paper">' + billPaperHtml(inv) + '</div>',
      '<button class="btn btn-outline" onclick="closeOverlay(\'#ovPaper\')">إغلاق</button>' +
      '<button class="btn btn-primary" onclick="printBill(' + inv.id + ')">' +
      '<svg class="ic"><use href="#i-printer"/></svg>طباعة / PDF</button>');
  }

  function openBillPaper(id) {
    call('invoices.php', { action: 'get', id: id }, function (res) { openPaperInvoice(res.invoice); });
  }

  function printHTML(html) {
    $('#printRoot').html(html);
    $('body').addClass('print-mode');
    setTimeout(function () { window.print(); }, 80);
  }
  $(window).on('afterprint', function () {
    $('body').removeClass('print-mode');
    $('#printRoot').empty();
  });

  function printBill(id) {
    call('invoices.php', { action: 'get', id: id }, function (res) {
      printHTML('<div class="paper">' + billPaperHtml(res.invoice) + '</div>');
    });
  }

  /* ======================================================================
     maintenance department (name, category, price, profit — dated)
     ====================================================================== */
  function renderMaintenance() {
    var f = state.filters.mnt, r = resolveRange('mnt', '#mntFrom', '#mntTo');
    $('#mntRangeLbl').text(r.label);
    call('maintenance.php', { action: 'list', q: f.q, category_id: f.cat, from: r.from, to: r.to }, function (res) {
      state.cache.maintenance = res.records;
      var s = res.summary;
      $('#mntCount').text(s.count + ' فاتورة صيانة في هذه الفترة · ' + money0(s.price) + ' إجمالي');
      $('#mntStats').html(
        statBox(s.count, 'الفواتير') +
        statBox(money0(s.price), 'إيراد الصيانة') +
        statBox(money0(s.profit), 'الأرباح', 'green') +
        statBox(money0(s.avg), 'متوسط الفاتورة')
      );
      navCounts({ maintenance: s.count });

      $('#mntBody').html(res.records.length ? res.records.map(function (m) {
        return '<tr class="rowlink" onclick="openMntPaper(' + m.id + ')">' +
          '<td class="no-label"><b>' + esc(m.code) + '</b></td>' +
          '<td data-l="التاريخ"><span class="mono">' + fmtDate(m.entry_date) + '</span></td>' +
          '<td data-l="العمل / العميل"><div class="p-cell" style="min-width:0">' + tile(m.name, m.category_color, 32) +
            '<div style="min-width:0"><b style="font-size:13px">' + esc(m.name) + '</b>' +
            '<small>' + esc(m.note || '—') + '</small></div></div></td>' +
          '<td data-l="التصنيف"><span class="tag" style="color:' + (m.category_color || '#6153f4') + '"><i></i>' + esc(m.category) + '</span></td>' +
          '<td data-l="السعر" class="right"><b>' + money(m.price) + '</b></td>' +
          '<td data-l="الربح" class="right"><span class="profit">' + money(m.profit) + '</span></td>' +
          '<td data-l="" class="right"><div class="row-actions">' +
            '<button class="icon-btn sm" title="عرض الفاتورة" onclick="event.stopPropagation();openMntPaper(' + m.id + ')"><svg class="ic" style="width:15px;height:15px"><use href="#i-eye"/></svg></button>' +
            '<button class="icon-btn sm" title="طباعة الفاتورة" onclick="event.stopPropagation();printMaintenance(' + m.id + ')"><svg class="ic" style="width:15px;height:15px"><use href="#i-printer"/></svg></button>' +
            '<button class="icon-btn sm" title="تعديل" onclick="event.stopPropagation();openMaintenance(' + m.id + ')"><svg class="ic" style="width:15px;height:15px"><use href="#i-edit"/></svg></button>' +
            '<button class="icon-btn sm" title="حذف" onclick="event.stopPropagation();deleteMaintenance(' + m.id + ')"><svg class="ic" style="width:15px;height:15px"><use href="#i-trash"/></svg></button>' +
          '</div></td></tr>';
      }).join('') : emptyRow(7, 'لا توجد فواتير صيانة في هذه الفترة'));
    });
  }

  function openMaintenance(id) {
    var m = null;
    (state.cache.maintenance || []).forEach(function (r) { if (Number(r.id) === Number(id)) { m = r; } });
    $('#mntModalTitle').text(m ? 'تعديل فاتورة صيانة ' + m.code : 'فاتورة صيانة جديدة');
    $('#mntId').val(m ? m.id : '');
    $('#mntName').val(m ? m.name : '');
    $('#mntCatSel').html(catOptions('maintenance', m ? m.category_id : '', '— بدون تصنيف —'));
    $('#mntPrice').val(m ? m.price : '');
    $('#mntProfit').val(m ? m.profit : '');
    $('#mntDate').val(m ? m.entry_date : todayStr());
    $('#mntNote').val(m ? m.note : '');
    paintCurrencyTags();
    openOverlay('#ovMnt');
  }

  function saveMaintenance() {
    call('maintenance.php', {
      action: 'save', id: $('#mntId').val() || '', name: $('#mntName').val(),
      category_id: $('#mntCatSel').val(), price: $('#mntPrice').val(), profit: $('#mntProfit').val(),
      entry_date: $('#mntDate').val(), note: $('#mntNote').val()
    }, function (res) {
      closeOverlay('#ovMnt');
      toast(res.message);
      rerender();
    });
  }

  function deleteMaintenance(id) {
    if (!window.confirm('هل تريد حذف فاتورة الصيانة هذه؟')) { return; }
    call('maintenance.php', { action: 'delete', id: id }, function (res) {
      toast(res.message);
      rerender();
    });
  }

  function maintenancePaperHtml(m) {
    var s = state.settings;
    return '<div class="pv-head">' +
      '<div class="pv-shop"><div style="display:flex;gap:10px;align-items:center;margin-bottom:8px">' +
        '<div class="logo-tile" style="width:34px;height:34px;border-radius:10px"><svg class="ic" style="width:17px;height:17px"><use href="#i-wrench"/></svg></div>' +
        '<b>' + esc(s.shop) + ' · قسم الصيانة</b></div>' +
        '<span>' + esc(s.addr) + '</span><span class="num" dir="ltr">' + esc(s.phone) + '</span><span>' + esc(s.email) + '</span></div>' +
      '<div class="pv-meta"><div class="inword">صيانة</div>' +
        '<b style="font-size:14px">' + esc(m.code) + '</b>' +
        '<div style="color:#9aa0b5;font-size:12px;margin-top:2px">' + fmtDate(m.entry_date) + '</div><br>' +
        '<span class="pv-stamp" style="color:#d97706;background:#fdf1df">فاتورة صيانة</span></div></div>' +
      '<div class="pv-grid">' +
        '<div><div class="pv-lbl">العميل / العمل</div><b>' + esc(m.name) + '</b>' +
          '<div style="color:#697089;font-size:12.5px">' + esc(m.note || 'خدمة وقطع غيار') + '</div></div>' +
        '<div style="text-align:left"><div class="pv-lbl">التصنيف</div><b>' + esc(m.category) + '</b>' +
          '<div style="color:#697089;font-size:12.5px">قسم الصيانة</div></div></div>' +
      '<table class="pv-items"><thead><tr><th>الوصف</th><th class="r">المبلغ</th></tr></thead><tbody>' +
        '<tr><td><b>' + esc(m.name) + '</b><div class="p-row-note">' + esc(m.category) +
        ' — أعمال صيانة</div></td><td class="r"><b>' + money(m.price) + '</b></td></tr></tbody></table>' +
      '<div class="pv-tot"><div class="trow grand"><span>الإجمالي المستحق</span><span>' + money(m.price) + '</span></div></div>' +
      '<div class="pv-foot"><div><div class="pv-lbl">ملاحظات</div>' +
        '<div style="color:#697089;font-size:12px">' + esc(s.maintenance_note || '') + '</div></div>' +
        '<div style="text-align:left"><div class="barcode"></div>' +
        '<div style="font-size:10.5px;letter-spacing:.2em;color:#9aa0b5;margin-top:5px">' + esc(m.code) + '</div></div></div>';
  }

  function openMntPaper(id) {
    call('maintenance.php', { action: 'get', id: id }, function (res) {
      openPaper('صيانة ' + res.record.code, '<div class="paper">' + maintenancePaperHtml(res.record) + '</div>',
        '<button class="btn btn-outline" onclick="closeOverlay(\'#ovPaper\')">إغلاق</button>' +
        '<button class="btn btn-primary" onclick="printMaintenance(' + res.record.id + ')">' +
        '<svg class="ic"><use href="#i-printer"/></svg>طباعة / PDF</button>');
    });
  }

  function printMaintenance(id) {
    call('maintenance.php', { action: 'get', id: id }, function (res) {
      printHTML('<div class="paper">' + maintenancePaperHtml(res.record) + '</div>');
    });
  }

  /* ======================================================================
     expenses — shop bills (Wi-Fi, electricity, water, rent, salaries…)
     NOTE: the table and the API keep the name "bills" (api/bills.php) while
     the UI calls this section «المصروفات»: the sales invoices already use the
     bill* namespace in this file.
     ====================================================================== */
  var BILL_KINDS = ['إنترنت', 'كهرباء', 'ماء', 'إيجار', 'رواتب', 'صيانة عامة', 'أخرى'];

  function expById(id) {
    var found = null;
    (state.cache.billsExp || []).forEach(function (r) { if (Number(r.id) === Number(id)) { found = r; } });
    return found;
  }

  /**
   * Expense types offered in the modal and the filter: the categories switched
   * on for «المصروفات» first, then the built-in types, then any legacy value.
   */
  function expKindList(extra) {
    var list = [];
    state.categories.forEach(function (c) {
      if (catInSection(c, 'expense') && list.indexOf(c.name) < 0) { list.push(c.name); }
    });
    BILL_KINDS.forEach(function (k) { if (list.indexOf(k) < 0) { list.push(k); } });
    (extra || []).forEach(function (k) { if (k && list.indexOf(k) < 0) { list.push(k); } });
    return list;
  }

  function expKindOptions(selected) {
    return expKindList(selected ? [selected] : []).map(function (k) {
      return '<option value="' + esc(k) + '"' + (k === selected ? ' selected' : '') + '>' + esc(k) + '</option>';
    }).join('');
  }

  function renderExpenses() {
    var r = resolveRange('exp', '#expFrom', '#expTo');
    $('#expRangeLbl').text(r.label);
    call('bills.php', {
      action: 'list', q: state.filters.exp.q, kind: state.filters.exp.kind, from: r.from, to: r.to
    }, function (res) {
      state.cache.billsExp = res.records;
      var s = res.summary;

      var kinds = expKindList((res.kinds || []).concat((res.records || []).map(function (b) { return b.kind; })));
      var opts = '<option value="all">كل الأنواع</option>';
      kinds.forEach(function (k) {
        opts += '<option value="' + esc(k) + '"' +
          (String(state.filters.exp.kind) === String(k) ? ' selected' : '') + '>' + esc(k) + '</option>';
      });
      $('#expKind').html(opts);

      $('#expCount').text(s.count + ' مصروف في هذه الفترة · ' + money0(s.total) + ' إجمالي' +
        (s.top ? ' · أكبر نوع: ' + s.top : ''));
      $('#expStats').html(
        statBox(s.count, 'عدد المصروفات') +
        statBox(money0(s.total), 'إجمالي المصروفات', 'red') +
        statBox(money0(s.avg), 'متوسط المصروف') +
        statBox(s.top || '—', 'أكبر نوع')
      );
      navCounts({ expenses: s.count });

      $('#expBody').html(res.records.length ? res.records.map(function (b) {
        return '<tr>' +
          '<td class="no-label"><b>' + esc(b.code) + '</b></td>' +
          '<td data-l="التاريخ"><span class="mono">' + fmtDate(b.entry_date) + '</span></td>' +
          '<td data-l="المصروف"><div class="p-cell" style="min-width:0">' + tile(b.name, '#ef4444', 32) +
            '<div style="min-width:0"><b style="font-size:13px">' + esc(b.name) + '</b>' +
            '<small>' + esc(b.note || '—') + '</small></div></div></td>' +
          '<td data-l="النوع"><span class="tag" style="color:' + kindColor(b.kind) + '"><i></i>' + esc(b.kind || 'أخرى') + '</span></td>' +
          '<td data-l="المبلغ" class="right"><span class="expense">' + money(b.amount) + '</span></td>' +
          '<td data-l="" class="right"><div class="row-actions">' +
            '<button class="icon-btn sm" title="تعديل" onclick="openExpense(' + b.id + ')"><svg class="ic" style="width:15px;height:15px"><use href="#i-edit"/></svg></button>' +
            '<button class="icon-btn sm" title="حذف" onclick="deleteExpense(' + b.id + ')"><svg class="ic" style="width:15px;height:15px"><use href="#i-trash"/></svg></button>' +
          '</div></td></tr>';
      }).join('') : emptyRow(6, 'لا مصروفات في هذه الفترة'));
    });
  }

  function openExpense(id) {
    var b = id ? expById(id) : null;
    if (id && !b) {
      call('bills.php', { action: 'get', id: id }, function (res) { fillExpense(res.record); });
      return;
    }
    fillExpense(b);
  }

  function fillExpense(b) {
    $('#expModalTitle').text(b ? 'تعديل مصروف ' + b.code : 'مصروف جديد');
    $('#expId').val(b ? b.id : '');
    $('#expName').val(b ? b.name : '');
    $('#expKindSel').html(expKindOptions(b && b.kind ? b.kind : ''));
    $('#expAmount').val(b ? b.amount : '');
    $('#expDate').val(b ? b.entry_date : todayStr());
    $('#expNote').val(b ? b.note : '');
    paintCurrencyTags();
    openOverlay('#ovExp');
  }

  function saveExpense() {
    call('bills.php', {
      action: 'save', id: $('#expId').val() || '', name: $('#expName').val(), kind: $('#expKindSel').val(),
      amount: $('#expAmount').val(), entry_date: $('#expDate').val(), note: $('#expNote').val()
    }, function (res) {
      closeOverlay('#ovExp');
      toast(res.message);
      rerender();
    });
  }

  function deleteExpense(id) {
    var b = expById(id);
    if (!window.confirm('هل تريد حذف المصروف «' + (b ? b.name : '') + '»؟')) { return; }
    call('bills.php', { action: 'delete', id: id }, function (res) {
      toast(res.message);
      rerender();
    });
  }

  /** Printable expense report for the filters currently on screen. */
  function printExpenses() {
    var r = resolveRange('exp', '#expFrom', '#expTo');
    call('bills.php', {
      action: 'list', q: state.filters.exp.q, kind: state.filters.exp.kind, from: r.from, to: r.to
    }, function (res) {
      var s = res.summary, rows = res.records || [], total = 0;
      rows.forEach(function (b) { total += num(b.amount); });
      printHTML('<div class="paper">' +
        '<div class="pv-head"><div class="pv-shop"><b>' + esc(state.settings.shop) + '</b>' +
          '<span>كشف المصروفات · ' + esc(r.label) + '</span></div>' +
          '<div class="pv-meta"><div class="inword">مصروفات</div><b style="font-size:13px">' +
          rows.length + ' فاتورة</b></div></div>' +
        '<div class="rp-sum">' + paperStat(rows.length, 'المصروفات') + paperStat(money(total), 'الإجمالي') +
          paperStat(money(s.avg), 'المتوسط') + paperStat(s.top || '—', 'أكبر نوع') + '</div>' +
        '<table class="pv-items"><thead><tr><th>#</th><th>الفاتورة</th><th>التاريخ</th><th>المصروف</th>' +
          '<th>النوع</th><th class="r">المبلغ</th></tr></thead><tbody>' +
          (rows.map(function (b, i) {
            return '<tr><td>' + (i + 1) + '</td><td>' + esc(b.code) + '</td><td>' + fmtDate(b.entry_date) + '</td>' +
              '<td><b>' + esc(b.name) + '</b>' + (b.note ? '<div class="p-row-note">' + esc(b.note) + '</div>' : '') +
              '</td><td>' + esc(b.kind || 'أخرى') + '</td><td class="r">' + money(b.amount) + '</td></tr>';
          }).join('') || '<tr><td colspan="6" style="color:#9aa0b5">لا مصروفات في هذه الفترة</td></tr>') +
        '</tbody></table>' +
        '<div class="pv-tot"><div class="trow grand"><span>إجمالي المصروفات</span><span>' + money(total) + '</span></div></div>' +
        '<div class="pv-foot"><div style="color:#697089;font-size:12px">' + esc(state.settings.shop) +
          ' · مستند داخلي</div><div><div class="barcode"></div>' +
          '<div style="font-size:10.5px;letter-spacing:.2em;color:#9aa0b5;margin-top:5px">مصروفات-' +
          new Date().getFullYear() + '</div></div></div></div>');
    });
  }

  function printStockList() {
    function render() {
      var s = state.settings, units = 0, value = 0;
      state.items.forEach(function (p) { units += num(p.qty); value += num(p.qty) * num(p.price); });
      printHTML('<div class="paper">' +
        '<div class="pv-head"><div class="pv-shop"><b>' + esc(s.shop) + '</b>' +
          '<span>قائمة المخزون · بتاريخ ' + fmtDate(todayStr()) + '</span></div>' +
          '<div class="pv-meta"><div class="inword">مخزون</div><b style="font-size:13px">' +
          state.items.length + ' صنف</b></div></div>' +
        '<div class="rp-sum">' + paperStat(state.items.length, 'الأصناف') + paperStat(units, 'الوحدات') +
          paperStat(money(value), 'قيمة المخزون') + '</div>' +
        '<table class="pv-items"><thead><tr><th>#</th><th>الصنف</th><th>التصنيف</th><th class="r">سعر البيع</th>' +
          '<th class="r">سعر الجملة</th><th class="r">الكمية</th><th class="r">القيمة</th></tr></thead><tbody>' +
          (state.items.map(function (p, i) {
            return '<tr><td>' + (i + 1) + '</td><td><b>' + esc(p.name) + '</b></td><td>' + esc(p.category) + '</td>' +
              '<td class="r">' + money(p.price) + '</td>' +
              '<td class="r">' + (num(p.wholesale) > 0 ? money(p.wholesale) : '—') + '</td>' +
              '<td class="r"><b>' + p.qty + '</b></td>' +
              '<td class="r">' + money(num(p.qty) * num(p.price)) + '</td></tr>';
          }).join('') || '<tr><td colspan="7" style="color:#9aa0b5">لا توجد أصناف في المخزون</td></tr>') +
        '</tbody></table>' +
        '<div class="pv-foot"><div style="color:#697089;font-size:12px">' + esc(s.shop) + ' · مستند داخلي</div>' +
          '<div><div class="barcode"></div><div style="font-size:10.5px;letter-spacing:.2em;color:#9aa0b5;margin-top:5px">مخزون-' +
          new Date().getFullYear() + '</div></div></div></div>');
    }
    if (state.items.length) {
      render();
    } else {
      call('items.php', { action: 'list' }, function (res) { state.items = res.items; render(); });
    }
  }

  /* ======================================================================
     suppliers — فواتير الموردين (اسم المورد + القطع: مستحق / مدفوع)
     NOTE: this ledger is deliberately kept OUT of the revenue / profit
     numbers — nothing here is sent to the dashboard or to any sales report.
     ====================================================================== */
  function supById(id) {
    var found = null;
    state.cache.suppliers.forEach(function (s) { if (Number(s.id) === Number(id)) { found = s; } });
    return found;
  }
  function supPartById(id) {
    var found = null;
    state.cache.supParts.forEach(function (p) { if (Number(p.id) === Number(id)) { found = p; } });
    return found;
  }
  /** Supplier options for a select ('' → nothing selected). */
  function supOptions(selected, emptyLabel) {
    var html = emptyLabel ? '<option value="">' + esc(emptyLabel) + '</option>' : '';
    state.cache.suppliers.forEach(function (s) {
      html += '<option value="' + s.id + '"' + (Number(selected) === Number(s.id) ? ' selected' : '') + '>' +
        esc(s.name) + '</option>';
    });
    return html;
  }
  function supStatusBadge(p) {
    return p.status === 'Paid'
      ? '<span class="badge b-green">مدفوع</span>'
      : '<span class="badge b-amber">مستحق</span>';
  }

  function renderSuppliers() {
    var f = state.filters.sup, r = resolveRange('sup', '#supFrom', '#supTo');
    $('#supRangeLbl').text(r.label);
    call('suppliers.php', {
      action: 'list', q: f.q, sq: f.sq, supplier_id: f.supplier, status: f.status, from: r.from, to: r.to
    }, function (res) {
      state.cache.supParts  = res.records || [];
      state.cache.suppliers = res.suppliers || [];
      var s = res.summary, o = res.overall;

      /* supplier filter (and its «قطعه» shortcut on the cards) */
      var $f = $('#supFilter');
      $f.html('<option value="all">كل الموردين</option>' + supOptions(f.supplier));
      if ($f.find('option[value="' + f.supplier + '"]').length) {
        $f.val(String(f.supplier));
      } else {
        $f.val('all');
        f.supplier = 'all';
      }

      navCounts({ suppliers: o.parts });
      $('#supCount').text(o.suppliers + ' مورد · ' + o.parts + ' قطعة مسجّلة · إجمالي مستحق ' +
        money0(o.due) + ' · مدفوع ' + money0(o.paid));
      $('#supStats').html(
        statBox(s.count, 'قطع الفترة') +
        statBox(money0(s.due), 'مستحق في الفترة', 'red') +
        statBox(money0(s.paid), 'مدفوع في الفترة', 'green') +
        statBox(money0(s.total), 'إجمالي الفترة')
      );

      $('#supGrid').html(res.suppliers.length ? res.suppliers.map(function (x) {
        return '<div class="sup-card">' +
          '<div class="cc-top"><span class="cat-dot" style="background:#0ea5e9">' + esc(firstChar(x.name)) + '</span>' +
            '<div style="min-width:0"><div class="cc-name">' + esc(x.name) + '</div>' +
            '<div class="cc-meta">' + (x.phone ? esc(x.phone) + ' · ' : '') + x.parts + ' قطعة' +
            (x.note ? ' · ' + esc(x.note) : '') + '</div></div></div>' +
          '<div class="cat-stats">' +
            '<div class="cat-stat"><b>' + money0(x.due) + '</b><span>مستحق</span></div>' +
            '<div class="cat-stat"><b>' + money0(x.paid) + '</b><span>مدفوع</span></div>' +
            '<div class="cat-stat"><b>' + money0(x.total) + '</b><span>الإجمالي</span></div>' +
          '</div>' +
          '<div class="cat-actions">' +
            '<button class="btn btn-soft" onclick="filterSupplier(' + x.id + ')">قطعه</button>' +
            '<button class="btn btn-outline" title="تعديل المورد" onclick="openSupplier(' + x.id + ')"><svg class="ic" style="width:14px;height:14px"><use href="#i-edit"/></svg>تعديل</button>' +
            '<button class="btn btn-danger" title="حذف المورد" onclick="deleteSupplier(' + x.id + ')"><svg class="ic" style="width:14px;height:14px"><use href="#i-trash"/></svg></button>' +
          '</div></div>';
      }).join('') : '<div class="empty-sm">لا يوجد موردون بعد — اضغط «مورد جديد» بالأعلى</div>');
      $('#supBody').html(res.records.length ? res.records.map(function (p) {
        return '<tr>' +
          '<td class="no-label"><b>' + esc(p.code) + '</b></td>' +
          '<td data-l="التاريخ"><span class="mono">' + fmtDate(p.entry_date) + '</span></td>' +
          '<td data-l="المورد"><b>' + esc(p.supplier) + '</b></td>' +
          '<td data-l="القطعة"><div class="p-cell" style="min-width:0">' + tile(p.name, '#0ea5e9', 32) +
            '<div style="min-width:0"><b style="font-size:13px">' + esc(p.name) + '</b>' +
            '<small>' + esc(p.note || '—') + '</small></div></div></td>' +
          '<td data-l="المبلغ" class="right"><b>' + money(p.amount) + '</b></td>' +
          '<td data-l="الحالة">' + supStatusBadge(p) + '</td>' +
          '<td data-l="" class="right"><div class="row-actions">' +
            '<button class="icon-btn sm" title="' + (p.status === 'Paid' ? 'إرجاع إلى مستحق' : 'تعليم كمدفوع') +
              '" onclick="toggleSupStatus(' + p.id + ')"><svg class="ic" style="width:15px;height:15px"><use href="#i-' +
              (p.status === 'Paid' ? 'refresh' : 'check') + '"/></svg></button>' +
            '<button class="icon-btn sm" title="تعديل" onclick="openSupBill(' + p.id + ')"><svg class="ic" style="width:15px;height:15px"><use href="#i-edit"/></svg></button>' +
            '<button class="icon-btn sm" title="حذف" onclick="deleteSupBill(' + p.id + ')"><svg class="ic" style="width:15px;height:15px"><use href="#i-trash"/></svg></button>' +
          '</div></td></tr>';
      }).join('') : emptyRow(7, 'لا توجد قطع مسجّلة في هذه الفترة'));
    });
  }

  /** Show the parts of one supplier (the «قطعه» button on a supplier card). */
  function filterSupplier(id) {
    state.filters.sup.supplier = String(id);
    renderSuppliers();
    var body = document.getElementById('supBody');
    if (body && body.scrollIntoView) { body.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
  }

  /* ---- suppliers: add / edit / delete (small form on the paper overlay) ---- */
  function openSupplier(id) {
    var x = id ? supById(id) : null;
    openPaper(x ? 'تعديل المورد «' + x.name + '»' : 'مورد جديد',
      '<div class="f-field"><label class="f-label">اسم المورد</label>' +
        '<input class="inp" id="supFormName" placeholder="مثال: محمد الاعور" value="' + (x ? esc(x.name) : '') + '"></div>' +
      '<div class="f-field"><label class="f-label">الهاتف (اختياري)</label>' +
        '<input class="inp num" id="supFormPhone" dir="ltr" value="' + (x ? esc(x.phone) : '') + '"></div>' +
      '<div class="f-field" style="margin-bottom:0"><label class="f-label">ملاحظة</label>' +
        '<input class="inp" id="supFormNote" placeholder="القطع التي يورّدها…" value="' + (x ? esc(x.note) : '') + '"></div>' +
      '<div class="switch-hint">سجل الموردين مستقل تماماً — لا يدخل في الإيرادات ولا في الأرباح.</div>',
      '<button class="btn btn-outline" onclick="closeOverlay(\'#ovPaper\')">إلغاء</button>' +
      '<button class="btn btn-primary" onclick="saveSupplier(' + (x ? x.id : '') + ')">' +
        '<svg class="ic"><use href="#i-check"/></svg>' + (x ? 'حفظ التعديل' : 'إضافة المورد') + '</button>');
    if (!x) { setTimeout(function () { $('#supFormName').focus(); }, 120); }
  }

  function saveSupplier(id) {
    var name = $.trim($('#supFormName').val() || '');
    if (!name) { toast('اسم المورد مطلوب', 'warn'); return; }
    call('suppliers.php', {
      action: 'save', id: id || '', name: name,
      phone: $('#supFormPhone').val(), note: $('#supFormNote').val()
    }, function (res) {
      closeOverlay('#ovPaper');
      toast(res.message);
      renderSuppliers();
    });
  }

  function deleteSupplier(id) {
    var x = supById(id);
    if (!x) { return; }
    if (Number(x.parts) > 0) {
      toast('لا يمكن حذف «' + x.name + '» — لديه ' + x.parts + ' قطعة مسجّلة', 'warn');
      return;
    }
    if (!window.confirm('هل تريد حذف المورد «' + x.name + '»؟')) { return; }
    call('suppliers.php', { action: 'delete', id: id }, function (res) {
      toast(res.message);
      renderSuppliers();
    });
  }

  /* ---- supplier parts: add / edit / delete / paid-due toggle ---- */
  function openSupBill(id) {
    var p = id ? supPartById(id) : null;
    if (id && !p) { return; }
    var pre = p ? p.supplier_id : ($('#supFilter').val() !== 'all' ? $('#supFilter').val() : '');
    $('#supModalTitle').text(p ? 'تعديل قطعة ' + p.code : 'قطعة مورد جديدة');
    $('#supBillId').val(p ? p.id : '');
    $('#supBillSupplier').html(supOptions(pre, state.cache.suppliers.length ? '' : 'أضف مورداً أولاً'));
    $('#supBillName').val(p ? p.name : '');
    $('#supBillAmount').val(p ? p.amount : '');
    $('#supBillStatus').val(p ? p.status : 'Due');
    $('#supBillDate').val(p ? p.entry_date : todayStr());
    $('#supBillNote').val(p ? p.note : '');
    paintCurrencyTags();
    openOverlay('#ovSupBill');
    if (!p) { setTimeout(function () { $('#supBillName').focus(); }, 120); }
  }

  function saveSupBill() {
    call('suppliers.php', {
      action: 'part_save', id: $('#supBillId').val() || '', supplier_id: $('#supBillSupplier').val(),
      name: $('#supBillName').val(), amount: $('#supBillAmount').val(), status: $('#supBillStatus').val(),
      entry_date: $('#supBillDate').val(), note: $('#supBillNote').val()
    }, function (res) {
      closeOverlay('#ovSupBill');
      toast(res.message);
      renderSuppliers();
    });
  }

  function toggleSupStatus(id) {
    call('suppliers.php', { action: 'part_status', id: id }, function (res) {
      toast(res.message);
      renderSuppliers();
    });
  }

  function deleteSupBill(id) {
    var p = supPartById(id);
    if (!window.confirm('هل تريد حذف «' + (p ? p.name : '') + '» من سجل المورد؟')) { return; }
    call('suppliers.php', { action: 'part_delete', id: id }, function (res) {
      toast(res.message);
      renderSuppliers();
    });
  }

  /** Printable statement of the supplier parts currently on screen. */
  function printSupBills() {
    var f = state.filters.sup, r = resolveRange('sup', '#supFrom', '#supTo');
    call('suppliers.php', {
      action: 'list', q: f.q, sq: f.sq, supplier_id: f.supplier, status: f.status, from: r.from, to: r.to
    }, function (res) {
      var s = res.summary, rows = res.records || [];
      var who = (f.supplier !== 'all' && supById(f.supplier)) ? supById(f.supplier).name : 'كل الموردين';
      printHTML('<div class="paper">' +
        '<div class="pv-head"><div class="pv-shop"><b>' + esc(state.settings.shop) + '</b>' +
          '<span>كشف فواتير الموردين · ' + esc(who) + ' · ' + esc(r.label) + '</span></div>' +
          '<div class="pv-meta"><div class="inword">موردين</div><b style="font-size:13px">' +
          rows.length + ' قطعة</b></div></div>' +
        '<div class="rp-sum">' + paperStat(rows.length, 'القطع') + paperStat(money(s.total), 'الإجمالي') +
          paperStat(money(s.due), 'مستحق') + paperStat(money(s.paid), 'مدفوع') + '</div>' +
        '<table class="pv-items"><thead><tr><th>#</th><th>الرقم</th><th>التاريخ</th><th>المورد</th>' +
          '<th>القطعة</th><th class="r">المبلغ</th><th>الحالة</th></tr></thead><tbody>' +
          (rows.map(function (p, i) {
            return '<tr><td>' + (i + 1) + '</td><td>' + esc(p.code) + '</td><td>' + fmtDate(p.entry_date) + '</td>' +
              '<td>' + esc(p.supplier) + '</td><td><b>' + esc(p.name) + '</b>' +
              (p.note ? '<div class="p-row-note">' + esc(p.note) + '</div>' : '') + '</td>' +
              '<td class="r">' + money(p.amount) + '</td><td>' + esc(p.status_label) + '</td></tr>';
          }).join('') || '<tr><td colspan="7" style="color:#9aa0b5">لا توجد قطع في هذه الفترة</td></tr>') +
        '</tbody></table>' +
        '<div class="pv-tot"><div class="trow grand"><span>إجمالي الفترة</span><span>' + money(s.total) + '</span></div>' +
          '<div class="trow"><span>منها مستحق</span><span>' + money(s.due) + '</span></div>' +
          '<div class="trow"><span>منها مدفوع</span><span>' + money(s.paid) + '</span></div></div>' +
        '<div class="pv-foot"><div style="color:#697089;font-size:12px">' + esc(state.settings.shop) +
          ' · مستند داخلي للموردين — لا يدخل في الإيرادات أو الأرباح</div><div><div class="barcode"></div>' +
          '<div style="font-size:10.5px;letter-spacing:.2em;color:#9aa0b5;margin-top:5px">موردين-' +
          new Date().getFullYear() + '</div></div></div></div>');
    });
  }

  /* ======================================================================
     categories (add / update / remove)
     ====================================================================== */
  var SWATCH = ['#6153f4', '#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6'];
  var catPick = '#6153f4';
  var catEditId = null;
  var catRows = [];   // rows drawn in the categories grid (may be a search result)

  /** Look a category up in the drawn grid first, then in the full list. */
  function catLookup(id) {
    var i;
    for (i = 0; i < catRows.length; i++) {
      if (Number(catRows[i].id) === Number(id)) { return catRows[i]; }
    }
    return catById(id);
  }

  function paintSwatches() {
    $('#catSwatches').html(SWATCH.map(function (c) {
      return '<button type="button" class="swatch' + (c === catPick ? ' on' : '') + '" style="background:' + c +
        '" data-c="' + c + '" aria-label="' + c + '"></button>';
    }).join(''));
  }

  /** Badges of the departments a category is switched on for. */
  function catSectionBadges(c) {
    var keys = catSections(c);
    if (!keys.length) {
      return '<div class="sec-badges"><span class="sec-badge none">لا يظهر في أي قسم</span></div>';
    }
    return '<div class="sec-badges">' + keys.map(function (k) {
      return '<span class="sec-badge">' + esc(SEC_LABELS[k] || k) + '</span>';
    }).join('') + '</div>';
  }

  function renderCategories() {
    paintSwatches();
    call('categories.php', { action: 'list', q: $('#catQ').val() || '' }, function (res) {
      var searching = $.trim($('#catQ').val() || '') !== '';
      catRows = res.categories;
      if (!searching) {
        // the full list stays in state — every section select reads it
        state.categories = catRows;
        navCounts({ categories: catRows.length });
      }

      var items = 0, units = 0, reps = 0, exps = 0;
      res.categories.forEach(function (c) {
        items += num(c.items); units += num(c.units); reps += num(c.maintenance); exps += num(c.expenses);
      });
      $('#catCount').text(res.categories.length + ' تصنيف · ' + items + ' صنف مخزون · ' + reps +
        ' سجل صيانة · ' + exps + ' مصروف');
      $('#catStats').html(
        statBox(res.categories.length, 'التصنيفات') +
        statBox(items, 'أصناف المخزون') +
        statBox(units, 'وحدات في المخزون') +
        statBox(reps, 'سجلات الصيانة')
      );

      $('#catGrid').html(res.categories.length ? res.categories.map(function (c) {
        return '<div class="cat-card">' +
          '<div class="cc-top"><span class="cat-dot" style="background:' + c.color + '">' + esc(firstChar(c.name)) + '</span>' +
            '<div style="min-width:0"><div class="cc-name">' + esc(c.name) + '</div>' +
            '<div class="cc-meta">' + c.billed + ' بند فاتورة · ' + c.expenses + ' مصروف · أُضيف ' + fmtDate(c.created_at) + '</div></div></div>' +
          catSectionBadges(c) +
          '<div class="cat-stats">' +
            '<div class="cat-stat"><b>' + c.items + '</b><span>أصناف</span></div>' +
            '<div class="cat-stat"><b>' + c.units + '</b><span>وحدات</span></div>' +
            '<div class="cat-stat"><b>' + c.maintenance + '</b><span>صيانة</span></div>' +
          '</div>' +
          '<div class="cat-actions">' +
            '<button class="btn btn-outline" onclick="editCategory(' + c.id + ')"><svg class="ic" style="width:14px;height:14px"><use href="#i-edit"/></svg>تعديل</button>' +
            '<button class="btn btn-danger" onclick="deleteCategory(' + c.id + ')"><svg class="ic" style="width:14px;height:14px"><use href="#i-trash"/></svg>حذف</button>' +
          '</div></div>';
      }).join('') : '<div class="empty-sm">لا توجد تصنيفات — أضف أول تصنيف من الأعلى</div>');
    });
  }

  function saveCategory() {
    var name     = $.trim($('#catName').val());
    var sections = catCheckedSections();
    if (!name) { toast('اسم التصنيف مطلوب', 'warn'); return; }
    if (!sections.length) {
      toast('فعّل قسماً واحداً على الأقل: المخزون أو الصيانة أو المصروفات', 'warn');
      return;
    }
    call('categories.php', {
      action: 'save', id: catEditId || '', name: name, color: catPick,
      use_inventory:   sections.indexOf('inventory') >= 0 ? 1 : 0,
      use_maintenance: sections.indexOf('maintenance') >= 0 ? 1 : 0,
      use_expense:     sections.indexOf('expense') >= 0 ? 1 : 0
    }, function (res) {
      state.categories = res.categories;
      toast(res.message);
      cancelCategoryEdit();
      loadCategories();
      renderCategories();
    });
  }

  function editCategory(id) {
    var c = catLookup(id);
    if (!c) { return; }
    catEditId = c.id;
    catPick = c.color || '#6153f4';
    $('#catName').val(c.name);
    catSectionInputs(catSections(c));
    $('#catFormTitle').text('تعديل التصنيف');
    $('#catSaveLbl').text('حفظ التعديل');
    $('#catCancelBtn').show();
    paintSwatches();
    $('#catName').focus();
  }

  function cancelCategoryEdit() {
    catEditId = null;
    $('#catName').val('');
    catSectionInputs();
    $('#catFormTitle').text('إضافة تصنيف');
    $('#catSaveLbl').text('إضافة تصنيف');
    $('#catCancelBtn').hide();
  }

  function deleteCategory(id) {
    var c = catLookup(id);
    if (!c) { return; }
    var used = num(c.items) + num(c.maintenance) + num(c.billed) + num(c.expenses);
    if (!used) {
      if (!window.confirm('هل تريد حذف التصنيف «' + c.name + '»؟')) { return; }
      call('categories.php', { action: 'delete', id: id }, function (res) {
        state.categories = res.categories;
        toast(res.message);
        loadCategories();
        renderCategories();
      });
      return;
    }
    var opts = state.categories.filter(function (x) { return Number(x.id) !== Number(id); }).map(function (x) {
      return '<option value="' + x.id + '">' + esc(x.name) + '</option>';
    }).join('');
    openPaper('حذف التصنيف «' + c.name + '»',
      '<p class="sub" style="margin-bottom:14px">هذا التصنيف مستخدم في ' + c.items + ' صنف مخزون و' +
        c.maintenance + ' سجل صيانة و' + c.billed + ' بند فاتورة و' + c.expenses +
        ' مصروف. هل تريد نقل هذه البيانات إلى تصنيف آخر ثم حذفه؟</p>' +
      (opts ? '<div class="f-field" style="margin-bottom:0"><label class="f-label">نقل البيانات إلى</label>' +
        '<select class="inp" id="moveTarget">' + opts + '</select></div>'
        : '<div class="empty-sm">أضف تصنيفاً آخر أولاً ثم أعد المحاولة.</div>'),
      '<button class="btn btn-outline" onclick="closeOverlay(\'#ovPaper\')">إلغاء</button>' +
      (opts ? '<button class="btn btn-danger" onclick="confirmCategoryDelete(' + id + ')">' +
        '<svg class="ic"><use href="#i-trash"/></svg>نقل وحذف</button>' : ''));
  }

  function confirmCategoryDelete(id) {
    call('categories.php', { action: 'delete', id: id, reassign: $('#moveTarget').val() }, function (res) {
      closeOverlay('#ovPaper');
      state.categories = res.categories;
      toast(res.message);
      loadCategories();
      renderCategories();
    });
  }

  /* ======================================================================
     settings
     ====================================================================== */
  function renderSettings() {
    var s = state.settings;
    $('#sName').val(s.shop);
    $('#sPhone').val(s.phone);
    $('#sEmail').val(s.email);
    $('#sAddr').val(s.addr);
    $('#sCurr').val(s.currency);
    $('#sTax').val(s.tax);
    $('#sLow').val(s.low_stock);
    $('#sInvNote').val(s.invoice_note);
    $('#sMntNote').val(s.maintenance_note);
    $('#thLight').toggleClass('active', $('html').attr('data-theme') !== 'dark');
    $('#thDark').toggleClass('active', $('html').attr('data-theme') === 'dark');
    loadBackups();
  }

  function saveSettings() {
    call('settings.php', {
      action: 'save', shop: $('#sName').val(), phone: $('#sPhone').val(), email: $('#sEmail').val(),
      addr: $('#sAddr').val(), currency: $('#sCurr').val(), tax: $('#sTax').val(), low_stock: $('#sLow').val(),
      invoice_note: $('#sInvNote').val(), maintenance_note: $('#sMntNote').val()
    }, function (res) {
      state.settings = res.settings;
      $('#sbShop, #tbShop').text(res.settings.shop);
      document.title = res.settings.shop + ' — Phone Shop Manager';
      paintCurrencyTags();
      toast(res.message);
      rerender();
    });
  }

  function resetData() {
    if (!window.confirm('سيتم حذف كل التصنيفات والأصناف والفواتير وسجلات الصيانة ثم إعادة تحميل البيانات التجريبية. هل تريد المتابعة؟')) {
      return;
    }
    call('settings.php', { action: 'reset' }, function (res) {
      toast(res.message);
      setTimeout(function () { window.location.reload(); }, 900);
    });
  }

  /* ======================================================================
     النسخ الاحتياطي (Backup file url + auto backup)
     ====================================================================== */
  function loadBackups() {
    call('backup.php', { action: 'status' }, function (res) {
      paintBackup(res.status);
    });
  }

  function paintBackup(s) {
    state.backup = s;
    $('#bkStats').html(
      statBox(s.count, 'عدد النسخ الموجودة') +
      statBox(s.last ? s.last.size_h : '—', 'حجم آخر نسخة') +
      statBox(s.auto_enabled && s.interval_hours > 0 ? s.interval_hours + ' ساعات' : 'متوقف', 'نسخة تلقائية كل') +
      statBox(s.total_size_h, 'الحجم الكلي')
    );
    $('#bkDir').val(s.dir);
    $('#bkTool').val(s.dump_found ? ('متوفرة · ' + s.dump_path) : 'غير متوفرة — حدّد المسار في config.php');

    var notes = [];
    if (s.auto_enabled && s.interval_hours > 0) {
      notes.push('النسخ التلقائي مفعّل: نسخة عند بدء تشغيل الخادم (بعد ' + s.boot_gap_minutes +
        ' دقيقة توقف) ونسخة كل ' + s.interval_hours + ' ساعات.');
      if (s.next_run) { notes.push('النسخة القادمة: ' + fmtDate(s.next_run.date) + ' · ' + s.next_run.time + '.'); }
      if (s.keep > 0) { notes.push('يُحتفظ بأحدث ' + s.keep + ' نسخة ويُحذف الأقدم تلقائياً.'); }
    } else {
      notes.push('النسخ التلقائي متوقف (BACKUP_ON_LOAD = false في config.php).');
    }
    if (s.last) {
      notes.push('آخر نسخة: ' + s.last.name + ' — ' + s.last.label + ' · ' + fmtDate(s.last.date) + ' ' + s.last.time + '.');
    } else {
      notes.push('لا توجد نسخ بعد — اضغط «إنشاء نسخة احتياطية الآن».');
    }
    if (s.dir_error) { notes.push(s.dir_error); }
    if (s.last_error) { notes.push('آخر خطأ (' + s.last_error_at + '): ' + s.last_error); }
    $('#bkHintBox').html('<svg class="ic"><use href="#i-spark"/></svg><span>' + esc(notes.join(' ')) + '</span>');

    $('#bkBody').html(s.files.length ? s.files.map(function (f) {
      return '<tr>' +
        '<td class="no-label"><span class="file-pill">' + esc(f.name) + '</span></td>' +
        '<td data-l="النوع">' + esc(f.label) + '</td>' +
        '<td data-l="الحجم">' + esc(f.size_h) + '</td>' +
        '<td data-l="التاريخ"><span class="mono">' + fmtDate(f.date) + ' · ' + esc(f.time) + '</span></td>' +
        '<td data-l="" class="right"><div class="row-actions">' +
          '<a class="icon-btn sm" title="تنزيل النسخة" href="' + API + 'backup.php?action=download&file=' +
            encodeURIComponent(f.name) + '"><svg class="ic" style="width:15px;height:15px"><use href="#i-file"/></svg></a>' +
          '<button class="icon-btn sm" title="حذف الملف" onclick="deleteBackup(\'' + f.name + '\')">' +
            '<svg class="ic" style="width:15px;height:15px"><use href="#i-trash"/></svg></button>' +
        '</div></td></tr>';
    }).join('') : emptyRow(5, 'لا توجد نسخ احتياطية بعد'));
  }

  function createBackup() {
    call('backup.php', { action: 'create' }, function (res) {
      toast(res.message);
      paintBackup(res.status);
    });
  }

  function deleteBackup(name) {
    if (!window.confirm('هل تريد حذف ملف النسخة الاحتياطية؟\n' + name)) { return; }
    call('backup.php', { action: 'delete', file: name }, function (res) {
      toast(res.message);
      paintBackup(res.status);
    });
  }

  function copyBackupDir() {
    var path = $('#bkDir').val() || (state.backup ? state.backup.dir : '');
    if (!path) { return; }
    function fallback() {
      var $tmp = $('<textarea>').val(path).appendTo('body').trigger('select');
      try { document.execCommand('copy'); toast('تم نسخ مسار النسخ الاحتياطي'); }
      catch (e) { toast('انسخ المسار يدوياً: ' + path, 'warn'); }
      $tmp.remove();
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(path).then(
        function () { toast('تم نسخ مسار النسخ الاحتياطي'); },
        fallback
      );
    } else {
      fallback();
    }
  }

  /** Delete every record (settings are kept) — a safety copy is taken first. */
  function wipeData() {
    if (!window.confirm('سيتم حذف كل الأصناف والتصنيفات والفواتير وسجلات الصيانة نهائياً (تبقى الإعدادات).\n' +
      'ملاحظة: يتم إنشاء نسخة احتياطية تلقائياً قبل الحذف.\nهل تريد المتابعة؟')) {
      return;
    }
    if (!window.confirm('تأكيد نهائي: هل أنت متأكد من حذف كل بيانات النظام؟')) {
      return;
    }
    call('backup.php', { action: 'wipe' }, function (res) {
      state.items = [];
      state.ledger = { movements: [], summary: null };
      state.cache = { invoices: [], maintenance: [], billsExp: [], dashboard: null };
      state.categories = [];
      toast(res.message);
      if (res.status) { paintBackup(res.status); }
      navCounts({ units: 0, invoices: 0, maintenance: 0, suppliers: 0, categories: 0, low: 0 });
      loadCategories(function () { rerender(); });
    });
  }

  /* ======================================================================
     notifications (low stock)
     ====================================================================== */
  function toggleNotif(e) {
    if (e) { e.stopPropagation(); }
    var open = !$('#notifPop').hasClass('open');
    closeNotif();
    if (open) { renderNotifs(); $('#notifPop').addClass('open'); }
  }
  function closeNotif() { $('#notifPop').removeClass('open'); }

  function renderNotifs() {
    function paint() {
      var low = state.items.filter(function (p) { return num(p.qty) <= num(p.low_stock); })
        .sort(function (a, b) { return a.qty - b.qty; });
      $('#notifPop').html('<div class="pop-h">التنبيهات<span class="badge ' + (low.length ? 'b-amber' : 'b-green') +
        '">' + low.length + '</span></div>' +
        (low.length ? low.map(function (p) {
          return '<div class="pop-item" onclick="closeNotif();openStock(' + p.id + ',\'in\')">' +
            tile(p.name, p.category_color) + '<div><b>' + esc(p.name) + '</b><small>' +
            (p.qty === 0 ? 'غير متوفر — أعد التزويد' : 'بقي ' + p.qty + ' فقط — أقل من حد التنبيه') +
            '</small></div></div>';
        }).join('') : '<div class="pop-ok">المخزون بحالة جيدة ✓</div>'));
    }
    if (state.items.length) {
      paint();
    } else {
      call('items.php', { action: 'list' }, function (res) { state.items = res.items; paint(); });
    }
  }

  /* ======================================================================
     command palette
     ====================================================================== */
  var palIdx = 0, palItems = [];

  function openPalette() {
    openOverlay('#ovPal');
    $('#palInput').val('');
    palIdx = 0;
    var pending = 4;
    function ready() { pending -= 1; if (pending <= 0) { buildPal(); } }
    buildPal();
    call('items.php', { action: 'list' }, function (res) { state.items = res.items; ready(); });
    call('invoices.php', { action: 'list', status: 'all' }, function (res) {
      state.cache.invoices = res.invoices;
      ready();
    });
    call('bills.php', { action: 'list', kind: 'all' }, function (res) {
      state.cache.billsExp = res.records;
      ready();
    });
    call('suppliers.php', { action: 'list', status: 'all' }, function (res) {
      state.cache.suppliers = res.suppliers || [];
      state.cache.supParts  = res.records || [];
      ready();
    });
    setTimeout(function () { $('#palInput').focus(); }, 60);
  }

  function buildPal() {
    var q = ($('#palInput').val() || '').toLowerCase().trim();
    function P(g, icon, label, sub, run) { return { g: g, icon: icon, label: label, sub: sub, run: run }; }

    palItems = [
      P('الصفحات', 'home', 'لوحة التحكم', 'نظرة عامة والرسوم البيانية', function () { go('dashboard'); }),
      P('الصفحات', 'box', 'المخزون', 'الأصناف وحركات المخزون', function () { go('inventory'); }),
      P('الصفحات', 'receipt', 'الفواتير', 'المبيعات والطباعة', function () { go('invoices'); }),
      P('الصفحات', 'wrench', 'الصيانة', 'قسم الإصلاح', function () { go('maintenance'); }),
      P('الصفحات', 'bolt', 'المصروفات', 'كهرباء، إنترنت، إيجار، رواتب', function () { go('expenses'); }),
      P('الصفحات', 'truck', 'فواتير الموردين', 'قطع الموردين — مستحق / مدفوع', function () { go('suppliers'); }),
      P('الصفحات', 'tag', 'التصنيفات', 'آيفون، سامسونج، شاومي…', function () { go('categories'); }),
      P('الصفحات', 'gear', 'الإعدادات', 'بيانات المتجر والتفضيلات', function () { go('settings'); }),
      P('إجراءات', 'plus', 'فاتورة جديدة', 'تسجيل بيع وطبعه', function () { openBill(); }),
      P('إجراءات', 'cart', 'بيع سريع', 'فتح فاتورة صنف محدد', function () { go('inventory'); }),
      P('إجراءات', 'down', 'إضافة / خصم كمية', 'حركة مخزون بتاريخ', function () { openStock(null, 'in'); }),
      P('إجراءات', 'wrench', 'فاتورة صيانة جديدة', 'تسجيل عملية إصلاح', function () { openMaintenance(); }),
      P('إجراءات', 'bolt', 'مصروف جديد', 'فاتورة كهرباء أو إنترنت أو إيجار', function () { openExpense(); }),
      P('إجراءات', 'truck', 'قطعة مورد', 'إضافة قطعة إلى سجل الموردين', function () { openSupBill(); }),
      P('إجراءات', 'truck', 'مورد جديد', 'إضافة اسم مورد', function () { openSupplier(); }),
      P('إجراءات', 'printer', 'طباعة قائمة المخزون', 'كشف كامل بالأصناف', printStockList),
      P('إجراءات', 'down', 'نسخة احتياطية الآن', 'نسخ قاعدة البيانات إلى مجلد النسخ', function () { go('settings'); setTimeout(createBackup, 400); }),
      P('إجراءات', 'spark', 'دليل الاستخدام', 'شرح مختصر للنظام', openHelp),
      P('إجراءات', 'moon', 'تغيير المظهر', 'فاتح / داكن', toggleTheme)
    ]
      .concat(state.categories.map(function (c) {
        return P('التصنيفات', 'tag', c.name, c.items + ' صنف · ' + c.units + ' وحدة في المخزون',
          function () { go('categories'); });
      }))
      .concat(state.items.map(function (p) {
        return P('المخزون', 'phone', p.name, p.category + ' · ' + money(p.price) + ' · متوفر ' + p.qty,
          function () {
            go('inventory');
            $('#invQ').val(p.name);
            state.filters.inv.q = p.name;
            renderInventory();
          });
      }))
      .concat((state.cache.invoices || []).map(function (v) {
        return P('الفواتير', 'receipt', v.code, v.customer + ' · ' + money(v.total) + ' · ' + statusLabel(v.status),
          function () { openBillPaper(v.id); });
      }))
      .concat((state.cache.billsExp || []).map(function (b) {
        return P('المصروفات', 'bolt', b.code + ' · ' + b.name,
          (b.kind || 'أخرى') + ' · ' + money(b.amount), function () { openExpense(b.id); });
      }))
      .concat((state.cache.suppliers || []).map(function (x) {
        return P('الموردين', 'truck', x.name,
          x.parts + ' قطعة · مستحق ' + money(x.due) + ' · مدفوع ' + money(x.paid),
          function () { go('suppliers'); filterSupplier(x.id); });
      }))
      .concat((state.cache.supParts || []).map(function (p) {
        return P('قطع الموردين', 'box', p.code + ' · ' + p.name,
          p.supplier + ' · ' + money(p.amount) + ' · ' + p.status_label, function () { openSupBill(p.id); });
      }));

    palItems = palItems.filter(function (x) {
      return !q || (x.label + ' ' + x.sub).toLowerCase().indexOf(q) >= 0;
    });
    palIdx = Math.max(0, Math.min(palIdx, palItems.length - 1));

    var lastG = '', html = '';
    palItems.forEach(function (it, i) {
      if (it.g !== lastG) { html += '<div class="pal-g">' + esc(it.g) + '</div>'; lastG = it.g; }
      html += '<div class="pal-item' + (i === palIdx ? ' on' : '') + '" onclick="palRun(' + i + ')" onmouseenter="palHover(' + i + ')">' +
        '<span class="pi-ic"><svg class="ic" style="width:16px;height:16px"><use href="#i-' + it.icon + '"/></svg></span>' +
        '<span style="min-width:0"><b>' + esc(it.label) + '</b><small>' + esc(it.sub) + '</small></span>' +
        '<span class="enter">↵</span></div>';
    });
    $('#palList').html(html || '<div class="pal-empty">لا نتائج مطابقة لـ «' + esc($('#palInput').val()) + '»</div>');
  }

  function palRun(i) {
    closeOverlay('#ovPal');
    var it = palItems[i];
    if (it) { setTimeout(function () { it.run(); }, 80); }
  }
  function palHover(i) {
    if (i === palIdx) { return; }
    palIdx = i;
    $('#palList .pal-item').each(function (j) { $(this).toggleClass('on', j === palIdx); });
  }
  function scrollPal() {
    var el = $('#palList .pal-item.on').get(0);
    if (el) { el.scrollIntoView({ block: 'nearest' }); }
  }
  function palKey(e) {
    if (e.key === 'ArrowDown') {
      e.preventDefault(); palIdx = Math.min(palIdx + 1, palItems.length - 1); buildPal(); scrollPal();
    } else if (e.key === 'ArrowUp') {
      e.preventDefault(); palIdx = Math.max(palIdx - 1, 0); buildPal(); scrollPal();
    } else if (e.key === 'Enter') {
      e.preventDefault(); palRun(palIdx);
    }
  }

  function openSheet() { openOverlay('#ovSheet'); }

  /** Quick Arabic guide — also shown automatically on the first visit. */
  function openHelp() {
    localStorage.setItem('nc-help-seen', '1');
    openOverlay('#ovHelp');
  }

  /* ======================================================================
     init + global handlers (used by inline onclick attributes)
     ====================================================================== */
  function debounce(fn, ms) {
    var t;
    return function () {
      var args = arguments, self = this;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(self, args); }, ms || 300);
    };
  }

  $(function () {
    setTheme(localStorage.getItem('nc-theme') || 'light');
    paintCurrencyTags();

    /* navigation */
    $('[data-nav]').on('click', function () { go(String($(this).data('nav'))); });

    /* date range pickers */
    bindRange('#dashRange', '#dashCustom', '#dashFrom', '#dashTo', 'dash', renderDashboard);
    bindRange('#invRange', '#invCustom', '#invFrom', '#invTo', 'inv', function () { renderInventory(); });
    bindRange('#billRange', '#billCustom', '#billFrom', '#billTo', 'bill', renderBills);
    bindRange('#mntRange', '#mntCustom', '#mntFrom', '#mntTo', 'mnt', renderMaintenance);
    bindRange('#expRange', '#expCustom', '#expFrom', '#expTo', 'exp', renderExpenses);
    bindRange('#supRange', '#supCustom', '#supFrom', '#supTo', 'sup', renderSuppliers);

    /* inventory filters */
    $('#invQ').on('input', debounce(function () { state.filters.inv.q = this.value; renderInventory(); }));
    $('#invCat').on('change', function () { state.filters.inv.cat = this.value; renderInventory(); });
    $('#invStatus').on('click', 'button', function () {
      $('#invStatus button').removeClass('active');
      $(this).addClass('active');
      state.filters.inv.status = $(this).data('s');
      renderInventory();
    });

    /* bill filters */
    $('#billQ').on('input', debounce(function () { state.filters.bill.q = this.value; renderBills(); }));
    $('#billStatus').on('click', 'button', function () {
      $('#billStatus button').removeClass('active');
      $(this).addClass('active');
      state.filters.bill.status = $(this).data('s');
      renderBills();
    });

    /* maintenance filters */
    $('#mntQ').on('input', debounce(function () { state.filters.mnt.q = this.value; renderMaintenance(); }));
    $('#mntCat').on('change', function () { state.filters.mnt.cat = this.value; renderMaintenance(); });

    /* expenses filters */
    $('#expQ').on('input', debounce(function () { state.filters.exp.q = this.value; renderExpenses(); }));
    $('#expKind').on('change', function () { state.filters.exp.kind = this.value; renderExpenses(); });

    /* suppliers filters (two separate searches: suppliers card grid / parts table) */
    $('#supQ').on('input', debounce(function () {
      state.filters.sup.sq = this.value;
      renderSuppliers();
    }));
    $('#supPartQ').on('input', debounce(function () {
      state.filters.sup.q = this.value;
      renderSuppliers();
    }));
    $('#supFilter').on('change', function () { state.filters.sup.supplier = this.value; renderSuppliers(); });
    $('#supStatus').on('click', 'button', function () {
      $('#supStatus button').removeClass('active');
      $(this).addClass('active');
      state.filters.sup.status = $(this).data('s');
      renderSuppliers();
    });

    /* new bill modal */
    $('#billItemQ').on('input', function () { state.billSearch = this.value; paintBillItems(); });
    $('#billDiscount').on('input', billTotals);

    /* stock + categories */
    $('#stockItem').on('change', fillStockPriceProfit);
    $('#itemPrice, #itemWholesale').on('input', paintItemProfit);
    $('#catSwatches').on('click', '.swatch', function () { catPick = $(this).data('c'); paintSwatches(); });
    $('#catQ').on('input', debounce(renderCategories));
    $('#catName').on('keydown', function (e) { if (e.key === 'Enter') { saveCategory(); } });

    /* palette */
    $('#palInput').on('input', function () { palIdx = 0; buildPal(); });

    /* click the backdrop to close */
    ['#ovBill', '#ovItem', '#ovStock', '#ovLedger', '#ovMnt', '#ovExp', '#ovSupBill', '#ovPaper', '#ovSheet', '#ovHelp', '#ovPal'].forEach(function (id) {
      $(id).on('click', function (e) { if (e.target === this) { closeOverlay(id); } });
    });

    /* keyboard shortcuts */
    $(document).on('click', function (e) {
      if (!$(e.target).closest('#notifPop').length && !$(e.target).closest('[onclick*="toggleNotif"]').length) {
        closeNotif();
      }
    });
    $(document).on('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && String(e.key).toLowerCase() === 'k') {
        e.preventDefault();
        if ($('#ovPal').hasClass('open')) { closeOverlay('#ovPal'); } else { openPalette(); }
        return;
      }
      if (e.key === 'Escape') {
        $('.overlay.open').each(function () { closeOverlay('#' + this.id); });
        closeNotif();
        return;
      }
      if ($('#ovPal').hasClass('open')) { palKey(e); }
    });

    /* boot: load the category list, then draw the dashboard */
    loadCategories(function () {
      go('dashboard');
      if (!localStorage.getItem('nc-help-seen')) {
        setTimeout(openHelp, 600);
      }
    });
  });

  $.extend(window, {
    go: go, toast: toast, openOverlay: openOverlay, closeOverlay: closeOverlay,
    openSheet: openSheet, openHelp: openHelp, toggleTheme: toggleTheme, setTheme: setTheme,
    toggleNotif: toggleNotif, closeNotif: closeNotif, kpiLowStock: kpiLowStock,
    openPalette: openPalette, palRun: palRun, palHover: palHover,
    openBill: openBill, billAdd: billAdd, billLineStep: billLineStep,
    billLinePrice: billLinePrice, billLineRemove: billLineRemove, createBill: createBill,
    setBillStatus: setBillStatus, deleteBill: deleteBill, openBillPaper: openBillPaper,
    printBill: printBill, printStockList: printStockList, quickSell: quickSell,
    openItem: openItem, saveItem: saveItem, deleteItem: deleteItem, quickStock: quickStock,
    openStock: openStock, setStockType: setStockType, saveStock: saveStock,
    openLedger: openLedger, saveLedger: saveLedger, deleteLedger: deleteLedger,
    openMaintenance: openMaintenance, saveMaintenance: saveMaintenance, deleteMaintenance: deleteMaintenance,
    openExpense: openExpense, saveExpense: saveExpense, deleteExpense: deleteExpense, printExpenses: printExpenses,
    openSupplier: openSupplier, saveSupplier: saveSupplier, deleteSupplier: deleteSupplier,
    filterSupplier: filterSupplier, openSupBill: openSupBill, saveSupBill: saveSupBill,
    toggleSupStatus: toggleSupStatus, deleteSupBill: deleteSupBill, printSupBills: printSupBills,
    openMntPaper: openMntPaper, printMaintenance: printMaintenance,
    saveCategory: saveCategory, editCategory: editCategory, cancelCategoryEdit: cancelCategoryEdit,
    deleteCategory: deleteCategory, confirmCategoryDelete: confirmCategoryDelete,
    saveSettings: saveSettings, resetData: resetData,
    loadBackups: loadBackups, createBackup: createBackup, deleteBackup: deleteBackup,
    copyBackupDir: copyBackupDir, wipeData: wipeData
  });
})(jQuery);
