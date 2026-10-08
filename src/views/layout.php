<?php /** @var string $content @var string $title */ ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> &middot; Team Data</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800">
<header class="bg-slate-900 text-slate-100">
  <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-6">
    <a href="<?= url('/admin') ?>" class="font-semibold tracking-tight">Team Data</a>
    <nav class="flex gap-4 text-sm">
      <a class="hover:text-white/80" href="<?= url('/admin') ?>">Teams</a>
      <a class="hover:text-white/80" href="<?= url('/docs') ?>">API</a>
    </nav>
  </div>
</header>
<main class="max-w-6xl mx-auto px-4 py-6">
<?= $content ?>
</main>
</body>
</html>
