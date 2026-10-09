-- Team Data API - SQLite schema (PHP 7.2 / PDO-SQLite)
-- Rebuildable at any time via: php bin/init_db.php --force

PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS teams (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    slug            TEXT NOT NULL UNIQUE,              -- stable public identifier for consumer apps
    full_name       TEXT NOT NULL UNIQUE,
    short_name      TEXT NOT NULL DEFAULT '',
    code            TEXT NOT NULL DEFAULT '',
    mascot          TEXT NOT NULL DEFAULT '',
    location        TEXT NOT NULL DEFAULT '',
    conference      TEXT NOT NULL DEFAULT '',
    website         TEXT NOT NULL DEFAULT '',
    website_provider TEXT NOT NULL DEFAULT '',         -- platform the website runs on
    primary_color   TEXT NOT NULL DEFAULT '',          -- "#RRGGBB" or ''
    secondary_color TEXT NOT NULL DEFAULT '',
    logo_bg_hex     TEXT NOT NULL DEFAULT '',          -- flat fill behind the logo (vMix -bkgd.png swatch)
    logo_filename   TEXT NOT NULL DEFAULT '',          -- file in /logos/
    stat_id         TEXT NOT NULL DEFAULT '',
    created_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,   -- UTC "YYYY-MM-DD HH:MM:SS"
    updated_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS teams_updated_at ON teams (updated_at);
