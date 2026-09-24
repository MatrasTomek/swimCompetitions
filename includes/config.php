<?php
// ============================================================

define('ADMIN_USER', 'admin');

// ADMIN_PASSWORD_HASH and JWT_SECRET live in includes/secrets.php, which is
// gitignored so real credentials never get committed. Copy
// includes/secrets.example.php to includes/secrets.php and fill it in.
if (!file_exists(__DIR__ . '/secrets.php')) {
    http_response_code(500);
    die('Brakuje includes/secrets.php — skopiuj includes/secrets.example.php i uzupełnij prawdziwymi wartościami.');
}
require_once __DIR__ . '/secrets.php';

// Refuse to run with the shipped placeholder/example secret — this guards
// against ever deploying with the value from secrets.example.php.
if (!defined('JWT_SECRET') || strlen(JWT_SECRET) < 32 || str_starts_with(JWT_SECRET, 'CHANGE_THIS')) {
    http_response_code(500);
    die('JWT_SECRET w includes/secrets.php jest brakujący, zbyt krótki lub wciąż ma wartość przykładową. Wygeneruj: php -r "echo bin2hex(random_bytes(32));"');
}
if (!defined('ADMIN_PASSWORD_HASH') || ADMIN_PASSWORD_HASH === 'CHANGE_THIS_PASSWORD_HASH') {
    http_response_code(500);
    die('ADMIN_PASSWORD_HASH w includes/secrets.php jest wciąż wartością przykładową.');
}

// ============================================================
// BASE URL — leave empty if the site is in the root directory
// E.g. if the site is at /swim, enter '/swim'
// ============================================================
define('BASE_URL', '');

// Directory with competition JSON files
define('ZAWODY_DIR', __DIR__ . '/../zawody');

// Maximum JSON file size (5 MB)
define('MAX_JSON_SIZE', 5 * 1024 * 1024);

// Directory with athlete profiles
define('ZAWODNICY_DIR', __DIR__ . '/../zawodnicy');

// Live config file (active competition)
define('LIVE_CONFIG_FILE', __DIR__ . '/../live_config.json');

// File with competition announcements (without start list)
define('ZAPOWIEDZI_FILE', __DIR__ . '/../zapowiedzi.json');

// ============================================================
// REST API v1 — Angular SPA integration
// ============================================================

// JWT token time-to-live in seconds (default: 24 h)
define('JWT_TTL', 86400);

// Allowed CORS origin for the Angular SPA. The production domain is set in
// gitignored includes/secrets.php so it is not committed; without it the
// local dev server origin is allowed.
if (!defined('CORS_ALLOWED_ORIGIN')) {
    define('CORS_ALLOWED_ORIGIN', 'http://localhost:4200');
}

// ============================================================
// Login rate limiting (api/v1/auth.php)
// ============================================================
define('LOGIN_ATTEMPTS_FILE',   __DIR__ . '/../login_attempts.json');
define('LOGIN_MAX_ATTEMPTS',    5);
define('LOGIN_WINDOW_SECONDS',  900); // 15 min
define('LOGIN_LOCKOUT_SECONDS', 300); // 5 min

// ============================================================
// Public start list preview rate limiting (api/v1/startlist.php)
// Preview is available without login and triggers a server-side PDF
// download + parse, so it is throttled per IP.
// ============================================================
define('STARTLIST_PREVIEW_RATE_LIMIT', true);
define('STARTLIST_PREVIEW_RATE_FILE', __DIR__ . '/../startlist_preview_rate.json');
define('STARTLIST_PREVIEW_MAX',       20);   // requests per window
define('STARTLIST_PREVIEW_WINDOW',    3600); // 1 h

// ============================================================
// Contact / registration form (api/v1/contact.php)
// Public endpoint that sends an e-mail via PHP mail() (OVH web hosting).
// ============================================================
define('CONTACT_TO_EMAIL', 'info@nd-soft.pl');
// Sender address — on OVH it must be a mailbox in a domain hosted on the
// same account, otherwise mail() messages are rejected or land in spam.
// Override in includes/secrets.php, e.g. define('CONTACT_FROM_EMAIL', 'formularz@twojadomena.pl');
if (!defined('CONTACT_FROM_EMAIL')) {
    define('CONTACT_FROM_EMAIL', CONTACT_TO_EMAIL);
}
define('CONTACT_RATE_FILE', __DIR__ . '/../contact_rate.json');
define('CONTACT_MAX',       5);    // messages per window
define('CONTACT_WINDOW',    3600); // 1 h

// Only livetiming.pl contest URLs may be fetched server-side (SSRF guard)
define('ALLOWED_CONTEST_HOST_SUFFIX', 'livetiming.pl');
