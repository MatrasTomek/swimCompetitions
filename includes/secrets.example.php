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
