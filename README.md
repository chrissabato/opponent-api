# Team Data API

One place to manage team identity data — names, codes, mascots, colours, logos, stats
IDs — with an admin UI and a read-only JSON/CSV API, so your other apps (scoreboards,
graphics, websites) stop keeping their own copies of the team table.

Plain PHP + SQLite: no framework, no Composer, no build step.

## Requirements

- PHP 7.2+ with `pdo_sqlite` and `zlib`
- Apache with `mod_rewrite` (and `mod_headers` so logos are CORS-enabled)
- Write access to `database/` and `logos/` for the web server user

## Install

1. Copy this directory anywhere under your web root, e.g. `https://example.edu/api/teams/`.
2. Create the config files:
   ```bash
   cp config.example.php config.php
   cp .htaccess.example .htaccess
   ```
3. Create the database: `php bin/init_db.php`
4. Create an admin account and paste the line it prints into `admin_users` in `config.php`:
   ```bash
   php bin/add_admin.php alice
   ```
5. Open `.../admin`, log in, and add teams (logos can be uploaded from the team form).
6. Make `database/` (and the `.sqlite` file in it) and `logos/` writable by the web server user.

The URL path is detected from where `index.php` lives. If the app is reached through an
Apache `Alias` or a proxy, set `base_path` in `config.php` and `RewriteBase` in `.htaccess`
to the URL path (e.g. `/api/teams`).

## Admin login

`auth` in `config.php` picks how `/admin` is protected:

- **`password`** (default) — a built-in login form. Accounts live in `admin_users` as
  `username => password hash`; `php bin/add_admin.php <username>` makes the line. Delete a
  line to remove that admin.
- **`shibboleth`** — your server's Shibboleth SSO. Uncomment the Shibboleth block in
  `.htaccess` and list allowed `REMOTE_USER` values in `shib_admins`. Someone who signs in
  but isn't listed sees a "Not authorised" page showing the username to add.

Either way, admin form posts must come from the same host (CSRF check), and the API, logos
and `/docs` stay public.

## Local development

```bash
cp config.example.php config.php     # uncomment 'dev_user' to skip the login locally
php bin/init_db.php
php -S localhost:8001 index.php      # then open http://localhost:8001/admin
```

`dev_user` is honoured only by PHP's built-in server, never by Apache.

`bin/import_vmix.php` imports teams and logos from an install of the companion
vmix-config app; you can ignore it otherwise.

## API (read-only, no key, CORS `*`)

Paths are relative to wherever the app is installed (`/api/teams` here).

| Path                          | Purpose                                              |
|-------------------------------|------------------------------------------------------|
| `/api/teams/`                 | All teams (JSON). Filters: `q`, `slug`, `conference`, `variant`, `updated_since` |
| `/api/teams/{slug\|id}`        | One team (JSON)                                      |
| `/api/teams/export.csv`       | All teams, flat CSV (same filters)                   |
| `/api/teams/bg/{hex}.png`     | 64×64 solid-colour PNG                               |
| `/api/teams/logos/{file}`     | Logo images                                          |

Use the trailing slash on the list URL: Apache 301s `/api/teams` → `/api/teams/` and that
redirect has no CORS header, so a cross-origin `fetch()` of the slashless form fails.

The `/docs` page shows the full team object and copy-paste examples.
Consumer apps should store the team **slug**.

## License

MIT — see [LICENSE](LICENSE). Team names and logos are the property of their owners; the
logo files aren't part of this repository.
