<?php
// Copy to config.php and adjust for the environment. config.php is git-ignored.
return array(
    // Absolute path to the SQLite file. Stays under database/, which .htaccess blocks.
    'db_path' => __DIR__ . '/database/teams.sqlite',

    // URL path the app is served from, no trailing slash (e.g. '/api/teams', or '' for a
    // site root). Leave unset to use the directory index.php is in; set it if that guess is
    // wrong (e.g. behind an Alias or proxy).
    // 'base_path' => '/api/teams',

    // How /admin is protected:
    //   'password'   - built-in login form (default). Accounts go in admin_users below.
    //   'shibboleth' - your web server's Shibboleth SSO; see the commented block in
    //                  .htaccess and list allowed REMOTE_USER values in shib_admins.
    'auth' => 'password',

    // auth = 'password': username => password hash. Make a line with
    //   php bin/add_admin.php <username>
    'admin_users' => array(
    ),

    // auth = 'shibboleth': REMOTE_USER values allowed into /admin (case-insensitive).
    // To find someone's value, add them, have them sign in, and read the "Not authorised"
    // page, which shows who they are signed in as.
    'shib_admins' => array(
        // 'someone@example.edu',
    ),

    // Local dev only: skip login and act as this user (honoured only by `php -S`, never
    // by Apache or nginx).
    // 'dev_user' => 'dev@localhost',
);
