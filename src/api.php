<?php
/**
 * Read-only public API for consumer apps. Every response is CORS-open (team data
 * is public) and carries an ETag so pollers get a cheap 304 when nothing changed.
 *
 * The JSON shape of team_resource() is the contract other apps code against:
 * add fields freely, but don't rename or remove existing ones.
 */

require_once __DIR__ . '/app.php';

/** Public JSON representation of one team row. */
function team_resource(array $t)
{
    $real = logo_real_name($t['logo_filename']);
    $bg = strtolower(ltrim($t['logo_bg_hex'], '#'));
    return array(
        'id'         => (int) $t['id'],
        'slug'       => $t['slug'],
        'full_name'  => $t['full_name'],
        'short_name' => $t['short_name'],
        'code'       => $t['code'],
        'mascot'     => $t['mascot'],
        'location'   => $t['location'],
        'conference' => $t['conference'],
        'website'    => $t['website'],
        'website_provider' => $t['website_provider'],
        'colors'     => array(
            'primary'   => $t['primary_color'],
            'secondary' => $t['secondary_color'],
            'logo_bg'   => $t['logo_bg_hex'],
        ),
        'logo'       => array(
            'filename' => $t['logo_filename'],
            // null when the file hasn't been uploaded to /logos/ on this server
            'url'      => $real !== '' ? base_url() . '/logos/' . rawurlencode($real) : null,
            'bg_url'   => $bg !== '' ? base_url() . '/bg/' . $bg . '.png' : null,
        ),
        'stats'      => array(
            'id'       => $t['stat_id'],
        ),
        'updated_at' => iso_utc($t['updated_at']),
    );
}

/** Flat columns for export.csv (spreadsheets, vMix data sources). */
function team_csv_columns()
{
    return array(
        'id', 'slug', 'full_name', 'short_name', 'code', 'mascot', 'location', 'conference', 'website', 'website_provider',
        'primary_color', 'secondary_color', 'logo_bg_hex', 'logo_filename', 'logo_url', 'logo_bg_url',
        'stat_id', 'updated_at',
    );
}

/**
 * Teams matching the request's query-string filters:
 *   q=             substring of full/short name, code or mascot (case-insensitive)
 *   slug=a,b,c     only these slugs
 *   conference=    exact conference (case-insensitive)
 *   updated_since= ISO 8601 / any strtotime() date; teams changed at or after it
 * Throws InvalidArgumentException on a bad filter value.
 */
function api_query_teams()
{
    $where = array();
    $args = array();

    $q = param('q');
    if ($q !== '') {
        // One placeholder per column: reusing a named placeholder isn't portable across PDO setups.
        $like = array();
        foreach (array('full_name', 'short_name', 'code', 'mascot') as $i => $col) {
            $like[] = "$col LIKE :q$i ESCAPE '\\'";
            $args[':q' . $i] = '%' . str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $q) . '%';
        }
        $where[] = '(' . implode(' OR ', $like) . ')';
    }

    $slugs = param('slug');
    if ($slugs !== '') {
        $ph = array();
        foreach (array_values(array_filter(array_map('trim', explode(',', strtolower($slugs))), 'strlen')) as $i => $s) {
            $ph[] = ':s' . $i;
            $args[':s' . $i] = $s;
        }
        $where[] = $ph ? 'slug IN (' . implode(', ', $ph) . ')' : '0';
    }

    $conf = param('conference');
    if ($conf !== '') {
        $where[] = 'conference = :conf COLLATE NOCASE';
        $args[':conf'] = $conf;
    }

    $since = param('updated_since');
    if ($since !== '') {
        $ts = strtotime($since);
        if ($ts === false) {
            throw new InvalidArgumentException('updated_since is not a recognisable date.');
        }
        $where[] = 'updated_at >= :since';
        $args[':since'] = gmdate('Y-m-d H:i:s', $ts);
    }

    $sql = 'SELECT * FROM teams' . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY full_name COLLATE NOCASE';
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function api_cors_headers()
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: If-None-Match');
    header('Access-Control-Expose-Headers: ETag');
}

/**
 * Send $body with an ETag; answers 304 with no body when the client already has it.
 * max-age is short: consumer apps should see admin edits within a minute.
 */
function api_send($body, $contentType)
{
    $etag = '"' . md5($body) . '"';
    header('ETag: ' . $etag);
    header('Cache-Control: public, max-age=60');
    $inm = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : '';
    if ($inm !== '' && ($inm === $etag || $inm === 'W/' . $etag || $inm === '*')) {
        http_response_code(304);
        return;
    }
    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . strlen($body));
    echo $body;
}

function api_json($data)
{
    api_send(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        'application/json; charset=utf-8');
}

function api_error($status, $message)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(array('error' => $message)) . "\n";
}

function send_teams_json()
{
    try {
        $rows = api_query_teams();
    } catch (InvalidArgumentException $ex) {
        api_error(400, $ex->getMessage());
        return;
    }
    api_json(array(
        'count' => count($rows),
        'teams' => array_map('team_resource', $rows),
    ));
}

function send_team_json($key)
{
    $t = team_find_by_key($key);
    if (!$t) {
        api_error(404, 'Team not found.');
        return;
    }
    api_json(team_resource($t));
}

/** RFC 4180 field: quote when it contains a comma, quote, CR or LF. */
function csv_field($v)
{
    $v = (string) $v;
    if (preg_match('/[",\r\n]/', $v)) {
        return '"' . str_replace('"', '""', $v) . '"';
    }
    return $v;
}

function csv_line(array $fields)
{
    return implode(',', array_map('csv_field', $fields)) . "\r\n";
}

function send_teams_csv()
{
    try {
        $rows = api_query_teams();
    } catch (InvalidArgumentException $ex) {
        api_error(400, $ex->getMessage());
        return;
    }
    $cols = team_csv_columns();
    $out = csv_line($cols);
    foreach ($rows as $t) {
        $r = team_resource($t);
        $t['logo_url'] = $r['logo']['url'];
        $t['logo_bg_url'] = $r['logo']['bg_url'];
        $t['updated_at'] = $r['updated_at'];
        $line = array();
        foreach ($cols as $c) {
            $line[] = $t[$c];
        }
        $out .= csv_line($line);
    }
    api_send($out, 'text/csv; charset=utf-8');
}

/** One PNG chunk: length + type + data + CRC32(type+data). */
function png_chunk($type, $data)
{
    return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
}

/**
 * A $size x $size solid-colour RGB PNG for "#RRGGBB", built by hand (no GD needed;
 * only zlib). Returns null for a malformed hex.
 */
function solid_color_png($hex, $size = 64)
{
    $hex = ltrim(trim($hex), '#');
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        return null;
    }
    $rgb = chr(hexdec(substr($hex, 0, 2)))
         . chr(hexdec(substr($hex, 2, 2)))
         . chr(hexdec(substr($hex, 4, 2)));
    $ihdr = pack('NN', $size, $size) . "\x08\x02\x00\x00\x00"; // 8-bit, colour type 2 (RGB)
    $scanline = "\x00" . str_repeat($rgb, $size);              // filter byte 0 + pixels
    $idat = gzcompress(str_repeat($scanline, $size), 9);
    return "\x89PNG\r\n\x1a\n"
        . png_chunk('IHDR', $ihdr)
        . png_chunk('IDAT', $idat)
        . png_chunk('IEND', '');
}
