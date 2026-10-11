<?php /** @var string|null $error @var bool $noAdmins */ ?>
<div class="bg-white rounded-lg shadow-sm p-5 max-w-sm mx-auto">
  <h1 class="text-lg font-semibold mb-4">Admin log in</h1>
  <?php if ($noAdmins): ?>
    <p class="mb-4 text-sm rounded bg-amber-50 text-amber-800 p-3">
      No admin accounts yet. On the server run <code>php bin/add_admin.php &lt;username&gt;</code>
      and paste its output into <code>admin_users</code> in <code>config.php</code>.
    </p>
  <?php endif ?>
  <?php if ($error): ?>
    <p class="mb-4 text-sm rounded bg-red-50 text-red-700 p-3"><?= e($error) ?></p>
  <?php endif ?>
  <form method="post" action="<?= url('/admin/login') ?>" class="space-y-3">
    <label class="block text-sm">Username
      <input name="username" autocomplete="username" required autofocus value="<?= e(param('username')) ?>"
             class="mt-1 w-full rounded border-slate-300 border px-2 py-1.5">
    </label>
    <label class="block text-sm">Password
      <input name="password" type="password" autocomplete="current-password" required
             class="mt-1 w-full rounded border-slate-300 border px-2 py-1.5">
    </label>
    <button class="rounded bg-slate-900 text-white text-sm px-4 py-2 hover:bg-slate-700">Log in</button>
  </form>
  <p class="mt-4 text-sm"><a class="text-blue-600 hover:underline" href="<?= url('/docs') ?>">API documentation</a> is public.</p>
</div>
