<?php
/**
 * One-off hosting check (OVH): is the mongodb extension loaded, is vendor/
 * uploaded and can the server connect to MongoDB Atlas with MONGO_URI from
 * includes/secrets.php? Also creates the users indexes (idempotent), so
 * scripts/mongo_init.php is not needed on hosting without SSH.
 *
 * Upload to the web root (next to index.html), open once in the browser:
 *   https://your-domain/mongo_check.php
 * then DELETE this file.
 */

header('Content-Type: text/plain; charset=utf-8');

// Works both from the web root and from a first-level subfolder.
$root = is_dir(__DIR__ . '/includes') ? __DIR__ : dirname(__DIR__);

echo 'PHP:               ', PHP_VERSION, "\n";
echo 'ext-mongodb:       ', extension_loaded('mongodb') ? 'TAK, wersja ' . phpversion('mongodb') : 'NIE', "\n";
echo 'vendor/autoload:   ', file_exists($root . '/vendor/autoload.php') ? 'TAK' : 'NIE', "\n";

require_once $root . '/includes/config.php';
require_once $root . '/includes/mongo.php';

echo 'MONGO_URI:         ', defined('MONGO_URI') && MONGO_URI !== '' ? 'TAK' : 'NIE (brak w includes/secrets.php)', "\n";

if (!mongo_available()) {
    echo "\nNie można sprawdzić połączenia — popraw pozycje z NIE powyżej.\n";
    exit;
}

try {
    mongo_db()->command(['ping' => 1]);
    echo 'Połączenie z bazą: OK (baza ', MONGO_DB, ")\n";

    require_once $root . '/includes/user_repo.php';
    user_ensure_indexes();
    $names = [];
    foreach (mongo_users()->listIndexes() as $index) {
        $names[] = $index->getName();
    }
    echo 'Indeksy users:     OK (', implode(', ', $names), ")\n";
} catch (Throwable $e) {
    // The driver message names hosts, never the password.
    echo 'Połączenie z bazą: BŁĄD — ', $e->getMessage(), "\n";
}
