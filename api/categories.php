<?php
/**
 * Categories API — list / save (add + update) / delete with re-assignment.
 */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

api_run(static function (): array {
    $pdo    = db();
    $action = action_name();

    if ($action === 'list') {
        return ['categories' => categories_with_counts(sstr(req('q', ''), 80))];
    }

    if ($action === 'save') {
        $id    = nid(req('id'));
        $name  = sstr(req('name', ''), 80);
        $color = sstr(req('color', '#6153f4'), 9);
        if ($name === '') {
            throw new RuntimeException('اسم التصنيف مطلوب.');
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#6153f4';
        }
        $duplicate = q1($pdo, 'SELECT id FROM categories WHERE LOWER(name) = LOWER(?) AND id <> ? LIMIT 1', [$name, $id ?? 0]);
        if ($duplicate) {
            throw new RuntimeException('يوجد تصنيف آخر بنفس الاسم.');
        }

        /* department checkboxes: المخزون / الصيانة / المصروفات — the category is
           listed in the sections that are switched on here and nowhere else */
        $sections = [];
        foreach (category_sections() as $key => $meta) {
            $sections[$meta['column']] = flag(req('use_' . $key), (bool) $meta['default']) ? 1 : 0;
        }
        if (array_sum($sections) === 0) {
            throw new RuntimeException('فعّل قسماً واحداً على الأقل ليظهر فيه التصنيف: المخزون أو الصيانة أو المصروفات.');
        }
        $columns = array_keys($sections);

        if ($id !== null) {
            $before = category_row($pdo, $id);
            if (!$before) {
                throw new RuntimeException('هذا التصنيف لم يعد موجوداً.');
            }
            $pdo->prepare('UPDATE categories SET name = ?, color = ?, ' . implode(' = ?, ', $columns) . ' = ? WHERE id = ?')
                ->execute(array_merge([$name, $color], array_values($sections), [$id]));
            // «المصروفات» keeps the category name as its type, so a rename moves it too
            if ($before['name'] !== $name) {
                $pdo->prepare('UPDATE bills SET kind = ? WHERE kind = ?')->execute([$name, $before['name']]);
            }
            $message = 'تم تحديث التصنيف «' . $name . '».';
        } else {
            $pdo->prepare('INSERT INTO categories (name, color, ' . implode(', ', $columns) . ')
                           VALUES (?, ?, ' . implode(', ', array_fill(0, count($columns), '?')) . ')')
                ->execute(array_merge([$name, $color], array_values($sections)));
            $id      = (int) $pdo->lastInsertId();
            $message = 'تمت إضافة التصنيف «' . $name . '».';
        }
        return [
            'id'         => $id,
            'message'    => $message,
            'category'   => category_payload(category_row($pdo, $id) ?? ['id' => $id, 'name' => $name, 'color' => $color]),
            'categories' => categories_with_counts(),
        ];
    }

    if ($action === 'delete') {
        $id   = nid(req('id'));
        $move = nid(req('reassign'));
        if ($id === null) {
            throw new RuntimeException('لم يتم تحديد التصنيف المطلوب حذفه.');
        }
        $category = q1($pdo, 'SELECT * FROM categories WHERE id = ?', [$id]);
        if (!$category) {
            throw new RuntimeException('هذا التصنيف لم يعد موجوداً.');
        }
        $usage = [
            'items'       => (int) scalar($pdo, 'SELECT COUNT(*) FROM items WHERE category_id = ?', [$id], 0),
            'maintenance' => (int) scalar($pdo, 'SELECT COUNT(*) FROM maintenance WHERE category_id = ?', [$id], 0),
            'bills'       => (int) scalar($pdo, 'SELECT COUNT(*) FROM invoice_items WHERE category_id = ?', [$id], 0),
            // «المصروفات» links to the category by name (bills.kind)
            'expenses'    => (int) scalar($pdo, 'SELECT COUNT(*) FROM bills WHERE kind = ?', [$category['name']], 0),
        ];
        $inUse = array_sum($usage);

        if ($inUse > 0 && $move === null) {
            throw new RuntimeException(sprintf(
                'لا يمكن حذف «%s» لأنه مستخدم في %d صنف مخزون و%d سجل صيانة و%d بند فاتورة و%d مصروف. اختر تصنيفاً آخر لنقل البيانات إليه أو أعد تسميته.',
                $category['name'], $usage['items'], $usage['maintenance'], $usage['bills'], $usage['expenses']
            ));
        }
        if ($move !== null) {
            $target = category_row($pdo, $move);
            if ($move === $id || $target === null) {
                throw new RuntimeException('اختر تصنيفاً مختلفاً لنقل البيانات إليه.');
            }
            $pdo->prepare('UPDATE items SET category_id = ? WHERE category_id = ?')->execute([$move, $id]);
            $pdo->prepare('UPDATE maintenance SET category_id = ? WHERE category_id = ?')->execute([$move, $id]);
            $pdo->prepare('UPDATE invoice_items SET category_id = ? WHERE category_id = ?')->execute([$move, $id]);
            $pdo->prepare('UPDATE bills SET kind = ? WHERE kind = ?')->execute([$target['name'], $category['name']]);
        }

        $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
        return [
            'message'    => 'تم حذف التصنيف «' . $category['name'] . '».',
            'categories' => categories_with_counts(),
        ];
    }

    throw new RuntimeException('إجراء غير معروف: ' . $action);
});
