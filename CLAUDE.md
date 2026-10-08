# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Central store for team identity data (names, code, mascot, colours, logos, stats IDs) with
an admin UI and a read-only public API that the other athletics web apps consume, so each
app doesn't keep its own team table. Built on the same skeleton as `/claude/vmix-config`
and initially populated from it via `bin/import_vmix.php`.

## Commands

```bash
php bin/init_db.php               # create database/teams.sqlite / apply schema (idempotent)
php bin/init_db.php --force       # wipe and recreate empty
php bin/import_vmix.php [path]    # import teams + logos from vmix-config (default ../vmix-config); skips existing full_names
php -S localhost:8001 index.php   # local dev server -> http://localhost:8001/api/teams/admin
```

There is no build step, no Composer, no test suite. `config.php` is the local (git-ignored)
config; `config.example.php` holds the production values.

## Deployment

Flat drop-in at `athletics.willamette.edu/api/teams/` — no vhost. `.htaccess`
rewrites non-file requests to `index.php`, blocks `src/`, `bin/`, `database/`, `config*.php`,
`*.sqlite*`, `*.md`, and (mod_headers) adds `Access-Control-Allow-Origin: *` to logo images.

The URL prefix lives in **two** places that must agree: `RewriteBase` in `.htaccess` and
`base_path` in `config.php` (`/api/teams`, no trailing slash). Local dev uses the same
`base_path`; the `cli-server` block in `index.php` serves `/logos/` itself because the
built-in server can't map base_path-prefixed URLs to files.

## Architecture

Plain PHP, no framework, PHP 7.2 baseline: no typed properties, arrow functions, `??=`,
`match`, `str_contains`. SQLite via PDO; avoid UPSERT (SQLite bundled with 7.2 predates it).

- `index.php` — front controller; flat list of route checks each ending in `exit`.
  The API lives at the root of base_path: `/` (list), `/{slug|id}`, `/export.csv`,
  `/bg/{hex}.png`. The admin UI is `/admin`, `/admin/new`, `/admin/{id}/edit`, and POSTs to
  `/admin[/{id}][/delete]`; docs at `/docs`. Admin/docs routes are matched first; anything
  else falls through to the API, which sets CORS headers, answers `OPTIONS` with 204 and
  rejects non-GET with 405.
- `src/app.php` — bootstrap (`config()`, `db()`, `base_url()`), view helpers, logo file
  helpers, validation (`team_clean()`), and team data access. No classes.
- `src/api.php` — `team_resource()` (the public JSON shape), query filters, ETag/304 handling,
  CSV output, `solid_color_png()`.
- `src/views/` — PHP templates rendered into `layout.php` via `view()`. Tailwind via CDN.
- `database/schema.sql` — one table, `teams`.

## Contracts

- **`team_resource()` is a public contract.** Consumer apps code against it. Adding fields is
  fine; renaming/removing fields or changing types is a breaking change. Keep `/docs`
  (`src/views/api/docs.php`) and README in sync when adding fields.
- **Slugs are the stable ID** consumer apps store. Generated from `full_name` only when blank
  (create, or cleared by the user) — never regenerated on rename. Never all-digits, so
  `/{key}` can tell ids from slugs. Slugs share the URL space with app paths, so
  `RESERVED_SLUGS()` (admin, docs, bg, logos, ...) are rejected/skipped — extend it when adding
  a top-level route.
- The list URL is documented with a trailing slash (`/api/teams/`): Apache's mod_dir 301 for
  the slashless form has no CORS header.
- Colours are stored normalised as `#RRGGBB` upper-case, or `''`.
- `updated_at` is UTC (`CURRENT_TIMESTAMP`), bumped on every save; exposed as ISO 8601 `Z`.
  Deletes are hard deletes, so `updated_since` syncs don't see them.
- `logo.url` is `null` when the file isn't in `/logos/` (lookup is case-insensitive because
  the logo pack is authored on Windows). `/logos/` is git-ignored and deployed per server.
- `PDOException` extends `RuntimeException` — catch it first.

## Admin auth

Campus SSO via the server's Shibboleth SP (entityID `https://athletics.willamette.edu/shibboleth`).
`.htaccess` wraps `/api/teams/admin…` in `<If "%{THE_REQUEST} =~ ...">` with `AuthType shibboleth`
and `Require shib-user <list>` — the admin allowlist lives there, not in the app. THE_REQUEST is
used because the rewrite to `index.php` re-runs access checks with the new REQUEST_URI.
The app adds two checks (`index.php`, helpers in `src/app.php`): `require_admin()` 403s `/admin`
when no `REMOTE_USER`/`REDIRECT_REMOTE_USER` is present (fail-closed for URLs the .htaccess
regex misses, e.g. `//admin`), and `same_origin_request()` rejects admin POSTs whose
Origin/Referer isn't this host (CSRF). `dev_user` in config stands in for SSO under `php -S` only.
