<?php
/**
 * Front controller. On the server, Apache rewrites every non-file request here
 * (see .htaccess). Local dev:  php -S localhost:8001 index.php
 */

require_once __DIR__ . '/src/app.php';
require_once __DIR__ . '/src/api.php';

// Turn an uncaught error into a readable 500 instead of Apache's blank one. Full
// details (file, line, trace) go to the PHP error log; the page shows the message.
set_exception_handler(function ($ex) {
    error_log('opponent-api: ' . $ex);
    $msg = $ex->getMessage();
    if (preg_match('/unable to open database file|readonly database/i', $msg)) {
        $msg .= "\n\nThe web server user needs write access to database/ and the .sqlite file in it"
              . " (SQLite creates -wal/-shm files alongside it, even for reads).";
    } elseif (stripos($msg, 'no such table') !== false) {
        $msg .= "\n\nThe database has no schema. Run:  php bin/init_db.php";
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo 'Server error: ' . $msg . "\n";
});

// Local dev: serve real files (logos) ourselves. The built-in server can't do it via
// `return false` because the URL carries base_path, which isn't a directory on disk.
if (PHP_SAPI === 'cli-server') {
    $file = realpath(__DIR__ . rawurldecode(request_path()));
    if ($file !== false && strpos($file, __DIR__ . '/logos/') === 0 && is_file($file)) {
        header('Content-Type: ' . mime_content_type($file));
        header('Access-Control-Allow-Origin: *'); // mirrors the .htaccess header rule
        readfile($file);
        exit;
    }
}

if (!is_file(config()['db_path'])) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "Database not found. Run:  php bin/init_db.php\n";
    exit;
}

$path = request_path();
$post = is_post();

/* Everything under /admin needs an SSO user; .htaccess decides which ones. */
if ($path === '/admin' || strpos($path, '/admin/') === 0) {
    require_admin();
    if ($post && !same_origin_request()) {
        http_response_code(403);
        header('Content-Type: text/plain');
        echo "Refused: this form was not submitted from this site.\n";
        exit;
    }
}

/* ---- Admin UI + docs (everything under /admin, plus /docs) -------------- */
if ($path === '/docs') {
    $sample = team_find_by_key('willamette-university');
    if (!$sample) {
        $all = teams_all();
        $sample = $all ? $all[0] : null;
    }
    view('api/docs', array('title' => 'API', 'sample' => $sample));
    exit;
}

if ($path === '/admin' && !$post) {
    view('teams/index', array('title' => 'Teams', 'teams' => teams_all()));
    exit;
}
if ($path === '/admin/new' || preg_match('#^/admin/(\d+)/edit$#', $path, $m)) {
    $team = isset($m[1]) ? team_find($m[1]) : null;
    if (isset($m[1]) && !$team) { http_response_code(404); echo 'Team not found'; exit; }
    view('teams/form', array(
        'title' => $team ? 'Edit Team' : 'New Team',
        'team'  => $team,
    ));
    exit;
}
if ($post && preg_match('#^/admin(?:/(\d+))?$#', $path, $m)) {
    $id = isset($m[1]) ? (int) $m[1] : 0;
    if ($id && !team_find($id)) { http_response_code(404); echo 'Team not found'; exit; }
    $data = array();
    foreach (TEAM_FIELDS() as $f) {
        $data[$f] = param($f);
    }
    $error = null;
    try {
        $clean = team_clean($id, $data);
        $stored = logo_upload_save(isset($_FILES['logo_file']) ? $_FILES['logo_file'] : null, $clean['logo_filename']);
        if ($stored !== '') {
            $clean['logo_filename'] = $stored;
        }
        team_save($id, $clean);
    } catch (PDOException $ex) { // before RuntimeException, which it extends
        $error = 'Could not save. Full name + variant and slug must both be unique.';
    } catch (RuntimeException $ex) {
        $error = $ex->getMessage();
    }
    if ($error !== null) {
        http_response_code(422);
        view('teams/form', array(
            'title' => $id ? 'Edit Team' : 'New Team',
            'team'  => $id ? array('id' => $id) + $data : $data,
            'error' => $error,
        ));
        exit;
    }
    redirect('/admin');
}
if ($post && preg_match('#^/admin/(\d+)/delete$#', $path, $m)) {
    team_delete($m[1]);
    redirect('/admin');
}

if ($path === '/admin' || strpos($path, '/admin/') === 0) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo "Not found: $path\n";
    exit;
}

/* ---- Public API (read-only, CORS-open): everything else ----------------- */
api_cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    header('Allow: GET, HEAD, OPTIONS');
    api_error(405, 'The API is read-only.');
    exit;
}
if ($path === '/') { send_teams_json(); exit; }
if ($path === '/export.csv') { send_teams_csv(); exit; }

/* Solid-colour PNG for a team's logo background, rendered from its hex. */
if (preg_match('#^/bg/([0-9A-Fa-f]{6})\.png$#', $path, $m)) {
    $png = solid_color_png($m[1]);
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=31536000, immutable');
    header('Content-Length: ' . strlen($png));
    echo $png;
    exit;
}

/* One team by slug or id. Reserved words (admin, docs, ...) never match a slug. */
if (preg_match('#^/([A-Za-z0-9-]+)(?:\.json)?$#', $path, $m)) { send_team_json($m[1]); exit; }

api_error(404, 'Unknown API endpoint. See ' . base_url() . '/docs');
