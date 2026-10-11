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
php bin/add_admin.php <user>      # print an admin_users line (password hash) for config.php
php -S localhost:8001 index.php   # local dev server -> http://localhost:8001/api/teams/admin
```

There is no build step, no Composer, no test suite. `config.php` and `.htaccess` are
per-deployment and git-ignored; `config.example.php` and `.htaccess.example` are the
templates. The repo is public: keep real admin usernames and password hashes out of
tracked files.

## Deployment

Flat drop-in directory (ours: `athletics.willamette.edu/api/teams/`, `auth` = `shibboleth`).
`.htaccess` rewrites non-file requests to `index.php`, blocks `src/`, `bin/`, `database/`,
`config*.php`, `*.sqlite*`, `*.md`, and (mod_headers) adds `Access-Control-Allow-Origin: *`
to logo images.

`base_path()` is `config['base_path']` when set, else `dirname(SCRIPT_NAME)` under a web
server, else `''` (`php -S`, CLI). Behind an Alias, set both `base_path` and `RewriteBase`.
Our local `config.php` sets `base_path` = `/api/teams` to mirror production; the `cli-server`
block in `index.php` serves `/logos/` itself because the built-in server can't map
base_path-prefixed URLs to files.

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
- **Slugs are the stable ID** consumer apps store. Generated from `full_name` + `variant` only when blank
  (create, or cleared by the user) — never regenerated on rename. Never all-digits, so
  `/{key}` can tell ids from slugs. Slugs share the URL space with app paths, so
  `RESERVED_SLUGS()` (admin, docs, bg, logos, ...) are rejected/skipped — extend it when adding
  a top-level route.
- The list URL is documented with a trailing slash (`/api/teams/`): Apache's mod_dir 301 for
  the slashless form has no CORS header.
- A school with several entries (different mascot/logo for men and women) is one row per
  entry with the same `full_name` and a `variant` ("Men", "Women"); `full_name` + `variant`
  is unique (case-insensitive). Don't put variants in brackets in the name.
- Colours are stored normalised as `#RRGGBB` upper-case, or `''`.
- `updated_at` is UTC (`CURRENT_TIMESTAMP`), bumped on every save; exposed as ISO 8601 `Z`.
  Deletes are hard deletes, so `updated_since` syncs don't see them.
- `logo.url` is `null` when the file isn't in `/logos/` (lookup is case-insensitive because
  the logo pack is authored on Windows). `/logos/` is git-ignored and deployed per server.
- `PDOException` extends `RuntimeException` — catch it first.

## Admin auth

`config['auth']`: `password` (default) or `shibboleth`. Helpers in `src/app.php`.

- **password**: session login at `/admin/login` (POST), logout POST `/admin/logout`. Accounts
  are `admin_users` (username => `password_hash`), made with `bin/add_admin.php`. Session
  cookie `teamdata_admin`, scoped to base_path; `current_user()` doesn't start a session unless
  that cookie exists, so public pages set no cookie. A user removed from config is logged out.
- **shibboleth**: the commented `<If "%{THE_REQUEST} =~ ...">` block in `.htaccess.example`
  makes mod_shib require a login on any `/admin` URL (THE_REQUEST because the rewrite to
  `index.php` re-runs access checks with the new REQUEST_URI). The app checks
  `REMOTE_USER`/`REDIRECT_REMOTE_USER` against `shib_admins` (case-insensitive); empty = nobody.

Both: `require_admin()` guards every `/admin` URL except login/logout (fail-closed),
`same_origin_request()` rejects admin POSTs (login included) whose Origin/Referer isn't this
host (CSRF). `dev_user` in config bypasses login under `php -S` only.
