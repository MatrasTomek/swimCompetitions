---
name: app-docs
description: 'Documentation for the SwimCompetitions PHP application. Use when: exploring project structure, understanding features, installation instructions, admin panel usage, live mode configuration, and security considerations.'
user-invocable: true
---

# SwimCompetitions

A web application for managing and displaying swimming competition results for the **Olimpijczyk Proszówki** club. It shows start lists, fetches live results from [livetiming.pl](https://livetiming.pl) PDFs, and generates PDF reports.

---

## Features

- **Public pages** — competition list with cards, per-competition start lists
- **Live results** — automatic result fetching from livetiming.pl PDFs after each race
- **Athlete profiles** — race history stored per athlete in JSON files
- **Admin panel** — add, edit and delete competitions; manage live mode
- **PDF export** — downloadable results report for each competition
- **Announcements** — competitions without a start list shown as "coming soon"

---

## Technology stack

- PHP 8.x (no framework, no database)
- Data storage: JSON files
- PDF generation: [FPDF](http://www.fpdf.org/) library
- PDF text extraction: `pdftotext` (poppler-utils) or pure PHP (zlib + BT/ET parser)
- CSS: custom dark-mode design, fully responsive

---

## Directory structure

```
swimCompetitions/
├── index.php               # Home page — competition list
├── lista_startowa.php      # Start list for a selected competition
├── wyniki.php              # Live / archived results
│
├── admin/
│   ├── login.php           # Admin login
│   ├── logout.php          # Admin logout
│   ├── lista.php           # Competition list in the admin panel
│   ├── dodaj.php           # Add competition / announcement
│   ├── edytuj.php          # Edit competition metadata
│   ├── usun.php            # Delete competition / announcement
│   └── live.php            # Live mode configuration
│
├── api/
│   ├── fetch_result.php    # AJAX: fetch pending results (POST)
│   └── generuj_pdf.php     # Generate results PDF (GET)
│
├── cron/
│   └── check_results.php   # Cron script for fetching results
│
├── includes/
│   ├── config.php          # Configuration constants
│   ├── functions.php       # Helper functions, competition JSON handling
│   ├── auth.php            # Admin session check
│   ├── athlete.php         # Athlete profile management
│   ├── result_fetch.php    # Result fetching logic from livetiming.pl
│   └── pdf_extract.php     # PDF text extraction (pdftotext / pure PHP)
│
├── assets/
│   ├── style.css           # Public styles
│   └── admin.css           # Admin panel styles
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
- Optional: `pdftotext` from the `poppler-utils` package (speeds up PDF extraction)

### 2. Configuration

Edit `includes/config.php`:

```php
define('ADMIN_USER',          'admin');
define('ADMIN_PASSWORD_HASH', '...');   // bcrypt hash — generate with: password_hash('password', PASSWORD_BCRYPT)

define('BASE_URL', '');                 // e.g. '/swim' if the app is not in the web root
define('CSS_VERSION', '11');            // bump this when updating CSS (cache-busting)
define('RESULT_DELAY_SECONDS', 300);    // delay before fetching results after a race start (default 5 min)
```

### 3. Directory permissions

```bash
mkdir -p zawody zawodnicy
chmod 755 zawody zawodnicy
```

The `live_config.json` and `zapowiedzi.json` files are created automatically by the application.

### 4. Cron job (optional, as a fallback)

If browser-based JS polling is not sufficient, set up a cron job:

```cron
* * * * * php /path/to/swimCompetitions/cron/check_results.php >> /var/log/swim_cron.log 2>&1
```

---

## Live mode — how it works

1. The admin navigates to **Live competition** and enters the URL of the livetiming.pl `index.html` page (e.g. `https://live.livetiming.pl/zak/2026/05_10_oswiecim/index.html`) and selects the matching JSON start list file.
2. The application saves the configuration to `live_config.json`.
3. Every minute (via JS polling in the browser or the cron job), the endpoint `POST /api/fetch_result.php` is called.
4. For each entry that started more than 5 minutes ago and has no result yet, the file `ResultList_{event_nr}.pdf` is downloaded from the livetiming.pl server.
5. The result is extracted from the PDF and saved to both the competition JSON file and the athlete's profile.
6. The page auto-reloads when new results appear.

---

## Admin panel

Available at `/admin/login.php`. Default username: `admin`.

To generate a new password hash:

```php
echo password_hash('new_password', PASSWORD_BCRYPT);
```

Paste the output into the `ADMIN_PASSWORD_HASH` constant in `includes/config.php`.

---

## Security

- CSRF protection on all POST forms
- JSON file validation and sanitization on upload
- Admin password stored as a bcrypt hash
- API endpoint accepts only same-origin requests
- File paths restricted to the `zawody/` directory (basename + character whitelist)

---

## Author

Tomasz Matras / [nd-soft.pl](https://www.nd-soft.pl)

