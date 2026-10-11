<?php
/** @var array|null $sample  one team row to show as the example response */
$base = base_url();
$exampleSlug = $sample ? $sample['slug'] : 'example-university';
// Trailing slash: on Apache {base} is a real directory, and mod_dir's 301 from
// {base} to {base}/ carries no CORS header, so cross-origin fetch() would fail.
$list = rtrim(url('/'), '/') . '/';
$endpoints = array(
    array($list, 'All teams as JSON: { "count": n, "teams": [ ... ] }, sorted by full name.'),
    array(url('/{slug}'), 'One team by slug (preferred) or numeric id. 404 JSON when missing.'),
    array(url('/export.csv'), 'All teams as a flat CSV (same filters). Good for spreadsheets and vMix data sources.'),
    array(url('/bg/{hex}.png'), 'A 64&times;64 solid-colour PNG, e.g. ' . e(url('/bg/791716.png')) . '. Already linked from logo.bg_url.'),
);
$filters = array(
    array('q', 'Case-insensitive substring of full name, variant, short name, code or mascot.', 'q=lute'),
    array('slug', 'Comma-separated list of slugs to fetch in one request.', 'slug=' . $exampleSlug . ',another-college'),
    array('conference', 'Exact conference name (case-insensitive).', 'conference=NWC'),
    array('variant', 'For schools with several entries (e.g. Men / Women), return that variant; schools without it return their plain entry. Case-insensitive.', 'variant=women'),
    array('updated_since', 'Only teams changed at or after this time (ISO 8601, UTC). For incremental syncs.', 'updated_since=2026-10-01T00:00:00Z'),
);
$pre = 'mt-2 rounded bg-slate-900 text-slate-100 text-xs p-3 overflow-x-auto';
?>
<div class="space-y-6 max-w-4xl">
  <section class="bg-white rounded-lg shadow-sm p-5">
    <h1 class="text-lg font-semibold">Team Data API</h1>
    <p class="mt-2 text-sm text-slate-600">
      Read-only, no key needed, CORS-enabled (<code>Access-Control-Allow-Origin: *</code>) so browser apps on
      other hosts can call it directly. Responses carry an <code>ETag</code> and <code>Cache-Control: max-age=60</code>;
      send <code>If-None-Match</code> to get a cheap <code>304</code> when nothing changed.
    </p>
    <p class="mt-2 text-sm text-slate-600">
      Request the list as <code class="font-mono"><?= e($list) ?></code> <em>with</em> the trailing slash &mdash; without it the
      server redirects first, and that redirect breaks cross-origin <code>fetch()</code>.
    </p>
    <p class="mt-2 text-sm text-slate-600">
      Store a team&rsquo;s <strong>slug</strong> in your app, not its name or id &mdash; names get edited, slugs are meant to stay put.
      Base URL: <code class="font-mono"><?= e($base) ?></code>
    </p>
    <p class="mt-2 text-sm text-slate-600">
      Source code: <a class="text-blue-600 hover:underline" href="https://github.com/chrissabato/opponent-api">github.com/chrissabato/opponent-api</a>
    </p>
  </section>

  <section class="bg-white rounded-lg shadow-sm p-5">
    <h2 class="font-semibold mb-2">Endpoints</h2>
    <table class="w-full text-sm">
      <tbody class="divide-y">
      <?php foreach ($endpoints as $ep): ?>
        <tr>
          <td class="py-2 pr-4 font-mono text-xs whitespace-nowrap align-top">GET <?= e($ep[0]) ?></td>
          <td class="py-2 text-slate-600"><?= $ep[1] ?></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>

    <h3 class="font-semibold mt-5 mb-2 text-sm">Filters (query string, for <code><?= e($list) ?></code> and <code><?= e(url('/export.csv')) ?></code>)</h3>
    <table class="w-full text-sm">
      <tbody class="divide-y">
      <?php foreach ($filters as $f): ?>
        <tr>
          <td class="py-2 pr-4 font-mono text-xs align-top"><?= e($f[0]) ?></td>
          <td class="py-2 text-slate-600">
            <?= e($f[1]) ?>
            <a class="block font-mono text-xs text-blue-600 hover:underline" href="<?= e($list . '?' . $f[2]) ?>"><?= e($list . '?' . $f[2]) ?></a>
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </section>

  <section class="bg-white rounded-lg shadow-sm p-5">
    <h2 class="font-semibold">Team object</h2>
    <p class="mt-1 text-sm text-slate-600">
      Unset text fields are <code>""</code>.
      <code>variant</code> is <code>""</code> unless the school has several entries (e.g. <code>"Men"</code> / <code>"Women"</code>) &mdash; show it alongside <code>full_name</code> where it matters. Colours are <code>"#RRGGBB"</code> or <code>""</code>.
      <code>logo.url</code> is <code>null</code> when no logo file has been uploaded;
      <code>logo.bg_url</code> is <code>null</code> when no logo BG colour is set.
      New fields may be added over time; existing ones won&rsquo;t be renamed or removed.
    </p>
    <pre class="<?= $pre ?>"><?= e($sample
        ? json_encode(team_resource($sample), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : '(add a team to see an example)') ?></pre>
  </section>

  <section class="bg-white rounded-lg shadow-sm p-5">
    <h2 class="font-semibold">Example (JavaScript)</h2>
    <pre class="<?= $pre ?>"><?= e("const API = '$base';

// Every team, e.g. to fill a picker
const { teams } = await fetch(`\${API}/`).then(r => r.json());   // keep the trailing slash

// One team by its stored slug
const team = await fetch(`\${API}/$exampleSlug`).then(r => r.json());
el.style.setProperty('--team-primary', team.colors.primary);
logo.src = team.logo.url ?? '/img/placeholder.png';") ?></pre>

    <h2 class="font-semibold mt-5">Example (PHP)</h2>
    <pre class="<?= $pre ?>"><?= e("\$team = json_decode(file_get_contents('$base/$exampleSlug'), true);
echo \$team['full_name'], ' ', \$team['colors']['primary'];") ?></pre>
  </section>
</div>
