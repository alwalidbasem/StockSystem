<?php
/**
 * NovaCell — Phone Shop Manager
 * Single page app shell (styled after mockup/index.html).
 */
declare(strict_types=1);
require_once __DIR__ . '/api/bootstrap.php';

$boot = app_boot_data();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?php echo htmlspecialchars($boot['settings']['shop'], ENT_QUOTES); ?> — إدارة متجر الجوالات</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📱</text></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="./assets/styles.css" rel="stylesheet">
</head>
<body>

<!-- ======== icon sprite ======== -->
<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">
  <symbol id="i-home" viewBox="0 0 24 24"><path d="m3 9.5 9-7 9 7V20a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/><path d="M9 22v-8h6v8"/></symbol>
  <symbol id="i-box" viewBox="0 0 24 24"><path d="M21 8.5V17a2 2 0 0 1-1 1.73l-7 4a2 2 0 0 1-2 0l-7-4A2 2 0 0 1 3 17V8.5a2 2 0 0 1 1-1.73l7-4a2 2 0 0 1 2 0l7 4A2 2 0 0 1 21 8.5Z"/><path d="m3.3 7.3 8.7 5 8.7-5"/><path d="M12 22.1V12.2"/></symbol>
  <symbol id="i-receipt" viewBox="0 0 24 24"><path d="M5 2h14v20l-2.3-1.5-2.3 1.5-2.4-1.5L9.6 22l-2.3-1.5L5 22Z"/><path d="M9 7h6M9 11h6M9 15h4"/></symbol>
  <symbol id="i-wrench" viewBox="0 0 24 24"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></symbol>
  <symbol id="i-bolt" viewBox="0 0 24 24"><path d="M13 2 4.5 13.5H11l-1 8.5 8.5-11.5H12Z"/></symbol>
  <symbol id="i-tag" viewBox="0 0 24 24"><path d="M12.59 2.59A2 2 0 0 0 11.17 2H4a2 2 0 0 0-2 2v7.17a2 2 0 0 0 .59 1.42l8.7 8.7a2.43 2.43 0 0 0 3.42 0l6.58-6.58a2.43 2.43 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r="1.5"/></symbol>
  <symbol id="i-gear" viewBox="0 0 24 24"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></symbol>
  <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></symbol>
  <symbol id="i-bell" viewBox="0 0 24 24"><path d="M6 8.5a6 6 0 0 1 12 0c0 6.3 2.5 8 2.5 8H3.5S6 14.8 6 8.5"/><path d="M10.3 20.5a2 2 0 0 0 3.4 0"/></symbol>
  <symbol id="i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></symbol>
  <symbol id="i-moon" viewBox="0 0 24 24"><path d="M12 3a6.4 6.4 0 0 0 9 9 9 9 0 1 1-9-9Z"/></symbol>
  <symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
  <symbol id="i-minus" viewBox="0 0 24 24"><path d="M5 12h14"/></symbol>
  <symbol id="i-printer" viewBox="0 0 24 24"><path d="M6 9V3h12v6"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8" rx="1"/></symbol>
  <symbol id="i-x" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></symbol>
  <symbol id="i-check" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></symbol>
  <symbol id="i-alert" viewBox="0 0 24 24"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/></symbol>
  <symbol id="i-phone" viewBox="0 0 24 24"><rect x="7" y="2" width="10" height="20" rx="2.5"/><path d="M11 18.5h2"/></symbol>
  <symbol id="i-cart" viewBox="0 0 24 24"><circle cx="9" cy="20" r="1.4"/><circle cx="17.5" cy="20" r="1.4"/><path d="M2.5 3h2l2.5 12.2a1.6 1.6 0 0 0 1.6 1.3h8.6a1.6 1.6 0 0 0 1.6-1.3L20.8 7H6"/></symbol>
  <symbol id="i-card" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2.5"/><path d="M2 10h20"/></symbol>
  <symbol id="i-cal" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="2.5"/><path d="M16 2v4M8 2v4M3 9.5h18"/></symbol>
  <symbol id="i-spark" viewBox="0 0 24 24"><path d="M12 3l1.8 5.6a2 2 0 0 0 1.3 1.3L20.7 12l-5.6 1.8a2 2 0 0 0-1.3 1.3L12 20.7l-1.8-5.6a2 2 0 0 0-1.3-1.3L3.3 12l5.6-1.8a2 2 0 0 0 1.3-1.3Z"/></symbol>
  <symbol id="i-trend" viewBox="0 0 24 24"><path d="m22 7-8.5 8.5-4-4L2 19"/><path d="M16 7h6v6"/></symbol>
  <symbol id="i-edit" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></symbol>
  <symbol id="i-trash" viewBox="0 0 24 24"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></symbol>
  <symbol id="i-refresh" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-2.6-6.3L21 8"/><path d="M21 3v5h-5"/></symbol>
  <symbol id="i-down" viewBox="0 0 24 24"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></symbol>
  <symbol id="i-up" viewBox="0 0 24 24"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></symbol>
  <symbol id="i-truck" viewBox="0 0 24 24"><path d="M2 7h10v9H2zM12 10h4.5l3 3v3H12z"/><circle cx="6" cy="18.5" r="1.6"/><circle cx="16.5" cy="18.5" r="1.6"/></symbol>
  <symbol id="i-eye" viewBox="0 0 24 24"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></symbol>
  <symbol id="i-logout" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/></symbol>
</svg>

<!-- ======== sidebar (desktop) ======== -->
<aside class="sidebar">
  <div class="sb-logo">
    <div class="logo-tile"><svg class="ic" style="width:20px;height:20px"><use href="#i-phone"/></svg></div>
    <div><b id="sbShop"><?php echo htmlspecialchars($boot['settings']['shop'], ENT_QUOTES); ?></b><small>نظام المخزون والصيانة</small></div>
  </div>
  <nav class="sb-nav">
    <div class="sb-label">نظرة عامة</div>
    <button class="sb-item" data-nav="dashboard"><svg class="ic"><use href="#i-home"/></svg>لوحة التحكم</button>
    <div class="sb-label">الإدارة</div>
    <button class="sb-item" data-nav="inventory"><svg class="ic"><use href="#i-box"/></svg>المخزون<span class="cnt" id="cntStock">0 u</span></button>
    <button class="sb-item" data-nav="invoices"><svg class="ic"><use href="#i-receipt"/></svg>الفواتير<span class="cnt" id="cntInv">0</span></button>
    <button class="sb-item" data-nav="maintenance"><svg class="ic"><use href="#i-wrench"/></svg>الصيانة<span class="cnt" id="cntMnt">0</span></button>
    <button class="sb-item" data-nav="expenses"><svg class="ic"><use href="#i-bolt"/></svg>المصروفات<span class="cnt" id="cntExp">0</span></button>
    <button class="sb-item" data-nav="suppliers"><svg class="ic"><use href="#i-truck"/></svg>فواتير الموردين<span class="cnt" id="cntSup">0</span></button>
    <button class="sb-item" data-nav="categories"><svg class="ic"><use href="#i-tag"/></svg>التصنيفات<span class="cnt" id="cntCat">0</span></button>
    <div class="sb-label">أدوات</div>
    <button class="sb-item" data-nav="settings"><svg class="ic"><use href="#i-gear"/></svg>الإعدادات</button>
  </nav>
  <div class="sb-foot">
    <div class="sb-tip"><svg class="ic"><use href="#i-spark"/></svg><div><b>نصيحة سريعة</b><span>افلتر حركات المخزون بالتاريخ لمراجعة أي يوم خلال ثوانٍ.</span></div></div>
    <div class="sb-user">
      <div class="avatar-btn">عب</div>
      <div style="flex:1;min-width:0"><b>محمد عامر</b><small>مدير المتجر</small></div>
      <button class="icon-btn sm" onclick="toast('تم تسجيل الخروج — حساب تجريبي','warn')" aria-label="تسجيل الخروج"><svg class="ic flip" style="width:16px;height:16px"><use href="#i-logout"/></svg></button>
    </div>
  </div>
</aside>

<!-- ======== main ======== -->
<div class="main">
  <header class="topbar">
    <div class="top-logo"><div class="logo-tile sm"><svg class="ic" style="width:16px;height:16px"><use href="#i-phone"/></svg></div><span id="tbShop"><?php echo htmlspecialchars($boot['settings']['shop'], ENT_QUOTES); ?></span></div>
    <button class="search-pill" onclick="openPalette()"><svg class="ic"><use href="#i-search"/></svg><span>ابحث عن صنف أو فاتورة أو صيانة…</span><span class="kbd">Ctrl+K</span></button>
    <div class="spacer"></div>
    <button class="icon-btn" id="btnSearchM" onclick="openPalette()" aria-label="بحث"><svg class="ic"><use href="#i-search"/></svg></button>
    <button class="icon-btn" onclick="openHelp()" aria-label="دليل الاستخدام"><b style="font-weight:800;font-size:17px">؟</b></button>
    <button class="icon-btn" id="btnTheme" onclick="toggleTheme()" aria-label="تغيير المظهر"></button>
    <button class="icon-btn" onclick="toggleNotif(event)" aria-label="التنبيهات"><svg class="ic"><use href="#i-bell"/></svg><span class="dot-badge" id="bellDot" style="display:none"></span></button>
    <button class="avatar-btn" onclick="go('settings')" aria-label="الملف الشخصي">عب</button>
  </header>

  <div class="content">
    <!-- ========== DASHBOARD ========== -->
    <section class="view active" id="view-dashboard">
      <div class="page-head">
        <div>
          <h1 class="title"><span id="greetWord">صباح الخير</span>، محمد عامر</h1>
          <p class="sub" id="greetDate"></p>
        </div>
        <div class="toolbar-actions">
          <div class="chips" id="dashRange">
            <button class="chip" data-r="7">7 أيام</button>
            <button class="chip active" data-r="30">30 يوم</button>
            <button class="chip" data-r="90">90 يوم</button>
            <button class="chip" data-r="month">هذا الشهر</button>
            <button class="chip" data-r="custom">مخصص</button>
          </div>
          <div class="range-inputs" id="dashCustom" style="display:none">
            <input type="date" class="inp w-date" id="dashFrom">
            <span class="range-sep">→</span>
            <input type="date" class="inp w-date" id="dashTo">
          </div>
          <button class="btn btn-outline" onclick="printStockList()"><svg class="ic"><use href="#i-printer"/></svg>طباعة المخزون</button>
          <button class="btn btn-primary" onclick="openBill()"><svg class="ic"><use href="#i-plus"/></svg>فاتورة جديدة</button>
        </div>
      </div>
      <div class="hint-line"><svg class="ic"><use href="#i-spark"/></svg><span>اضغط على أي بطاقة إحصائية للانتقال إلى تفاصيلها، وعلى أي فاتورة لعرضها وطباعتها.</span></div>
      <div class="card cash-card" id="cashCard"><div class="empty-sm">جارٍ حساب الكاش…</div></div>
      <div class="stats" id="statCards"></div>
      <div class="grid-charts">
        <div class="card">
          <div class="card-h"><h3><span class="live-dot"></span>الإيرادات اليومية</h3><span class="badge b-green"><svg class="ic" style="width:12px;height:12px"><use href="#i-trend"/></svg><span id="revTotal"></span></span></div>
          <div class="chart-wrap"><canvas id="incomeChart"></canvas></div>
        </div>
        <div class="card">
          <div class="card-h"><h3>المبيعات حسب التصنيف</h3><span class="badge b-blue" id="catUnits"></span></div>
          <div class="chart-wrap"><canvas id="catChart"></canvas></div>
        </div>
      </div>
      <div class="grid-tri">
        <div class="card"><div class="card-h"><h3>الأكثر بيعاً</h3><span class="badge b-gray" id="topRangeLbl">آخر 30 يوم</span></div><div class="card-b" id="topItems"></div></div>
        <div class="card"><div class="card-h"><h3>أحدث الفواتير</h3><button class="btn btn-soft" style="padding:6px 12px;font-size:12px" onclick="go('invoices')">عرض الكل</button></div><div class="card-b" id="recentBills"></div></div>
        <div class="card"><div class="card-h"><h3>مخزون منخفض</h3><span class="badge b-amber" id="lowCnt">0</span></div><div class="card-b" id="lowStockList"></div></div>
      </div>
      <div class="card">
        <div class="card-h"><h3>حركة قسم الصيانة</h3><button class="btn btn-soft" style="padding:6px 12px;font-size:12px" onclick="go('maintenance')">فتح القسم</button></div>
        <div class="card-b" id="recentMaintenance"></div>
      </div>
    </section>
    <!-- ========== INVENTORY ========== -->
    <section class="view" id="view-inventory">
      <div class="page-head">
        <div><h1 class="title">المخزون</h1><p class="sub" id="invCount">جارٍ تحميل المخزون…</p></div>
        <div class="toolbar-actions">
          <button class="btn btn-outline" onclick="printStockList()"><svg class="ic"><use href="#i-printer"/></svg>طباعة القائمة</button>
          <button class="btn btn-soft" onclick="openStock(null,'in')"><svg class="ic"><use href="#i-down"/></svg>إضافة كمية</button>
          <button class="btn btn-primary" onclick="openItem()"><svg class="ic"><use href="#i-plus"/></svg>صنف جديد</button>
        </div>
      </div>
      <div class="hint-line"><svg class="ic"><use href="#i-spark"/></svg><span>استخدم زر «+ / −» لتعديل الكمية بسرعة، أو «إضافة/خصم» لتسجيل حركة بتاريخ محدد. الفلتر الزمني بالأسفل يخص جدول حركات المخزون.</span></div>

      <div class="inv-toolbar">
        <input class="inp" id="invQ" placeholder="ابحث باسم الصنف أو التصنيف…">
        <select class="inp" id="invCat"><option value="all">كل التصنيفات</option></select>
        <div class="chips" id="invStatus">
          <button class="chip active" data-s="all">الكل</button>
          <button class="chip" data-s="in">متوفر</button>
          <button class="chip" data-s="low">منخفض</button>
          <button class="chip" data-s="out">غير متوفر</button>
        </div>
      </div>

      <div class="range-bar">
        <div class="chips" id="invRange">
          <button class="chip" data-r="all">كل الفترات</button>
          <button class="chip" data-r="today">اليوم</button>
          <button class="chip" data-r="7">7 أيام</button>
          <button class="chip active" data-r="30">30 يوم</button>
          <button class="chip" data-r="month">هذا الشهر</button>
          <button class="chip" data-r="custom">مخصص</button>
        </div>
        <div class="range-inputs" id="invCustom" style="display:none">
          <input type="date" class="inp w-date" id="invFrom">
          <span class="range-sep">إلى</span>
          <input type="date" class="inp w-date" id="invTo">
        </div>
      </div>

      <div class="card" style="overflow:hidden">
        <div class="card-h">
          <h3>أصناف المخزون</h3>
          <span class="hint" id="invSummaryLbl">—</span>
        </div>
        <div class="tbl-scroll">
          <table class="tbl">
            <thead><tr><th>الصنف</th><th>التصنيف</th><th class="right">سعر البيع</th><th class="right">سعر الجملة</th><th class="right">ربح الوحدة</th><th>الكمية</th><th>الحالة</th><th class="right">إجراءات</th></tr></thead>
            <tbody id="invBody"></tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-h">
          <h3>حركات المخزون (بالتاريخ)</h3>
          <span class="hint" id="invRangeLbl">آخر 30 يوم</span>
        </div>
        <div class="card-b" style="padding-bottom:6px"><div class="mini-stats wide" id="invRangeStats"></div></div>
        <div class="tbl-scroll">
          <table class="tbl">
            <thead><tr><th>التاريخ</th><th>الصنف</th><th>الحركة</th><th class="right">الكمية</th><th class="right">السعر</th><th class="right">القيمة</th><th class="right">الربح</th><th class="right">إجراءات</th></tr></thead>
            <tbody id="invMoveBody"></tbody>
          </table>
        </div>
      </div>
    </section>
    <!-- ========== BILLS ========== -->
    <section class="view" id="view-invoices">
      <div class="page-head">
        <div><h1 class="title">الفواتير</h1><p class="sub" id="billCount">المبيعات والفواتير — اضغط على أي فاتورة لعرضها وطباعتها.</p></div>
        <button class="btn btn-primary" onclick="openBill()"><svg class="ic"><use href="#i-plus"/></svg>فاتورة جديدة</button>
      </div>
      <div class="hint-line"><svg class="ic"><use href="#i-spark"/></svg><span>عند إنشاء فاتورة تُخصم الكميات من المخزون تلقائياً، وعند حذف الفاتورة تُعاد الكميات إلى المخزون.</span></div>
      <div class="inv-toolbar">
        <input class="inp" id="billQ" placeholder="ابحث برقم الفاتورة أو اسم العميل أو الهاتف…">
        <div class="chips" id="billStatus">
          <button class="chip active" data-s="all">الكل</button>
          <button class="chip" data-s="Paid">مدفوعة</button>
          <button class="chip" data-s="Pending">قيد الانتظار</button>
          <button class="chip" data-s="Overdue">متأخرة</button>
        </div>
      </div>
      <div class="range-bar">
        <div class="chips" id="billRange">
          <button class="chip" data-r="all">كل الفترات</button>
          <button class="chip" data-r="today">اليوم</button>
          <button class="chip" data-r="7">7 أيام</button>
          <button class="chip active" data-r="30">30 يوم</button>
          <button class="chip" data-r="month">هذا الشهر</button>
          <button class="chip" data-r="custom">مخصص</button>
        </div>
        <div class="range-inputs" id="billCustom" style="display:none">
          <input type="date" class="inp w-date" id="billFrom">
          <span class="range-sep">إلى</span>
          <input type="date" class="inp w-date" id="billTo">
        </div>
      </div>
      <div class="card">
        <div class="card-h"><h3>ملخص الفترة</h3><span class="hint" id="billRangeLbl">آخر 30 يوم</span></div>
        <div class="card-b" style="padding-bottom:6px"><div class="mini-stats" id="billStats"></div></div>
      </div>
      <div class="card" style="overflow:hidden">
        <div class="tbl-scroll">
          <table class="tbl">
            <thead><tr><th>الفاتورة</th><th>العميل</th><th>التاريخ</th><th class="right">القطع</th><th class="right">الإجمالي</th><th>الحالة</th><th class="right">إجراءات</th></tr></thead>
            <tbody id="billBody"></tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ========== MAINTENANCE ========== -->
    <section class="view" id="view-maintenance">
      <div class="page-head">
        <div><h1 class="title">الصيانة</h1><p class="sub" id="mntCount">فواتير الإصلاح والصيانة مسجّلة بالتاريخ.</p></div>
        <div class="toolbar-actions">
          <button class="btn btn-primary" onclick="openMaintenance()"><svg class="ic"><use href="#i-plus"/></svg>فاتورة صيانة جديدة</button>
        </div>
      </div>
      <div class="hint-line"><svg class="ic"><use href="#i-spark"/></svg><span>سجّل اسم الجهاز أو العمل، التصنيف، السعر والربح، ثم استخدم الفلتر الزمني لمراجعة أي فترة. الأرباح داخلية ولا تظهر في الفاتورة المطبوعة.</span></div>
      <div class="inv-toolbar">
        <input class="inp" id="mntQ" placeholder="ابحث باسم العمل أو التصنيف أو الملاحظة…">
        <select class="inp" id="mntCat"><option value="all">كل التصنيفات</option></select>
        <div class="chips" id="mntRange">
          <button class="chip" data-r="all">كل الفترات</button>
          <button class="chip" data-r="today">اليوم</button>
          <button class="chip" data-r="7">7 أيام</button>
          <button class="chip active" data-r="30">30 يوم</button>
          <button class="chip" data-r="month">هذا الشهر</button>
          <button class="chip" data-r="custom">مخصص</button>
        </div>
      </div>
      <div class="range-bar">
        <div class="range-inputs" id="mntCustom" style="display:none">
          <input type="date" class="inp w-date" id="mntFrom">
          <span class="range-sep">إلى</span>
          <input type="date" class="inp w-date" id="mntTo">
        </div>
        <span class="hint" id="mntRangeLbl">آخر 30 يوم</span>
      </div>
      <div class="card">
        <div class="card-h"><h3>إجماليات القسم</h3><span class="hint">الربح داخلي فقط — لا يظهر في الفاتورة المطبوعة</span></div>
        <div class="card-b" style="padding-bottom:6px"><div class="mini-stats wide" id="mntStats"></div></div>
      </div>
      <div class="card" style="overflow:hidden">
        <div class="tbl-scroll">
          <table class="tbl">
            <thead><tr><th>الفاتورة</th><th>التاريخ</th><th>العمل / العميل</th><th>التصنيف</th><th class="right">السعر</th><th class="right">الربح</th><th class="right">إجراءات</th></tr></thead>
            <tbody id="mntBody"></tbody>
          </table>
        </div>
      </div>
    </section>
    <!-- ========== EXPENSES (shop bills: Wi-Fi, electricity, rent…) ========== -->
    <section class="view" id="view-expenses">
      <div class="page-head">
        <div><h1 class="title">المصروفات</h1><p class="sub" id="expCount">فواتير الكهرباء والإنترنت والإيجار والرواتب — مسجّلة بالتاريخ.</p></div>
        <div class="toolbar-actions">
          <button class="btn btn-outline" onclick="printExpenses()"><svg class="ic"><use href="#i-printer"/></svg>طباعة القائمة</button>
          <button class="btn btn-primary" onclick="openExpense()"><svg class="ic"><use href="#i-plus"/></svg>مصروف جديد</button>
        </div>
      </div>
      <div class="hint-line"><svg class="ic"><use href="#i-spark"/></svg><span>لا تدخل المصروفات في إيرادات الفواتير — تُطرح من الأرباح في لوحة التحكم (صافي الربح) وتُعرض في الرسم البياني اليومي.</span></div>
      <div class="inv-toolbar">
        <input class="inp" id="expQ" placeholder="ابحث باسم المصروف أو النوع أو الملاحظة…">
        <select class="inp" id="expKind"><option value="all">كل الأنواع</option></select>
        <div class="chips" id="expRange">
          <button class="chip" data-r="all">كل الفترات</button>
          <button class="chip" data-r="today">اليوم</button>
          <button class="chip" data-r="7">7 أيام</button>
          <button class="chip active" data-r="30">30 يوم</button>
          <button class="chip" data-r="month">هذا الشهر</button>
          <button class="chip" data-r="custom">مخصص</button>
        </div>
      </div>
      <div class="range-bar">
        <div class="range-inputs" id="expCustom" style="display:none">
          <input type="date" class="inp w-date" id="expFrom">
          <span class="range-sep">إلى</span>
          <input type="date" class="inp w-date" id="expTo">
        </div>
        <span class="hint" id="expRangeLbl">آخر 30 يوم</span>
      </div>
      <div class="card">
        <div class="card-h"><h3>إجماليات القسم</h3><span class="hint">تُطرح من الأرباح في لوحة التحكم</span></div>
        <div class="card-b" style="padding-bottom:6px"><div class="mini-stats wide" id="expStats"></div></div>
      </div>
      <div class="card" style="overflow:hidden">
        <div class="tbl-scroll">
          <table class="tbl">
            <thead><tr><th>الفاتورة</th><th>التاريخ</th><th>المصروف</th><th>النوع</th><th class="right">المبلغ</th><th class="right">إجراءات</th></tr></thead>
            <tbody id="expBody"></tbody>
          </table>
        </div>
      </div>
    </section>
    <!-- ========== SUPPLIERS (فواتير الموردين) ========== -->
    <section class="view" id="view-suppliers">
      <div class="page-head">
        <div><h1 class="title">فواتير الموردين</h1><p class="sub" id="supCount">أسماء الموردين والقطع المشتراة منهم — سجل مستقل تماماً عن الإيرادات والأرباح.</p></div>
        <div class="toolbar-actions">
          <button class="btn btn-outline" onclick="openSupplier()"><svg class="ic"><use href="#i-plus"/></svg>مورد جديد</button>
          <button class="btn btn-outline" onclick="printSupBills()"><svg class="ic"><use href="#i-printer"/></svg>طباعة الكشف</button>
          <button class="btn btn-primary" onclick="openSupBill()"><svg class="ic"><use href="#i-plus"/></svg>إضافة قطعة</button>
        </div>
      </div>
      <div class="hint-line"><svg class="ic"><use href="#i-alert"/></svg><span>هذا القسم <b>لا يدخل في الإيرادات ولا في الأرباح ولا في صافي الربح</b> — سجل داخلي فقط لمعرفة ما هو <b>مستحق</b> وما تم <b>دفعه</b> لكل مورد.</span></div>

      <div class="card">
        <div class="card-h"><h3>الموردين</h3><input class="inp" id="supQ" placeholder="ابحث عن مورد…" style="max-width:220px"></div>
        <div class="card-b"><div class="sup-grid" id="supGrid"></div></div>
      </div>

      <div class="card">
        <div class="card-h"><h3>إجماليات القسم</h3><span class="hint">لا تُحتسب في الأرباح أو الإيرادات</span></div>
        <div class="card-b" style="padding-bottom:6px"><div class="mini-stats wide" id="supStats"></div></div>
      </div>

      <div class="inv-toolbar">
        <input class="inp" id="supPartQ" placeholder="ابحث باسم القطعة أو المورد أو الملاحظة…">
        <select class="inp" id="supFilter"><option value="all">كل الموردين</option></select>
        <div class="chips" id="supStatus">
          <button class="chip active" data-s="all">الكل</button>
          <button class="chip" data-s="Due">مستحق</button>
          <button class="chip" data-s="Paid">مدفوع</button>
        </div>
      </div>
      <div class="range-bar">
        <div class="chips" id="supRange">
          <button class="chip" data-r="all">كل الفترات</button>
          <button class="chip" data-r="today">اليوم</button>
          <button class="chip" data-r="7">7 أيام</button>
          <button class="chip active" data-r="30">30 يوم</button>
          <button class="chip" data-r="month">هذا الشهر</button>
          <button class="chip" data-r="custom">مخصص</button>
        </div>
        <div class="range-inputs" id="supCustom" style="display:none">
          <input type="date" class="inp w-date" id="supFrom">
          <span class="range-sep">إلى</span>
          <input type="date" class="inp w-date" id="supTo">
        </div>
        <span class="hint" id="supRangeLbl">آخر 30 يوم</span>
      </div>

      <div class="card" style="overflow:hidden">
        <div class="card-h">
          <h3>قطع الموردين</h3>
          <span class="hint">اضغط «مستحق / مدفوع» لتبديل الحالة، أو عدّل المبلغ والتاريخ</span>
        </div>
        <div class="tbl-scroll">
          <table class="tbl">
            <thead><tr><th>الرقم</th><th>التاريخ</th><th>المورد</th><th>القطعة</th><th class="right">المبلغ</th><th>الحالة</th><th class="right">إجراءات</th></tr></thead>
            <tbody id="supBody"></tbody>
          </table>
        </div>
      </div>
    </section>
    <!-- ========== CATEGORIES ========== -->
    <section class="view" id="view-categories">
      <div class="page-head">
        <div><h1 class="title">التصنيفات</h1><p class="sub" id="catCount">أضف أو عدّل أو احذف التصنيفات، وحدّد الأقسام التي يظهر فيها كل تصنيف: المخزون والصيانة والمصروفات.</p></div>
      </div>
      <div class="card">
        <div class="card-h"><h3 id="catFormTitle">إضافة تصنيف</h3><span class="hint">مثال: آيفون، سامسونج، شاومي… أو كهرباء وإنترنت لقسم المصروفات</span></div>
        <div class="card-b">
          <div class="inline-form">
            <div class="f-field">
              <label class="f-label" for="catName">اسم التصنيف</label>
              <input class="inp" id="catName" placeholder="مثال: سامسونج">
            </div>
            <div class="f-field" style="flex:none">
              <label class="f-label">اللون</label>
              <div class="swatches" id="catSwatches"></div>
            </div>
            <div class="f-field" style="flex:none">
              <label class="f-label">يظهر في الأقسام</label>
              <div class="sec-checks" id="catSections">
                <label class="sec-chk" title="قوائم المخزون وأصنافه"><input type="checkbox" id="catSecInventory" checked><span>المخزون</span></label>
                <label class="sec-chk" title="قوائم فواتير الصيانة"><input type="checkbox" id="catSecMaintenance" checked><span>الصيانة</span></label>
                <label class="sec-chk" title="أنواع المصروفات"><input type="checkbox" id="catSecExpense"><span>المصروفات</span></label>
              </div>
            </div>
            <button class="btn btn-primary" id="catSaveBtn" onclick="saveCategory()"><svg class="ic"><use href="#i-plus"/></svg><span id="catSaveLbl">إضافة تصنيف</span></button>
            <button class="btn btn-outline" id="catCancelBtn" onclick="cancelCategoryEdit()" style="display:none">إلغاء</button>
          </div>
          <div class="switch-hint" style="margin-top:10px">التصنيف يظهر فقط في الأقسام المعلّمة: «المخزون» لقوائم الأصناف، «الصيانة» لفواتير الإصلاح، «المصروفات» لأنواع المصروفات (كهرباء، إنترنت، إيجار…).</div>
          <div class="mini-stats wide" id="catStats" style="margin-top:16px"></div>
        </div>
      </div>
      <div class="card">
        <div class="card-h">
          <h3>كل التصنيفات</h3>
          <input class="inp" id="catQ" placeholder="ابحث في التصنيفات…" style="max-width:220px">
        </div>
        <div class="card-b"><div class="cat-grid" id="catGrid"></div></div>
      </div>
    </section>

    <!-- ========== SETTINGS ========== -->
    <section class="view" id="view-settings">
      <div class="page-head">
        <div><h1 class="title">الإعدادات</h1><p class="sub">بيانات المتجر، إعدادات الفواتير، وملاحظات الطباعة.</p></div>
        <button class="btn btn-primary" onclick="saveSettings()"><svg class="ic"><use href="#i-check"/></svg>حفظ التغييرات</button>
      </div>
      <div class="grid-2">
        <div class="card">
          <div class="card-h"><h3>بيانات المتجر</h3><span class="badge b-gray">تظهر في المستندات المطبوعة</span></div>
          <div class="card-b">
            <div class="f-field"><label class="f-label">اسم المتجر</label><input class="inp" id="sName"></div>
            <div class="f-row">
              <div class="f-field"><label class="f-label">الهاتف</label><input class="inp num" id="sPhone" dir="ltr"></div>
              <div class="f-field"><label class="f-label">البريد الإلكتروني</label><input class="inp" id="sEmail"></div>
            </div>
            <div class="f-field" style="margin-bottom:0"><label class="f-label">العنوان</label><textarea class="inp" id="sAddr" rows="2"></textarea></div>
          </div>
        </div>
        <div class="card">
          <div class="card-h"><h3>التفضيلات</h3></div>
          <div class="card-b">
            <div class="f-row">
              <div class="f-field"><label class="f-label">العملة</label>
                <select class="inp" id="sCurr">
                  <option value="JOD">د.أ دينار أردني</option><option value="USD">$ دولار أمريكي</option><option value="EUR">€ يورو</option><option value="GBP">£ جنيه إسترليني</option>
                  <option value="INR">₹ روبية</option><option value="AED">AED درهم إماراتي</option>
                </select>
              </div>
              <div class="f-field"><label class="f-label">نسبة الضريبة (%)</label><input type="number" min="0" max="50" class="inp" id="sTax"></div>
            </div>
            <div class="f-field"><label class="f-label">حد التنبيه للمخزون المنخفض (وحدة)</label><input type="number" min="0" max="999" class="inp" id="sLow"></div>
            <div class="f-field"><label class="f-label">المظهر</label>
              <div class="chips">
                <button class="chip" id="thLight" onclick="setTheme('light')">☀️ فاتح</button>
                <button class="chip" id="thDark" onclick="setTheme('dark')">🌙 داكن</button>
              </div>
            </div>
            <div class="f-field"><label class="f-label">ملاحظة أسفل الفاتورة</label><input class="inp" id="sInvNote"></div>
            <div class="f-field" style="margin-bottom:0"><label class="f-label">ملاحظة فاتورة الصيانة</label><input class="inp" id="sMntNote"></div>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-h">
          <h3>النسخ الاحتياطي</h3>
          <button class="btn btn-soft" style="padding:7px 13px;font-size:12.5px" onclick="loadBackups()"><svg class="ic" style="width:14px;height:14px"><use href="#i-refresh"/></svg>تحديث</button>
        </div>
        <div class="card-b">
          <div class="mini-stats wide" id="bkStats"></div>
          <div class="f-row" style="margin-top:14px">
            <div class="f-field">
              <label class="f-label">مجلد ملفات النسخ الاحتياطي (Backup file url)</label>
              <div style="display:flex;gap:8px">
                <input class="inp path-pill" id="bkDir" dir="ltr" readonly>
                <button class="btn btn-outline" onclick="copyBackupDir()" title="نسخ المسار">نسخ</button>
              </div>
            </div>
            <div class="f-field">
              <label class="f-label">أداة النسخ (mysqldump)</label>
              <input class="inp" id="bkTool" readonly>
            </div>
          </div>
          <div class="hint-line" id="bkHintBox" style="margin-bottom:14px"></div>
          <div class="toolbar-actions">
            <button class="btn btn-primary" onclick="createBackup()"><svg class="ic"><use href="#i-down"/></svg>إنشاء نسخة احتياطية الآن</button>
          </div>
          <div class="tbl-scroll" style="margin-top:14px">
            <table class="tbl">
              <thead><tr><th>الملف</th><th>النوع</th><th>الحجم</th><th>التاريخ</th><th class="right">إجراءات</th></tr></thead>
              <tbody id="bkBody"></tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-h"><h3>البيانات</h3><span class="hint">قاعدة البيانات: novacell_stock · يتم إنشاء نسخة احتياطية تلقائياً قبل أي حذف</span></div>
        <div class="card-b" style="display:flex;gap:14px;justify-content:space-between;align-items:center;flex-wrap:wrap">
          <span class="sub" style="margin:0">«إعادة تحميل البيانات التجريبية» تُعيد الكتالوج التجريبي، و«حذف كل البيانات» يُفرّغ النظام بالكامل مع الإبقاء على الإعدادات.</span>
          <div class="toolbar-actions">
            <button class="btn btn-outline" onclick="resetData()"><svg class="ic"><use href="#i-refresh"/></svg>إعادة تحميل البيانات التجريبية</button>
            <button class="btn btn-danger" onclick="wipeData()"><svg class="ic"><use href="#i-trash"/></svg>حذف كل بيانات النظام</button>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-h"><h3>دليل سريع</h3><span class="hint">شرح مختصر لأقسام النظام</span></div>
        <div class="card-b" style="display:flex;justify-content:space-between;gap:14px;align-items:center;flex-wrap:wrap">
          <span class="sub" style="margin:0">تعرّف على طريقة إضافة المخزون وتسجيل الفواتير والصيانة في دقيقة واحدة.</span>
          <button class="btn btn-soft" onclick="openHelp()"><b style="font-weight:800">؟</b>عرض الدليل</button>
        </div>
      </div>
    </section>
  </div>
</div>

<!-- ======== bottom nav (mobile) ======== -->
<nav class="bottom-nav">
  <button class="bn-item" data-nav="dashboard"><svg class="ic"><use href="#i-home"/></svg><span>الرئيسية</span></button>
  <button class="bn-item" data-nav="inventory"><svg class="ic"><use href="#i-box"/></svg><span>المخزون</span></button>
  <button class="bn-fab" onclick="openSheet()" aria-label="إجراءات سريعة"><svg class="ic"><use href="#i-plus"/></svg></button>
  <button class="bn-item" data-nav="invoices"><svg class="ic"><use href="#i-receipt"/></svg><span>الفواتير</span></button>
  <button class="bn-item" data-nav="maintenance"><svg class="ic"><use href="#i-wrench"/></svg><span>الصيانة</span></button>
  <button class="bn-item" data-nav="expenses"><svg class="ic"><use href="#i-bolt"/></svg><span>المصروفات</span></button>
</nav>

<!-- ======== notifications ======== -->
<div class="pop" id="notifPop"></div>

<!-- ======== modals ======== -->
<div class="overlay" id="ovBill">
  <div class="modal modal-lg">
    <div class="modal-h"><b>فاتورة جديدة</b><button class="icon-btn" onclick="closeOverlay('#ovBill')" aria-label="إغلاق"><svg class="ic"><use href="#i-x"/></svg></button></div>
    <div class="modal-b">
      <div class="f-row">
        <div class="f-field"><label class="f-label">اسم العميل</label><input class="inp" id="billCustomer" placeholder="مثال: سارة إدريس"></div>
        <div class="f-field"><label class="f-label">رقم الهاتف</label><input class="inp num" id="billPhone" placeholder="07xx xxx xxxx" dir="ltr"></div>
      </div>
      <div class="f-row">
        <div class="f-field"><label class="f-label">تاريخ الفاتورة</label><input type="date" class="inp" id="billDate"></div>
        <div class="f-field"><label class="f-label">حالة الدفع</label>
          <select class="inp" id="billStatusSel"><option value="Paid">مدفوعة</option><option value="Pending">قيد الانتظار</option><option value="Overdue">متأخرة</option></select>
        </div>
      </div>
      <div class="f-row">
        <div class="f-field"><label class="f-label">طريقة الدفع</label>
          <select class="inp" id="billPay"><option value="Cash">نقدي</option><option value="Card">بطاقة</option><option value="Transfer">تحويل</option></select>
        </div>
        <div class="f-field"><label class="f-label">الخصم (<span class="cur">$</span>)</label><input type="number" min="0" step="0.01" class="inp" id="billDiscount" placeholder="0"></div>
      </div>
      <label class="f-label">اختر الأصناف من المخزون <span class="hint">كل ضغطة «إضافة» تضيف قطعة في سطر مستقل</span></label>
      <div class="ni-search"><input class="inp" id="billItemQ" placeholder="ابحث في المخزون…"></div>
      <div class="ni-list" id="billItems"></div>
      <label class="f-label" style="margin-top:14px">بنود الفاتورة — لكل سطر كميته وسعره <span class="hint" id="billLinesCount">—</span></label>
      <div class="bl-list" id="billLines"></div>
      <div class="sum-box">
        <div class="sum-row"><span>المجموع</span><b id="billSub">$0</b></div>
        <div class="sum-row"><span>الخصم (يُخصم من الربح)</span><b id="billDisc">$0</b></div>
        <div class="sum-row"><span>الضريبة (<span id="billTaxRate">0</span>%)</span><b id="billTax">$0</b></div>
        <div class="sum-row grand"><span>الإجمالي المستحق</span><span id="billTotal">$0</span></div>
      </div>
      <div class="switch-hint">الخصم يُحسم من الأرباح فقط ولا يغيّر الإجمالي المستحق.
        <span id="billProfitHint">الربح المتوقع: $0</span> — داخلي ولا يظهر في الفاتورة المطبوعة.</div>
    </div>
    <div class="modal-f">
      <button class="btn btn-outline" onclick="closeOverlay('#ovBill')">إلغاء</button>
      <button class="btn btn-primary" onclick="createBill()"><svg class="ic"><use href="#i-check"/></svg>إنشاء الفاتورة</button>
    </div>
  </div>
</div>
<div class="overlay" id="ovItem">
  <div class="modal">
    <div class="modal-h"><b id="itemModalTitle">صنف مخزون جديد</b><button class="icon-btn" onclick="closeOverlay('#ovItem')" aria-label="إغلاق"><svg class="ic"><use href="#i-x"/></svg></button></div>
    <div class="modal-b">
      <input type="hidden" id="itemId">
      <div class="f-field"><label class="f-label">اسم الصنف</label><input class="inp" id="itemName" placeholder="مثال: آيفون 16 برو 256 جيجا"></div>
      <div class="f-row">
        <div class="f-field"><label class="f-label">التصنيف</label><select class="inp" id="itemCat"></select></div>
        <div class="f-field"><label class="f-label">الكمية المتوفرة</label><input type="number" min="0" class="inp" id="itemQty" placeholder="0"></div>
      </div>
      <div class="f-row">
        <div class="f-field"><label class="f-label">سعر البيع (<span class="cur">$</span>)</label><input type="number" min="0" step="0.01" class="inp" id="itemPrice" placeholder="999"></div>
        <div class="f-field"><label class="f-label">سعر الجملة (<span class="cur">$</span>)</label><input type="number" min="0" step="0.01" class="inp" id="itemWholesale" placeholder="850"></div>
      </div>
      <div class="f-row">
        <div class="f-field"><label class="f-label">ربح الوحدة (<span class="cur">$</span>) <span class="auto-tag" id="itemProfitTag">تلقائي</span></label><input type="number" min="0" step="0.01" class="inp" id="itemProfit" placeholder="150"></div>
        <div class="f-field"><label class="f-label">التنبيه عند وصول الكمية إلى</label><input type="number" min="0" class="inp" id="itemLow" placeholder="3"></div>
      </div>
      <div class="f-field" style="margin-bottom:0"><label class="f-label">تاريخ هذا الإدخال</label><input type="date" class="inp" id="itemDate"></div>
      <div class="switch-hint">الربح يُحسب تلقائياً = <b>سعر البيع − سعر الجملة</b> (وعند ترك سعر الجملة فارغاً يمكنك كتابة الربح يدوياً). الربح داخلي فقط ولا يظهر في الفاتورة المطبوعة.</div>
    </div>
    <div class="modal-f">
      <button class="btn btn-outline" onclick="closeOverlay('#ovItem')">إلغاء</button>
      <button class="btn btn-primary" onclick="saveItem()"><svg class="ic"><use href="#i-check"/></svg>حفظ الصنف</button>
    </div>
  </div>
</div>

<div class="overlay" id="ovStock">
  <div class="modal">
    <div class="modal-h"><b>حركة مخزون</b><button class="icon-btn" onclick="closeOverlay('#ovStock')" aria-label="إغلاق"><svg class="ic"><use href="#i-x"/></svg></button></div>
    <div class="modal-b">
      <div class="f-field"><label class="f-label">الصنف</label><select class="inp" id="stockItem"></select></div>
      <div class="f-field">
        <label class="f-label">نوع الحركة</label>
        <div class="seg" id="stockSeg">
          <button class="on in" data-t="in" onclick="setStockType('in')"><svg class="ic" style="width:15px;height:15px"><use href="#i-down"/></svg>إضافة كمية</button>
          <button class="out" data-t="out" onclick="setStockType('out')"><svg class="ic" style="width:15px;height:15px"><use href="#i-up"/></svg>خصم كمية</button>
        </div>
      </div>
      <div class="f-row">
        <div class="f-field"><label class="f-label">الكمية</label><input type="number" min="1" class="inp" id="stockQty" placeholder="1"></div>
        <div class="f-field"><label class="f-label">التاريخ</label><input type="date" class="inp" id="stockDate"></div>
      </div>
      <div class="f-row">
        <div class="f-field"><label class="f-label">السعر (<span class="cur">$</span>)</label><input type="number" min="0" step="0.01" class="inp" id="stockPrice"></div>
        <div class="f-field"><label class="f-label">ربح الوحدة (<span class="cur">$</span>)</label><input type="number" min="0" step="0.01" class="inp" id="stockProfit"></div>
      </div>
      <div class="f-field" style="margin-bottom:0"><label class="f-label">ملاحظة</label><input class="inp" id="stockNote" placeholder="مثال: تزويد من المورد"></div>
    </div>
    <div class="modal-f">
      <button class="btn btn-outline" onclick="closeOverlay('#ovStock')">إلغاء</button>
      <button class="btn btn-primary" onclick="saveStock()"><svg class="ic"><use href="#i-check"/></svg>حفظ الحركة</button>
    </div>
  </div>
</div>
<div class="overlay" id="ovLedger">
  <div class="modal">
    <div class="modal-h"><b>تعديل حركة مخزون</b><button class="icon-btn" onclick="closeOverlay('#ovLedger')" aria-label="إغلاق"><svg class="ic"><use href="#i-x"/></svg></button></div>
    <div class="modal-b">
      <input type="hidden" id="ledgerId">
      <div class="f-field"><label class="f-label">الصنف</label><input class="inp" id="ledgerItem" disabled></div>
      <div class="f-row">
        <div class="f-field"><label class="f-label">الكمية</label><input type="number" min="1" class="inp" id="ledgerQty"></div>
        <div class="f-field"><label class="f-label">التاريخ</label><input type="date" class="inp" id="ledgerDate"></div>
      </div>
      <div class="f-row">
        <div class="f-field"><label class="f-label">السعر (<span class="cur">$</span>)</label><input type="number" min="0" step="0.01" class="inp" id="ledgerPrice"></div>
        <div class="f-field"><label class="f-label">ربح الوحدة (<span class="cur">$</span>)</label><input type="number" min="0" step="0.01" class="inp" id="ledgerProfit"></div>
      </div>
      <div class="f-field" style="margin-bottom:0"><label class="f-label">ملاحظة</label><input class="inp" id="ledgerNote"></div>
      <div class="switch-hint">سيتم تعديل كمية الصنف تلقائياً حسب التغيير في هذه الحركة.</div>
    </div>
    <div class="modal-f">
      <button class="btn btn-outline" onclick="closeOverlay('#ovLedger')">إلغاء</button>
      <button class="btn btn-primary" onclick="saveLedger()"><svg class="ic"><use href="#i-check"/></svg>تحديث الحركة</button>
    </div>
  </div>
</div>

<div class="overlay" id="ovMnt">
  <div class="modal">
    <div class="modal-h"><b id="mntModalTitle">فاتورة صيانة جديدة</b><button class="icon-btn" onclick="closeOverlay('#ovMnt')" aria-label="إغلاق"><svg class="ic"><use href="#i-x"/></svg></button></div>
    <div class="modal-b">
      <input type="hidden" id="mntId">
      <div class="f-field"><label class="f-label">الاسم أو وصف العمل</label><input class="inp" id="mntName" placeholder="مثال: آيفون 13 — تبديل شاشة"></div>
      <div class="f-field"><label class="f-label">التصنيف</label><select class="inp" id="mntCatSel"></select></div>
      <div class="f-row">
        <div class="f-field"><label class="f-label">السعر (<span class="cur">$</span>)</label><input type="number" min="0" step="0.01" class="inp" id="mntPrice" placeholder="0"></div>
        <div class="f-field"><label class="f-label">الربح (<span class="cur">$</span>)</label><input type="number" min="0" step="0.01" class="inp" id="mntProfit" placeholder="0"></div>
      </div>
      <div class="f-field"><label class="f-label">التاريخ</label><input type="date" class="inp" id="mntDate"></div>
      <div class="f-field" style="margin-bottom:0"><label class="f-label">ملاحظة</label><input class="inp" id="mntNote" placeholder="القطع المستخدمة، الفني…"></div>
      <div class="switch-hint">الربح يظهر في التقارير الداخلية فقط، ولا يُطبع على فاتورة الصيانة.</div>
    </div>
    <div class="modal-f">
      <button class="btn btn-outline" onclick="closeOverlay('#ovMnt')">إلغاء</button>
      <button class="btn btn-primary" onclick="saveMaintenance()"><svg class="ic"><use href="#i-check"/></svg>حفظ الفاتورة</button>
    </div>
  </div>
</div>

<div class="overlay" id="ovSupBill">
  <div class="modal">
    <div class="modal-h"><b id="supModalTitle">قطعة مورد جديدة</b><button class="icon-btn" onclick="closeOverlay('#ovSupBill')" aria-label="إغلاق"><svg class="ic"><use href="#i-x"/></svg></button></div>
    <div class="modal-b">
      <input type="hidden" id="supBillId">
      <div class="f-field"><label class="f-label">المورد</label><select class="inp" id="supBillSupplier"></select></div>
      <div class="f-field"><label class="f-label">القطعة / الوصف</label><input class="inp" id="supBillName" placeholder="مثال: شاشة آيفون 14 برو ماكس"></div>
      <div class="f-row">
        <div class="f-field"><label class="f-label">المبلغ (<span class="cur">$</span>)</label><input type="number" min="0" step="0.01" class="inp" id="supBillAmount" placeholder="50"></div>
        <div class="f-field"><label class="f-label">الحالة</label>
          <select class="inp" id="supBillStatus"><option value="Due">مستحق (لم يُدفع)</option><option value="Paid">مدفوع</option></select>
        </div>
      </div>
      <div class="f-field"><label class="f-label">التاريخ</label><input type="date" class="inp" id="supBillDate"></div>
      <div class="f-field" style="margin-bottom:0"><label class="f-label">ملاحظة</label><input class="inp" id="supBillNote" placeholder="رقم الفاتورة، الكمية…"></div>
      <div class="switch-hint">هذه القطعة تُسجَّل في سجل الموردين فقط — لا تدخل في إيرادات الفواتير ولا في الأرباح.</div>
    </div>
    <div class="modal-f">
      <button class="btn btn-outline" onclick="closeOverlay('#ovSupBill')">إلغاء</button>
      <button class="btn btn-primary" onclick="saveSupBill()"><svg class="ic"><use href="#i-check"/></svg>حفظ القطعة</button>
    </div>
  </div>
</div>

<div class="overlay" id="ovExp">
  <div class="modal">
    <div class="modal-h"><b id="expModalTitle">مصروف جديد</b><button class="icon-btn" onclick="closeOverlay('#ovExp')" aria-label="إغلاق"><svg class="ic"><use href="#i-x"/></svg></button></div>
    <div class="modal-b">
      <input type="hidden" id="expId">
      <div class="f-field"><label class="f-label">اسم المصروف</label><input class="inp" id="expName" placeholder="مثال: اشتراك الإنترنت (Wi-Fi)"></div>
      <div class="f-row">
        <div class="f-field"><label class="f-label">النوع</label><select class="inp" id="expKindSel"></select></div>
        <div class="f-field"><label class="f-label">المبلغ (<span class="cur">$</span>)</label><input type="number" min="0" step="0.01" class="inp" id="expAmount" placeholder="0"></div>
      </div>
      <div class="switch-hint" style="margin:-6px 0 14px">الأنواع هي التصنيفات المُفعّلة لقسم «المصروفات» — أضِف نوعاً جديداً من شاشة التصنيفات.</div>
      <div class="f-field"><label class="f-label">التاريخ</label><input type="date" class="inp" id="expDate"></div>
      <div class="f-field" style="margin-bottom:0"><label class="f-label">ملاحظة</label><input class="inp" id="expNote" placeholder="المزوّد، رقم الفاتورة…"></div>
      <div class="switch-hint">المصروف يُطرح من الأرباح في لوحة التحكم ولا يُضاف إلى إيرادات الفواتير.</div>
    </div>
    <div class="modal-f">
      <button class="btn btn-outline" onclick="closeOverlay('#ovExp')">إلغاء</button>
      <button class="btn btn-primary" onclick="saveExpense()"><svg class="ic"><use href="#i-check"/></svg>حفظ المصروف</button>
    </div>
  </div>
</div>

<div class="overlay" id="ovPaper">
  <div class="modal modal-lg" id="paperModal"></div>
</div>

<div class="overlay sheet-ov" id="ovSheet">
  <div class="sheet">
    <div class="sheet-h"></div>
    <button class="qa" onclick="closeOverlay('#ovSheet');openBill()"><span class="qa-ic" style="background:#6153f4"><svg class="ic"><use href="#i-receipt"/></svg></span><span>فاتورة جديدة<small>تسجيل بيع وطبعه</small></span></button>
    <button class="qa" onclick="closeOverlay('#ovSheet');openItem()"><span class="qa-ic" style="background:#0ea5e9"><svg class="ic"><use href="#i-plus"/></svg></span><span>صنف جديد<small>إضافة منتج للمخزون</small></span></button>
    <button class="qa" onclick="closeOverlay('#ovSheet');openStock(null,'in')"><span class="qa-ic" style="background:#10b981"><svg class="ic"><use href="#i-down"/></svg></span><span>إضافة أو خصم كمية<small>حركة بتاريخ محدد</small></span></button>
    <button class="qa" onclick="closeOverlay('#ovSheet');openMaintenance()"><span class="qa-ic" style="background:#f59e0b"><svg class="ic"><use href="#i-wrench"/></svg></span><span>فاتورة صيانة<small>قسم الإصلاح</small></span></button>
    <button class="qa" onclick="closeOverlay('#ovSheet');openExpense()"><span class="qa-ic" style="background:#ef4444"><svg class="ic"><use href="#i-bolt"/></svg></span><span>مصروف جديد<small>كهرباء، إنترنت، إيجار…</small></span></button>
    <button class="qa" onclick="closeOverlay('#ovSheet');openSupBill()"><span class="qa-ic" style="background:#0ea5e9"><svg class="ic"><use href="#i-truck"/></svg></span><span>قطعة مورد<small>سجل الموردين — خارج الأرباح</small></span></button>
    <button class="qa" onclick="closeOverlay('#ovSheet');printStockList()"><span class="qa-ic" style="background:#8b5cf6"><svg class="ic"><use href="#i-printer"/></svg></span><span>طباعة قائمة المخزون<small>كشف كامل بالأصناف</small></span></button>
    <button class="qa" onclick="closeOverlay('#ovSheet');openHelp()"><span class="qa-ic" style="background:#0ea5e9"><b style="font-weight:800;font-size:18px">؟</b></span><span>دليل الاستخدام<small>خطوات سريعة</small></span></button>
    <button class="qa" onclick="closeOverlay('#ovSheet');toggleTheme()"><span class="qa-ic" style="background:#64748b"><svg class="ic"><use href="#i-moon"/></svg></span><span>تغيير المظهر<small>فاتح / داكن</small></span></button>
  </div>
</div>

<div class="overlay pal-ov" id="ovPal">
  <div class="pal">
    <div class="pal-in"><svg class="ic"><use href="#i-search"/></svg><input id="palInput" placeholder="اكتب اسم صفحة أو صنف أو فاتورة أو صيانة…"><span class="kbd">esc</span></div>
    <div class="pal-list" id="palList"></div>
  </div>
</div>

<!-- ======== help / quick guide ======== -->
<div class="overlay" id="ovHelp">
  <div class="modal modal-lg">
    <div class="modal-h"><b>دليل الاستخدام السريع</b><button class="icon-btn" onclick="closeOverlay('#ovHelp')" aria-label="إغلاق"><svg class="ic"><use href="#i-x"/></svg></button></div>
    <div class="modal-b">
      <div class="help-list">
        <div class="help-item"><div class="hi-ic" style="background:#6153f4"><svg class="ic" style="width:18px;height:18px"><use href="#i-tag"/></svg></div>
          <div><b>1) ابدأ بالتصنيفات</b><small>من قسم «التصنيفات» أضف تصنيفاتك مثل آيفون وسامسونج، وحدّد بـ«يظهر في الأقسام» أين يظهر التصنيف: المخزون أو الصيانة أو المصروفات — أو أكثر من قسم. لا يمكن حذف تصنيف مستخدم، لكن يمكنك نقل أصنافه إلى تصنيف آخر ثم حذفه.</small></div></div>
        <div class="help-item"><div class="hi-ic" style="background:#0ea5e9"><svg class="ic" style="width:18px;height:18px"><use href="#i-box"/></svg></div>
          <div><b>2) أضف أصناف المخزون</b><small>من «المخزون» اضغط «صنف جديد» وأدخل الاسم، التصنيف، سعر البيع، سعر الجملة (ويُحسب الربح تلقائياً = البيع − الجملة)، والكمية. كل إضافة تُسجَّل بتاريخ يمكن الفلترة به لاحقاً.</small></div></div>
        <div class="help-item"><div class="hi-ic" style="background:#10b981"><svg class="ic" style="width:18px;height:18px"><use href="#i-down"/></svg></div>
          <div><b>3) حرّك الكميات</b><small>«+ / −» لتعديل سريع بكمية 1، أو «إضافة/خصم كمية» لتسجيل حركة بتاريخ وملاحظة. يمكنك تعديل أو حذف أي حركة، وتُحدَّث الكمية تلقائياً.</small></div></div>
        <div class="help-item"><div class="hi-ic" style="background:#f59e0b"><svg class="ic" style="width:18px;height:18px"><use href="#i-receipt"/></svg></div>
          <div><b>4) أصدر الفواتير</b><small>من «الفواتير» اضغط «فاتورة جديدة»، أضف العميل، ثم أضف كل قطعة من قائمة المخزون — كل قطعة تُضاف في سطر مستقل يمكنك تغيير سعرها فيه (مثلاً جوالان: واحد بـ120 وآخر بـ110)، وأي زيادة على السعر تُضاف إلى الربح. تُخصم الكميات من المخزون فوراً، ولا تظهر الأرباح في الفاتورة.</small></div></div>
        <div class="help-item"><div class="hi-ic" style="background:#ef4444"><svg class="ic" style="width:18px;height:18px"><use href="#i-wrench"/></svg></div>
          <div><b>5) سجّل الصيانة</b><small>من قسم «الصيانة» سجّل العمل والتصنيف والسعر والربح، ثم استخدم الفلتر الزمني واطبع فاتورة الصيانة عند الحاجة.</small></div></div>
        <div class="help-item"><div class="hi-ic" style="background:#14b8a6"><svg class="ic" style="width:18px;height:18px"><use href="#i-bolt"/></svg></div>
          <div><b>6) سجّل مصروفات المحل</b><small>من قسم «المصروفات» أضف فواتير الكهرباء والإنترنت والإيجار والرواتب. تظهر في لوحة التحكم وتُطرح من الأرباح لإظهار صافي الربح.</small></div></div>
        <div class="help-item"><div class="hi-ic" style="background:#0ea5e9"><svg class="ic" style="width:18px;height:18px"><use href="#i-truck"/></svg></div>
          <div><b>7) فواتير الموردين</b><small>من قسم «فواتير الموردين» أضف أسماء الموردين (مثال: محمد الاعور) ثم قطعهم وأسعارها، وحدّد لكل قطعة <b>مستحق</b> أو <b>مدفوع</b> مع تصفية حسب المورد. <b>هذا القسم لا يدخل في الإيرادات ولا في الأرباح إطلاقاً</b> — سجل داخلي فقط.</small></div></div>
        <div class="help-item"><div class="hi-ic" style="background:#8b5cf6"><svg class="ic" style="width:18px;height:18px"><use href="#i-trend"/></svg></div>
          <div><b>8) تابع الأداء</b><small>لوحة التحكم تعرض الإيرادات والأرباح والمصروفات وصافي الربح والرسوم البيانية. اضغط <b>Ctrl + K</b> للبحث السريع عن أي صنف أو فاتورة.</small></div></div>
      </div>
    </div>
    <div class="modal-f">
      <button class="btn btn-primary" onclick="closeOverlay('#ovHelp')"><svg class="ic"><use href="#i-check"/></svg>تمام، فهمت</button>
    </div>
  </div>
</div>

<div class="toasts" id="toasts"></div>
<div id="printRoot"></div>

<script>window.APP = <?php echo json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<script src="./assets/vendor/jquery-3.7.1.min.js"></script>
<script src="./assets/vendor/chart.umd.min.js"></script>
<script src="./assets/js/app.js?v=<?php echo (int) @filemtime(__DIR__ . '/assets/js/app.js'); ?>"></script>

  </div>
</div>

</body>
</html>
