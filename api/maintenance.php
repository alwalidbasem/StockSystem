<?php
/**
 * Maintenance department API — dated maintenance invoices
 * (name, category, price, profit) with add / edit / delete + date filter.
 */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function maintenance_query(string $q, ?int $categoryId, string $from, string $to): array
{
    $params = [];
    $sql    = 'SELECT m.*, c.name AS category_name, c.color AS category_color
               FROM maintenance m
               LEFT JOIN categories c ON c.id = m.category_id
               WHERE 1 = 1';
    $sql   .= date_clause('m.entry_date', $from, $to, $params);

    if ($q !== '') {
        $sql      .= ' AND (m.name LIKE ? OR m.code LIKE ? OR c.name LIKE ? OR m.note LIKE ?)';
        array_push($params, '%' . $q . '%', '%' . $q . '%', '%' . $q . '%', '%' . $q . '%');
    }
    if ($categoryId !== null) {
        $sql      .= ' AND m.category_id = ?';
        $params[] = $categoryId;
    }
    $rows = q(db(), $sql . ' ORDER BY m.entry_date DESC, m.id DESC LIMIT 400', $params);
    return array_map('maintenance_payload', $rows);
}

api_run(static function (): array {
    $pdo    = db();
    $action = action_name();

    /* ---------------- list + date filter ---------------- */
    if ($action === 'list') {
        $rows   = maintenance_query(sstr(req('q', ''), 80), nid(req('category_id')),
                                    (string) req('from', ''), (string) req('to', ''));
        $price  = 0.0;
        $profit = 0.0;
        foreach ($rows as $r) {
            $price  += $r['price'];
            $profit += $r['profit'];
        }
        return [
            'records' => $rows,
            'summary' => [
                'count'  => count($rows),
                'price'  => round($price, 2),
                'profit' => round($profit, 2),
                'avg'    => count($rows) ? round($price / count($rows), 2) : 0.0,
            ],
        ];
    }

    /* ---------------- single record ---------------- */
    if ($action === 'get') {
        $id = nid(req('id'));
        if ($id === null) {
            throw new RuntimeException('لم يتم تحديد سجل الصيانة.');
        }
        $row = q1($pdo, 'SELECT m.*, c.name AS category_name, c.color AS category_color
                         FROM maintenance m
                         LEFT JOIN categories c ON c.id = m.category_id
                         WHERE m.id = ?', [$id]);
        if (!$row) {
            throw new RuntimeException('هذا السجل لم يعد موجوداً.');
        }
        return ['record' => maintenance_payload($row)];
    }

    /* ---------------- add / update ---------------- */
    if ($action === 'save') {
        $id         = nid(req('id'));
        $name       = sstr(req('name', ''), 160);
        $categoryId = nid(req('category_id'));
        $price      = max(0.0, dec(req('price', 0)));
        $profit     = max(0.0, dec(req('profit', 0)));
        $entryDate  = fdate(req('entry_date'), today());
        $note       = sstr(req('note', ''), 255);

        if ($name === '') {
            throw new RuntimeException('الاسم أو وصف العمل مطلوب.');
        }
        if ($categoryId !== null) {
            $category = category_row($pdo, $categoryId);
            if ($category === null) {
                throw new RuntimeException('هذا التصنيف لم يعد موجوداً.');
            }
            /* categories switched off for «الصيانة» stay out of it — a record
               already linked to one keeps it, so editing still works */
            $kept = $id !== null && (int) scalar($pdo, 'SELECT category_id FROM maintenance WHERE id = ?', [$id], 0) === $categoryId;
            if (!$kept && !category_in_section($category, 'maintenance')) {
                throw new RuntimeException('التصنيف «' . $category['name'] . '» غير مُفعّل لقسم الصيانة — فعّله من شاشة التصنيفات أو اختر تصنيفاً آخر.');
            }
        }

        if ($id !== null) {
            if (!scalar($pdo, 'SELECT 1 FROM maintenance WHERE id = ?', [$id], 0)) {
                throw new RuntimeException('هذا السجل لم يعد موجوداً.');
            }
            $pdo->prepare('UPDATE maintenance SET name = ?, category_id = ?, price = ?, profit = ?, entry_date = ?, note = ?
                           WHERE id = ?')
                ->execute([$name, $categoryId, $price, $profit, $entryDate, $note, $id]);
            $message = 'تم تحديث فاتورة الصيانة.';
        } else {
            $pdo->prepare('INSERT INTO maintenance (name, category_id, price, profit, entry_date, note)
                           VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$name, $categoryId, $price, $profit, $entryDate, $note]);
            $id = (int) $pdo->lastInsertId();
            $pdo->prepare('UPDATE maintenance SET code = ? WHERE id = ?')
                ->execute([next_maintenance_code($pdo, $id), $id]);
            $message = 'تم تسجيل فاتورة الصيانة.';
        }

        return [
            'id'      => $id,
            'message' => $message,
            'record'  => maintenance_payload(q1($pdo, 'SELECT m.*, c.name AS category_name, c.color AS category_color
                                                  FROM maintenance m
                                                  LEFT JOIN categories c ON c.id = m.category_id
                                                  WHERE m.id = ?', [$id])),
        ];
    }

    /* ---------------- delete ---------------- */
    if ($action === 'delete') {
        $id = nid(req('id'));
        if ($id === null) {
            throw new RuntimeException('لم يتم تحديد سجل الصيانة.');
        }
        $row = q1($pdo, 'SELECT * FROM maintenance WHERE id = ?', [$id]);
        if (!$row) {
            throw new RuntimeException('هذا السجل لم يعد موجوداً.');
        }
        $pdo->prepare('DELETE FROM maintenance WHERE id = ?')->execute([$id]);
        return ['message' => 'تم حذف فاتورة الصيانة ' . $row['code'] . '.'];
    }

    throw new RuntimeException('إجراء غير معروف: ' . $action);
});
