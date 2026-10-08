<?php
/**
 * Bootstrap + data access for the Team Data API app.
 * Targets PHP 7.2 (no typed properties, arrow functions, match, str_contains, ??=).
 */

define('APP_ROOT', dirname(__DIR__));

function config()
{
    static $cfg = null;
    if ($cfg === null) {
        $file = APP_ROOT . '/config.php';
        $cfg = is_file($file) ? require $file : require APP_ROOT . '/config.example.php';
    }
    return $cfg;
}

/** Shared PDO handle (SQLite). */
function db()
{
    static $pdo = null;
    if ($pdo === null) {
        $cfg = config();
        $pdo = new PDO('sqlite:' . $cfg['db_path']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}

/** HTML-escape. */
function e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** URL for an app path, honouring base_path. */
function url($path = '')
{
    $base = rtrim(config()['base_path'], '/');
    if ($path === '' || $path === '/') {
        return $base === '' ? '/' : $base;
    }
    return $base . '/' . ltrim($path, '/');
}

/**
 * Absolute URL of this deployment (scheme + host + base_path, no trailing slash),
 * taken from the incoming request. Used for logo URLs in API responses so consumer
 * apps on other hosts can load them directly.
 */
function base_url()
{
    $proto = 'http';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = strtolower(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]);
    } elseif (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        $proto = 'https';
    }
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    return $proto . '://' . $host . rtrim(config()['base_path'], '/');
}

function redirect($path)
{
    header('Location: ' . url($path));
    exit;
}

function request_path()
{
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $base = rtrim(config()['base_path'], '/');
    if ($base !== '' && strpos($uri, $base) === 0) {
        $uri = substr($uri, strlen($base));
    }
    $uri = '/' . trim($uri, '/');
    return $uri === '/' ? '/' : rtrim($uri, '/');
}

function is_post()
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function param($key, $default = '')
{
    $v = isset($_POST[$key]) ? $_POST[$key] : (isset($_GET[$key]) ? $_GET[$key] : $default);
    return is_string($v) ? trim($v) : $v;
}

/* --------------------------------------------------------------------------
 * Admin auth is campus SSO, enforced in .htaccess (mod_shib + Require shib-user).
 * The app only fails closed when no SSO user reaches /admin.
 * ------------------------------------------------------------------------ */

/** Signed-in username, or '' when there is none. */
function current_user()
{
    // After mod_rewrite's internal redirect to index.php Apache may expose it as REDIRECT_REMOTE_USER.
    foreach (array('REMOTE_USER', 'REDIRECT_REMOTE_USER') as $k) {
        if (!empty($_SERVER[$k])) {
            return (string) $_SERVER[$k];
        }
    }
    // Local dev has no Shibboleth; dev_user stands in, and only under the built-in server.
    $cfg = config();
    if (PHP_SAPI === 'cli-server' && !empty($cfg['dev_user'])) {
        return (string) $cfg['dev_user'];
    }
    return '';
}

/**
 * True when a POST came from one of this site's own pages, judged by the Origin
 * header (or Referer when a browser omits Origin). Blocks cross-site form posts
 * riding on an admin's SSO session.
 */
function same_origin_request()
{
    $src = !empty($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN']
         : (!empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
    $host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
    return $src !== '' && $host !== '' && strtolower((string) parse_url($src, PHP_URL_HOST)
        . (parse_url($src, PHP_URL_PORT) ? ':' . parse_url($src, PHP_URL_PORT) : '')) === $host;
}

/** Stop with a 403 page unless an SSO user reached the app (which list is .htaccess's job). */
function require_admin()
{
    if (current_user() !== '') {
        return;
    }
    http_response_code(403);
    view('auth/denied', array('title' => 'Not authorised'));
    exit;
}

/** Render a view file inside the layout. */
function view($name, array $data = array())
{
    extract($data, EXTR_SKIP);
    $title = isset($data['title']) ? $data['title'] : 'Team Data';
    ob_start();
    require APP_ROOT . '/src/views/' . $name . '.php';
    $content = ob_get_clean();
    require APP_ROOT . '/src/views/layout.php';
}

/* --------------------------------------------------------------------------
 * Logos (files in APP_ROOT/logos/)
 * ------------------------------------------------------------------------ */

/**
 * Resolve a logo_filename to the real file name in /logos/ on this server, or ''
 * when it isn't there. Falls back to a case-insensitive match because the logo
 * pack is authored on Windows (case-insensitive) but served from Linux.
 */
function logo_real_name($filename)
{
    static $map = null;
    $filename = trim($filename);
    if ($filename === '') {
        return '';
    }
    if (is_file(APP_ROOT . '/logos/' . $filename)) {
        return $filename;
    }
    if ($map === null) {
        $map = array();
        $dir = APP_ROOT . '/logos';
        if (is_dir($dir)) {
            foreach (scandir($dir) as $f) {
                if ($f !== '.' && $f !== '..') {
                    $map[strtolower($f)] = $f;
                }
            }
        }
    }
    $lc = strtolower($filename);
    return isset($map[$lc]) ? $map[$lc] : '';
}

/** base_path-relative URL for an uploaded logo, or '' when the file isn't there. */
function logo_asset_url($filename)
{
    $real = logo_real_name($filename);
    return $real === '' ? '' : url('/logos/' . rawurlencode($real));
}

/**
 * Move a just-uploaded logo into APP_ROOT/logos/. Returns the stored filename on
 * success, '' when no file was submitted, or throws RuntimeException with a
 * user-facing message. $preferred is the logo_filename the form wants it called
 * (may be ''); the extension is always forced to match the real image type.
 */
function logo_upload_save($file, $preferred = '')
{
    if (!is_array($file) || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Logo upload failed (PHP upload error ' . (int) $file['error'] . ').');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Logo upload was not a valid file.');
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Logo is too large (max 5 MB).');
    }
    $info = @getimagesize($file['tmp_name']);
    $exts = array(
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
    );
    if ($info === false || !isset($exts[$info[2]])) {
        throw new RuntimeException('Logo must be a PNG, JPEG, GIF or WebP image.');
    }

    $base = trim($preferred) !== '' ? basename(trim($preferred)) : basename($file['name']);
    $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base);
    $base = ltrim($base, '.');
    $base = preg_replace('/\.[A-Za-z0-9]+$/', '', $base);
    if ($base === '' || $base === null) {
        throw new RuntimeException('Could not work out a filename for the logo.');
    }
    $name = $base . '.' . $exts[$info[2]];

    $dir = APP_ROOT . '/logos';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        throw new RuntimeException('No writable /logos/ directory on this server.');
    }
    if (!@move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Could not write the logo into /logos/ (directory permissions?).');
    }
    return $name;
}

/* --------------------------------------------------------------------------
 * Field normalisation
 * ------------------------------------------------------------------------ */

/** "#RRGGBB" (upper-case) for any 6-digit hex with or without '#', '' for blank, null if invalid. */
function normalize_hex($v)
{
    $v = trim((string) $v);
    if ($v === '') {
        return '';
    }
    if (!preg_match('/^#?[0-9A-Fa-f]{6}$/', $v)) {
        return null;
    }
    return '#' . strtoupper(ltrim($v, '#'));
}

/** URL-safe slug from a team name. Never all-digits, so it can't be confused with an id. */
function slugify($s)
{
    $s = strtolower(trim((string) $s));
    $s = str_replace('&', ' and ', $s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    if ($s === '') {
        $s = 'team';
    }
    if (preg_match('/^\d+$/', $s)) {
        $s = 'team-' . $s;
    }
    return $s;
}

/**
 * Slugs share the URL space with the app's own paths ({base}/{slug} vs {base}/admin,
 * {base}/logos/...), so these can never be team slugs.
 */
function RESERVED_SLUGS()
{
    return array('admin', 'docs', 'bg', 'logos', 'export', 'src', 'bin', 'database', 'index', 'config');
}

function slug_valid($s)
{
    return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $s) && !preg_match('/^\d+$/', $s)
        && !in_array($s, RESERVED_SLUGS(), true);
}

/** $base, or $base-2, $base-3, ... whichever is free (ignoring team $exceptId). */
function slug_unique($base, $exceptId = 0)
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM teams WHERE slug = ? AND id <> ?');
    $slug = $base;
    for ($n = 2; ; $n++) {
        $stmt->execute(array($slug, (int) $exceptId));
        if ((int) $stmt->fetchColumn() === 0 && !in_array($slug, RESERVED_SLUGS(), true)) {
            return $slug;
        }
        $slug = $base . '-' . $n;
    }
}

/** SQLite "YYYY-MM-DD HH:MM:SS" (UTC) to ISO 8601 "YYYY-MM-DDTHH:MM:SSZ". */
function iso_utc($sqlTime)
{
    return $sqlTime === '' || $sqlTime === null ? null : str_replace(' ', 'T', $sqlTime) . 'Z';
}

/* --------------------------------------------------------------------------
 * Teams
 * ------------------------------------------------------------------------ */

/** Editable columns, in form order. */
function TEAM_FIELDS()
{
    return array(
        'full_name', 'short_name', 'code', 'mascot', 'slug',
        'location', 'conference', 'website',
        'primary_color', 'secondary_color', 'logo_bg_hex', 'logo_filename',
        'stats_provider', 'stat_id',
    );
}

function COLOR_FIELDS()
{
    return array('primary_color', 'secondary_color', 'logo_bg_hex');
}

function teams_all()
{
    return db()->query('SELECT * FROM teams ORDER BY full_name COLLATE NOCASE')->fetchAll();
}

function team_find($id)
{
    $stmt = db()->prepare('SELECT * FROM teams WHERE id = ?');
    $stmt->execute(array((int) $id));
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Look a team up by numeric id or by slug. */
function team_find_by_key($key)
{
    if (preg_match('/^\d+$/', (string) $key)) {
        return team_find($key);
    }
    $stmt = db()->prepare('SELECT * FROM teams WHERE slug = ?');
    $stmt->execute(array(strtolower($key)));
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Validate + normalise submitted team fields. Returns the cleaned array; throws
 * RuntimeException with a user-facing message when something is wrong.
 * A blank slug is generated from full_name (only ever on create or when cleared,
 * so renaming a team doesn't break consumer apps that stored its slug).
 */
function team_clean($id, array $data)
{
    $out = array();
    foreach (TEAM_FIELDS() as $f) {
        $out[$f] = isset($data[$f]) ? preg_replace('/\s{2,}/', ' ', trim((string) $data[$f])) : '';
    }
    if ($out['full_name'] === '') {
        throw new RuntimeException('Full name is required.');
    }
    foreach (COLOR_FIELDS() as $f) {
        $hex = normalize_hex($out[$f]);
        if ($hex === null) {
            throw new RuntimeException('Colours must be 6-digit hex values like #791716.');
        }
        $out[$f] = $hex;
    }
    $out['slug'] = strtolower($out['slug']);
    if ($out['slug'] === '') {
        $out['slug'] = slug_unique(slugify($out['full_name']), $id);
    } elseif (!slug_valid($out['slug'])) {
        throw new RuntimeException('Slug may only contain a-z, 0-9 and single hyphens, cannot be all digits, and cannot be one of: ' . implode(', ', RESERVED_SLUGS()) . '.');
    }
    return $out;
}

/** Insert ($id = 0) or update a team. Returns its id. Expects team_clean() output. */
function team_save($id, array $data)
{
    $fields = TEAM_FIELDS();
    $values = array();
    foreach ($fields as $f) {
        $values[':' . $f] = isset($data[$f]) ? $data[$f] : '';
    }
    if ($id) {
        $set = array();
        foreach ($fields as $f) {
            $set[] = "$f = :$f";
        }
        $values[':id'] = (int) $id;
        $sql = 'UPDATE teams SET ' . implode(', ', $set) . ', updated_at = CURRENT_TIMESTAMP WHERE id = :id';
        db()->prepare($sql)->execute($values);
        return (int) $id;
    }
    $cols = implode(', ', $fields);
    $ph = ':' . implode(', :', $fields);
    db()->prepare("INSERT INTO teams ($cols) VALUES ($ph)")->execute($values);
    return (int) db()->lastInsertId();
}

function team_delete($id)
{
    db()->prepare('DELETE FROM teams WHERE id = ?')->execute(array((int) $id));
}
