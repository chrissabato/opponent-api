<?php
// Copy to config.php and adjust for the environment. config.php is git-ignored.
return array(
    // Absolute path to the SQLite file. Stays under database/, which .htaccess blocks.
    'db_path' => __DIR__ . '/database/teams.sqlite',

    // URL path the app is served from, no trailing slash.
    // Production: athletics.willamette.edu/api/teams/
    // Local dev (php -S localhost:8001 index.php) works with the same value.
    'base_path' => '/api/teams',

    // Local dev only: pretend to be this SSO user (honoured only by `php -S`, never under Apache).
    // 'dev_user' => 'dev@localhost',
);
