# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Web application for managing swimming competition start lists and live results for Olimpijczyk Proszówki. Two parts:

- **Backend**: PHP 8.x REST API (`api/v1/`) — no framework, no database, all data stored as JSON files
- **Frontend**: Angular SPA (`swim-frontend/`) with PrimeNG — public pages and the admin panel

## Running and Testing

```bash
# Lint a PHP file
php -l api/v1/index.php

# Generate a bcrypt password hash for admin login
php -r "echo password_hash('password', PASSWORD_BCRYPT);"

# Frontend (from swim-frontend/)
npm install --legacy-peer-deps   # primeng 21 vs Angular 22 peer conflict
npm start                        # dev server on http://localhost:4200
npm run build
```

No automated tests are configured. Verify changes in a browser or via CLI.

## Architecture

### Data Storage (JSON files, no database)

- `zawody/*.json` — one file per competition; required field: `bloki` (array of race blocks)
- `zawodnicy/*.json` — per-athlete race history, auto-generated from result fetching
- `zapowiedzi.json` — competitions without a start list ("coming soon")
- `live_config.json` — currently active live competition config

### Backend — REST API (`api/v1/`)

Router: `api/v1/index.php` dispatches `/api/v1/{resource}` (works via PATH_INFO, no mod_rewrite needed). Auth: JWT (`includes/jwt.php`), issued by `POST /api/v1/auth/login`, enforced by `api/v1/require_auth.php`.

| Route | File | Role |
|-------|------|------|
| `/auth/*` | `api/v1/auth.php` | Login → JWT |
| `/competitions[/{slug}[/pdf]]` | `api/v1/competitions.php` | CRUD + results PDF (includes `api/generuj_pdf.php`) |
| `/athletes[/{slug}\|export]` | `api/v1/athletes.php` | Athlete profiles |
| `/startlist/{preview\|save}` | `api/v1/startlist.php` | Start list import from livetiming.pl PDF |
| `/results/fetch` | `api/v1/results.php` | Live result fetching |
| `/live` | `api/v1/live.php` | Live mode config |
| `/announcements[/{id}]` | `api/v1/announcements.php` | Announcements |
| `/contests/*` | `api/v1/contests.php` | livetiming.pl search + cache status/refresh |

### Key includes

| File | Role |
|------|------|
| `includes/config.php` | All constants: `BASE_URL`, `ZAWODY_DIR`, `ZAWODNICY_DIR`, `ADMIN_PASSWORD_HASH`, `JWT_SECRET`, `CORS_ALLOWED_ORIGIN`, `RESULT_DELAY_SECONDS` |
| `includes/functions.php` | Competition/announcement CRUD, `h()` (HTML escape), `slugify()`, `format_konkurencja()` |
| `includes/jwt.php` | JWT encode/verify for API auth |
| `includes/athlete.php` | Athlete profile load/save/dedup — `save_athlete_result()` deduplicates by competition+date+event_nr |
| `includes/result_fetch.php` | Fetches `ResultList_{nr}.pdf` from livetiming.pl, updates competition JSON and athlete profiles |
| `includes/lenex_fetch.php` | LENEX-based result fetching |
| `includes/livetiming_cache.php` | livetiming.pl contest list cache (`ltcache_status()`, `ltcache_refresh()`) |
| `includes/startlist_parse.php` | Start list PDF parsing |
| `includes/pdf_extract.php` | PDF text extraction: tries `pdftotext` (poppler-utils) first, falls back to pure PHP FlateDecode/BT-ET parser |

### Frontend — Angular SPA (`swim-frontend/`)

- `src/app/public/` — home (competition grid), start-list, results
- `src/app/admin/` — login, competitions (list/edit), athletes, import (PDF start list), live (LENEX)
- `src/app/core/` — `ApiService` (all HTTP calls, base URL from `src/environments/`), auth service + guard, error interceptor, models
- Standalone components with signals; PrimeNG for UI

### Result fetching pipeline

1. Admin sets the livetiming.pl competition + competition JSON file in the admin panel → saved to `live_config.json` via `PUT /api/v1/live`
2. Frontend polls `POST /api/v1/results/fetch`, which calls `process_pending_results()`
3. For each entry older than `RESULT_DELAY_SECONDS` with no result: downloads `ResultList_{event_nr}.pdf`, extracts athlete name + time + points
4. Writes `czas_result`, `punkty`, `result_fetched: true`, `result_fetched_at` back into the competition JSON
5. Updates/creates the athlete profile in `zawodnicy/`

## Configuration

Edit `includes/config.php` for:
- `ADMIN_PASSWORD_HASH` — bcrypt hash of admin password
- `BASE_URL` — set to e.g. `'/swim'` if not deployed at web root (default: `''`)
- `JWT_SECRET` — long random string, must be changed in production
- `CORS_ALLOWED_ORIGIN` — Angular app origin (dev default: `http://localhost:4200`)
- `RESULT_DELAY_SECONDS` — wait before fetching results after a race start (default: 300)

## Security Patterns

- API auth via JWT (`Authorization: Bearer`), issued on login; mutating endpoints require auth
- CORS locked to `CORS_ALLOWED_ORIGIN` (`api/v1/cors.php`)
- All HTML output through `h()` (`htmlspecialchars` with ENT_QUOTES/UTF-8)
- File access restricted to `zawody/` via `safe_json_path()` (basename + alphanumeric/dash/underscore whitelist)
- `/includes/` is blocked by `.htaccess` from direct HTTP access
