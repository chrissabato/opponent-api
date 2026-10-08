# Team Data API

One place to manage team identity data — names, codes, mascots, colours, logos, stats
IDs — and a read-only JSON/CSV API so other apps (vmix-config, etc.) stop keeping their
own copies.

## Requirements

- PHP 7.2+ with `pdo_sqlite` (Apache + mod_php, mod_rewrite; mod_headers for logo CORS)
- Write access to `database/` and `logos/` for the Apache user

## Install (server)

1. Upload this whole directory to `athletics.willamette.edu/api/teams/`.
2. `cp config.example.php config.php` (the example already has the right `base_path`).
3. `php bin/init_db.php` to create `database/teams.sqlite`.
4. Load data: either `php bin/import_vmix.php /path/to/vmix-config` on the server, or run
   the import locally and upload `database/teams.sqlite` + `logos/`.

If the app is ever moved to a different URL path, change `base_path` in `config.php` **and**
`RewriteBase` in `.htaccess` to match.

## Local development

```bash
cp config.example.php config.php     # same base_path as production works locally
php bin/init_db.php
php bin/import_vmix.php              # pulls teams + logos from ../vmix-config
php -S localhost:8001 index.php      # then open http://localhost:8001/api/teams/admin
```

## API (read-only, no key, CORS `*`)

| Path                          | Purpose                                              |
|-------------------------------|------------------------------------------------------|
| `/api/teams/`                 | All teams (JSON). Filters: `q`, `slug`, `conference`, `updated_since` |
| `/api/teams/{slug\|id}`        | One team (JSON)                                      |
| `/api/teams/export.csv`       | All teams, flat CSV (same filters)                   |
| `/api/teams/bg/{hex}.png`     | 64×64 solid-colour PNG                               |
| `/api/teams/logos/{file}`     | Logo images                                          |

Use the trailing slash on the list URL: Apache 301s `/api/teams` → `/api/teams/` and that
redirect has no CORS header, so a cross-origin `fetch()` of the slashless form fails.

The `/api/teams/docs` page shows the full team object and copy-paste examples.
Consumer apps should store the team **slug**.

## Admin UI

Protected by campus SSO. `.htaccess` has mod_shib require a login for `/api/teams/admin…`
and lists who may get in on its `Require shib-user` line (space-separated `REMOTE_USER`
values, e.g. `someone@willamette.edu`). To add an admin, edit that line. The API, logos
and `/docs` stay public.

To find someone's exact `REMOTE_USER`, temporarily change the line to
`Require shib-session` (any campus login), have them sign in: their username appears in
the page header. Then put the list back.

Local dev has no Shibboleth: `dev_user` in `config.php` stands in, and only under `php -S`.

| Path                 | Purpose                    |
|----------------------|----------------------------|
| `/api/teams/admin`   | Team list (CRUD)           |
| `/api/teams/docs`    | API documentation          |
