<?php /** @var array $teams */ ?>
<div class="flex flex-wrap items-center gap-3 mb-4">
  <h1 class="text-lg font-semibold">Teams <span class="text-slate-400 text-sm">(<?= count($teams) ?>)</span></h1>
  <input type="search" id="team-filter" placeholder="Filter&hellip;" autocomplete="off"
         class="ml-auto w-56 rounded border border-slate-300 px-3 py-2 text-sm bg-white">
  <a href="<?= url('/admin/new') ?>" class="rounded bg-slate-900 text-white text-sm px-4 py-2 hover:bg-slate-700">New team</a>
</div>

<div class="bg-white rounded-lg shadow-sm overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-slate-500 border-b bg-slate-50">
      <tr>
        <th class="px-3 py-2">Logo</th><th class="px-3 py-2">Full name</th>
        <th class="px-3 py-2">Short</th><th class="px-3 py-2">Code</th><th class="px-3 py-2">Mascot</th>
        <th class="px-3 py-2">Colours</th><th class="px-3 py-2">Slug</th><th class="px-3 py-2"></th>
      </tr>
    </thead>
    <tbody class="divide-y">
    <?php foreach ($teams as $t): ?>
      <?php $haystack = strtolower($t['full_name'] . ' ' . $t['short_name'] . ' ' . $t['code'] . ' ' . $t['mascot'] . ' ' . $t['slug']); ?>
      <tr class="hover:bg-slate-50" data-team-row="<?= e($haystack) ?>">
        <td class="px-3 py-2">
          <?php $logoSrc = logo_asset_url($t['logo_filename']); ?>
          <?php $logoBg = trim($t['logo_bg_hex']); ?>
          <?php if ($logoSrc !== ''): ?>
            <img src="<?= e($logoSrc) ?>" alt="" loading="lazy" title="<?= e($t['logo_filename']) ?>"
                 class="h-8 w-16 object-contain rounded<?= $logoBg === '' ? ' bg-slate-100' : '' ?>"
                 <?= $logoBg !== '' ? 'style="background: ' . e($logoBg) . '"' : '' ?>>
          <?php else: ?>
            <span class="font-mono text-xs text-slate-400"><?= e($t['logo_filename']) ?></span>
          <?php endif ?>
        </td>
        <td class="px-3 py-2 font-medium"><?= e($t['full_name']) ?></td>
        <td class="px-3 py-2"><?= e($t['short_name']) ?></td>
        <td class="px-3 py-2"><?= e($t['code']) ?></td>
        <td class="px-3 py-2"><?= e($t['mascot']) ?></td>
        <td class="px-3 py-2 whitespace-nowrap">
          <?php foreach (COLOR_FIELDS() as $cf): ?>
            <?php if ($t[$cf] !== ''): ?>
              <span class="inline-block h-4 w-4 rounded border border-slate-300 align-middle"
                    style="background: <?= e($t[$cf]) ?>" title="<?= e(str_replace('_', ' ', $cf) . ' ' . $t[$cf]) ?>"></span>
            <?php else: ?>
              <span class="inline-block h-4 w-4 rounded border border-dashed border-slate-300 align-middle" title="<?= e(str_replace('_', ' ', $cf)) ?> not set"></span>
            <?php endif ?>
          <?php endforeach ?>
        </td>
        <td class="px-3 py-2 font-mono text-xs text-slate-500"><?= e($t['slug']) ?></td>
        <td class="px-3 py-2 text-right whitespace-nowrap">
          <a href="<?= url('/admin/' . $t['id'] . '/edit') ?>" class="text-blue-600 hover:underline">Edit</a>
          <a href="<?= url('/' . $t['slug']) ?>" class="ml-2 text-slate-500 hover:underline">JSON</a>
          <form method="post" action="<?= url('/admin/' . $t['id'] . '/delete') ?>" class="inline"
                onsubmit="return confirm('Delete <?= e(addslashes($t['full_name'])) ?>? Apps referencing its slug will get a 404.')">
            <button class="ml-2 text-red-600 hover:underline">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</div>

<script>
(function () {
  var box = document.getElementById('team-filter');
  var rows = document.querySelectorAll('[data-team-row]');
  box.addEventListener('input', function () {
    var q = box.value.trim().toLowerCase();
    for (var i = 0; i < rows.length; i++) {
      rows[i].style.display = rows[i].getAttribute('data-team-row').indexOf(q) === -1 ? 'none' : '';
    }
  });
})();
</script>
