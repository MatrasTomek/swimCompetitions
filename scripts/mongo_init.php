<?php
/**
 * Creates the MongoDB indexes for user accounts (idempotent — safe to re-run).
 * CLI only:  php scripts/mongo_init.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mongo.php';

if (!mongo_available()) {
    fwrite(STDERR, "Brak MONGO_URI w includes/secrets.php, rozszerzenia mongodb albo vendor/ (composer install).\n");
    exit(1);
}
require_once __DIR__ . '/../includes/user_repo.php';

user_ensure_indexes();
foreach (mongo_users()->listIndexes() as $index) {
    echo $index->getName(), "\n";
}
echo "OK — indeksy kolekcji users gotowe w bazie ", MONGO_DB, ".\n";
