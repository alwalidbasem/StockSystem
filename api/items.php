<?php
/**
 * Inventory API — stock items (add / edit / remove) and the dated
 * stock ledger used by the date filter (add stock, remove stock, edit entry).
 */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

api_run(static function (): array {
    $pdo        = db();
    $settings   = settings_payload();
    $lowDefault = (int) $settings['low_stock'];
    $action     = action_name();

    /* ---------------- list of stock items ---------------- */
    if ($action === 'list') {
        $items = items_query(
            sstr(req('q', ''), 80),
            nid(req('category_id')),
            (string) req('status', 'all'),
            $lowDefault
        );
        $units = 0;
        $value = 0.0;
        $potential = 0.0;
        foreach ($items as $it) {
            $units     += $it['qty'];
            $value     += $it['value'];
            $potential += round($it['profit'] * $it['qty'], 2);
        }
        return [
            'items'   => $items,
            'summary' => [
                'models'    => count($items),
                'units'     => $units,
                'value'     => round($value, 2),
                'potential' => round($potential, 2),
                'low'       => count(array_filter($items, static fn ($i) => $i['state'] !== 'in')),
            ],
        ];
    }

    /* ---------------- dated ledger (drives the date filter) ---------------- */
    if ($action === 'movements') {
        $params = [];
        $sql    = 'SELECT m.*, i.name AS item_name, i.category_id, c.name AS category_name, c.color AS category_color
                   FROM stock_movements m
                   JOIN items i ON i.id = m.item_id
                   LEFT JOIN categories c ON c.id = i.category_id
                   WHERE 1 = 1';
        $sql   .= date_clause('m.entry_date', req('from'), req('to'), $params);

        $q = sstr(req('q', ''), 80);
        if ($q !== '') {
            $sql      .= ' AND (i.name LIKE ? OR c.name LIKE ? OR m.note LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        $type = (string) req('type', 'all');
        if (in_array($type, ['in', 'out'], true)) {
            $sql      .= ' AND m.type = ?';
            $params[] = $type;
        }
        $catFilter = nid(req('category_id'));
        if ($catFilter !== null) {
            $sql      .= ' AND i.category_id = ?';
            $params[] = $catFilter;
        }
        $itemFilter = nid(req('item_id'));
        if ($itemFilter !== null) {
            $sql      .= ' AND m.item_id = ?';
            $params[] = $itemFilter;
        }

        $rows    = q($pdo, $sql . ' ORDER BY m.entry_date DESC, m.id DESC LIMIT 400', $params);
        $rows    = array_map('movement_payload', $rows);
        $summary = ['entries' => count($rows), 'units_in' => 0, 'units_out' => 0,
                    'in_value' => 0.0, 'out_value' => 0.0, 'profit' => 0.0];

        foreach ($rows as $r) {
            if ($r['type'] === 'in') {
                $summary['units_in'] += $r['qty'];
                $summary['in_value'] += $r['value'];
            } else {
                $summary['units_out'] += $r['qty'];
                $summary['out_value'] += $r['value'];
                $summary['profit']    += $r['profit_total'];
            }
        }
        $summary['in_value']  = round($summary['in_value'], 2);
        $summary['out_value'] = round($summary['out_value'], 2);
        $summary['profit']    = round($summary['profit'], 2);

        return ['movements' => $rows, 'summary' => $summary];
    }

    /* ---------------- create / edit a stock item ---------------- */
    if ($action === 'save') {
        $id         = nid(req('id'));
        $name       = sstr(req('name', ''), 160);
        $categoryId = nid(req('category_id'));
        $price      = max(0.0, dec(req('price', 0)));
        $wholesale  = max(0.0, dec(req('wholesale', 0)));
        // the wholesale price (سعر الجملة) is the cost, so the profit follows it
        $profit     = item_unit_profit($price, $wholesale, max(0.0, dec(req('profit', 0))));
        $qty        = max(0, int0(req('qty', 0)));
        $lowStock   = max(0, int0(req('low_stock', $lowDefault), $lowDefault));
        $entryDate  = fdate(req('entry_date'), today());

        if ($name === '') {
            throw new RuntimeException('اسم الصنف مطلوب.');
        }
        if ($categoryId !== null) {
            $category = category_row($pdo, $categoryId);
            if ($category === null) {
                throw new RuntimeException('هذا التصنيف لم يعد موجوداً.');
            }
            /* categories that are switched off for «المخزون» stay out of it —
               an item already using one keeps it, so editing still works */
            $kept = $id !== null && (int) scalar($pdo, 'SELECT category_id FROM items WHERE id = ?', [$id], 0) === $categoryId;
            if (!$kept && !category_in_section($category, 'inventory')) {
                throw new RuntimeException('التصنيف «' . $category['name'] . '» غير مُفعّل لقسم المخزون — فعّله من شاشة التصنيفات أو اختر تصنيفاً آخر.');
            }
        }
        if (q1($pdo, 'SELECT id FROM items WHERE LOWER(name) = LOWER(?) AND id <> ? LIMIT 1', [$name, $id ?? 0])) {
            throw new RuntimeException('يوجد صنف آخر بنفس الاسم.');
        }

        $pdo->beginTransaction();
        if ($id !== null) {
            $before = q1($pdo, 'SELECT * FROM items WHERE id = ?', [$id]);
            if (!$before) {
                $pdo->rollBack();
                throw new RuntimeException('هذا الصنف لم يعد موجوداً.');
            }
            $pdo->prepare('UPDATE items SET name = ?, category_id = ?, price = ?, wholesale = ?, profit = ?, qty = ?, low_stock = ?
                           WHERE id = ?')
                ->execute([$name, $categoryId, $price, $wholesale, $profit, $qty, $lowStock, $id]);
            $delta = $qty - (int) $before['qty'];
            if ($delta !== 0) {
                $pdo->prepare('INSERT INTO stock_movements (item_id, type, qty, price, profit, entry_date, note)
                               VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$id, $delta > 0 ? 'in' : 'out', abs($delta), $price, $profit, $entryDate, 'تعديل يدوي من شاشة التعديل']);
            }
            $message = 'تم تحديث «' . $name . '».';
        } else {
            $pdo->prepare('INSERT INTO items (name, category_id, price, wholesale, profit, qty, low_stock)
                           VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$name, $categoryId, $price, $wholesale, $profit, $qty, $lowStock]);
            $id = (int) $pdo->lastInsertId();
            if ($qty > 0) {
                $pdo->prepare('INSERT INTO stock_movements (item_id, type, qty, price, profit, entry_date, note)
                               VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$id, 'in', $qty, $price, $profit, $entryDate, 'صنف جديد']);
            }
            $message = 'تمت إضافة «' . $name . '» إلى المخزون.';
        }
        $pdo->commit();

        return [
            'id'      => $id,
            'message' => $message,
            'items'   => items_query('', null, 'all', $lowDefault),
        ];
    }

    /* ---------------- add / remove stock (dated ledger entry) ---------------- */
    if ($action === 'adjust') {
        $itemId = nid(req('item_id'));
        $type   = (string) req('type', 'in');
        $qty    = max(0, int0(req('qty', 0)));
        $note   = sstr(req('note', ''), 255);
        $date   = fdate(req('entry_date'), today());

        if ($itemId === null) {
            throw new RuntimeException('اختر صنفاً من المخزون أولاً.');
        }
        if (!in_array($type, ['in', 'out'], true)) {
            throw new RuntimeException('نوع الحركة يجب أن يكون إدخال أو إخراج.');
        }
        if ($qty <= 0) {
            throw new RuntimeException('الكمية يجب أن تكون 1 على الأقل.');
        }
        $item = q1($pdo, 'SELECT * FROM items WHERE id = ?', [$itemId]);
        if (!$item) {
            throw new RuntimeException('هذا الصنف لم يعد موجوداً.');
        }
        if ($type === 'out' && $qty > (int) $item['qty']) {
            throw new RuntimeException('المتوفر من «' . $item['name'] . '» هو ' . (int) $item['qty'] . ' وحدة فقط.');
        }
        $price  = req('price') !== null ? max(0.0, dec(req('price'), (float) $item['price'])) : (float) $item['price'];
        $profit = req('profit') !== null ? max(0.0, dec(req('profit'), (float) $item['profit'])) : (float) $item['profit'];

        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO stock_movements (item_id, type, qty, price, profit, entry_date, note)
                       VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$itemId, $type, $qty, $price, $profit, $date,
                       $note !== '' ? $note : ($type === 'in' ? 'إضافة مخزون' : 'خصم مخزون')]);
        $pdo->prepare('UPDATE items SET qty = ' . ($type === 'in' ? 'qty + ?' : 'GREATEST(qty - ?, 0)') . ' WHERE id = ?')
            ->execute([$qty, $itemId]);
        $pdo->commit();

        return [
            'message' => ($type === 'in' ? 'تمت إضافة ' : 'تم خصم ') . $qty . ' وحدة — ' . $item['name'] . '.',
            'items'   => items_query('', null, 'all', $lowDefault),
        ];
    }

    /* ---------------- edit a ledger entry (quantity / date / note) ---------------- */
    if ($action === 'movement_save') {
        $id   = nid(req('id'));
        $qty  = max(0, int0(req('qty', 0)));
        $date = fdate(req('entry_date'), today());
        $note = sstr(req('note', ''), 255);
        if ($id === null) {
            throw new RuntimeException('لم يتم تحديد حركة المخزون.');
        }
        if ($qty <= 0) {
            throw new RuntimeException('الكمية يجب أن تكون 1 على الأقل.');
        }
        $move = q1($pdo, 'SELECT * FROM stock_movements WHERE id = ?', [$id]);
        if (!$move) {
            throw new RuntimeException('هذه الحركة لم تعد موجودة.');
        }
        $item = q1($pdo, 'SELECT * FROM items WHERE id = ?', [(int) $move['item_id']]);
        if (!$item) {
            throw new RuntimeException('الصنف المرتبط بهذه الحركة لم يعد موجوداً.');
        }
        $sign   = $move['type'] === 'in' ? 1 : -1;
        $oldQty = (int) $move['qty'];
        $delta  = $sign * ($qty - $oldQty);
        if ((int) $item['qty'] + $delta < 0) {
            throw new RuntimeException('هذا التعديل يجعل رصيد «' . $item['name'] . '» أقل من صفر.');
        }
        $price  = req('price') !== null ? max(0.0, dec(req('price'), (float) $move['price'])) : (float) $move['price'];
        $profit = req('profit') !== null ? max(0.0, dec(req('profit'), (float) $move['profit'])) : (float) $move['profit'];

        $pdo->beginTransaction();
        $pdo->prepare('UPDATE stock_movements SET qty = ?, entry_date = ?, note = ?, price = ?, profit = ? WHERE id = ?')
            ->execute([$qty, $date, $note, $price, $profit, $id]);
        if ($delta !== 0) {
            $pdo->prepare('UPDATE items SET qty = GREATEST(qty + ?, 0) WHERE id = ?')->execute([$delta, (int) $item['id']]);
        }
        $pdo->commit();

        return ['message' => 'تم تحديث حركة المخزون.', 'items' => items_query('', null, 'all', $lowDefault)];
    }

    /* ---------------- delete a ledger entry ---------------- */
    if ($action === 'movement_delete') {
        $id = nid(req('id'));
        if ($id === null) {
            throw new RuntimeException('لم يتم تحديد حركة المخزون.');
        }
        $move = q1($pdo, 'SELECT * FROM stock_movements WHERE id = ?', [$id]);
        if (!$move) {
            throw new RuntimeException('هذه الحركة لم تعد موجودة.');
        }
        $pdo->beginTransaction();
        $effect = $move['type'] === 'in' ? (int) $move['qty'] : -(int) $move['qty'];
        $pdo->prepare('UPDATE items SET qty = GREATEST(qty - ?, 0) WHERE id = ?')->execute([$effect, (int) $move['item_id']]);
        $pdo->prepare('DELETE FROM stock_movements WHERE id = ?')->execute([$id]);
        $pdo->commit();

        return ['message' => 'تم حذف حركة المخزون.', 'items' => items_query('', null, 'all', $lowDefault)];
    }

    /* ---------------- delete a stock item ---------------- */
    if ($action === 'delete') {
        $id = nid(req('id'));
        if ($id === null) {
            throw new RuntimeException('لم يتم تحديد الصنف.');
        }
        $item = q1($pdo, 'SELECT * FROM items WHERE id = ?', [$id]);
        if (!$item) {
            throw new RuntimeException('هذا الصنف لم يعد موجوداً.');
        }
        $pdo->prepare('DELETE FROM items WHERE id = ?')->execute([$id]);
        return [
            'message' => 'تم حذف «' . $item['name'] . '» من المخزون.',
            'items'   => items_query('', null, 'all', $lowDefault),
        ];
    }

    throw new RuntimeException('إجراء غير معروف: ' . $action);
});
