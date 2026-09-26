<?php
/**
 * Database backup helpers: mysqldump wrapper, file listing, retention,
 * start-up + interval auto backups and the destructive-data guard.
 */
declare(strict_types=1);

const BACKUP_FILE_PATTERN = '/^novacell_stock_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}_[a-z0-9\-]+\.sql$/i';

/** Label shown in the UI for each backup reason. */
function backup_reason_label(string $reason): string
{
    $labels = [
        'start'     => 'بدء التشغيل',
        'auto'      => 'تلقائية (كل ' . (int) BACKUP_INTERVAL_HOURS . ' ساعات)',
        'manual'    => 'يدوية',
        'cli'       => 'مجدولة / سطر أوامر',
        'pre-wipe'  => 'قبل حذف البيانات',
        'pre-reset' => 'قبل إعادة تحميل البيانات',
    ];
    return $labels[$reason] ?? $reason;
}

function bytes_human(float $bytes): string
{
    $units = ['بايت', 'ك.ب', 'م.ب', 'ج.ب'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, $i === 0 ? 0 : 1) . ' ' . $units[$i];
}

/**
 * Resolve (and create) the backup folder.
 * Returns null when the folder is unavailable — see backup_dir_error().
 */
function backup_dir(bool $create = true): ?string
{
    static $cache = false;
    static $dir = null;
    if ($cache !== false) {
        return $dir;
    }
    $cache = true;
    $dir = rtrim(str_replace('\\', '/', BACKUP_DIR), '/');

    if (!is_dir($dir)) {
        if (!$create || !@mkdir($dir, 0777, true)) {
            $dir = null;
            return null;
        }
    }
    if (!is_writable($dir)) {
        $dir = null;
    }
    return $dir;
}

function backup_dir_error(): string
{
    if (backup_dir() !== null) {
        return '';
    }
    return 'تعذّر الوصول إلى مجلد النسخ الاحتياطي «' . BACKUP_DIR . '» — تأكد من وجود القرص وأن الصلاحيات تسمح بالكتابة.';
}

/** Locate mysqldump.exe (XAMPP default locations + PATH). */
function mysqldump_binary(): ?string
{
    static $found = false;
    if ($found !== false) {
        return $found;
    }
    $found = null;

    $candidates = [];
    if (MYSQLDUMP_BIN !== '') {
        $candidates[] = MYSQLDUMP_BIN;
    }
    $candidates[] = dirname(PHP_BINARY) . '/../mysql/bin/mysqldump.exe';   // <xampp>/mysql/bin
    $candidates[] = 'C:/xampp/mysql/bin/mysqldump.exe';
    $candidates[] = 'D:/xampp/mysql/bin/mysqldump.exe';

    foreach ($candidates as $path) {
        $real = realpath($path);
        if ($real !== false && is_file($real)) {
            $found = str_replace('\\', '/', $real);
            return $found;
        }
    }
    $probe = @shell_exec('mysqldump --version 2>&1');
    if (is_string($probe) && stripos($probe, 'mysqldump') !== false) {
        $found = 'mysqldump';   // available on PATH
    }
    return $found;
}

/** All backup files, newest first. */
function backup_files(): array
{
    clearstatcache(true);                 // the folder changes outside this request
    $dir = backup_dir();
    if ($dir === null) {
        return [];
    }
    $files = [];
    foreach ((array) glob($dir . '/novacell_stock_*.sql') as $path) {
        if (!preg_match(BACKUP_FILE_PATTERN, basename($path))) {
            continue;
        }
        $mtime  = (int) filemtime($path);
        $reason = 'manual';
        if (preg_match('/novacell_stock_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}_([a-z0-9\-]+)\.sql$/i', basename($path), $m)) {
            $reason = strtolower($m[1]);
        }
        $files[] = [
            'name'   => basename($path),
            'path'   => $path,
            'size'   => (int) filesize($path),
            'size_h' => bytes_human((float) filesize($path)),
            'mtime'  => $mtime,
            'date'   => date('Y-m-d', $mtime),
            'time'   => date('H:i', $mtime),
            'reason' => $reason,
            'label'  => backup_reason_label($reason),
        ];
    }
    usort($files, static fn ($a, $b) => $b['mtime'] <=> $a['mtime']);
    return $files;
}

function backup_latest_time(): int
{
    $files = backup_files();
    return $files ? (int) $files[0]['mtime'] : 0;
}

function backup_total_size(): int
{
    $total = 0;
    foreach (backup_files() as $file) {
        $total += (int) $file['size'];
    }
    return $total;
}

/** Keep only the newest BACKUP_KEEP files. */
function backup_prune(): int
{
    if ((int) BACKUP_KEEP <= 0) {
        return 0;
    }
    $files   = backup_files();
    $removed = 0;
    foreach (array_slice($files, (int) BACKUP_KEEP) as $file) {
        if (@unlink($file['path'])) {
            $removed++;
        }
    }
    return $removed;
}

function backup_remember(string $key, string $value): void
{
    try {
        setting_set($key, $value);
    } catch (Throwable $e) {
        // never let logging break a request
    }
}

/**
 * Create a database copy with mysqldump.
 *
 * @param string $reason short slug stored inside the file name (manual, auto, …)
 */
function run_backup(string $reason = 'manual'): array
{
    $dir = backup_dir();
    if ($dir === null) {
        throw new RuntimeException(backup_dir_error());
    }
    $dump = mysqldump_binary();
    if ($dump === null) {
        throw new RuntimeException('لم يتم العثور على أداة mysqldump — حدّد المسار في config.php عبر MYSQLDUMP_BIN.');
    }

    $slug = preg_replace('/[^a-z0-9\-]/i', '', $reason) ?: 'manual';
    $name = 'novacell_stock_' . date('Y-m-d_H-i-s') . '_' . $slug . '.sql';
    $path = $dir . '/' . $name;
    $err  = $path . '.err';

    $cmd = escapeshellarg($dump)
        . ' --host=' . escapeshellarg(DB_HOST)
        . ' --port=' . (int) DB_PORT
        . ' --user=' . escapeshellarg(DB_USER);
    if (DB_PASS !== '') {
        $cmd .= ' --password=' . escapeshellarg(DB_PASS);
    }
    $cmd .= ' --default-character-set=utf8mb4 --single-transaction --routines --events'
          . ' --add-drop-table --databases ' . escapeshellarg(DB_NAME);

    $output = [];
    $code   = 0;
    @exec($cmd . ' > ' . escapeshellarg($path) . ' 2> ' . escapeshellarg($err), $output, $code);

    $errorText = is_file($err) ? trim((string) file_get_contents($err)) : '';
    @unlink($err);

    // mysqldump prints a password warning on stderr even on success — ignore it
    $realError = '';
    foreach ((array) preg_split('/\r?\n/', $errorText) as $line) {
        $line = trim((string) $line);
        if ($line === '' || stripos($line, 'password on the command line') !== false) {
            continue;
        }
        $realError .= ($realError === '' ? '' : ' | ') . $line;
    }

    $size      = is_file($path) ? (int) filesize($path) : 0;
    $head      = $size > 0 ? (string) file_get_contents($path, false, null, 0, 4096) : '';
    $looksGood = $size > 200 && (strpos($head, 'CREATE TABLE') !== false || strpos($head, 'CREATE DATABASE') !== false);

    if ($code !== 0 || !$looksGood || $realError !== '') {
        @unlink($path);
        $message = $realError !== '' ? $realError : ('فشل إنشاء النسخة الاحتياطية (رمز الخروج ' . $code . ').');
        backup_remember('backup_last_error', $message);
        backup_remember('backup_last_error_at', date('Y-m-d H:i:s'));
        throw new RuntimeException('تعذّر إنشاء النسخة الاحتياطية: ' . $message);
    }

    backup_remember('backup_last_error', '');
    backup_remember('backup_last_file', $name);
    backup_remember('backup_last_at', date('Y-m-d H:i:s'));
    backup_remember('backup_last_reason', $slug);
    backup_prune();

    return [
        'name'   => $name,
        'size'   => $size,
        'size_h' => bytes_human((float) $size),
        'mtime'  => (int) filemtime($path),
    ];
}

/** run_backup() that never throws (used by the automatic triggers). */
function backup_safe_run(string $reason): void
{
    try {
        run_backup($reason);
    } catch (Throwable $e) {
        backup_remember('backup_last_error', $e->getMessage());
        backup_remember('backup_last_error_at', date('Y-m-d H:i:s'));
    }
}

/* ---------- automatic scheduling (web requests) ---------- */

function backup_tick_state_file(): string
{
    $dir  = backup_dir(false);
    $base = $dir ?? sys_get_temp_dir();
    return rtrim($base, '/') . '/.novacell_backup_tick.json';
}

function backup_tick_state(): array
{
    $file  = backup_tick_state_file();
    $state = ['request' => 0, 'check' => 0];
    if (is_file($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded)) {
            $state['request'] = (int) ($decoded['request'] ?? 0);
            $state['check']   = (int) ($decoded['check'] ?? 0);
        }
    }
    return $state;
}

function backup_tick_state_save(array $state): void
{
    @file_put_contents(backup_tick_state_file(), json_encode($state), LOCK_EX);
}

/**
 * Runs on every web request:
 *  • "start" backup → first request after BACKUP_BOOT_GAP_MINUTES of inactivity
 *    (that is: after Apache/MySQL have been restarted).
 *  • "auto"  backup → every BACKUP_INTERVAL_HOURS since the newest copy.
 */
function backup_tick(): void
{
    if (!BACKUP_ON_LOAD || PHP_SAPI === 'cli') {
        return;
    }
    $now       = time();
    $state     = backup_tick_state();
    $bootGap   = max(1, (int) BACKUP_BOOT_GAP_MINUTES) * 60;
    $isBoot    = ($state['request'] === 0) || (($now - $state['request']) > $bootGap);
    $throttled = ($now - $state['check']) < 60;

    backup_tick_state_save([
        'request' => $now,
        'check'   => $throttled ? $state['check'] : $now,
    ]);
    if ($throttled) {
        return;
    }

    $last     = backup_latest_time();
    $interval = max(0, (int) BACKUP_INTERVAL_HOURS) * 3600;

    if ($isBoot) {
        if (($now - $last) > 60) {          // do not duplicate a fresh copy
            backup_safe_run('start');
        }
        return;
    }
    if ($interval > 0 && ($now - $last) >= $interval) {
        backup_safe_run('auto');
    }
}

/** Estimated time of the next automatic copy. */
function backup_next_run(): ?array
{
    if (!BACKUP_ON_LOAD || (int) BACKUP_INTERVAL_HOURS <= 0) {
        return null;
    }
    $last = backup_latest_time();
    $next = $last > 0 ? $last + ((int) BACKUP_INTERVAL_HOURS * 3600) : time();
    return ['date' => date('Y-m-d', $next), 'time' => date('H:i', $next)];
}

/** Everything the settings screen needs to show the backup status. */
function backup_status(): array
{
    $files = backup_files();
    $last  = $files ? $files[0] : null;
    $dump  = mysqldump_binary();

    return [
        'dir'              => str_replace('/', DIRECTORY_SEPARATOR, BACKUP_DIR),
        'dir_ok'           => backup_dir() !== null,
        'dir_error'        => backup_dir_error(),
        'dump_found'       => $dump !== null,
        'dump_path'        => $dump,
        'interval_hours'   => (int) BACKUP_INTERVAL_HOURS,
        'keep'             => (int) BACKUP_KEEP,
        'auto_enabled'     => (bool) BACKUP_ON_LOAD,
        'boot_gap_minutes' => (int) BACKUP_BOOT_GAP_MINUTES,
        'count'            => count($files),
        'total_size_h'     => bytes_human((float) backup_total_size()),
        'last'             => $last ? [
            'name'   => $last['name'],
            'size_h' => $last['size_h'],
            'date'   => $last['date'],
            'time'   => $last['time'],
            'label'  => $last['label'],
        ] : null,
        'next_run'         => backup_next_run(),
        'last_error'       => (string) setting_get('backup_last_error', ''),
        'last_error_at'    => (string) setting_get('backup_last_error_at', ''),
        'files'            => array_map(static fn ($f) => [
            'name'   => $f['name'],
            'size_h' => $f['size_h'],
            'date'   => $f['date'],
            'time'   => $f['time'],
            'label'  => $f['label'],
            'reason' => $f['reason'],
        ], $files),
    ];
}

/* ---------- destructive operations ---------- */

/** Delete every row of the business tables (the settings are kept). */
function wipe_all_data(): array
{
    $pdo    = db();
    $tables = ['bills', 'invoice_items', 'invoices', 'stock_movements', 'maintenance', 'items', 'categories'];
    $counts = [];
    foreach ($tables as $table) {
        $counts[$table] = (int) scalar($pdo, 'SELECT COUNT(*) FROM `' . $table . '`', [], 0);
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $table) {
        $pdo->exec('TRUNCATE TABLE `' . $table . '`');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    setting_set('seeded', '1');                       // keep the demo catalogue away
    setting_set('data_wiped_at', date('Y-m-d H:i:s'));

    return ['deleted' => $counts, 'total' => array_sum($counts)];
}

/** Safety copy before a destructive operation; throws when it cannot be taken. */
function backup_guard(string $reason): ?array
{
    if (!BACKUP_BEFORE_DESTRUCTIVE) {
        return null;
    }
    return run_backup($reason);
}
