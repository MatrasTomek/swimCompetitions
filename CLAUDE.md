# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Web application for managing swimming competition start lists and live results for Olimpijczyk Proszówki. Two parts:

- **Backend**: PHP 8.x REST API (`api/v1/`) — no framework; competition data stored as JSON files, club user accounts in MongoDB (Atlas)
- **Frontend**: Angular SPA (`swim-frontend/`) with PrimeNG — public pages and the admin panel

## Running and Testing

```bash
# Lint a PHP file
php -l api/v1/index.php

# Generate a bcrypt password hash for admin login
php -r "echo password_hash('password', PASSWORD_BCRYPT);"

# Local backend in Docker (dev only; PHP 8.3 like production, mbstring/openssl/zip/mongodb included)
# + local MongoDB (MONGO_URI env); mail() is written to /tmp/mails.log in the api container
docker compose -f dev/docker-compose.yml up --build   # http://127.0.0.1:8000, repo mounted at /app
docker compose -f dev/docker-compose.yml exec api composer install      # vendor/ (mongodb/mongodb)
docker compose -f dev/docker-compose.yml exec api php scripts/mongo_init.php   # users + results indexes (idempotent)

# Frontend (from swim-frontend/)
npm install --legacy-peer-deps   # primeng 21 vs Angular 22 peer conflict
npm start                        # dev server on http://localhost:4200 — proxies the API to API_TARGET from .env (may be production!)
npm run build
```

There is no test framework. Pure logic is covered by small test scripts; the rest is verified in a browser or via CLI:

```bash
# PHP — CLI assertion scripts (scripts/tests/, helper _assert.php, LENEX fixture in fixtures/)
php scripts/tests/test_member_match.php && php scripts/tests/test_lenex_parse_full.php && php scripts/tests/test_results_rows.php
# PHP — server-side downloads (starts a local `php -S`, needs ext-zip — run in the dev api container)
docker compose -f dev/docker-compose.yml exec api php scripts/tests/test_contest_http_get.php

# PHP + MongoDB — run in the dev api container; each test pins its own database (swim_test) and refuses to run
# without TEST_MONGO_URI, because includes/secrets.php may point MONGO_URI at the production cluster
docker compose -f dev/docker-compose.yml exec -e TEST_MONGO_URI=mongodb://mongo:27017 api php scripts/tests/test_results_repo.php
docker compose -f dev/docker-compose.yml exec -e TEST_MONGO_URI=mongodb://mongo:27017 api php scripts/tests/test_members.php
docker compose -f dev/docker-compose.yml exec -e TEST_MONGO_URI=mongodb://mongo:27017 api php scripts/tests/test_results_import.php
# test_results_cascade_failures.php needs a throwaway MongoDB with test commands — see the header of that file

# Frontend (from swim-frontend/) — Node's built-in runner over src/**/*.spec.ts (pure TypeScript helpers only, Node >= 22.6)
npm test
```

## Architecture

### Data Storage (JSON files + MongoDB for accounts)

- `zawody/*.json` — one file per competition; required field: `bloki` (array of race blocks)
- `zawodnicy/*.json` — per-athlete race history, auto-generated from result fetching
- `zapowiedzi.json` — competitions without a start list ("coming soon")
- `live_config.json` — currently active live competition config
- MongoDB `users` collection (database `MONGO_DB`, default `swim`) — club user accounts, one document per account: `userId` (UUID v4, unique), `userEmail` (login, lowercase, unique), `userPassword` (bcrypt), `userClub`, `userInvoice` (invoice details required at registration: `companyName`, `street`, `postalCode` 00-000, `city`, `nip` — 10 digits, checksum-validated; missing on older accounts → `null` in the API), `status` (`pending_email` → `pending_approval` → `active` / `disabled`), `tokenVersion`, hashed one-time e-mail/reset tokens, `clubItems.clubMembers[]` (`memberId`, `memberName`, `memberSex` M/K, `memberBirthYear`; accounts that used the former hand-entered times may still store `memberTimes[]` — never returned by the API, dropped by `scripts/drop_member_times.php`)
- MongoDB `results` collection — club members' starts fetched from LENEX, one document per start: `userId`, `memberId`, `contestUuid` (livetiming.pl contest), `contestName`, `contestCity`, `eventNr`, `date` (YYYY-MM-DD), `poolLength` (25/50), `distance`, `stroke` (`dowolny`/`grzbietowy`/`klasyczny`/`motylkowy`/`zmienny`), `time` (`1:05.32`), `timeMs`, `points` (null when LENEX has none or 0), `fetchedAt`. Unique `{userId, memberId, contestUuid, eventNr}`. Independent of `zawody/`, `zawodnicy/` and the browser's imported lists — removing a competition or an imported list keeps the results; removing a club member or the account removes theirs

### Backend — REST API (`api/v1/`)

Router: `api/v1/index.php` dispatches `/api/v1/{resource}` (works via PATH_INFO, no mod_rewrite needed). Auth: JWT (`includes/jwt.php`), issued by `POST /api/v1/auth/login`, enforced by `api/v1/require_auth.php`.

| Route | File | Role |
|-------|------|------|
| `/auth/*` | `api/v1/auth.php` | Login → JWT with `role`: username `admin` → admin (secrets.php hash); an e-mail → club user from MongoDB (only `active`; `tv` = tokenVersion) |
| `/account/*` | `api/v1/account.php` | Club user accounts: public register (honeypot, per-IP `ACCOUNT_*` limit) / verify-email / forgot-password / reset-password (never reveal whether an e-mail exists); user-only `me` (GET/PATCH `{userClub?, userInvoice?}`/DELETE), change-password (returns a fresh token), `members[/{id}]` CRUD (removing a member removes the member's results); `POST results/fetch` `{contest_url}` — downloads the LENEX of a `livetiming.pl/contest/{uuid}` page and stores the results of the account's members (matched by name + birth year; relays, DSQ/DNS and athletes without a birth year are skipped; the contest's rows of the account are replaced, so withdrawn results disappear; 409 while results are not published; per-account limit `ACCOUNT_RESULTS_FETCH_*`) → `{saved, members_matched, not_found[], ambiguous[], no_results[] (matched, but only DNS/DSQ starts), competition}`; `GET results?year=YYYY[&memberId=…]` — one season (calendar year), optionally one member |
| `/users[/{userId}]` | `api/v1/users.php` | Admin: account list (incl. invoice details), `PATCH {status}` — activating e-mails the owner |
| `/competitions[/{slug}[/pdf]]` | `api/v1/competitions.php` | CRUD + results PDF (includes `api/generuj_pdf.php`) |
| `/athletes[/{slug}\|export]` | `api/v1/athletes.php` | Athlete profiles |
| `/startlist/preview` | `api/v1/startlist.php` | Start list import from livetiming.pl PDF — public (per-IP rate limit); returns the competition JSON to the browser only (nothing is saved server-side); accepts only a `livetiming.pl/contest/{uuid}` page URL (`sl_contest_uuid()`), never a direct PDF link; name, city, dates, pool length (`basen`) and the start list PDF link (file titled "Lista startowa") are read from the contest object embedded in the contest page's `window.__data` (so the name matches the contest search); blocks come from Splash session headers (`1 - Blok 1  19.09.2026 - 16:00`; the block keeps the PDF's session number, since e.g. finals sessions may be missing; dates may also be `20/9/2026` or ISO `2026-09-20`), or per day from each event's `20.09.2026 - 9:30` line when the PDF has none |
| `/results/fetch` | `api/v1/results.php` | Live result fetching |
| `/live` | `api/v1/live.php` | Live mode config |
| `/announcements[/{id}]` | `api/v1/announcements.php` | Announcements |
| `/contact` | `api/v1/contact.php` | E-mail contact form (formerly `/rejestracja`, no longer used by the SPA) — public, per-IP rate limit (`CONTACT_*` in config), honeypot field `website`; sends a UTF-8 e-mail via PHP `mail()` (OVH hosting) to `CONTACT_TO_EMAIL` (info@nd-soft.pl) with the visitor in `Reply-To`; `From` = `CONTACT_FROM_EMAIL` (override in `secrets.php` — on OVH it must be a mailbox in a domain hosted on the account) |
| `/contests/*` | `api/v1/contests.php` | livetiming.pl search + cache status/refresh — refresh is public but only rebuilds a stale cache (admin forces it) |

### Key includes

| File | Role |
|------|------|
| `includes/config.php` | Non-secret constants: `BASE_URL`, `ZAWODY_DIR`, `ZAWODNICY_DIR`, `CORS_ALLOWED_ORIGIN`, `JWT_TTL`, login rate-limit settings, `ALLOWED_CONTEST_HOST_SUFFIX` (SSRF allowlist). Requires `includes/secrets.php` to exist (refuses to boot otherwise) |
| `includes/secrets.php` | `ADMIN_PASSWORD_HASH` and `JWT_SECRET` — gitignored, never committed; copy from `includes/secrets.example.php` |
| `includes/functions.php` | Competition/announcement CRUD, `h()` (HTML escape), `slugify()`, `format_konkurencja()`, `write_json_atomic()`/`with_file_lock()` (safe concurrent writes), `is_allowed_contest_host()` (SSRF guard), `contest_http_get()` (server-side GET: redirects followed by hand with the host re-checked on every hop, body capped at a size limit), login rate-limit helpers |
| `includes/jwt.php` | JWT encode/verify for API auth (HS256, mandatory `exp`) |
| `includes/mongo.php` | `mongo_available()` (MONGO_URI + ext-mongodb + vendor/), `mongo_db()`/`mongo_users()`/`mongo_results()`, `uuid_v4()` |
| `includes/user_repo.php` | All MongoDB access for accounts (the only file to change if storage moves): account lifecycle, tokens, admin list/status, validated atomic club member updates (`$push`/`$pull`, limit `ACCOUNT_MAX_MEMBERS`), and the `results` collection: `results_replace_contest()` (upsert, then `results_delete_stale()` removes the contest's rows that are not in the fetched set — chosen by member + event, so concurrent fetches cannot delete current results), `results_list()` (one year), `results_delete_member()`/`results_delete_user()` — called by `member_delete()`/`user_delete()` before the entity itself is removed, so a failed second write can be retried (no transactions: the dev MongoDB is not a replica set); storing fetched results and removing a member / the account run under `account_lock()` (per-account file lock in the system temp dir), and the import re-reads the account after the download, so nothing is stored for a member or account removed meanwhile |
| `includes/mailer.php` | `send_mail_utf8()` (PHP `mail()`, From = `CONTACT_FROM_EMAIL`), `app_url()` — e-mail links to SPA routes (`APP_PUBLIC_URL` + `/#` hash routing) |
| `includes/athlete.php` | Athlete profile load/save/dedup — `save_athlete_result()` deduplicates by competition+date+event_nr |
| `includes/result_fetch.php` | Live-config load/save + orchestrates result fetching: `fetch_and_apply_lenex()` downloads the LENEX file for a contest and applies all results to the competition JSON in one pass |
| `includes/lenex_fetch.php` | Low-level LENEX (.lxf) download/parsing: `lenex_download()`, `lenex_parse_xml()`, `lenex_find_athlete()` (admin's result fetching); `lenex_download_xml()` + `lenex_parse_full()` for club accounts — meet, individual events and every athlete with valid results (pool length per event: SESSION `course` overrides MEET, only LCM → 50 / SCM → 25, events in other sessions — yards, 33 m, OPEN, no course — are skipped; LENEX 3.0 layout only; `lenex_time_ms()` accepts `HH:MM:SS.hh`/`M:SS.hh`/`SS.hh` and rejects out-of-range parts) |
| `includes/member_match.php` | Matching club members to LENEX athletes — pure functions: same name (word order, case and Polish diacritics ignored) + same birth year; two candidates on either side → ambiguous, nothing stored |
| `includes/results_import.php` | `results_import_contest()` — validation, per-account rate limit, LENEX download, matching, `results_build_rows()`, store; returns `[HTTP status, body]` for `POST /account/results/fetch` |
| `includes/livetiming_cache.php` | livetiming.pl contest list cache (`ltcache_status()`, `ltcache_refresh()`) — only contests from the current year onward are scraped, cached and searched (`ltcache_in_scope()`); a cache built in an earlier year counts as stale |
| `includes/startlist_parse.php` | Start list PDF parsing |
| `includes/pdf_extract.php` | PDF text extraction: tries `pdftotext -layout` (poppler-utils) first, then the pure PHP layout extractor, last the simple FlateDecode/BT-ET parser |
| `includes/pdf_layout.php` | Pure PHP stand-in for `pdftotext -layout` (no poppler on local Windows PHP): object table incl. object streams, per-font ToUnicode/widths, text-state interpreter → lines grouped by baseline with ≥2 spaces between columns |

### Frontend — Angular SPA (`swim-frontend/`)

- `src/app/public/` — home (competition grid + visitor's own imported lists), start-list, results, import (one short form: livetiming cache status, contest search by name/city, club → `/import`, no login needed; form fields kept in `sessionStorage`)
- `src/app/public/register/` — account registration at `/rejestracja` (linked from the login page) via `POST /account/register`; its consent links to the RODO information clause at `/rodo` (`public/rodo/`, opens in a new tab; also linked from the footer). `POST /contact` (e-mail form) still exists in the API but the SPA no longer uses it
- `src/app/public/support/` — `/wsparcie`: about the project and ND-Soft, with a "Wesprzyj przez BLIK" button to an external donation page (the app processes no payments and does not know about donations). The address is `supportUrl` in `src/environments/environment*.ts` (empty by default → "Wpłaty uruchomimy wkrótce" instead of the button) and reaches the template only through `supportLink()` in `shared/support-link.ts`, which accepts absolute `https:` addresses only. `<app-footer>` (`src/app/shared/footer/`, rendered once in `app.ts`, hidden in print) links to `/wsparcie` and `/rodo` from every page
- `src/app/public/login/` — the one login form at `/logowanie` (card "Wyniki i Statystyki"; `/admin/login` redirects): admin → `/admin/zawody`, club user → `/konto`
- `src/app/public/account/` — `/konto/potwierdz`, `/konto/zapomniane-haslo`, `/konto/reset-hasla` (token links from e-mails)
- `src/app/account/` — club user pages behind `userGuard`: `/konto` (club, invoice details, password change, account removal), `/konto/zawodnicy` (club members; the season's start count links to the member's statistics), `/konto/statystyki` (`account/stats/`: season `?rok=`, tiles, two rankings and the table of all results with filters) and `/konto/statystyki/:memberId` (progression chart of one event, season bests, all starts). Aggregation lives in pure, tested files (`account/stats/stats-data.ts`, `season.ts`, `shared/swim-time.ts`); data for a changeable key is loaded through `shared/latest-request.ts` (switchMap — a late answer never overwrites the current season)
- `src/app/admin/` — competitions (list/edit), athletes, live (LENEX), users (`/admin/uzytkownicy` — activate/block accounts); behind `adminGuard`
- Start list imports (visitors and admins alike) are kept only in the browser (`localStorage`, `LocalCompetitionsService`) and shown at `/moje/:id/lista` — they are never written to `zawody/` on the server. The import remembers the contest page (`contest_url`), so a logged-in club user gets "Pobierz wyniki na konto" on that page (`POST /account/results/fetch`); imports made before that have no `contest_url` and must be re-imported
- `src/app/core/` — `ApiService` (all HTTP calls, base URL from `src/environments/`), `AuthService` (token + role in `localStorage`; tokens without a stored role are the admin's), `adminGuard`/`userGuard`, error interceptor (401 while logged in → logout), models
- Standalone components with signals; PrimeNG for UI
- Charts: ECharts through the thin `<app-chart [options]>` wrapper (`src/app/shared/chart/`, tree-shaken `echarts/core`, only in the lazy statistics chunk). Series colours are the fixed `CHART_COLORS` (checked for contrast and colour-blind separation on the dark card — the brand gold is too light for data marks); axis/label styling comes from `chartTheme()`. Tooltip formatters return HTML outside Angular's sanitisation, so they must be built with `shared/chart-tooltip.ts` (`escapeHtml()`), never by interpolating LENEX or user text directly. Ranking axis labels are cut to 50 characters (`truncateLabel()`); with `triggerEvent: true` on a category axis, `<app-chart>` shows the data point's tooltip (whole name) when its label is hovered
- Every search/filter box uses the shared `<app-search-input [(value)]>` (`src/app/shared/search-input/`): clear "x" + Esc, pulsing gold frame and optional "Filtr aktywny: …" `[badge]` while a filter is applied (`[highlight]="false"` for plain lookups); Polish plurals via `shared/plural.ts`

### Result fetching pipeline

1. Admin sets the livetiming.pl contest URL + competition JSON file in the admin panel (or the "Pobierz wyniki" dialog) → either saved to `live_config.json` via `PUT /api/v1/live`, or fetched immediately via `POST /api/v1/results/fetch`
2. Both call `fetch_and_apply_lenex($contest_url, $json_file)` in `includes/result_fetch.php`, which downloads the contest's LENEX (`.lxf`) file in one shot (`lenex_download()` in `includes/lenex_fetch.php`) — there is no per-event delay/polling
3. For every start in the competition JSON, looks up the matching athlete/event result in the LENEX data by event number + normalized name
4. Writes `czas_result`, `punkty`, `result_fetched: true`, `result_fetched_at` back into the competition JSON (always overwrites existing results with the latest LENEX data)
5. Updates/creates the athlete profile in `zawodnicy/`

Only `http(s)://livetiming.pl` (or a subdomain) URLs are fetched server-side — enforced by `is_allowed_contest_host()` — since `contest_url` is admin-supplied and this code makes a server-side HTTP request (SSRF guard).

## Configuration

- `includes/secrets.php` (gitignored — copy from `includes/secrets.example.php`): `ADMIN_PASSWORD_HASH` (bcrypt hash of admin password), `JWT_SECRET` (long random string; the app refuses to boot if it's missing, too short, or still the example placeholder), `MONGO_URI` (Atlas connection string; without it `/account` and `/users` answer 503, the rest works), optional `MONGO_DB`, `APP_PUBLIC_URL` (SPA address for e-mail links)
- Accounts on OVH need the `mongodb` PHP extension, `vendor/` uploaded (`composer install --no-dev`) and outbound TCP 27017 to Atlas (Atlas Network Access must allow the hosting's IPs) — check once with `scripts/mongo_check.php` and delete it afterwards. After deploying, run `php scripts/mongo_init.php` once (indexes of `users` and `results`, idempotent) and `php scripts/drop_member_times.php` once (removes the old hand-entered times), then delete the latter from the server
- `includes/config.php`:
  - `BASE_URL` — set to e.g. `'/swim'` if not deployed at web root (default: `''`)
  - `CORS_ALLOWED_ORIGIN` — Angular app origin (dev default: `http://localhost:4200`)
  - `JWT_TTL`, `LOGIN_MAX_ATTEMPTS`/`LOGIN_WINDOW_SECONDS`/`LOGIN_LOCKOUT_SECONDS` — login rate limiting
  - `ALLOWED_CONTEST_HOST_SUFFIX` — SSRF allowlist host for `contest_url` fetches (default: `livetiming.pl`)

## Security Patterns

- API auth via JWT (`Authorization: Bearer`, HS256, mandatory `exp`), issued on login with a `role` claim. Admin endpoints use `api_require_admin()` (a club user's token gets 403; legacy tokens without `role` count as admin only when `sub` is `ADMIN_USER`); club user endpoints use `api_require_user()`, which re-reads the account on every request (status must be `active`, `tv` must equal `tokenVersion` — password change/reset logs out other sessions, blocking takes effect at once)
- Account e-mail/reset tokens: 32 random bytes sent by e-mail, only the SHA-256 stored, with expiry (`ACCOUNT_VERIFY_TTL`, `ACCOUNT_RESET_TTL`), one-time; passwords `password_hash(PASSWORD_DEFAULT)`, min `ACCOUNT_PASSWORD_MIN` chars
- Public start list preview (`POST /startlist/preview`) is rate-limited per IP (`startlist_preview_rate_limited()`, `STARTLIST_PREVIEW_*` in config) since it triggers a server-side PDF download — **temporarily disabled** via `STARTLIST_PREVIEW_RATE_LIMIT = false`
- Login endpoint is rate-limited per IP (`login_rate_limit_*()` in `includes/functions.php`) — 5 failed attempts locks out for 5 minutes
- Secrets (`ADMIN_PASSWORD_HASH`, `JWT_SECRET`) live in gitignored `includes/secrets.php`, never committed; `config.php` refuses to boot with a missing/placeholder secret
- Admin-supplied `contest_url` (results fetch, live config, start-list import) is restricted to the `livetiming.pl` host via `is_allowed_contest_host()` — an SSRF guard, since these trigger server-side HTTP requests
- The LENEX path (contest page → `.lxf`, used by admins and every active club account) downloads only through `contest_http_get()`: no automatic redirects (each `Location` must pass `is_allowed_contest_host()`, max 3 hops), size limits `CONTEST_PAGE_MAX_BYTES` / `LENEX_MAX_BYTES`, and the `.lef` is unpacked with the `LENEX_XML_MAX_BYTES` cap (ZIP bomb). The start list (`resolve_contest_page()`, `pdf_download()`) and the livetiming cache still use plain `file_get_contents()`
- Club results: every `results` query is filtered by the token's `userId`, and a `memberId` from the URL must belong to the account (404 otherwise); `POST /account/results/fetch` accepts only a `livetiming.pl/contest/{uuid}` page (`sl_contest_uuid()` + `is_allowed_contest_host()`) and is rate-limited per account (`ACCOUNT_RESULTS_FETCH_MAX` per `ACCOUNT_RESULTS_FETCH_WINDOW`, file `account_results_rate.json`, blocked by `.htaccess`; a 409 "results not published yet" gives its hit back and counts in the looser `ACCOUNT_RESULTS_PENDING_MAX` instead, since it still downloads a file; a slot in both is reserved atomically before the download with `rate_limit_reserve()`, and the one that does not apply is given back by its reservation token — `rate_limit_refund()`); only results of members the club entered are stored
- LENEX text (contest names, cities) and member names are untrusted in the SPA: Angular bindings escape them, and chart tooltips — the one place HTML is built by hand — go through `escapeHtml()` in `shared/chart-tooltip.ts`
- CORS locked to `CORS_ALLOWED_ORIGIN` (`api/v1/cors.php`)
- All HTML output through `h()` (`htmlspecialchars` with ENT_QUOTES/UTF-8)
- File access restricted to `zawody/`/`zawodnicy/` via `safe_path()`/`safe_json_path()` (basename + alphanumeric/dash/underscore whitelist)
- JSON writes go through `write_json_atomic()` (write-to-temp + rename, refuses to write on encode failure) and `with_file_lock()` (serializes read-modify-write cycles) to avoid corruption/lost updates from concurrent requests
- `/includes/` is blocked by `.htaccess` from direct HTTP access; so are `vendor/`, `dev/`, `composer.*` and the runtime rate-limit files; `scripts/mongo_init.php`, `scripts/drop_member_times.php` and everything in `scripts/tests/` run only from the CLI
