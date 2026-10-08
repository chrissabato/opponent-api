<?php
/**
 * Create the SQLite database (empty). Populate it with bin/import_vmix.php or the web UI.
 *
 *   php bin/init_db.php            # create if missing / apply schema (idempotent)
 *   php bin/init_db.php --force    # delete the existing DB and rebuild from scratch
 */

require_once dirname(__DIR__) . '/src/app.php';

$force = in_array('--force', $argv, true);
$dbPath = config()['db_path'];

if ($force && is_file($dbPath)) {
    foreach (array('', '-wal', '-shm') as $suffix) {
        if (is_file($dbPath . $suffix)) {
            unlink($dbPath . $suffix);
        }
    }
    echo "Removed existing database.\n";
}

$dir = dirname($dbPath);
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}

db()->exec(file_get_contents(APP_ROOT . '/database/schema.sql'));
$count = (int) db()->query('SELECT COUNT(*) FROM teams')->fetchColumn();
echo "Schema applied. $count teams.\nDone: $dbPath\n";
