<div class="bg-white rounded-lg shadow-sm p-5 max-w-2xl">
  <h1 class="text-lg font-semibold mb-2">Not authorised</h1>
  <p class="text-sm text-slate-600">
    No campus login reached the app, so the admin pages are locked. Either the SSO rule in
    <code>.htaccess</code> isn&rsquo;t applying to this URL, or Shibboleth isn&rsquo;t providing a
    username (<code>REMOTE_USER</code>).
  </p>
  <p class="mt-4 text-sm"><a class="text-blue-600 hover:underline" href="<?= url('/docs') ?>">API documentation</a> is public.</p>
</div>
