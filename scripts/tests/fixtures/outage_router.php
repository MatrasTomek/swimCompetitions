<?php
/**
 * Router for test_mongo_outage.php: the API with a MongoDB that cannot be reached (OUTAGE_MONGO_URI).
 * Runs only under PHP's built-in server started by that test.
 */
if (PHP_SAPI !== 'cli-server' || !getenv('OUTAGE_MONGO_URI')) {
    http_response_code(404);
    exit;
}

define('MONGO_URI', getenv('OUTAGE_MONGO_URI'));
define('MONGO_DB', 'swim_test');

// secrets.php defines MONGO_URI too — keep ours and hide only that warning
set_error_handler(fn(int $no, string $msg) => str_contains($msg, 'MONGO_URI already defined'));

require __DIR__ . '/../../../api/v1/index.php';
