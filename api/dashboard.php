<?php
/**
 * Dashboard API — KPIs plus every dataset the charts draw.
 */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

api_run(static function (): array {
    $pdo   = db();
    $low   = (int) settings_payload()['low_stock'];

    $to        = fdate(req('to'), today());
    $from      = fdate(req('from'), date_shift($to, -29));
    $chartTo   = fdate(req('chart_to'), $to);
    $chartFrom = fdate(req('chart_from'), date_shift($chartTo, -13));

    /* ---------------- KPIs (window: from → to) ---------------- */
    $revenue = (float) scalar($pdo, 'SELECT COALESCE(SUM(total), 0) FROM invoices WHERE entry_date BETWEEN ? AND ?', [$from, $to], 0);
    $billProfit = (float) scalar($pdo, 'SELECT COALESCE(SUM(profit), 0) FROM invoices WHERE entry_date BETWEEN ? AND ?', [$from, $to], 0);
    $mntCount   = (int) scalar($pdo, 'SELECT COUNT(*) FROM maintenance WHERE entry_date BETWEEN ? AND ?', [$from, $to], 0);
    $mntPrice   = (float) scalar($pdo, 'SELECT COALESCE(SUM(price), 0) FROM maintenance WHERE entry_date BETWEEN ? AND ?', [$from, $to], 0);
    $mntProfit  = (float) scalar($pdo, 'SELECT COALESCE(SUM(profit), 0) FROM maintenance WHERE entry_date BETWEEN ? AND ?', [$from, $to], 0);
    $expCount   = (int) scalar($pdo, 'SELECT COUNT(*) FROM bills WHERE entry_date BETWEEN ? AND ?', [$from, $to], 0);
    $expTotal   = (float) scalar($pdo, 'SELECT COALESCE(SUM(amount), 0) FROM bills WHERE entry_date BETWEEN ? AND ?', [$from, $to], 0);

    /* Suppliers ledger: shown as a reminder only — it NEVER touches
       revenue, profit or net profit. Due = all unpaid parts (all time). */
    $supDue   = round((float) scalar($pdo, "SELECT COALESCE(SUM(amount), 0) FROM supplier_bills WHERE status = 'Due'", [], 0), 2);
    $supPaid  = round((float) scalar($pdo, "SELECT COALESCE(SUM(amount), 0) FROM supplier_bills WHERE status = 'Paid'", [], 0), 2);
    $supParts = (int) scalar($pdo, "SELECT COUNT(*) FROM supplier_bills WHERE status = 'Due'", [], 0);

    $kpis = [
        'from'                => $from,
        'to'                  => $to,
        'revenue'             => round($revenue, 2),
        'bill_profit'         => round($billProfit, 2),
        'maintenance_count'   => $mntCount,
        'maintenance_price'   => round($mntPrice, 2),
        'maintenance_profit'  => round($mntProfit, 2),
        'profit'              => round($billProfit + $mntProfit, 2),
        'expenses'            => round($expTotal, 2),
        'expenses_count'      => $expCount,
        'net_profit'          => round($billProfit + $mntProfit - $expTotal, 2),
        'supplier_due'        => $supDue,
        'supplier_paid'       => $supPaid,
        'supplier_due_parts'  => $supParts,
        'income'              => round($revenue + $mntPrice, 2),
        'bills'               => (int) scalar($pdo, 'SELECT COUNT(*) FROM invoices WHERE entry_date BETWEEN ? AND ?', [$from, $to], 0),
        'units_sold'          => (int) scalar($pdo, 'SELECT COALESCE(SUM(ii.qty), 0) FROM invoice_items ii
                                                    JOIN invoices v ON v.id = ii.invoice_id
                                                    WHERE v.entry_date BETWEEN ? AND ?', [$from, $to], 0),
        'items'               => (int) scalar($pdo, 'SELECT COUNT(*) FROM items', [], 0),
        'units_in_stock'      => (int) scalar($pdo, 'SELECT COALESCE(SUM(qty), 0) FROM items', [], 0),
        /* إجمالي قيمة المخزون = مجموع (الكمية × سعر البيع) لكل الأصناف — كل الوقت. */
        'stock_value'         => round((float) scalar($pdo, 'SELECT COALESCE(SUM(qty * price), 0) FROM items', [], 0), 2),
        'stock_cost'          => round((float) scalar($pdo, 'SELECT COALESCE(SUM(qty * COALESCE(wholesale, 0)), 0) FROM items', [], 0), 2),
        'stock_potential'     => round((float) scalar($pdo, 'SELECT COALESCE(SUM(qty * profit), 0) FROM items', [], 0), 2),
        'low_stock'           => (int) scalar($pdo, 'SELECT COUNT(*) FROM items WHERE qty <= IF(low_stock > 0, low_stock, ?)', [$low], 0),
    ];

    /* ---------------- chart series (window: chart_from → chart_to) ---------------- */
    $labels = [];
    $days   = 0;
    for ($d = $chartFrom; $d <= $chartTo && $days < 92; $d = date_shift($d, 1)) {
        $labels[] = $d;
        $days++;
    }
    if (!count($labels)) {
        $labels = [$chartTo];
    }

    $billMap = [];
    foreach (q($pdo, 'SELECT entry_date, SUM(total) AS total, SUM(profit) AS profit
                      FROM invoices
                      WHERE entry_date BETWEEN ? AND ?
                      GROUP BY entry_date', [$chartFrom, $chartTo]) as $r) {
        $billMap[(string) $r['entry_date']] = ['total' => (float) $r['total'], 'profit' => (float) $r['profit']];
    }
    $mntMap = [];
    foreach (q($pdo, 'SELECT entry_date, SUM(price) AS price, SUM(profit) AS profit
                      FROM maintenance
                      WHERE entry_date BETWEEN ? AND ?
                      GROUP BY entry_date', [$chartFrom, $chartTo]) as $r) {
        $mntMap[(string) $r['entry_date']] = ['price' => (float) $r['price'], 'profit' => (float) $r['profit']];
    }

    $expMap = [];
    foreach (q($pdo, 'SELECT entry_date, SUM(amount) AS amount
                      FROM bills
                      WHERE entry_date BETWEEN ? AND ?
                      GROUP BY entry_date', [$chartFrom, $chartTo]) as $r) {
        $expMap[(string) $r['entry_date']] = (float) $r['amount'];
    }

    $bills = $mnt = $expenses = $profit = [];
    foreach ($labels as $day) {
        $bills[]    = round($billMap[$day]['total'] ?? 0.0, 2);
        $mnt[]      = round($mntMap[$day]['price'] ?? 0.0, 2);
        $expenses[] = round($expMap[$day] ?? 0.0, 2);
        $profit[]   = round(($billMap[$day]['profit'] ?? 0.0) + ($mntMap[$day]['profit'] ?? 0.0), 2);
    }

    /* ---------------- sales by category (doughnut) ---------------- */
    $categories = array_map(static fn ($r) => [
        'name'  => (string) $r['name'],
        'color' => (string) $r['color'],
        'units' => (int) $r['units'],
        'value' => round((float) $r['value'], 2),
    ], q($pdo, "SELECT COALESCE(c.name, 'Uncategorised') AS name,
                       COALESCE(c.color, '#94a3b8')      AS color,
                       SUM(ii.qty)                       AS units,
                       SUM(ii.amount)                    AS value
                FROM invoice_items ii
                JOIN invoices v ON v.id = ii.invoice_id
                LEFT JOIN categories c ON c.id = ii.category_id
                WHERE v.entry_date BETWEEN ? AND ?
                GROUP BY c.id, c.name, c.color
                ORDER BY value DESC
                LIMIT 8", [$from, $to]));

    /* ---------------- top sellers ---------------- */
    $topItems = array_map(static fn ($r) => [
        'name'  => (string) $r['name'],
        'units' => (int) $r['units'],
        'value' => round((float) $r['value'], 2),
    ], q($pdo, 'SELECT ii.name, SUM(ii.qty) AS units, SUM(ii.amount) AS value
                FROM invoice_items ii
                JOIN invoices v ON v.id = ii.invoice_id
                WHERE v.entry_date BETWEEN ? AND ?
                GROUP BY ii.name
                ORDER BY units DESC, value DESC
                LIMIT 6', [$from, $to]));

    /* ---------------- bill status split ---------------- */
    $statuses = [];
    foreach (q($pdo, 'SELECT status, COUNT(*) AS c, COALESCE(SUM(total), 0) AS t
                      FROM invoices
                      WHERE entry_date BETWEEN ? AND ?
                      GROUP BY status', [$from, $to]) as $r) {
        $statuses[(string) $r['status']] = ['count' => (int) $r['c'], 'total' => round((float) $r['t'], 2)];
    }

    /* ---------------- recent activity ---------------- */
    $recentInvoices = array_map(static fn ($r) => [
        'id'         => (int) $r['id'],
        'code'       => (string) $r['code'],
        'customer'   => (string) $r['customer'],
        'status'     => (string) $r['status'],
        'entry_date' => (string) $r['entry_date'],
        'total'      => (float) $r['total'],
        'units'      => (int) $r['units'],
    ], q($pdo, 'SELECT v.id, v.code, v.customer, v.status, v.entry_date, v.total,
                       (SELECT COALESCE(SUM(ii.qty), 0) FROM invoice_items ii WHERE ii.invoice_id = v.id) AS units
                FROM invoices v
                ORDER BY v.entry_date DESC, v.id DESC
                LIMIT 6'));

    $recentMaintenance = array_map('maintenance_payload', q($pdo, 'SELECT m.*, c.name AS category_name, c.color AS category_color
                                                                 FROM maintenance m
                                                                 LEFT JOIN categories c ON c.id = m.category_id
                                                                 ORDER BY m.entry_date DESC, m.id DESC
                                                                 LIMIT 5'));

    $lowStockItems = array_map(static fn ($r) => [
        'id'          => (int) $r['id'],
        'name'        => (string) $r['name'],
        'category'    => $r['category_name'] ?? '—',
        'qty'         => (int) $r['qty'],
        'low_stock'   => (int) $r['low_stock'] > 0 ? (int) $r['low_stock'] : $low,
    ], q($pdo, 'SELECT i.id, i.name, i.qty, i.low_stock, c.name AS category_name
                FROM items i
                LEFT JOIN categories c ON c.id = i.category_id
                WHERE i.qty <= IF(i.low_stock > 0, i.low_stock, ?)
                ORDER BY i.qty ASC
                LIMIT 8', [$low]));

    return [
        'kpis'               => $kpis,
        'series'             => ['labels' => $labels, 'bills' => $bills, 'maintenance' => $mnt,
                                 'expenses' => $expenses, 'profit' => $profit],
        'categories'         => $categories,
        'top_items'          => $topItems,
        'statuses'           => $statuses,
        'recent_invoices'    => $recentInvoices,
        'recent_maintenance' => $recentMaintenance,
        'low_stock_items'    => $lowStockItems,
    ];
});

