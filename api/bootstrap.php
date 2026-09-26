<?php
/**
 * Shared bootstrap for every API endpoint:
 * PDO connection, schema auto-install, seeding and small helpers.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/backup_lib.php';

if (APP_TZ !== '') {
    date_default_timezone_set(APP_TZ);
}
error_reporting(APP_DEBUG ? E_ALL : 0);
ini_set('display_errors', '0');   // never print HTML inside a JSON response

/* ============================================================
 * database
 * ============================================================ */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;charset=utf8mb4', DB_HOST, DB_PORT),
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->exec('USE `' . DB_NAME . '`');
    } catch (Throwable $e) {
        json_out([
            'ok'    => false,
            'error' => 'قاعدة البيانات غير متاحة — شغّل خدمة MySQL من لوحة تحكم XAMPP وتحقق من ملف config.php. (' . $e->getMessage() . ')',
        ], 500);
    }
    ensure_schema($pdo);
    return $pdo;
}

function ensure_schema(PDO $pdo): void
{
    if (!table_exists($pdo, 'items')) {
        install_schema($pdo);
    } else {
        // lightweight migration: create any table added by a newer version
        foreach (schema_sql() as $sql) {
            if (preg_match('/CREATE TABLE IF NOT EXISTS `?([a-z0-9_]+)`?/i', $sql, $m) && !table_exists($pdo, $m[1])) {
                $pdo->exec($sql);
            }
        }
    }
    // lightweight migration: the per-category department switches
    ensure_category_sections($pdo);
    // lightweight migration: the wholesale price (سعر الجملة) of every item
    ensure_item_columns($pdo);
    // currency switch: JOD is now the shop currency; migrate old installs once
    if (setting_get('currency_migrated_to_jod', '') !== '1' && strtoupper((string) setting_get('currency', APP_CURRENCY)) === 'USD') {
        setting_set('currency', APP_CURRENCY);
    }
    setting_set('currency_migrated_to_jod', '1');
    if ((string) $pdo->query("SELECT v FROM settings WHERE k = 'seeded'")->fetchColumn() !== '1') {
        seed_data($pdo);
    }
    // automatic database backup (start-up + interval) — never breaks a request
    try {
        backup_tick();
    } catch (Throwable $e) {
        try {
            setting_set('backup_last_error', $e->getMessage());
        } catch (Throwable $ignored) {
        }
    }
}

/** Does the connected database already contain this table? */
function table_exists(PDO $pdo, string $table): bool
{
    // NOTE: MariaDB rejects a placeholder in `SHOW TABLES LIKE ?`, so the name is
    // stripped down to the safe identifier alphabet and inlined instead.
    $safe = (string) preg_replace('/[^a-z0-9_]/i', '', $table);
    return $safe !== '' && (bool) $pdo->query("SHOW TABLES LIKE '" . $safe . "'")->fetchColumn();
}

/** Does the connected database already contain this column? */
function column_exists(PDO $pdo, string $table, string $column): bool
{
    $safeTable  = (string) preg_replace('/[^a-z0-9_]/i', '', $table);
    $safeColumn = (string) preg_replace('/[^a-z0-9_]/i', '', $column);
    if ($safeTable === '' || $safeColumn === '') {
        return false;
    }
    return (bool) scalar($pdo, 'SELECT COUNT(*) FROM information_schema.COLUMNS
                                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                         [$safeTable, $safeColumn], 0);
}

function install_schema(PDO $pdo): void
{
    foreach (schema_sql() as $sql) {
        $pdo->exec($sql);
    }
}

function schema_sql(): array
{
    return [
        /* a category can be switched on for one, two or all three departments
           (المخزون / الصيانة / المصروفات) — the UI shows a checkbox for each of them */
        "CREATE TABLE IF NOT EXISTS categories (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(80) NOT NULL,
            color VARCHAR(9) NOT NULL DEFAULT '#6153f4',
            use_inventory TINYINT(1) NOT NULL DEFAULT 1,
            use_maintenance TINYINT(1) NOT NULL DEFAULT 1,
            use_expense TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_categories_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS items (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(160) NOT NULL,
            category_id INT UNSIGNED NULL,
            price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            wholesale DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            profit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            qty INT NOT NULL DEFAULT 0,
            low_stock INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_items_category (category_id),
            CONSTRAINT fk_items_category FOREIGN KEY (category_id)
                REFERENCES categories (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS stock_movements (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            item_id INT UNSIGNED NOT NULL,
            type ENUM('in','out') NOT NULL,
            qty INT NOT NULL,
            price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            profit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            entry_date DATE NOT NULL,
            note VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_movements_date (entry_date),
            KEY idx_movements_item (item_id),
            CONSTRAINT fk_movements_item FOREIGN KEY (item_id)
                REFERENCES items (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS invoices (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(24) NULL,
            customer VARCHAR(120) NOT NULL,
            phone VARCHAR(40) NULL,
            entry_date DATE NOT NULL,
            status ENUM('Paid','Pending','Overdue') NOT NULL DEFAULT 'Paid',
            pay_method VARCHAR(24) NOT NULL DEFAULT 'Cash',
            subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            discount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            tax DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            profit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            note VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_invoices_code (code),
            KEY idx_invoices_date (entry_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS invoice_items (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_id INT UNSIGNED NOT NULL,
            item_id INT UNSIGNED NULL,
            category_id INT UNSIGNED NULL,
            name VARCHAR(160) NOT NULL,
            price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            profit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            qty INT NOT NULL DEFAULT 0,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (id),
            KEY idx_invoice_items_invoice (invoice_id),
            CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id)
                REFERENCES invoices (id) ON DELETE CASCADE,
            CONSTRAINT fk_invoice_items_item FOREIGN KEY (item_id)
                REFERENCES items (id) ON DELETE SET NULL,
            CONSTRAINT fk_invoice_items_category FOREIGN KEY (category_id)
                REFERENCES categories (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS maintenance (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(24) NULL,
            name VARCHAR(160) NOT NULL,
            category_id INT UNSIGNED NULL,
            price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            profit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            entry_date DATE NOT NULL,
            note VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_maintenance_code (code),
            KEY idx_maintenance_date (entry_date),
            CONSTRAINT fk_maintenance_category FOREIGN KEY (category_id)
                REFERENCES categories (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        /* shop bills / running expenses: Wi-Fi, electricity, water, rent,
           salaries… (the UI calls this section «المصروفات») */
        "CREATE TABLE IF NOT EXISTS bills (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(24) NULL,
            name VARCHAR(160) NOT NULL,
            kind VARCHAR(60) NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            entry_date DATE NOT NULL,
            note VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_bills_code (code),
            KEY idx_bills_date (entry_date),
            KEY idx_bills_kind (kind)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        /* suppliers and their parts: a fully separate ledger. It NEVER touches
           the sales revenue or the profit — `supplier_bills` is only there to
           know what each supplier is owed (مستحق) and what was paid (مدفوع). */
        "CREATE TABLE IF NOT EXISTS suppliers (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL,
            phone VARCHAR(40) NULL,
            note VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_suppliers_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS supplier_bills (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(24) NULL,
            supplier_id INT UNSIGNED NOT NULL,
            name VARCHAR(160) NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            status ENUM('Due','Paid') NOT NULL DEFAULT 'Due',
            entry_date DATE NOT NULL,
            note VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_supplier_bills_code (code),
            KEY idx_supplier_bills_date (entry_date),
            KEY idx_supplier_bills_supplier (supplier_id),
            CONSTRAINT fk_supplier_bills_supplier FOREIGN KEY (supplier_id)
                REFERENCES suppliers (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS settings (
            k VARCHAR(60) NOT NULL,
            v VARCHAR(255) NOT NULL DEFAULT '',
            PRIMARY KEY (k)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
}

/* ============================================================
 * first-run sample data (gives the charts something to draw)
 * ============================================================ */

function seed_data(PDO $pdo): void
{
    setting_set('shop', APP_SHOP);
    setting_set('phone', '+964 770 000 0000');
    setting_set('email', 'hello@novamobiles.com');
    setting_set('addr', 'شارع السوق 128، وسط المدينة');
    setting_set('currency', APP_CURRENCY);
    setting_set('tax', (string) APP_TAX);
    setting_set('low_stock', (string) APP_LOW_STOCK);
    setting_set('invoice_note', 'شكراً لتعاملكم معنا — ضمان خدمة 6 أشهر على الأجهزة والملحقات.');
    setting_set('maintenance_note', 'فاتورة صيانة — الأجزاء واليد العاملة مشمولة بضمان المحل.');

    if ((int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn() > 0) {
        setting_set('seeded', '1');
        return;
    }

    $cats = [
        ['آيفون', '#6366f1'], ['سامسونج', '#0ea5e9'], ['شاومي', '#f59e0b'],
        ['أوبو', '#10b981'], ['إكسسوارات', '#8b5cf6'], ['قطع غيار', '#ef4444'],
    ];
    $catId = [];
    // device / parts categories show up in المخزون and الصيانة only
    $ins   = $pdo->prepare('INSERT INTO categories (name, color, use_inventory, use_maintenance, use_expense)
                            VALUES (?, ?, 1, 1, 0)');
    foreach ($cats as $c) {
        $ins->execute($c);
        $catId[$c[0]] = (int) $pdo->lastInsertId();
    }

    // expense categories: switched on for «المصروفات» only — they never show up
    // in the inventory / maintenance selects
    $expenseCats = [['إنترنت', '#0ea5e9'], ['كهرباء', '#f59e0b'], ['إيجار', '#8b5cf6']];
    $insExp      = $pdo->prepare('INSERT INTO categories (name, color, use_inventory, use_maintenance, use_expense)
                                 VALUES (?, ?, 0, 0, 1)');
    foreach ($expenseCats as $c) {
        $insExp->execute($c);
    }

    $items = [
        ['آيفون 15 برو ماكس 256 جيجا', 'آيفون', 1199, 180, 6],
        ['آيفون 15 — 128 جيجا', 'آيفون', 799, 120, 11],
        ['آيفون 13 — 128 جيجا', 'آيفون', 599, 90, 4],
        ['جالكسي S24 ألترا 512 جيجا', 'سامسونج', 1299, 200, 4],
        ['جالكسي S24 — 256 جيجا', 'سامسونج', 859, 130, 9],
        ['جالكسي A55 — 128 جيجا', 'سامسونج', 449, 60, 15],
        ['شاومي 14 — 512 جيجا', 'شاومي', 899, 110, 3],
        ['ريدمي نوت 13 برو 256 جيجا', 'شاومي', 329, 45, 18],
        ['أوبو رينو 11 — 256 جيجا', 'أوبو', 529, 70, 7],
        ['واقي شاشة زجاجي', 'إكسسوارات', 15, 9, 40],
        ['شاحن سريع USB-C 45 واط', 'إكسسوارات', 25, 12, 22],
        ['شاشة آيفون 12 OLED', 'قطع غيار', 129, 55, 5],
    ];
    $insItem = $pdo->prepare('INSERT INTO items (name, category_id, price, wholesale, profit, qty, low_stock)
                              VALUES (?, ?, ?, ?, ?, ?, ?)');
    $itemIds = [];
    foreach ($items as $i) {
        // the demo wholesale price (سعر الجملة) is the sale price minus the profit
        $insItem->execute([$i[0], $catId[$i[1]], $i[2], max($i[2] - $i[3], 0), $i[3], $i[4], APP_LOW_STOCK]);
        $itemIds[] = (int) $pdo->lastInsertId();
    }

    // dated stock ledger entries (the Inventory date filter reads these)
    $insMv = $pdo->prepare('INSERT INTO stock_movements (item_id, type, qty, price, profit, entry_date, note)
                            VALUES (?, ?, ?, ?, ?, ?, ?)');
    $moves = [
        [0, 'in', 5, 20, 'مخزون افتتاحي'], [1, 'in', 8, 20, 'مخزون افتتاحي'], [3, 'in', 4, 20, 'مخزون افتتاحي'],
        [4, 'in', 6, 20, 'مخزون افتتاحي'], [7, 'in', 12, 20, 'مخزون افتتاحي'], [9, 'in', 30, 20, 'مخزون افتتاحي'],
        [0, 'out', 2, 3, 'مبيعات الكاشير'], [1, 'out', 3, 9, 'مبيعات الكاشير'], [4, 'out', 4, 14, 'مبيعات الكاشير'],
        [7, 'out', 5, 16, 'مبيعات الكاشير'], [9, 'out', 10, 18, 'مبيعات الكاشير'], [2, 'in', 2, 6, 'تزويد من المورد'],
        [5, 'in', 6, 11, 'تزويد من المورد'], [10, 'out', 4, 1, 'مبيعات الكاشير'], [8, 'in', 3, 13, 'تزويد من المورد'],
    ];
    foreach ($moves as $m) {
        $insMv->execute([
            $itemIds[$m[0]], $m[1], $m[2], $items[$m[0]][2], $items[$m[0]][3],
            date('Y-m-d', strtotime('-' . $m[3] . ' days')), $m[4],
        ]);
    }

    // the item counter stays authoritative over the sample ledger
    $updQty = $pdo->prepare('UPDATE items SET qty = ? WHERE id = ?');
    foreach ($items as $k => $i) {
        $updQty->execute([$i[4], $itemIds[$k]]);
    }

    // ---- bills (invoices) spread over the last 3 weeks ----
    $customers = [
        ['أمير كريم', '+964 770 111 2233'], ['ليلى ناصر', '+964 770 222 3344'], ['سارة إدريس', '+964 770 333 4455'],
        ['حسين علي', '+964 770 444 5566'], ['مريم حسن', '+964 770 555 6677'], ['عمر خالد', '+964 770 666 7788'],
        ['نور الهدى', '+964 770 777 8899'], ['يوسف سالم', '+964 770 888 9900'],
    ];
    $bills = [
        [1, [0 => 1, 9 => 2], 'Paid', 'Card'],
        [2, [1 => 1, 10 => 1], 'Paid', 'Cash'],
        [3, [4 => 2], 'Paid', 'Card'],
        [5, [5 => 1, 7 => 1], 'Pending', 'Card'],
        [8, [3 => 1], 'Paid', 'Cash'],
        [11, [6 => 1, 9 => 3], 'Paid', 'Card'],
        [14, [2 => 1], 'Overdue', 'Card'],
        [17, [7 => 2, 10 => 2], 'Paid', 'Cash'],
    ];
    foreach ($bills as $n => $b) {
        create_invoice_row($pdo, [
            'customer'   => $customers[$n % count($customers)][0],
            'phone'      => $customers[$n % count($customers)][1],
            'entry_date' => date('Y-m-d', strtotime('-' . $b[0] . ' days')),
            'status'     => $b[2],
            'pay_method' => $b[3],
            'note'       => 'فاتورة تجريبية',
            'rows'       => array_map(
                static fn ($itemIdx, $qty) => ['item_id' => $itemIds[$itemIdx], 'qty' => $qty],
                array_keys($b[1]),
                array_values($b[1])
            ),
        ], false);
    }

    // ---- maintenance invoices ----
    $jobs = [
        ['آيفون 13 — تبديل شاشة', 'آيفون', 189, 74, 4, 'إصلاح فوري في نفس اليوم'],
        ['جالكسي S24 — منفذ شحن', 'سامسونج', 95, 38, 6, 'تنظيف وتبديل منفذ الشحن'],
        ['ريدمي نوت 13 — تبديل بطارية', 'شاومي', 65, 27, 9, 'بطارية أصلية'],
        ['آيفون 15 — زجاج خلفي', 'آيفون', 145, 60, 12, 'تبديل الزجاج فقط'],
        ['أوبو رينو 11 — تحديث نظام', 'أوبو', 40, 32, 15, 'إعادة تثبيت النظام'],
        ['جالكسي A55 — سماعة صوت', 'سامسونج', 78, 31, 19, 'تبديل سماعة الصوت'],
    ];
    $insMnt = $pdo->prepare('INSERT INTO maintenance (name, category_id, price, profit, entry_date, note)
                             VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($jobs as $j) {
        $insMnt->execute([$j[0], $catId[$j[1]], $j[2], $j[3], date('Y-m-d', strtotime('-' . $j[4] . ' days')), $j[5]]);
        $mntId = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE maintenance SET code = ? WHERE id = ?')->execute(['MNT-' . (1000 + $mntId), $mntId]);
    }

    // ---- shop bills / running expenses (Wi-Fi, electricity, rent, salaries…) ----
    $expenses = [
        ['اشتراك الإنترنت (Wi-Fi)', 'إنترنت', 45, 2, 'اشتراك شهري — شركة الاتصالات'],
        ['فاتورة الكهرباء', 'كهرباء', 120, 3, 'استهلاك الشهر الحالي'],
        ['إيجار المحل', 'إيجار', 600, 5, 'إيجار شهري'],
        ['فاتورة الماء', 'ماء', 25, 8, 'الاستهلاك الشهري'],
        ['رواتب الموظفين', 'رواتب', 900, 12, 'راتب شهر — موظفان'],
        ['صيانة مكيّف المحل', 'صيانة عامة', 60, 16, 'تنظيف وتعبئة غاز'],
        ['قرطاسية وأكياس تغليف', 'أخرى', 35, 20, 'مستلزمات تغليف'],
    ];
    $insBill = $pdo->prepare('INSERT INTO bills (name, kind, amount, entry_date, note) VALUES (?, ?, ?, ?, ?)');
    foreach ($expenses as $b) {
        $insBill->execute([$b[0], $b[1], $b[2], date('Y-m-d', strtotime('-' . $b[3] . ' days')), $b[4]]);
        $billId = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE bills SET code = ? WHERE id = ?')->execute([next_bill_code($pdo, $billId), $billId]);
    }

    // ---- suppliers + their parts (فواتير الموردين) --------------------------
    // NOTE: this ledger is deliberately outside the sales/profit numbers.
    $suppliers = [
        ['محمد الاعور', '+964 770 123 4567', 'مورد شاشات وقطع غيار'],
        ['مورد الإكسسوارات — أبو أحمد', '+964 771 555 8899', 'واقيات وشواحن'],
    ];
    $supId = [];
    $insSup = $pdo->prepare('INSERT INTO suppliers (name, phone, note) VALUES (?, ?, ?)');
    foreach ($suppliers as $s) {
        $insSup->execute($s);
        $supId[$s[0]] = (int) $pdo->lastInsertId();
    }
    $parts = [
        ['شاشة آيفون 14 برو ماكس', 'محمد الاعور', 50, 'Due', 3, 'شاشة أصلية'],
        ['شاشة آيفون 13', 'محمد الاعور', 42, 'Due', 9, 'شاشة أصلية'],
        ['بطارية جالكسي S24', 'محمد الاعور', 18, 'Paid', 14, 'دفعة نقدية'],
        ['واقي شاشة زجاجي (100 قطعة)', 'مورد الإكسسوارات — أبو أحمد', 60, 'Paid', 6, 'كارتون كامل'],
    ];
    $insPart = $pdo->prepare('INSERT INTO supplier_bills (supplier_id, name, amount, status, entry_date, note)
                              VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($parts as $p) {
        $insPart->execute([$supId[$p[1]], $p[0], $p[2], $p[3], date('Y-m-d', strtotime('-' . $p[4] . ' days')), $p[5]]);
        $supBillId = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE supplier_bills SET code = ? WHERE id = ?')
            ->execute([next_supplier_bill_code($pdo, $supBillId), $supBillId]);
    }

    setting_set('seeded', '1');
}

/* ============================================================
 * request / response helpers
 * ============================================================ */

function req(string $key, $default = null)
{
    static $body = null;
    if ($body === null) {
        $body = [];
        $raw  = trim((string) file_get_contents('php://input'));
        if ($raw !== '' && ($raw[0] === '{' || $raw[0] === '[')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }
    }
    if (array_key_exists($key, $_POST)) {
        return $_POST[$key];
    }
    if (array_key_exists($key, $_GET)) {
        return $_GET[$key];
    }
    if (array_key_exists($key, $body)) {
        return $body[$key];
    }
    return $default;
}

function action_name(): string
{
    return trim((string) req('action', 'list'));
}

/** Reads a list parameter (accepts a JSON string or a nested form array). */
function req_array(string $key): array
{
    $value = req($key, []);
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
    return is_array($value) ? $value : [];
}

function json_out(array $data, int $code = 200): void
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(string $message, int $code = 400): void
{
    json_out(['ok' => false, 'error' => $message], $code);
}

/** Wrap an endpoint body and convert exceptions into JSON errors. */
function api_run(callable $fn): void
{
    try {
        $result = $fn();
        json_out(['ok' => true] + (is_array($result) ? $result : []));
    } catch (Throwable $e) {
        json_out([
            'ok'    => false,
            'error' => APP_DEBUG ? $e->getMessage() : 'حدث خطأ غير متوقع في الخادم',
        ], 500);
    }
}

/* ============================================================
 * value helpers
 * ============================================================ */

function sstr($value, int $max = 255): string
{
    $value = trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
}

function dec($value, float $fallback = 0.0): float
{
    if (is_string($value)) {
        $value = str_replace([',', ' '], '', $value);
    }
    return is_numeric($value) ? round((float) $value, 2) : $fallback;
}

function int0($value, int $fallback = 0): int
{
    return is_numeric($value) ? (int) round((float) $value) : $fallback;
}

function nid($value): ?int
{
    $v = int0($value, 0);
    return $v > 0 ? $v : null;
}

/** Checkbox value → bool (accepts 1, "1", "true", "on", "yes" / 0, "0", "", null). */
function flag($value, bool $fallback = false): bool
{
    if ($value === null) {
        return $fallback;   // the form did not send the checkbox at all
    }
    if (is_bool($value)) {
        return $value;
    }
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'on', 'yes'], true);
}

function today(): string
{
    return date('Y-m-d');
}

/** Accepts YYYY-MM-DD (or a datetime string) and returns YYYY-MM-DD, else ''. */
function fdate($value, string $fallback = ''): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return $fallback;
    }
    $day = substr($value, 0, 10);
    $d   = DateTime::createFromFormat('Y-m-d', $day);
    return ($d && $d->format('Y-m-d') === $day) ? $day : $fallback;
}

/** Optional date-range SQL: " AND <col> >= ? AND <col> <= ?" */
function date_clause(string $col, $from, $to, array &$params): string
{
    $sql  = '';
    $from = fdate($from);
    $to   = fdate($to);
    if ($from !== '') {
        $sql      .= " AND {$col} >= ?";
        $params[] = $from;
    }
    if ($to !== '') {
        $sql      .= " AND {$col} <= ?";
        $params[] = $to;
    }
    return $sql;
}

function q(PDO $pdo, string $sql, array $params = []): array
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function q1(PDO $pdo, string $sql, array $params = []): ?array
{
    $st  = $pdo->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

function scalar(PDO $pdo, string $sql, array $params = [], $default = 0)
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $v  = $st->fetchColumn();
    return ($v === false || $v === null) ? $default : $v;
}

/** Add N days to a Y-m-d date. */
function date_shift(string $day, int $days): string
{
    $d = DateTime::createFromFormat('Y-m-d', $day) ?: new DateTime($day);
    $d->modify(($days >= 0 ? '+' : '') . $days . ' day');
    return $d->format('Y-m-d');
}

/* ============================================================
 * settings
 * ============================================================ */

function setting_set(string $key, string $value): void
{
    db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)')
        ->execute([$key, $value]);
}

function settings_all(): array
{
    $out = [];
    foreach (q(db(), 'SELECT k, v FROM settings') as $row) {
        $out[$row['k']] = $row['v'];
    }
    return $out;
}

/** Single setting value with a default. */
function setting_get(string $key, string $default = ''): string
{
    $value = scalar(db(), 'SELECT v FROM settings WHERE k = ? LIMIT 1', [$key], null);
    return $value === null ? $default : (string) $value;
}

function settings_payload(array $raw = null): array
{
    $raw = $raw ?? settings_all();
    return [
        'shop'             => (string) ($raw['shop'] ?? APP_SHOP),
        'phone'            => (string) ($raw['phone'] ?? ''),
        'email'            => (string) ($raw['email'] ?? ''),
        'addr'             => (string) ($raw['addr'] ?? ''),
        'currency'         => (string) ($raw['currency'] ?? APP_CURRENCY),
        'tax'              => (float) ($raw['tax'] ?? APP_TAX),
        'low_stock'        => (int) ($raw['low_stock'] ?? APP_LOW_STOCK),
        'invoice_note'     => (string) ($raw['invoice_note'] ?? ''),
        'maintenance_note' => (string) ($raw['maintenance_note'] ?? ''),
    ];
}

/* ============================================================
 * categories
 * ============================================================ */

/**
 * The three departments a category can be switched on for (checkboxes in the
 * UI): المخزون · الصيانة · المصروفات. The key is what the front-end sends
 * (`use_<key>`), `column` is the flag inside the `categories` table.
 */
function category_sections(): array
{
    return [
        'inventory'   => ['column' => 'use_inventory',   'label' => 'المخزون',   'default' => 1],
        'maintenance' => ['column' => 'use_maintenance', 'label' => 'الصيانة',   'default' => 1],
        'expense'     => ['column' => 'use_expense',     'label' => 'المصروفات', 'default' => 0],
    ];
}

/** Adds a column to an existing table when an older database lacks it. */
function ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!column_exists($pdo, $table, $column)) {
        $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
    }
}

/** Adds the department columns to databases created by an older version. */
function ensure_category_sections(PDO $pdo): void
{
    foreach (category_sections() as $meta) {
        ensure_column($pdo, 'categories', $meta['column'], 'TINYINT(1) NOT NULL DEFAULT ' . ((int) $meta['default']));
    }
}

/** Adds the wholesale price (سعر الجملة) to databases created by an older version. */
function ensure_item_columns(PDO $pdo): void
{
    ensure_column($pdo, 'items', 'wholesale', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `price`');
}

/** Department keys this category row is switched on for: ['inventory','expense']. */
function category_section_keys(array $row): array
{
    $keys = [];
    foreach (category_sections() as $key => $meta) {
        if (!empty($row[$meta['column']])) {
            $keys[] = $key;
        }
    }
    return $keys;
}

/** Is this category switched on for one department? */
function category_in_section(?array $row, string $section): bool
{
    $meta = category_sections()[$section] ?? null;
    return $row !== null && $meta !== null && !empty($row[$meta['column']]);
}

/** Shared JSON shape for a category (API list + the selects in the UI). */
function category_payload(array $row): array
{
    return [
        'id'       => (int) $row['id'],
        'name'     => (string) $row['name'],
        'color'    => (string) $row['color'],
        'sections' => category_section_keys($row),
    ];
}

/** Trimmed row (id, name, color, section flags) or null. */
function category_row(PDO $pdo, int $id): ?array
{
    return q1($pdo, 'SELECT id, name, color, use_inventory, use_maintenance, use_expense
                     FROM categories WHERE id = ?', [$id]);
}

function categories_with_counts(string $search = ''): array
{
    $params = [];
    $where  = '';
    if ($search !== '') {
        $where    = ' WHERE c.name LIKE ?';
        $params[] = '%' . $search . '%';
    }
    $sql = "SELECT c.id, c.name, c.color, c.created_at, c.use_inventory, c.use_maintenance, c.use_expense,
                   (SELECT COUNT(*) FROM items i WHERE i.category_id = c.id)               AS items,
                   (SELECT COALESCE(SUM(i.qty), 0) FROM items i WHERE i.category_id = c.id)  AS units,
                   (SELECT COUNT(*) FROM maintenance m WHERE m.category_id = c.id)          AS maintenance,
                   (SELECT COUNT(*) FROM invoice_items x WHERE x.category_id = c.id)        AS billed,
                   (SELECT COUNT(*) FROM bills b WHERE b.kind = c.name)                     AS expenses
            FROM categories c{$where}
            ORDER BY c.name ASC";
    return array_map(static fn ($r) => category_payload($r) + [
        'created_at'  => $r['created_at'],
        'items'       => (int) $r['items'],
        'units'       => (int) $r['units'],
        'maintenance' => (int) $r['maintenance'],
        'billed'      => (int) $r['billed'],
        'expenses'    => (int) $r['expenses'],
    ], q(db(), $sql, $params));
}

/**
 * Simple option list used by the selects in the UI. Pass a department key
 * ('inventory' / 'maintenance' / 'expense') to get only the categories that are
 * switched on for it, or leave it empty for every category.
 */
function category_options(string $section = ''): array
{
    $meta = category_sections()[$section] ?? null;
    $sql  = 'SELECT id, name, color, use_inventory, use_maintenance, use_expense FROM categories'
          . ($meta !== null ? ' WHERE `' . $meta['column'] . '` = 1' : '')
          . ' ORDER BY name ASC';
    return array_map('category_payload', q(db(), $sql));
}

/** Category names switched on for «المصروفات» (they feed the expense types). */
function expense_category_names(): array
{
    return array_map(
        static fn ($r) => (string) $r['name'],
        q(db(), 'SELECT name FROM categories WHERE use_expense = 1 ORDER BY name ASC')
    );
}

/* ============================================================
 * bills (invoices) — shared by the API and the seeder
 * ============================================================ */

/**
 * Validate the requested bill lines against the current stock and snapshot
 * name / price / profit / category for each of them.
 *
 * The same item may be sold on several lines at different prices (e.g. two
 * phones: one at 120 and one at 110), so the requested quantity of every item
 * is summed up and checked against the stock once.
 *
 * @param array $rows list of ['item_id'=>int,'qty'=>int,'price'=>?float,'profit'=>?float,'category_id'=>?int,'name'=>?string]
 * @return array{lines: array, subtotal: float, profit: float}
 */
function prepare_invoice_lines(PDO $pdo, array $rows, bool $touchStock): array
{
    $lines    = [];
    $subtotal = 0.0;
    $profit   = 0.0;

    $requested = [];
    foreach ($rows as $row) {
        $itemId = nid($row['item_id'] ?? null);
        $qty    = int0($row['qty'] ?? 0);
        if ($itemId !== null && $qty > 0) {
            $requested[$itemId] = ($requested[$itemId] ?? 0) + $qty;
        }
    }
    $stock = [];
    foreach ($requested as $itemId => $wantQty) {
        $item = q1($pdo, 'SELECT * FROM items WHERE id = ?', [$itemId]);
        if (!$item) {
            throw new RuntimeException('أحد الأصناف المختارة لم يعد موجوداً.');
        }
        if ($touchStock && (int) $item['qty'] < $wantQty) {
            throw new RuntimeException('الكمية المتوفرة من "' . $item['name'] . '" هي ' . (int) $item['qty'] . ' وحدة فقط.');
        }
        $stock[$itemId] = $item;
    }

    foreach ($rows as $row) {
        $qty = int0($row['qty'] ?? 0);
        if ($qty <= 0) {
            continue;
        }
        $itemId = nid($row['item_id'] ?? null);
        $item   = $itemId !== null ? ($stock[$itemId] ?? null) : null;
        $price      = ($row['price'] ?? '') !== '' && array_key_exists('price', $row) && $row['price'] !== null
            ? dec($row['price'], (float) ($item['price'] ?? 0))
            : (float) ($item['price'] ?? 0);
        $unitProfit = ($row['profit'] ?? '') !== '' && array_key_exists('profit', $row) && $row['profit'] !== null
            ? dec($row['profit'], (float) ($item['profit'] ?? 0))
            : (float) ($item['profit'] ?? 0);
        $catId      = array_key_exists('category_id', $row) && $row['category_id'] !== null && $row['category_id'] !== ''
            ? nid($row['category_id'])
            : ($item ? nid($item['category_id']) : null);
        $amount     = round($price * $qty, 2);

        $subtotal += $amount;
        $profit   += round($unitProfit * $qty, 2);

        $lines[] = [
            'item_id'     => $itemId,
            'category_id' => $catId,
            'name'        => $item ? $item['name'] : sstr($row['name'] ?? 'Item', 160),
            'price'       => $price,
            'profit'      => $unitProfit,
            'qty'         => $qty,
            'amount'      => $amount,
        ];
    }

    if (!count($lines)) {
        throw new RuntimeException('أضف صنفاً واحداً على الأقل بكمية صحيحة.');
    }
    return ['lines' => $lines, 'subtotal' => round($subtotal, 2), 'profit' => round($profit, 2)];
}

function next_invoice_code(PDO $pdo, int $id): string
{
    return 'INV-' . (1000 + $id);
}

function next_maintenance_code(PDO $pdo, int $id): string
{
    return 'MNT-' . (1000 + $id);
}

/** Bill (shop expense) code — BIL-1000, BIL-1001 … */
function next_bill_code(PDO $pdo, int $id): string
{
    return 'BIL-' . (1000 + $id);
}

/** Running-expense types offered in the UI: the categories switched on for
 *  «المصروفات» first, then the built-in types (free text is still accepted). */
function bill_kinds(): array
{
    $builtin = ['إنترنت', 'كهرباء', 'ماء', 'إيجار', 'رواتب', 'صيانة عامة', 'أخرى'];
    return array_values(array_unique(array_merge(expense_category_names(), $builtin)));
}

/**
 * Insert a bill with its lines, decrement stock and write the 'out' ledger rows.
 *
 * The discount is deducted from the profit only: it never lowers the invoice
 * total, which always stays subtotal + tax — the amount the customer pays.
 *
 * @param array $data customer, phone, entry_date, status, pay_method, discount,
 *                    note, tax_rate and rows[]
 */
function create_invoice_row(PDO $pdo, array $data, bool $touchStock = true): array
{
    $customer = sstr($data['customer'] ?? '', 120);
    if ($customer === '') {
        throw new RuntimeException('اسم العميل مطلوب.');
    }
    $rows = $data['rows'] ?? [];
    if (!is_array($rows) || !count($rows)) {
        throw new RuntimeException('أضف صنفاً واحداً على الأقل إلى الفاتورة.');
    }

    $entry_date = fdate($data['entry_date'] ?? '', today());
    $status     = in_array((string) ($data['status'] ?? 'Paid'), ['Paid', 'Pending', 'Overdue'], true)
        ? (string) $data['status'] : 'Paid';
    $pay     = sstr($data['pay_method'] ?? 'Cash', 24);
    $note    = sstr($data['note'] ?? '', 255);
    $taxRate = array_key_exists('tax_rate', $data)
        ? dec($data['tax_rate'])
        : (float) (settings_all()['tax'] ?? 0);

    $prepared = prepare_invoice_lines($pdo, $rows, $touchStock);
    $lines    = $prepared['lines'];
    $subtotal = $prepared['subtotal'];
    $profit   = $prepared['profit'];

    /* The discount never changes what the customer owes: the amount due stays
       subtotal + tax exactly as if no discount had been given. It comes out of
       the recorded profit instead, so the internal reports show the margin that
       is really left after the discount. */
    $discount = min(max(0.0, dec($data['discount'] ?? 0)), $subtotal);
    $tax      = round($subtotal * $taxRate / 100, 2);
    $total    = round($subtotal + $tax, 2);
    $profit   = round($profit - $discount, 2);

    $pdo->prepare('INSERT INTO invoices (customer, phone, entry_date, status, pay_method, subtotal, discount,
                        tax_rate, tax, total, profit, note)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$customer, sstr($data['phone'] ?? '', 40), $entry_date, $status, $pay,
                   $subtotal, $discount, $taxRate, $tax, $total, $profit, $note]);
    $invoiceId = (int) $pdo->lastInsertId();
    $code      = next_invoice_code($pdo, $invoiceId);
    $pdo->prepare('UPDATE invoices SET code = ? WHERE id = ?')->execute([$code, $invoiceId]);

    $insLine = $pdo->prepare('INSERT INTO invoice_items (invoice_id, item_id, category_id, name, price, profit, qty, amount)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $insMove = $pdo->prepare('INSERT INTO stock_movements (item_id, type, qty, price, profit, entry_date, note)
                              VALUES (?, ?, ?, ?, ?, ?, ?)');

    foreach ($lines as $line) {
        $insLine->execute([$invoiceId, $line['item_id'], $line['category_id'], $line['name'],
                           $line['price'], $line['profit'], $line['qty'], $line['amount']]);
        if ($touchStock && $line['item_id'] !== null) {
            $pdo->prepare('UPDATE items SET qty = GREATEST(qty - ?, 0) WHERE id = ?')
                ->execute([$line['qty'], $line['item_id']]);
            $insMove->execute([$line['item_id'], 'out', $line['qty'], $line['price'], $line['profit'],
                               $entry_date, 'الفاتورة ' . $code]);
        }
    }

    return ['id' => $invoiceId, 'code' => $code, 'total' => $total];
}

/** Put the billed units back in stock (used when a bill is deleted). */
function restore_invoice_stock(PDO $pdo, array $invoice): void
{
    $lines = q($pdo, 'SELECT * FROM invoice_items WHERE invoice_id = ?', [(int) $invoice['id']]);
    $ins   = $pdo->prepare('INSERT INTO stock_movements (item_id, type, qty, price, profit, entry_date, note)
                            VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ($lines as $l) {
        if ($l['item_id'] === null) {
            continue;
        }
        $pdo->prepare('UPDATE items SET qty = qty + ? WHERE id = ?')->execute([(int) $l['qty'], (int) $l['item_id']]);
        $ins->execute([(int) $l['item_id'], 'in', (int) $l['qty'], (float) $l['price'], (float) $l['profit'],
                       today(), 'إلغاء الفاتورة ' . $invoice['code']]);
    }
}

function invoice_payload(array $row, array $lines): array
{
    return [
        'id'         => (int) $row['id'],
        'code'       => (string) $row['code'],
        'customer'   => (string) $row['customer'],
        'phone'      => (string) $row['phone'],
        'entry_date' => (string) $row['entry_date'],
        'status'     => (string) $row['status'],
        'pay_method' => (string) $row['pay_method'],
        'subtotal'   => (float) $row['subtotal'],
        'discount'   => (float) $row['discount'],
        'tax_rate'   => (float) $row['tax_rate'],
        'tax'        => (float) $row['tax'],
        'total'      => (float) $row['total'],
        'profit'     => (float) $row['profit'],
        'note'       => (string) $row['note'],
        'created_at' => (string) $row['created_at'],
        'items'      => array_map(static fn ($l) => [
            'id'          => (int) $l['id'],
            'item_id'     => $l['item_id'] === null ? null : (int) $l['item_id'],
            'category_id' => $l['category_id'] === null ? null : (int) $l['category_id'],
            'name'        => (string) $l['name'],
            'category'    => $l['category_name'] ?? null,
            'price'       => (float) $l['price'],
            'profit'      => (float) $l['profit'],
            'qty'         => (int) $l['qty'],
            'amount'      => (float) $l['amount'],
        ], $lines),
    ];
}

/** Row → JSON shape for a maintenance invoice (shared by API endpoints). */
function maintenance_payload(array $r): array
{
    return [
        'id'             => (int) $r['id'],
        'code'           => (string) $r['code'],
        'name'           => (string) $r['name'],
        'category_id'    => $r['category_id'] === null ? null : (int) $r['category_id'],
        'category'       => $r['category_name'] ?? '—',
        'category_color' => $r['category_color'] ?? '#6153f4',
        'price'          => (float) $r['price'],
        'profit'         => (float) $r['profit'],
        'entry_date'     => (string) $r['entry_date'],
        'note'           => (string) $r['note'],
        'created_at'     => (string) $r['created_at'],
    ];
}

/** Row → JSON shape for a shop bill / running expense (المصروفات). */
function bill_payload(array $r): array
{
    return [
        'id'         => (int) $r['id'],
        'code'       => (string) $r['code'],
        'name'       => (string) $r['name'],
        'kind'       => (string) ($r['kind'] ?? ''),
        'amount'     => (float) $r['amount'],
        'entry_date' => (string) $r['entry_date'],
        'note'       => (string) $r['note'],
        'created_at' => (string) $r['created_at'],
    ];
}

/* ============================================================
 * suppliers + supplier bills (فواتير الموردين)
 *
 * A ledger of its own: it never feeds the revenue, the profit, the
 * dashboard or any sales report — it only tracks what each supplier is
 * owed (مستحق) and what has been paid (مدفوع).
 * ============================================================ */

/** Supplier code — SUP-1000, SUP-1001 … */
function next_supplier_bill_code(PDO $pdo, int $id): string
{
    return 'SUP-' . (1000 + $id);
}

/** Row → JSON shape for a supplier (with its running totals). */
function supplier_payload(array $r): array
{
    return [
        'id'          => (int) $r['id'],
        'name'        => (string) $r['name'],
        'phone'       => (string) ($r['phone'] ?? ''),
        'note'        => (string) ($r['note'] ?? ''),
        'created_at'  => (string) $r['created_at'],
        'parts'       => (int) ($r['parts'] ?? 0),
        'total'       => (float) ($r['total'] ?? 0),
        'due'         => (float) ($r['due'] ?? 0),
        'paid'        => (float) ($r['paid'] ?? 0),
    ];
}

/** Row → JSON shape for one supplier part line. */
function supplier_bill_payload(array $r): array
{
    return [
        'id'            => (int) $r['id'],
        'code'          => (string) $r['code'],
        'supplier_id'   => (int) $r['supplier_id'],
        'supplier'      => (string) ($r['supplier_name'] ?? '—'),
        'name'          => (string) $r['name'],
        'amount'        => (float) $r['amount'],
        'status'        => (string) $r['status'],
        'status_label'  => $r['status'] === 'Paid' ? 'مدفوع' : 'مستحق',
        'entry_date'    => (string) $r['entry_date'],
        'note'          => (string) $r['note'],
        'created_at'    => (string) $r['created_at'],
    ];
}

/** Suppliers + how much each one has (parts · total · due · paid). */
function suppliers_with_totals(string $search = ''): array
{
    $params = [];
    $where  = '';
    if ($search !== '') {
        $where    = ' WHERE s.name LIKE ? OR s.phone LIKE ? OR s.note LIKE ?';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    $sql = "SELECT s.*,
                   (SELECT COUNT(*) FROM supplier_bills b WHERE b.supplier_id = s.id)                          AS parts,
                   (SELECT COALESCE(SUM(b.amount), 0) FROM supplier_bills b WHERE b.supplier_id = s.id)         AS total,
                   (SELECT COALESCE(SUM(b.amount), 0) FROM supplier_bills b
                     WHERE b.supplier_id = s.id AND b.status = 'Due')                                           AS due,
                   (SELECT COALESCE(SUM(b.amount), 0) FROM supplier_bills b
                     WHERE b.supplier_id = s.id AND b.status = 'Paid')                                          AS paid
            FROM suppliers s{$where}
            ORDER BY s.name ASC";
    return array_map('supplier_payload', q(db(), $sql, $params));
}

/** Simple option list for the supplier select / filter. */
function supplier_options(): array
{
    return array_map(static fn ($r) => [
        'id'    => (int) $r['id'],
        'name'  => (string) $r['name'],
        'phone' => (string) ($r['phone'] ?? ''),
    ], q(db(), 'SELECT id, name, phone FROM suppliers ORDER BY name ASC'));
}

/** Supplier rows honouring the toolbar filters (search / supplier / status / date). */
function supplier_bill_query(string $q, ?int $supplierId, string $status, string $from, string $to): array
{
    $params = [];
    $sql    = 'SELECT b.*, s.name AS supplier_name, s.phone AS supplier_phone
               FROM supplier_bills b
               LEFT JOIN suppliers s ON s.id = b.supplier_id
               WHERE 1 = 1';
    $sql   .= date_clause('b.entry_date', $from, $to, $params);

    if ($q !== '') {
        $sql .= ' AND (b.name LIKE ? OR b.code LIKE ? OR b.note LIKE ? OR s.name LIKE ?)';
        array_push($params, '%' . $q . '%', '%' . $q . '%', '%' . $q . '%', '%' . $q . '%');
    }
    if ($supplierId !== null) {
        $sql      .= ' AND b.supplier_id = ?';
        $params[] = $supplierId;
    }
    if (in_array($status, ['Due', 'Paid'], true)) {
        $sql      .= ' AND b.status = ?';
        $params[] = $status;
    }
    return array_map('supplier_bill_payload', q(db(), $sql . ' ORDER BY b.entry_date DESC, b.id DESC LIMIT 500', $params));
}

/** Totals of a set of supplier part rows (due / paid / count). */
function supplier_bill_summary(array $rows): array
{
    $total = 0.0;
    $due   = 0.0;
    $paid  = 0.0;
    foreach ($rows as $r) {
        $total += $r['amount'];
        if ($r['status'] === 'Paid') { $paid += $r['amount']; } else { $due += $r['amount']; }
    }
    return [
        'count' => count($rows),
        'total' => round($total, 2),
        'due'   => round($due, 2),
        'paid'  => round($paid, 2),
        'avg'   => count($rows) ? round($total / count($rows), 2) : 0.0,
    ];
}

/* ============================================================
 * inventory row helpers (shared by items.php / invoices.php)
 * ============================================================ */

/**
 * Unit profit of a stock item: the wholesale price (سعر الجملة) is the cost, so
 * the profit is price − wholesale whenever a wholesale price is filled in.
 * Items without one keep the profit that was entered by hand.
 */
function item_unit_profit(float $price, float $wholesale, float $manual = 0.0): float
{
    return $wholesale > 0 ? round(max($price - $wholesale, 0.0), 2) : $manual;
}

function item_payload(array $r): array
{
    return [
        'id'             => (int) $r['id'],
        'name'           => (string) $r['name'],
        'category_id'    => $r['category_id'] === null ? null : (int) $r['category_id'],
        'category'       => $r['category_name'] ?? '—',
        'category_color' => $r['category_color'] ?? '#6153f4',
        'price'          => (float) $r['price'],
        'wholesale'      => (float) ($r['wholesale'] ?? 0),
        'profit'         => (float) $r['profit'],
        'qty'            => (int) $r['qty'],
        'low_stock'      => (int) $r['low_stock'],
        'value'          => round((float) $r['price'] * (int) $r['qty'], 2),
        'created_at'     => (string) $r['created_at'],
        'updated_at'     => (string) $r['updated_at'],
    ];
}

function movement_payload(array $r): array
{
    $qty = (int) $r['qty'];
    return [
        'id'             => (int) $r['id'],
        'item_id'        => (int) $r['item_id'],
        'item'           => (string) $r['item_name'],
        'category_id'    => $r['category_id'] === null ? null : (int) $r['category_id'],
        'category'       => $r['category_name'] ?? '—',
        'category_color' => $r['category_color'] ?? '#6153f4',
        'type'           => (string) $r['type'],
        'qty'            => $qty,
        'price'          => (float) $r['price'],
        'profit'         => (float) $r['profit'],
        'value'          => round((float) $r['price'] * $qty, 2),
        'profit_total'   => round((float) $r['profit'] * $qty, 2),
        'entry_date'     => (string) $r['entry_date'],
        'note'           => (string) $r['note'],
    ];
}

/** Load stock items honouring the toolbar filters (search / category / status). */
function items_query(string $q, ?int $categoryId, string $status, int $lowDefault): array
{
    $params = [];
    $sql    = 'SELECT i.*, c.name AS category_name, c.color AS category_color
               FROM items i
               LEFT JOIN categories c ON c.id = i.category_id
               WHERE 1 = 1';
    if ($q !== '') {
        $sql      .= ' AND (i.name LIKE ? OR c.name LIKE ?)';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    if ($categoryId !== null) {
        $sql      .= ' AND i.category_id = ?';
        $params[] = $categoryId;
    }

    $items = [];
    foreach (q(db(), $sql . ' ORDER BY i.name ASC', $params) as $row) {
        $low   = (int) $row['low_stock'] > 0 ? (int) $row['low_stock'] : $lowDefault;
        $qty   = (int) $row['qty'];
        $state = $qty <= 0 ? 'out' : ($qty <= $low ? 'low' : 'in');
        if ($status !== 'all' && $status !== $state) {
            continue;
        }
        $row['low_stock'] = $low;
        $items[]          = item_payload($row) + ['state' => $state];
    }
    return $items;
}

/* ============================================================
 * boot data handed to index.php
 * ============================================================ */

function app_boot_data(): array
{
    $pdo = db();
    return [
        'api'        => 'api/',
        'app'        => APP_NAME,
        'today'      => today(),
        'settings'   => settings_payload(),
        'categories' => category_options(),
        'counts'     => [
            'items'    => (int) scalar($pdo, 'SELECT COUNT(*) FROM items', [], 0),
            'invoices' => (int) scalar($pdo, 'SELECT COUNT(*) FROM invoices', [], 0),
            'bills'    => (int) scalar($pdo, 'SELECT COUNT(*) FROM bills', [], 0),
            'units'    => (int) scalar($pdo, 'SELECT COALESCE(SUM(qty), 0) FROM items', [], 0),
        ],
    ];
}

