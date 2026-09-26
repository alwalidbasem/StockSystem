<?php
/**
 * Bills / invoices API — sales created from inventory stock.
 *
 * NOTE: profit is stored for internal reporting, but the invoice payload the
 * UI prints or shows never renders it (see assets/js/app.js -> invoicePaper()).
 */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

/** Load one invoice together with its lines. */
function load_invoice(PDO $pdo, int $id): array
{
    $row = q1($pdo, 'SELECT * FROM invoices WHERE id = ?', [$id]);
    if (!$row) {
        throw new RuntimeException('هذه الفاتورة لم تعد موجودة.');
    }
    $lines = q($pdo, 'SELECT ii.*, c.name AS category_name
                      FROM invoice_items ii
                      LEFT JOIN categories c ON c.id = ii.category_id
                      WHERE ii.invoice_id = ?
                      ORDER BY ii.id ASC', [$id]);
    return invoice_payload($row, $lines);
}

function invoice_summary(PDO $pdo, array $rows): array
{
    $units = 0;
    foreach ($rows as $r) {
        $units += (int) $r['units'];
    }
    $sum = static fn (string $key) => round(array_sum(array_map(static fn ($r) => (float) $r[$key], $rows)), 2);
    return [
        'count'    => count($rows),
        'units'    => $units,
        'revenue'  => $sum('total'),
        'profit'   => $sum('profit'),
        'paid'     => count(array_filter($rows, static fn ($r) => $r['status'] === 'Paid')),
        'pending'  => count(array_filter($rows, static fn ($r) => $r['status'] === 'Pending')),
        'overdue'  => count(array_filter($rows, static fn ($r) => $r['status'] === 'Overdue')),
    ];
}

api_run(static function (): array {
    $pdo    = db();
    $action = action_name();

    /* ---------------- list (date filter + status + search) ---------------- */
    if ($action === 'list') {
        $params = [];
        $sql    = 'SELECT v.*,
                          (SELECT COALESCE(SUM(ii.qty), 0) FROM invoice_items ii WHERE ii.invoice_id = v.id) AS units,
                          (SELECT COUNT(*) FROM invoice_items ii WHERE ii.invoice_id = v.id)              AS line_count
                   FROM invoices v
                   WHERE 1 = 1';
        $sql   .= date_clause('v.entry_date', req('from'), req('to'), $params);

        $q = sstr(req('q', ''), 80);
        if ($q !== '') {
            $sql      .= ' AND (v.customer LIKE ? OR v.code LIKE ? OR v.phone LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        $status = (string) req('status', 'all');
        if (in_array($status, ['Paid', 'Pending', 'Overdue'], true)) {
            $sql      .= ' AND v.status = ?';
            $params[] = $status;
        }

        $rows = q($pdo, $sql . ' ORDER BY v.entry_date DESC, v.id DESC LIMIT 300', $params);
        $list = array_map(static fn ($r) => [
            'id'         => (int) $r['id'],
            'code'       => (string) $r['code'],
            'customer'   => (string) $r['customer'],
            'phone'      => (string) $r['phone'],
            'entry_date' => (string) $r['entry_date'],
            'status'     => (string) $r['status'],
            'pay_method' => (string) $r['pay_method'],
            'subtotal'   => (float) $r['subtotal'],
            'discount'   => (float) $r['discount'],
            'tax'        => (float) $r['tax'],
            'total'      => (float) $r['total'],
            'profit'     => (float) $r['profit'],
            'units'      => (int) $r['units'],
            'lines'      => (int) $r['line_count'],
        ], $rows);

        return ['invoices' => $list, 'summary' => invoice_summary($pdo, $list)];
    }

    /* ---------------- single bill ---------------- */
    if ($action === 'get') {
        $id = nid(req('id'));
        if ($id === null) {
            throw new RuntimeException('لم يتم تحديد الفاتورة.');
        }
        return ['invoice' => load_invoice($pdo, $id)];
    }

    /* ---------------- create a bill (deducts stock) ---------------- */
    if ($action === 'create') {
        $rows = req_array('items');
        $pdo->beginTransaction();
        try {
            $created = create_invoice_row($pdo, [
                'customer'   => req('customer'),
                'phone'      => req('phone'),
                'entry_date' => req('entry_date'),
                'status'     => req('status', 'Paid'),
                'pay_method' => req('pay_method', 'Cash'),
                'discount'   => req('discount', 0),
                'note'       => req('note', ''),
                'tax_rate'   => req('tax_rate', settings_payload()['tax']),
                'rows'       => $rows,
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return [
            'message' => 'تم إنشاء الفاتورة ' . $created['code'] . '.',
            'invoice' => load_invoice($pdo, $created['id']),
            'items'   => items_query('', null, 'all', (int) settings_payload()['low_stock']),
        ];
    }

    /* ---------------- update status / payment method ---------------- */
    if ($action === 'set_status') {
        $id = nid(req('id'));
        if ($id === null) {
            throw new RuntimeException('لم يتم تحديد الفاتورة.');
        }
        $row = q1($pdo, 'SELECT * FROM invoices WHERE id = ?', [$id]);
        if (!$row) {
            throw new RuntimeException('هذه الفاتورة لم تعد موجودة.');
        }
        $status = (string) req('status', $row['status']);
        if (!in_array($status, ['Paid', 'Pending', 'Overdue'], true)) {
            throw new RuntimeException('حالة الفاتورة غير صحيحة.');
        }
        $pdo->prepare('UPDATE invoices SET status = ?, pay_method = ? WHERE id = ?')
            ->execute([$status, sstr(req('pay_method', $row['pay_method']), 24), $id]);
        $labels = ['Paid' => 'مدفوعة', 'Pending' => 'قيد الانتظار', 'Overdue' => 'متأخرة'];
        return ['message' => 'تم تعليم الفاتورة ' . $row['code'] . ' كـ' . $labels[$status] . '.'];
    }

    /* ---------------- delete a bill (returns the units to stock) ---------------- */
    if ($action === 'delete') {
        $id = nid(req('id'));
        if ($id === null) {
            throw new RuntimeException('لم يتم تحديد الفاتورة.');
        }
        $row = q1($pdo, 'SELECT * FROM invoices WHERE id = ?', [$id]);
        if (!$row) {
            throw new RuntimeException('هذه الفاتورة لم تعد موجودة.');
        }
        $pdo->beginTransaction();
        restore_invoice_stock($pdo, $row);
        $pdo->prepare('DELETE FROM invoices WHERE id = ?')->execute([$id]);
        $pdo->commit();

        return [
            'message' => 'تم حذف الفاتورة ' . $row['code'] . ' وإرجاع الكميات إلى المخزون.',
            'items'   => items_query('', null, 'all', (int) settings_payload()['low_stock']),
        ];
    }

    throw new RuntimeException('إجراء غير معروف: ' . $action);
});

