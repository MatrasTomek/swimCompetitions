# SwimCompetitions

A web application for managing and displaying swimming competition results for the **Olimpijczyk Proszówki** club. It shows start lists, fetches live results from [livetiming.pl](https://livetiming.pl) PDFs, and generates PDF reports.

The application is split into two parts:

- **Backend** — PHP 8.x REST API (`api/v1/`), no framework, no database (JSON file storage)
- **Frontend** — Angular SPA (`swim-frontend/`) with PrimeNG, covering both the public pages and the admin panel

---

## Features

- **Public pages** — competition list with cards, per-competition start lists
- **Live results** — automatic result fetching from livetiming.pl PDFs after each race
- **Athlete profiles** — race history stored per athlete in JSON files
- **Admin panel** — add, edit and delete competitions; import start lists from livetiming.pl PDFs; manage live mode
- **PDF export** — downloadable results report for each competition
- **Announcements** — competitions without a start list shown as "coming soon"

---

## Technology stack

- Backend: PHP 8.x (no framework, no database), JWT auth
- Frontend: Angular + PrimeNG (standalone components, signals)
- Data storage: JSON files
- PDF generation: [FPDF](http://www.fpdf.org/) library
- PDF text extraction: `pdftotext` (poppler-utils) or pure PHP (zlib + BT/ET parser)

---

## Directory structure

```
swimCompetitions/
├── api/
│   ├── generuj_pdf.php     # Results PDF generation (included by api/v1/competitions.php)
│   └── v1/                 # REST API for the Angular SPA
│       ├── index.php       # Router (/api/v1/{resource})
│       ├── auth.php        # POST /auth/login — JWT issuance
│       ├── competitions.php# Competition CRUD + PDF
│       ├── athletes.php    # Athlete profiles
│       ├── startlist.php   # Start list import (preview/save)
│       ├── results.php     # Live result fetching
│       ├── live.php        # Live mode configuration
│       ├── announcements.php
│       └── contests.php    # livetiming.pl contest search + cache
│
├── includes/
│   ├── config.php          # Configuration constants
│   ├── functions.php       # Helper functions, competition JSON handling
│   ├── jwt.php             # JWT encode/verify
│   ├── athlete.php         # Athlete profile management
│   ├── result_fetch.php    # Result fetching logic from livetiming.pl
│   ├── lenex_fetch.php     # LENEX result fetching
│   ├── livetiming_cache.php# livetiming.pl contest list cache
│   ├── startlist_parse.php # Start list PDF parsing
│   └── pdf_extract.php     # PDF text extraction (pdftotext / pure PHP)
│
├── swim-frontend/          # Angular SPA (public pages + admin panel)
│   └── src/app/
│       ├── public/         # home, start-list, results
│       ├── admin/          # login, competitions, athletes, import, live
│       └── core/           # ApiService, auth, guards, interceptors, models
│
├── zawody/                 # Competition JSON files (auto-created)
├── zawodnicy/              # Athlete profile JSON files (auto-created)
├── fpdf/                   # FPDF library
│
├── zapowiedzi.json         # Competitions without a start list
└── live_config.json        # Active live configuration
```

---

Each competition is a single `.json` file in the `zawody/` directory. The required field is `bloki`.

After a result is fetched from livetiming.pl, the following fields are added to the entry:

- `czas_result` — official race time
- `punkty` — FINA points
- `result_fetched` — `true`
- `result_fetched_at` — fetch timestamp

---

## Installation and configuration

### 1. Requirements

- PHP 8.0+
- Web server (Apache / Nginx)
- Node.js + npm (to build the Angular frontend)
- Optional: `pdftotext` from the `poppler-utils` package (speeds up PDF extraction)

### 2. Backend configuration

Edit `includes/config.php`:

```php
define('ADMIN_USER',          'admin');
define('ADMIN_PASSWORD_HASH', '...');   // bcrypt hash — generate with: password_hash('password', PASSWORD_BCRYPT)

define('BASE_URL', '');                 // e.g. '/swim' if the app is not in the web root
define('RESULT_DELAY_SECONDS', 300);    // delay before fetching results after a race start (default 5 min)

define('JWT_SECRET', '...');            // long random string — REQUIRED in production
define('JWT_TTL', 86400);               // token lifetime in seconds
define('CORS_ALLOWED_ORIGIN', 'http://localhost:4200'); // Angular dev server / production URL
```

### 3. Frontend build

```bash
cd swim-frontend
npm install --legacy-peer-deps
npm run build
```

Deploy the build output (`swim-frontend/dist/`) to the web root; the SPA calls the API at `/api/v1/index.php` (see `src/environments/`).

### 4. Directory permissions

```bash
mkdir -p zawody zawodnicy
chmod 755 zawody zawodnicy
```

The `live_config.json` and `zapowiedzi.json` files are created automatically by the application.

---

## Live mode — how it works

1. The admin opens **Live** in the admin panel, enters the livetiming.pl competition URL and selects the matching JSON start list file.
2. The application saves the configuration to `live_config.json`.
3. The frontend polls `POST /api/v1/results/fetch`.
4. For each entry that started more than 5 minutes ago and has no result yet, the file `ResultList_{event_nr}.pdf` is downloaded from the livetiming.pl server.
5. The result is extracted from the PDF and saved to both the competition JSON file and the athlete's profile.
6. The page updates when new results appear.

---

## Admin panel

Part of the Angular SPA — route `/admin`. Default username: `admin`.

To generate a new password hash:

```php
echo password_hash('new_password', PASSWORD_BCRYPT);
```

Paste the output into the `ADMIN_PASSWORD_HASH` constant in `includes/config.php`.

---

## Security

- JWT-based API authentication (login via `POST /api/v1/auth/login`)
- CORS restricted to the configured SPA origin
- JSON file validation and sanitization on upload
- Admin password stored as a bcrypt hash
- File paths restricted to the `zawody/` directory (basename + character whitelist)

---

## Author

Tomasz Matras / [nd-soft.pl](https://www.nd-soft.pl)
