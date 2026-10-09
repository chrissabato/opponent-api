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

// Migration: stats_provider was renamed website_provider. Must run before schema.sql, whose
// CREATE TABLE IF NOT EXISTS won't touch an existing table. RENAME COLUMN needs SQLite 3.25+,
// so older builds copy into a new column and leave the old one unused.
$cols = array_column(db()->query('PRAGMA table_info(teams)')->fetchAll(), 'name');
if (in_array('stats_provider', $cols, true) && !in_array('website_provider', $cols, true)) {
    if (version_compare(db()->query('SELECT sqlite_version()')->fetchColumn(), '3.25.0', '>=')) {
        db()->exec('ALTER TABLE teams RENAME COLUMN stats_provider TO website_provider');
    } else {
        db()->exec("ALTER TABLE teams ADD COLUMN website_provider TEXT NOT NULL DEFAULT ''");
        db()->exec('UPDATE teams SET website_provider = stats_provider');
    }
    echo "Renamed stats_provider to website_provider.\n";
}

db()->exec(file_get_contents(APP_ROOT . '/database/schema.sql'));
$count = (int) db()->query('SELECT COUNT(*) FROM teams')->fetchColumn();
echo "Schema applied. $count teams.\nDone: $dbPath\n";
