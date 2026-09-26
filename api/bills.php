<?php
/**
 * Shop bills / running-expenses API — Wi-Fi, electricity, water, rent, salaries…
 *
 * The UI section is «المصروفات»; the table is named `bills` (like the request).
 * Expenses never touch the sales numbers: the dashboard lists them as their own
 * chart series and subtracts them from the profit to show the net profit.
 *
 * actions: list · get · save (add/update) · delete
 */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function bills_query(string $q, string $kind, string $from, string $to): array
{
    $params = [];
    $sql    = 'SELECT * FROM bills WHERE 1 = 1';
    $sql   .= date_clause('entry_date', $from, $to, $params);

    if ($q !== '') {
        $sql .= ' AND (name LIKE ? OR code LIKE ? OR kind LIKE ? OR note LIKE ?)';
        array_push($params, '%' . $q . '%', '%' . $q . '%', '%' . $q . '%', '%' . $q . '%');
    }
    if ($kind !== '' && $kind !== 'all') {
        $sql      .= ' AND kind = ?';
        $params[] = $kind;
    }
    return array_map('bill_payload', q(db(), $sql . ' ORDER BY entry_date DESC, id DESC LIMIT 400', $params));
}

api_run(static function (): array {
    $pdo    = db();
    $action = action_name();

    /* ---------------- list (date filter + type filter + search) ---------------- */
    if ($action === 'list') {
        $rows   = bills_query(sstr(req('q', ''), 80), sstr(req('kind', 'all'), 60),
                              (string) req('from', ''), (string) req('to', ''));
        $total  = 0.0;
        $byKind = [];
        foreach ($rows as $r) {
            $total += $r['amount'];
            $label  = $r['kind'] !== '' ? $r['kind'] : 'أخرى';
            $byKind[$label] = round(($byKind[$label] ?? 0.0) + $r['amount'], 2);
        }
        arsort($byKind);

        return [
            'records' => $rows,
            'kinds'   => bill_kinds(),
            'summary' => [
                'count'   => count($rows),
                'total'   => round($total, 2),
                'avg'     => count($rows) ? round($total / count($rows), 2) : 0.0,
                'top'     => $byKind ? (string) array_key_first($byKind) : '',
                'by_kind' => array_map(
                    static fn ($k, $v) => ['kind' => (string) $k, 'total' => (float) $v],
                    array_keys($byKind),
                    array_values($byKind)
                ),
            ],
        ];
    }

    /* ---------------- single record ---------------- */
    if ($action === 'get') {
        $id = nid(req('id'));
        if ($id === null) {
            throw new RuntimeException('لم يتم تحديد المصروف.');
        }
        $row = q1($pdo, 'SELECT * FROM bills WHERE id = ?', [$id]);
        if (!$row) {
            throw new RuntimeException('هذا المصروف لم يعد موجوداً.');
        }
        return ['record' => bill_payload($row)];
    }

    /* ---------------- add / update ---------------- */
    if ($action === 'save') {
        $id        = nid(req('id'));
        $name      = sstr(req('name', ''), 160);
        $kind      = sstr(req('kind', ''), 60);
        $amount    = max(0.0, dec(req('amount', 0)));
        $entryDate = fdate(req('entry_date'), today());
        $note      = sstr(req('note', ''), 255);

        if ($name === '') {
            throw new RuntimeException('اسم المصروف مطلوب — مثال: اشتراك الإنترنت (Wi-Fi).');
        }
        if ($amount <= 0) {
            throw new RuntimeException('أدخل مبلغاً أكبر من صفر.');
        }

        $before = null;
        if ($id !== null) {
            $before = q1($pdo, 'SELECT * FROM bills WHERE id = ?', [$id]);
            if (!$before) {
                throw new RuntimeException('هذا المصروف لم يعد موجوداً.');
            }
        }
        /* the expense «النوع» is a category switched on for «المصروفات» (or one of
           the built-in types) — a category hidden from this section is refused */
        if ($kind !== '' && ($before === null || $before['kind'] !== $kind)) {
            $known = q1($pdo, 'SELECT name, use_expense FROM categories WHERE LOWER(name) = LOWER(?) LIMIT 1', [$kind]);
            if ($known && !(int) $known['use_expense']) {
                throw new RuntimeException('التصنيف «' . $known['name'] . '» غير مُفعّل لقسم المصروفات — فعّله من شاشة التصنيفات أو اختر نوعاً آخر.');
            }
        }

        if ($id !== null) {
            $pdo->prepare('UPDATE bills SET name = ?, kind = ?, amount = ?, entry_date = ?, note = ? WHERE id = ?')
                ->execute([$name, $kind, $amount, $entryDate, $note, $id]);
            $message = 'تم تحديث المصروف.';
        } else {
            $pdo->prepare('INSERT INTO bills (name, kind, amount, entry_date, note) VALUES (?, ?, ?, ?, ?)')
                ->execute([$name, $kind, $amount, $entryDate, $note]);
            $id = (int) $pdo->lastInsertId();
            $pdo->prepare('UPDATE bills SET code = ? WHERE id = ?')->execute([next_bill_code($pdo, $id), $id]);
            $message = 'تم تسجيل المصروف.';
        }

        return [
            'id'      => $id,
            'message' => $message,
            'record'  => bill_payload(q1($pdo, 'SELECT * FROM bills WHERE id = ?', [$id])),
            'kinds'   => bill_kinds(),
        ];
    }

    /* ---------------- delete ---------------- */
    if ($action === 'delete') {
        $id = nid(req('id'));
        if ($id === null) {
            throw new RuntimeException('لم يتم تحديد المصروف.');
        }
        $row = q1($pdo, 'SELECT * FROM bills WHERE id = ?', [$id]);
        if (!$row) {
            throw new RuntimeException('هذا المصروف لم يعد موجوداً.');
        }
        $pdo->prepare('DELETE FROM bills WHERE id = ?')->execute([$id]);
        return ['message' => 'تم حذف المصروف ' . $row['code'] . ' (' . $row['name'] . ').'];
    }

    throw new RuntimeException('إجراء غير معروف: ' . $action);
});
