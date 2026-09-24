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

# Local backend in Docker (dev only; PHP 8.3 like production, mbstring/openssl/zip included)
docker compose -f dev/docker-compose.yml up --build   # http://127.0.0.1:8000, repo mounted at /app

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
| `/startlist/preview` | `api/v1/startlist.php` | Start list import from livetiming.pl PDF — public (per-IP rate limit); returns the competition JSON to the browser only (nothing is saved server-side); accepts only a `livetiming.pl/contest/{uuid}` page URL (`sl_contest_uuid()`), never a direct PDF link; name, city, dates, pool length (`basen`) and the start list PDF link (file titled "Lista startowa") are read from the contest object embedded in the contest page's `window.__data` (so the name matches the contest search); blocks come from Splash session headers (`1 - Blok 1  19.09.2026 - 16:00`; the block keeps the PDF's session number, since e.g. finals sessions may be missing; dates may also be `20/9/2026` or ISO `2026-09-20`), or per day from each event's `20.09.2026 - 9:30` line when the PDF has none |
| `/results/fetch` | `api/v1/results.php` | Live result fetching |
| `/live` | `api/v1/live.php` | Live mode config |
| `/announcements[/{id}]` | `api/v1/announcements.php` | Announcements |
| `/contact` | `api/v1/contact.php` | Registration form (`/rejestracja` page) — public, per-IP rate limit (`CONTACT_*` in config), honeypot field `website`; sends a UTF-8 e-mail via PHP `mail()` (OVH hosting) to `CONTACT_TO_EMAIL` (info@nd-soft.pl) with the visitor in `Reply-To`; `From` = `CONTACT_FROM_EMAIL` (override in `secrets.php` — on OVH it must be a mailbox in a domain hosted on the account) |
| `/contests/*` | `api/v1/contests.php` | livetiming.pl search + cache status/refresh — refresh is public but only rebuilds a stale cache (admin forces it) |

### Key includes

| File | Role |
|------|------|
| `includes/config.php` | Non-secret constants: `BASE_URL`, `ZAWODY_DIR`, `ZAWODNICY_DIR`, `CORS_ALLOWED_ORIGIN`, `JWT_TTL`, login rate-limit settings, `ALLOWED_CONTEST_HOST_SUFFIX` (SSRF allowlist). Requires `includes/secrets.php` to exist (refuses to boot otherwise) |
| `includes/secrets.php` | `ADMIN_PASSWORD_HASH` and `JWT_SECRET` — gitignored, never committed; copy from `includes/secrets.example.php` |
| `includes/functions.php` | Competition/announcement CRUD, `h()` (HTML escape), `slugify()`, `format_konkurencja()`, `write_json_atomic()`/`with_file_lock()` (safe concurrent writes), `is_allowed_contest_host()` (SSRF guard), login rate-limit helpers |
| `includes/jwt.php` | JWT encode/verify for API auth (HS256, mandatory `exp`) |
| `includes/athlete.php` | Athlete profile load/save/dedup — `save_athlete_result()` deduplicates by competition+date+event_nr |
| `includes/result_fetch.php` | Live-config load/save + orchestrates result fetching: `fetch_and_apply_lenex()` downloads the LENEX file for a contest and applies all results to the competition JSON in one pass |
| `includes/lenex_fetch.php` | Low-level LENEX (.lxf) download/parsing: `lenex_download()`, `lenex_parse_xml()`, `lenex_find_athlete()` |
| `includes/livetiming_cache.php` | livetiming.pl contest list cache (`ltcache_status()`, `ltcache_refresh()`) — only contests from the current year onward are scraped, cached and searched (`ltcache_in_scope()`); a cache built in an earlier year counts as stale |
| `includes/startlist_parse.php` | Start list PDF parsing |
| `includes/pdf_extract.php` | PDF text extraction: tries `pdftotext -layout` (poppler-utils) first, then the pure PHP layout extractor, last the simple FlateDecode/BT-ET parser |
| `includes/pdf_layout.php` | Pure PHP stand-in for `pdftotext -layout` (no poppler on local Windows PHP): object table incl. object streams, per-font ToUnicode/widths, text-state interpreter → lines grouped by baseline with ≥2 spaces between columns |

### Frontend — Angular SPA (`swim-frontend/`)

- `src/app/public/` — home (competition grid + visitor's own imported lists), start-list, results, import (one short form: livetiming cache status, contest search by name/city, club → `/import`, no login needed; form fields kept in `sessionStorage`)
- `src/app/public/register/` — registration form at `/rejestracja` (linked from the login page), sent via `POST /contact`; its consent links to the RODO information clause at `/rodo` (`public/rodo/`, opens in a new tab)
- `src/app/admin/` — login, competitions (list/edit), athletes, live (LENEX)
- Start list imports (visitors and admins alike) are kept only in the browser (`localStorage`, `LocalCompetitionsService`) and shown at `/moje/:id/lista` — they are never written to `zawody/` on the server
- `src/app/core/` — `ApiService` (all HTTP calls, base URL from `src/environments/`), auth service + guard, error interceptor, models
- Standalone components with signals; PrimeNG for UI
- Every search/filter box uses the shared `<app-search-input [(value)]>` (`src/app/shared/search-input/`): clear "x" + Esc, pulsing gold frame and optional "Filtr aktywny: …" `[badge]` while a filter is applied (`[highlight]="false"` for plain lookups); Polish plurals via `shared/plural.ts`

### Result fetching pipeline

1. Admin sets the livetiming.pl contest URL + competition JSON file in the admin panel (or the "Pobierz wyniki" dialog) → either saved to `live_config.json` via `PUT /api/v1/live`, or fetched immediately via `POST /api/v1/results/fetch`
2. Both call `fetch_and_apply_lenex($contest_url, $json_file)` in `includes/result_fetch.php`, which downloads the contest's LENEX (`.lxf`) file in one shot (`lenex_download()` in `includes/lenex_fetch.php`) — there is no per-event delay/polling
3. For every start in the competition JSON, looks up the matching athlete/event result in the LENEX data by event number + normalized name
4. Writes `czas_result`, `punkty`, `result_fetched: true`, `result_fetched_at` back into the competition JSON (always overwrites existing results with the latest LENEX data)
5. Updates/creates the athlete profile in `zawodnicy/`

Only `http(s)://livetiming.pl` (or a subdomain) URLs are fetched server-side — enforced by `is_allowed_contest_host()` — since `contest_url` is admin-supplied and this code makes a server-side HTTP request (SSRF guard).

## Configuration

- `includes/secrets.php` (gitignored — copy from `includes/secrets.example.php`): `ADMIN_PASSWORD_HASH` (bcrypt hash of admin password), `JWT_SECRET` (long random string; the app refuses to boot if it's missing, too short, or still the example placeholder)
- `includes/config.php`:
  - `BASE_URL` — set to e.g. `'/swim'` if not deployed at web root (default: `''`)
  - `CORS_ALLOWED_ORIGIN` — Angular app origin (dev default: `http://localhost:4200`)
  - `JWT_TTL`, `LOGIN_MAX_ATTEMPTS`/`LOGIN_WINDOW_SECONDS`/`LOGIN_LOCKOUT_SECONDS` — login rate limiting
  - `ALLOWED_CONTEST_HOST_SUFFIX` — SSRF allowlist host for `contest_url` fetches (default: `livetiming.pl`)

## Security Patterns

- API auth via JWT (`Authorization: Bearer`, HS256, mandatory `exp`), issued on login; mutating endpoints require auth
- Public start list preview (`POST /startlist/preview`) is rate-limited per IP (`startlist_preview_rate_limited()`, `STARTLIST_PREVIEW_*` in config) since it triggers a server-side PDF download — **temporarily disabled** via `STARTLIST_PREVIEW_RATE_LIMIT = false`
- Login endpoint is rate-limited per IP (`login_rate_limit_*()` in `includes/functions.php`) — 5 failed attempts locks out for 5 minutes
- Secrets (`ADMIN_PASSWORD_HASH`, `JWT_SECRET`) live in gitignored `includes/secrets.php`, never committed; `config.php` refuses to boot with a missing/placeholder secret
- Admin-supplied `contest_url` (results fetch, live config, start-list import) is restricted to the `livetiming.pl` host via `is_allowed_contest_host()` — an SSRF guard, since these trigger server-side HTTP requests
- CORS locked to `CORS_ALLOWED_ORIGIN` (`api/v1/cors.php`)
- All HTML output through `h()` (`htmlspecialchars` with ENT_QUOTES/UTF-8)
- File access restricted to `zawody/`/`zawodnicy/` via `safe_path()`/`safe_json_path()` (basename + alphanumeric/dash/underscore whitelist)
- JSON writes go through `write_json_atomic()` (write-to-temp + rename, refuses to write on encode failure) and `with_file_lock()` (serializes read-modify-write cycles) to avoid corruption/lost updates from concurrent requests
- `/includes/` is blocked by `.htaccess` from direct HTTP access
