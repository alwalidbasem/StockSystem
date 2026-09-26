<?php
/**
 * NovaCell — Phone Shop Manager
 * Application configuration.
 *
 * XAMPP / MariaDB defaults are pre-filled. Change DB_USER / DB_PASS if your
 * MySQL (MariaDB) server uses different credentials.
 */
declare(strict_types=1);

/* ---------- database ---------- */
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'novacell_stock');
define('DB_USER', 'root');
define('DB_PASS', '');

/* ---------- application ---------- */
define('APP_NAME', 'NovaCell');
define('APP_SHOP', 'نوفا موبايل');              // default shop name (editable in Settings)
define('APP_CURRENCY', 'JOD');                 // JOD | USD | EUR | GBP | INR | AED
define('APP_TAX', 0);                          // default tax %
define('APP_LOW_STOCK', 3);                    // default low-stock threshold

/**
 * Server timezone. Leave empty ('') to use php.ini's default.
 * Set a value (e.g. 'Asia/Baghdad') when you want the server to agree with the
 * shop's local time — the UI always sends the browser's local "today" anyway.
 */
define('APP_TZ', '');

/* ---------- database backups ---------- */
/** Folder that holds the database copies ("Backup file url"). */
define('BACKUP_DIR', 'D:/DB_backup');
/** Automatic backup interval (hours) counted from the last backup. 0 = off. */
define('BACKUP_INTERVAL_HOURS', 5);
/** How many backup files to keep (newest first). 0 = keep everything. */
define('BACKUP_KEEP', 30);
/**
 * Idle gap (minutes) that is treated as "the server was restarted":
 * the first request after that gap triggers a start-up backup.
 */
define('BACKUP_BOOT_GAP_MINUTES', 10);
/** Allow automatic backups triggered by web requests (start-up + interval). */
define('BACKUP_ON_LOAD', true);
/** Take a safety copy automatically before wiping / resetting the data. */
define('BACKUP_BEFORE_DESTRUCTIVE', true);
/**
 * Full path to mysqldump.exe. Leave empty ('') to auto-detect inside XAMPP.
 * Example: 'C:/xampp/mysql/bin/mysqldump.exe'
 */
define('MYSQLDUMP_BIN', '');

/** Show raw error messages in API responses while developing. */
define('APP_DEBUG', true);
