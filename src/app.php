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

/**
 * URL path the app is served from, no trailing slash ('' at the web root). config
 * 'base_path' when set; otherwise the directory index.php sits in (Apache), or '' (php -S).
 */
function base_path()
{
    static $base = null;
    if ($base === null) {
        $cfg = config();
        if (isset($cfg['base_path'])) {
            $base = (string) $cfg['base_path'];
        } elseif (PHP_SAPI !== 'cli-server' && PHP_SAPI !== 'cli' && !empty($_SERVER['SCRIPT_NAME'])) {
            $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        } else {
            $base = '';
        }
        $base = rtrim($base, '/');
    }
    return $base;
}

/** URL for an app path, honouring base_path. */
function url($path = '')
{
    $base = base_path();
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
    return $proto . '://' . $host . base_path();
}

function redirect($path)
{
    header('Location: ' . url($path));
    exit;
}

function request_path()
{
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $base = base_path();
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
 * Admin auth. config 'auth' picks the mode:
 *   'password'   (default) built-in login form; accounts in 'admin_users' (bin/add_admin.php).
 *   'shibboleth' the web server's SSO sets REMOTE_USER (.htaccess); 'shib_admins' lists who
 *                may in. Either way the app fails closed when no allowed user is present.
 * ------------------------------------------------------------------------ */

function auth_mode()
{
    $cfg = config();
    return isset($cfg['auth']) && $cfg['auth'] === 'shibboleth' ? 'shibboleth' : 'password';
}

/** Start the admin session (password mode). Cookie scoped to base_path, HttpOnly, SameSite=Lax. */
function admin_session_start()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $path = url('/');
    $secure = strpos(base_url(), 'https://') === 0;
    session_name('teamdata_admin');
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params(array(
            'path' => $path, 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax',
        ));
    } else { // 7.2 has no samesite option; it rides along in the path attribute
        session_set_cookie_params(0, $path . '; samesite=Lax', '', $secure, true);
    }
    session_start();
}

/** Signed-in admin's username, or '' when there is none (or they are not allowed). */
function current_user()
{
    $cfg = config();
    // Local dev: dev_user stands in for a login, and only under the built-in server.
    if (PHP_SAPI === 'cli-server' && !empty($cfg['dev_user'])) {
        return (string) $cfg['dev_user'];
    }
    if (auth_mode() === 'shibboleth') {
        // After mod_rewrite's internal redirect to index.php Apache may expose it as REDIRECT_REMOTE_USER.
        foreach (array('REMOTE_USER', 'REDIRECT_REMOTE_USER') as $k) {
            if (!empty($_SERVER[$k])) {
                $user = (string) $_SERVER[$k];
                $allowed = isset($cfg['shib_admins']) ? (array) $cfg['shib_admins'] : array();
                return in_array(strtolower($user), array_map('strtolower', $allowed), true) ? $user : '';
            }
        }
        return '';
    }
    if (session_status() !== PHP_SESSION_ACTIVE && !isset($_COOKIE['teamdata_admin'])) {
        return ''; // don't start a session (and set a cookie) for visitors who never logged in
    }
    admin_session_start();
    $user = isset($_SESSION['user']) ? (string) $_SESSION['user'] : '';
    $users = admin_users();
    return $user !== '' && isset($users[$user]) ? $user : ''; // removed from config = logged out
}

/** username => password hash, from config 'admin_users'. */
function admin_users()
{
    $cfg = config();
    return isset($cfg['admin_users']) && is_array($cfg['admin_users']) ? $cfg['admin_users'] : array();
}

/** Check a username/password against admin_users and start a session. True on success. */
function admin_login($user, $password)
{
    $users = admin_users();
    // Verify against a dummy hash for unknown users so timing doesn't reveal which names exist.
    $hash = isset($users[$user]) ? (string) $users[$user] : '$2y$10$v5pMaWWzC7ZCm7QQfB9hYewwIXD3o8.B4tFqyB5bJsL4xrdmkTKz6';
    if (!password_verify((string) $password, $hash) || !isset($users[$user])) {
        sleep(1); // slow down guessing
        return false;
    }
    admin_session_start();
    session_regenerate_id(true);
    $_SESSION['user'] = $user;
    return true;
}

function admin_logout()
{
    admin_session_start();
    $_SESSION = array();
    session_destroy();
}

/**
 * True when a POST came from one of this site's own pages, judged by the Origin
 * header (or Referer when a browser omits Origin). Blocks cross-site form posts
 * riding on an admin's login.
 */
function same_origin_request()
{
    $src = !empty($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN']
         : (!empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
    $host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
    return $src !== '' && $host !== '' && strtolower((string) parse_url($src, PHP_URL_HOST)
        . (parse_url($src, PHP_URL_PORT) ? ':' . parse_url($src, PHP_URL_PORT) : '')) === $host;
}

/** Continue only for a signed-in admin: password mode sends others to the login form, SSO mode 403s. */
function require_admin()
{
    if (current_user() !== '') {
        return;
    }
    if (auth_mode() === 'password') {
        redirect('/admin/login');
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
        'full_name', 'variant', 'short_name', 'code', 'mascot', 'slug',
        'location', 'conference', 'website', 'website_provider',
        'primary_color', 'secondary_color', 'logo_bg_hex', 'logo_filename',
        'stat_id',
    );
}

function COLOR_FIELDS()
{
    return array('primary_color', 'secondary_color', 'logo_bg_hex');
}

/** Sort order for team lists: by name, the plain entry before its variants. */
function TEAM_ORDER()
{
    return 'full_name COLLATE NOCASE, variant COLLATE NOCASE';
}

/**
 * Split a legacy "Name [Variant]" full name into array(name, variant).
 * Used to migrate/import names that carried the variant in brackets.
 */
function split_variant($fullName)
{
    $fullName = trim((string) $fullName);
    if (preg_match('/^(.*\S)\s*\[([^\]]+)\]$/', $fullName, $m)) {
        return array($m[1], trim($m[2]));
    }
    return array($fullName, '');
}

function teams_all()
{
    return db()->query('SELECT * FROM teams ORDER BY ' . TEAM_ORDER())->fetchAll();
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
 * A blank slug is generated from full_name + variant (only ever on create or when cleared,
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
        $out['slug'] = slug_unique(slugify(trim($out['full_name'] . ' ' . $out['variant'])), $id);
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
