<div class="bg-white rounded-lg shadow-sm p-5 max-w-2xl">
  <h1 class="text-lg font-semibold mb-2">Not authorised</h1>
  <?php $u = !empty($_SERVER['REMOTE_USER']) ? $_SERVER['REMOTE_USER'] : (!empty($_SERVER['REDIRECT_REMOTE_USER']) ? $_SERVER['REDIRECT_REMOTE_USER'] : ''); ?>
  <?php if ($u !== ''): ?>
    <p class="text-sm text-slate-600">
      You are signed in as <code><?= e($u) ?></code>, but that user isn&rsquo;t in <code>shib_admins</code>
      in <code>config.php</code>.
    </p>
  <?php else: ?>
    <p class="text-sm text-slate-600">
      No SSO login reached the app, so the admin pages are locked. Either the Shibboleth rule in
      <code>.htaccess</code> isn&rsquo;t applying to this URL, or the server isn&rsquo;t providing a
      username (<code>REMOTE_USER</code>).
    </p>
  <?php endif ?>
  <p class="mt-4 text-sm"><a class="text-blue-600 hover:underline" href="<?= url('/docs') ?>">API documentation</a> is public.</p>
</div>
