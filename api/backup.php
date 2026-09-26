<?php
/**
 * Backup API — database copies in BACKUP_DIR (default: D:/DB_backup).
 *
 * actions: status · create · delete · wipe · download (GET)
 */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

/* ---------------- file download (streamed, not JSON) ---------------- */
if (action_name() === 'download') {
    $name = basename((string) req('file', ''));
    $dir  = backup_dir();
    if ($dir === null || !preg_match(BACKUP_FILE_PATTERN, $name)) {
        fail('اسم ملف النسخة الاحتياطية غير صالح.');
    }
    $path = $dir . '/' . $name;
    if (!is_file($path)) {
        fail('ملف النسخة الاحتياطية غير موجود.');
    }
    if (!headers_sent()) {
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: no-store');
    }
    readfile($path);
    exit;
}

api_run(static function (): array {
    $action = action_name();

    /* ---------------- status + file list ---------------- */
    if ($action === 'status') {
        return ['status' => backup_status()];
    }

    /* ---------------- create a copy now ---------------- */
    if ($action === 'create') {
        $result = run_backup('manual');
        return [
            'message' => 'تم إنشاء نسخة احتياطية: ' . $result['name'] . ' (' . $result['size_h'] . ').',
            'file'    => $result['name'],
            'status'  => backup_status(),
        ];
    }

    /* ---------------- delete one backup file ---------------- */
    if ($action === 'delete') {
        $name = basename((string) req('file', ''));
        $dir  = backup_dir();
        if ($dir === null || !preg_match(BACKUP_FILE_PATTERN, $name)) {
            throw new RuntimeException('اسم ملف النسخة الاحتياطية غير صالح.');
        }
        $path = $dir . '/' . $name;
        if (!is_file($path)) {
            throw new RuntimeException('ملف النسخة الاحتياطية غير موجود.');
        }
        if (!@unlink($path)) {
            throw new RuntimeException('تعذّر حذف الملف: ' . $name);
        }
        return [
            'message' => 'تم حذف النسخة الاحتياطية: ' . $name,
            'status'  => backup_status(),
        ];
    }

    /* ---------------- wipe every record (settings kept) ---------------- */
    if ($action === 'wipe') {
        $backup = backup_guard('pre-wipe');          // aborts when the copy fails
        $result = wipe_all_data();
        return [
            'message' => 'تم حذف كل بيانات النظام (' . $result['total'] . ' سجل).' .
                         ($backup ? ' نسخة احتياطية قبل الحذف: ' . $backup['name'] . '.' : ''),
            'deleted' => $result['deleted'],
            'backup'  => $backup ? $backup['name'] : null,
            'status'  => backup_status(),
        ];
    }

    throw new RuntimeException('إجراء غير معروف: ' . $action);
});
