<?php
/**
 * Import teams (and their logo files) from a vmix-config install.
 *
 *   php bin/import_vmix.php [path/to/vmix-config]     # default: ../vmix-config
 *
 * Teams whose full_name + variant already exists here are skipped, so it's safe to re-run.
 * A vmix name like "Cal Lutheran University [Men]" becomes full_name + variant "Men".
 * primary_color is seeded from vmix's logo_bg_hex (it's the team's main colour in
 * practice); secondary_color and conference start blank. Logo files are copied
 * into ./logos/ unless a file of the same name is already there.
 */

require_once dirname(__DIR__) . '/src/app.php';

$src = isset($argv[1]) ? rtrim($argv[1], '/') : dirname(APP_ROOT) . '/vmix-config';
$srcDb = $src . '/database/vmix_setup.sqlite';
if (!is_file($srcDb)) {
    fwrite(STDERR, "No vmix-config database at $srcDb\n");
    exit(1);
}
if (!is_file(config()['db_path'])) {
    fwrite(STDERR, "Run php bin/init_db.php first.\n");
    exit(1);
}

$vmix = new PDO('sqlite:' . $srcDb); // only SELECTs; PDO::SQLITE_ATTR_OPEN_FLAGS (read-only) needs PHP 7.3+
$vmix->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$vmix->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$exists = db()->prepare('SELECT COUNT(*) FROM teams WHERE full_name = ? COLLATE NOCASE AND variant = ? COLLATE NOCASE');
$added = 0;
$skipped = 0;
db()->beginTransaction();
foreach ($vmix->query('SELECT * FROM teams ORDER BY id') as $row) {
    list($name, $variant) = split_variant($row['full_name']);
    $exists->execute(array($name, $variant));
    if ((int) $exists->fetchColumn() > 0) {
        $skipped++;
        continue;
    }
    $data = array(
        'full_name'       => $name,
        'variant'         => $variant,
        'short_name'      => $row['short_name'],
        'code'            => $row['code'],
        'mascot'          => $row['mascot'],
        'location'        => $row['location'],
        'website'         => $row['website'],
        'website_provider' => $row['stats_provider'],
        'primary_color'   => $row['logo_bg_hex'],
        'logo_bg_hex'     => $row['logo_bg_hex'],
        'logo_filename'   => $row['logo_filename'],
        'stat_id'         => $row['stat_id'],
    );
    try {
        team_save(0, team_clean(0, $data));
        $added++;
    } catch (PDOException $ex) { // a DB problem (permissions, schema) will hit every row - stop
        db()->rollBack();
        fwrite(STDERR, "Database error, nothing imported: {$ex->getMessage()}\n");
        exit(1);
    } catch (RuntimeException $ex) {
        fwrite(STDERR, "  ! {$row['full_name']}: {$ex->getMessage()}\n");
    }
}
db()->commit();
echo "Teams: $added added, $skipped already present.\n";

$copied = 0;
if (is_dir($src . '/logos')) {
    if (!is_dir(APP_ROOT . '/logos')) {
        mkdir(APP_ROOT . '/logos', 0775, true);
    }
    foreach (scandir($src . '/logos') as $f) {
        $from = $src . '/logos/' . $f;
        $to = APP_ROOT . '/logos/' . $f;
        if (is_file($from) && preg_match('/\.(png|jpe?g|gif|webp)$/i', $f) && !is_file($to)) {
            copy($from, $to);
            $copied++;
        }
    }
}
echo "Logos: $copied copied.\n";
