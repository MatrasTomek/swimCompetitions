<?php
// ============================================================
// Copy this file to includes/secrets.php and fill in real values.
// includes/secrets.php is gitignored — it must never be committed.
// ============================================================

// Bcrypt hash of the admin password. Generate with:
//   php -r "echo password_hash('your-password-here', PASSWORD_BCRYPT);"
define('ADMIN_PASSWORD_HASH', 'CHANGE_THIS_PASSWORD_HASH');

// JWT signing secret — a long random string (64+ chars recommended). Generate with:
//   php -r "echo bin2hex(random_bytes(32));"
define('JWT_SECRET', 'CHANGE_THIS_TO_A_LONG_RANDOM_SECRET_STRING_AT_LEAST_64_CHARS_RECOMMENDED');

// Origin of the Angular SPA in production (scheme + host, no trailing slash).
// Omit locally — the dev server origin http://localhost:4200 is used then.
// define('CORS_ALLOWED_ORIGIN', 'https://www.example.com');

// Sender (From) of registration form e-mails. On OVH it must be a mailbox in
// a domain hosted on this account. Omit to send from info@nd-soft.pl.
// define('CONTACT_FROM_EMAIL', 'formularz@example.com');
