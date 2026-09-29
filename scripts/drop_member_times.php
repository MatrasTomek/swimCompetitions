<?php
/**
 * One-off: removes the old hand-entered times (clubItems.clubMembers[].memberTimes) from every account.
 * Results now come only from LENEX (collection "results"). Delete this file from the server after running it.
 * CLI only:  php scripts/drop_member_times.php
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

echo "OK — usunięto memberTimes z ", user_drop_member_times(), " kont w bazie ", MONGO_DB, ".\n";
