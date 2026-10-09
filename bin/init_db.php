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

// Migration: variants ("Cal Lutheran University [Men]") moved out of full_name into their own
// column, and full_name alone is no longer UNIQUE. SQLite can't drop a column constraint, so
// rebuild the table (keeping ids, slugs and timestamps) under the new schema.
$cols = array_column(db()->query('PRAGMA table_info(teams)')->fetchAll(), 'name');
$migrateVariant = $cols && !in_array('variant', $cols, true);
if ($migrateVariant) {
    db()->beginTransaction();
    db()->exec('ALTER TABLE teams RENAME TO teams_old');
    db()->exec('DROP INDEX IF EXISTS teams_updated_at'); // moved with teams_old; schema.sql recreates it
}

db()->exec(file_get_contents(APP_ROOT . '/database/schema.sql'));

if ($migrateVariant) {
    $newCols = array_column(db()->query('PRAGMA table_info(teams)')->fetchAll(), 'name');
    $keep = array_values(array_intersect($newCols, $cols)); // drops leftovers like stats_provider
    $keep[] = 'variant';
    $insert = db()->prepare('INSERT INTO teams (' . implode(', ', $keep) . ') VALUES (:' . implode(', :', $keep) . ')');
    $moved = 0;
    foreach (db()->query('SELECT * FROM teams_old ORDER BY id')->fetchAll() as $row) {
        list($row['full_name'], $row['variant']) = split_variant($row['full_name']);
        if ($row['variant'] !== '') { // full_name changed: bump so updated_since syncs pick it up
            $row['updated_at'] = gmdate('Y-m-d H:i:s');
            $moved++;
        }
        $values = array();
        foreach ($keep as $c) {
            $values[':' . $c] = $row[$c];
        }
        $insert->execute($values);
    }
    db()->exec('DROP TABLE teams_old');
    db()->commit();
    echo "Added variant column; moved $moved bracketed name(s) into it.\n";
}
$count = (int) db()->query('SELECT COUNT(*) FROM teams')->fetchColumn();
echo "Schema applied. $count teams.\nDone: $dbPath\n";
