<?php
/**
 * Settings API — shop profile, currency, tax and low-stock threshold.
 */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

const CURRENCIES = ['JOD', 'USD', 'EUR', 'GBP', 'INR', 'AED'];

api_run(static function (): array {
    $action = action_name();

    if ($action === 'get') {
        return ['settings' => settings_payload()];
    }
    if ($action === 'save') {
        $current  = settings_payload();
        $currency = strtoupper(sstr(req('currency', $current['currency']), 6));
        if (!in_array($currency, CURRENCIES, true)) {
            $currency = $current['currency'];
        }
        $values = [
            'shop'             => sstr(req('shop', $current['shop']), 120) ?: $current['shop'],
            'phone'            => sstr(req('phone', $current['phone']), 40),
            'email'            => sstr(req('email', $current['email']), 80),
            'addr'             => sstr(req('addr', $current['addr']), 200),
            'currency'         => $currency,
            'tax'              => (string) max(0, min(50, dec(req('tax', $current['tax'])))),
            'low_stock'        => (string) max(0, min(999, int0(req('low_stock', $current['low_stock']), $current['low_stock']))),
            'invoice_note'     => sstr(req('invoice_note', $current['invoice_note']), 200),
            'maintenance_note' => sstr(req('maintenance_note', $current['maintenance_note']), 200),
        ];
        foreach ($values as $k => $v) {
            setting_set($k, $v);
        }
        return ['message' => 'تم حفظ الإعدادات.', 'settings' => settings_payload()];
    }

    if ($action === 'reset') {
        $pdo    = db();
        $backup = backup_guard('pre-reset');          // safety copy first (see config.php)
        foreach (['supplier_bills', 'suppliers', 'bills', 'invoice_items', 'invoices', 'stock_movements', 'maintenance', 'items', 'categories', 'settings'] as $table) {
            $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
        }
        install_schema($pdo);
        seed_data($pdo);
        return [
            'message' => 'تمت إعادة تحميل البيانات التجريبية.' .
                         ($backup ? ' نسخة احتياطية قبل الإعادة: ' . $backup['name'] . '.' : ''),
        ];
    }

    throw new RuntimeException('إجراء غير معروف: ' . $action);
});
