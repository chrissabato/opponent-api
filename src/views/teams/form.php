<?php
/** @var array|null $team @var string|null $error */
$v = function ($k) use ($team) { return $team && isset($team[$k]) ? $team[$k] : ''; };
$id = $team && isset($team['id']) ? (int) $team['id'] : 0;
$action = $id ? url('/admin/' . $id) : url('/admin');
$fields = array(
    'full_name'       => 'Full name',
    'variant'         => 'Variant',
    'short_name'      => 'Short name',
    'code'            => 'Code',
    'mascot'          => 'Mascot',
    'slug'            => 'Slug',
    'location'        => 'Location',
    'conference'      => 'Conference',
    'website'         => 'Website',
    'website_provider' => 'Website provider',
    'primary_color'   => 'Primary colour',
    'secondary_color' => 'Secondary colour',
    'logo_bg_hex'     => 'Logo BG colour',
    'logo_filename'   => 'Logo filename',
    'stat_id'         => 'Stat ID',
);
$hints = array(
    'variant'         => 'Only when a school has more than one entry (e.g. Men / Women, different mascot or logo). Blank otherwise.',
    'slug'            => 'Stable ID other apps store. Leave blank to generate from the full name. Changing it breaks apps that saved the old one.',
    'primary_color'   => 'Main brand colour.',
    'secondary_color' => 'Accent colour.',
    'logo_bg_hex'     => 'Flat fill behind the logo in graphics. Blank keeps the generic Home/Away art.',
);
?>
<div class="bg-white rounded-lg shadow-sm p-5 max-w-2xl">
  <h1 class="text-lg font-semibold mb-4"><?= $id ? 'Edit team' : 'New team' ?></h1>
  <?php if (!empty($error)): ?>
    <p class="mb-4 rounded bg-red-50 text-red-700 text-sm px-3 py-2"><?= e($error) ?></p>
  <?php endif ?>
  <form method="post" action="<?= $action ?>" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-2">
    <?php foreach ($fields as $key => $label): ?>
      <?php $wrap = in_array($key, array('full_name', 'slug', 'logo_filename'), true) ? ' md:col-span-2' : ''; ?>

      <?php if (in_array($key, COLOR_FIELDS(), true)): ?>
        <?php
          $hex = trim($v($key));
          $pickerVal = preg_match('/^#?[0-9A-Fa-f]{6}$/', $hex) ? '#' . ltrim($hex, '#') : '#1E293B';
        ?>
        <div class="block<?= $wrap ?>" data-color-field>
          <span class="text-sm font-medium"><?= e($label) ?></span>
          <span class="mt-1 flex items-center gap-2">
            <input name="<?= e($key) ?>" value="<?= e($hex) ?>" data-hex
                   placeholder="#RRGGBB" pattern="#?[0-9A-Fa-f]{6}"
                   class="w-full rounded border border-slate-300 px-3 py-2 text-sm font-mono">
            <input type="color" value="<?= e($pickerVal) ?>" data-picker aria-label="Pick <?= e(strtolower($label)) ?>"
                   class="h-9 w-10 shrink-0 cursor-pointer rounded border border-slate-300 bg-white p-1">
            <button type="button" data-clear
                    class="shrink-0 rounded border border-slate-300 px-2 py-2 text-xs text-slate-500 hover:bg-slate-50">Clear</button>
          </span>
          <span class="mt-1 block text-xs text-slate-400"><?= e($hints[$key]) ?></span>
        </div>

      <?php elseif ($key === 'logo_filename'): ?>
        <div class="block<?= $wrap ?>">
          <span class="text-sm font-medium"><?= e($label) ?></span>
          <input name="logo_filename" value="<?= e($v($key)) ?>"
                 class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm font-mono">
          <?php if (logo_asset_url($v($key)) !== ''): ?>
            <?php $bg = trim($v('logo_bg_hex')); ?>
            <img src="<?= e(logo_asset_url($v($key))) ?>" alt=""
                 class="mt-2 h-12 w-24 object-contain rounded border border-slate-200<?= $bg === '' ? ' bg-slate-100' : '' ?>"
                 <?= $bg !== '' ? 'style="background: ' . e($bg) . '"' : '' ?>>
          <?php elseif (trim($v($key)) !== ''): ?>
            <span class="mt-2 block text-xs text-slate-400">Not in <code>/logos/</code> &mdash; the API will return <code>logo.url: null</code>.</span>
          <?php endif ?>
          <input type="file" name="logo_file" accept="image/png,image/jpeg,image/gif,image/webp"
                 class="mt-2 block w-full text-xs text-slate-600 file:mr-2 file:rounded file:border-0 file:bg-slate-900 file:px-3 file:py-1.5 file:text-xs file:text-white hover:file:bg-slate-700">
          <span class="mt-1 block text-xs text-slate-400">
            Uploads to <code>/logos/</code> on this server &mdash; saved as the filename above when set, otherwise the file&rsquo;s own name.
          </span>
        </div>

      <?php else: ?>
        <label class="block<?= $wrap ?>">
          <span class="text-sm font-medium"><?= e($label) ?></span>
          <input name="<?= e($key) ?>" value="<?= e($v($key)) ?>"
                 class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm<?= $key === 'slug' ? ' font-mono' : '' ?>"
                 <?= $key === 'full_name' ? 'required' : '' ?>
                 <?= $key === 'slug' ? 'pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="generated from full name"' : '' ?>>
          <?php if (isset($hints[$key])): ?>
            <span class="mt-1 block text-xs text-slate-400"><?= e($hints[$key]) ?></span>
          <?php endif ?>
        </label>
      <?php endif ?>
    <?php endforeach ?>

    <div class="md:col-span-2 flex gap-3">
      <button class="rounded bg-slate-900 text-white text-sm px-4 py-2 hover:bg-slate-700">Save</button>
      <a href="<?= url('/admin') ?>" class="rounded border border-slate-300 text-sm px-4 py-2 hover:bg-slate-50">Cancel</a>
    </div>
  </form>
</div>

<script>
(function () {
  var re = /^#?[0-9a-f]{6}$/i;
  var fields = document.querySelectorAll('[data-color-field]');
  for (var i = 0; i < fields.length; i++) {
    (function (f) {
      var hex = f.querySelector('[data-hex]');
      var picker = f.querySelector('[data-picker]');
      hex.addEventListener('input', function () {
        var val = hex.value.trim();
        if (re.test(val)) { picker.value = '#' + val.replace(/^#/, ''); }
      });
      picker.addEventListener('input', function () { hex.value = picker.value.toUpperCase(); });
      f.querySelector('[data-clear]').addEventListener('click', function () { hex.value = ''; hex.focus(); });
    })(fields[i]);
  }
})();
</script>
