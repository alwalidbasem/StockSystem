<?php
/**
 * Suppliers API — فواتير الموردين: the suppliers list plus the parts they
 * supplied, each line marked «مستحق» (still owed) or «مدفوع» (settled).
 *
 * IMPORTANT: this ledger is completely separate from the sales side — it never
 * touches the revenue, the profit, the net profit or any dashboard figure.
 *
 * actions: list · save · delete · part_save · part_status · part_delete
 */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

/** Everything the section needs after any change (suppliers + filtered parts). */
function sup_state(string $q, ?int $supplierId, string $status, string $from, string $to, string $supplierQuery = ''): array
{
    $parts     = supplier_bill_query($q, $supplierId, $status, $from, $to);
    $suppliers = suppliers_with_totals($supplierQuery);
    $due       = 0.0;
    $paid      = 0.0;
    $partsCount = 0;
    foreach ($suppliers as $s) {
        $due   += $s['due'];
        $paid  += $s['paid'];
        $partsCount += $s['parts'];
    }
    return [
        'suppliers' => $suppliers,
        'options'   => supplier_options(),
        'records'   => $parts,
        'summary'   => supplier_bill_summary($parts),
        'overall'   => [
            'suppliers' => count($suppliers),
            'parts'     => $partsCount,
            'due'       => round($due, 2),
            'paid'      => round($paid, 2),
            'total'     => round($due + $paid, 2),
        ],
    ];
}

api_run(static function (): array {
    $pdo    = db();
    $action = action_name();

    /* ---------------- suppliers + parts for the current filters ---------------- */
    if ($action === 'list' || $action === 'parts') {
        return sup_state(
            sstr(req('q', ''), 80),
            nid(req('supplier_id')),
            (string) req('status', 'all'),
            (string) req('from', ''),
            (string) req('to', ''),
            sstr(req('sq', ''), 80)
        );
    }

    /* ---------------- add / update a supplier ---------------- */
    if ($action === 'save') {
        $id    = nid(req('id'));
        $name  = sstr(req('name', ''), 120);
        $phone = sstr(req('phone', ''), 40);
        $note  = sstr(req('note', ''), 255);
        if ($name === '') {
            throw new RuntimeException('اسم المورد مطلوب — مثال: محمد الاعور.');
        }
        if (q1($pdo, 'SELECT id FROM suppliers WHERE LOWER(name) = LOWER(?) AND id <> ? LIMIT 1', [$name, $id ?? 0])) {
            throw new RuntimeException('يوجد مورد آخر بنفس الاسم.');
        }
        if ($id !== null) {
            if (!scalar($pdo, 'SELECT 1 FROM suppliers WHERE id = ?', [$id], 0)) {
                throw new RuntimeException('هذا المورد لم يعد موجوداً.');
            }
            $pdo->prepare('UPDATE suppliers SET name = ?, phone = ?, note = ? WHERE id = ?')
                ->execute([$name, $phone, $note, $id]);
            $message = 'تم تحديث بيانات المورد «' . $name . '».';
        } else {
            $pdo->prepare('INSERT INTO suppliers (name, phone, note) VALUES (?, ?, ?)')
                ->execute([$name, $phone, $note]);
            $id      = (int) $pdo->lastInsertId();
            $message = 'تمت إضافة المورد «' . $name . '».';
        }
        return ['id' => $id, 'message' => $message] + sup_state('', null, 'all', '', '');
    }

    /* ---------------- add / update a supplier part ---------------- */
    if ($action === 'part_save') {
        $id         = nid(req('id'));
        $supplierId = nid(req('supplier_id'));
        $name       = sstr(req('name', ''), 160);
        $amount     = max(0.0, dec(req('amount', 0)));
        $status     = (string) req('status', 'Due');
        $entryDate  = fdate(req('entry_date'), today());
        $note       = sstr(req('note', ''), 255);

        if ($supplierId === null || !scalar($pdo, 'SELECT 1 FROM suppliers WHERE id = ?', [$supplierId], 0)) {
            throw new RuntimeException('اختر المورد أولاً — ويمكنك إضافة مورد جديد من بطاقة الموردين.');
        }
        if ($name === '') {
            throw new RuntimeException('اسم القطعة مطلوب — مثال: شاشة آيفون 14 برو ماكس.');
        }
        if ($amount <= 0) {
            throw new RuntimeException('أدخل مبلغاً أكبر من صفر.');
        }
        if (!in_array($status, ['Due', 'Paid'], true)) {
            $status = 'Due';
        }

        if ($id !== null) {
            if (!scalar($pdo, 'SELECT 1 FROM supplier_bills WHERE id = ?', [$id], 0)) {
                throw new RuntimeException('هذه القطعة لم تعد موجودة.');
            }
            $pdo->prepare('UPDATE supplier_bills SET supplier_id = ?, name = ?, amount = ?, status = ?,
                                                  entry_date = ?, note = ? WHERE id = ?')
                ->execute([$supplierId, $name, $amount, $status, $entryDate, $note, $id]);
            $message = 'تم تحديث قطعة المورد.';
        } else {
            $pdo->prepare('INSERT INTO supplier_bills (supplier_id, name, amount, status, entry_date, note)
                           VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$supplierId, $name, $amount, $status, $entryDate, $note]);
            $id = (int) $pdo->lastInsertId();
            $pdo->prepare('UPDATE supplier_bills SET code = ? WHERE id = ?')
                ->execute([next_supplier_bill_code($pdo, $id), $id]);
            $message = 'تمت إضافة القطعة إلى فاتورة المورد.';
        }
        return ['id' => $id, 'message' => $message] + sup_state('', null, 'all', '', '');
    }

    /* ---------------- mark a part as paid / due again ---------------- */
    if ($action === 'part_status') {
        $id = nid(req('id'));
        if ($id === null) {
            throw new RuntimeException('لم يتم تحديد القطعة.');
        }
        $row = q1($pdo, 'SELECT * FROM supplier_bills WHERE id = ?', [$id]);
        if (!$row) {
            throw new RuntimeException('هذه القطعة لم تعد موجودة.');
        }
        $status = $row['status'] === 'Paid' ? 'Due' : 'Paid';
        $pdo->prepare('UPDATE supplier_bills SET status = ? WHERE id = ?')->execute([$status, $id]);
        return [
            'message' => 'تم تعليم «' . $row['name'] . '» كـ' . ($status === 'Paid' ? 'مدفوع' : 'مستحق') . '.',
        ] + sup_state('', null, 'all', '', '');
    }

    /* ---------------- delete a supplier part ---------------- */
    if ($action === 'part_delete') {
        $id = nid(req('id'));
        if ($id === null) {
            throw new RuntimeException('لم يتم تحديد القطعة.');
        }
        $row = q1($pdo, 'SELECT * FROM supplier_bills WHERE id = ?', [$id]);
        if (!$row) {
            throw new RuntimeException('هذه القطعة لم تعد موجودة.');
        }
        $pdo->prepare('DELETE FROM supplier_bills WHERE id = ?')->execute([$id]);
        return ['message' => 'تم حذف «' . $row['name'] . '» من سجل المورد.'] + sup_state('', null, 'all', '', '');
    }

    /* ---------------- delete a supplier (only when nothing is registered) ---------------- */
    if ($action === 'delete') {
        $id = nid(req('id'));
        if ($id === null) {
            throw new RuntimeException('لم يتم تحديد المورد.');
        }
        $row = q1($pdo, 'SELECT * FROM suppliers WHERE id = ?', [$id]);
        if (!$row) {
            throw new RuntimeException('هذا المورد لم يعد موجوداً.');
        }
        $parts = (int) scalar($pdo, 'SELECT COUNT(*) FROM supplier_bills WHERE supplier_id = ?', [$id], 0);
        if ($parts > 0) {
            throw new RuntimeException('لا يمكن حذف «' . $row['name'] . '» لأن لديه ' . $parts .
                ' قطعة مسجّلة. احذف قطعه أولاً أو عدّل بياناته بدلاً من حذفه.');
        }
        $pdo->prepare('DELETE FROM suppliers WHERE id = ?')->execute([$id]);
        return ['message' => 'تم حذف المورد «' . $row['name'] . '».'] + sup_state('', null, 'all', '', '');
    }

    throw new RuntimeException('إجراء غير معروف: ' . $action);
});
