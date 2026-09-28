<?php
/**
 * MongoDB connection for user accounts.
 * Needs the mongodb PHP extension and vendor/ (composer install).
 */

require_once __DIR__ . '/config.php';

/** True when accounts can be used: URI configured, extension and library present. */
function mongo_available(): bool {
    return defined('MONGO_URI') && MONGO_URI !== ''
        && extension_loaded('mongodb')
        && file_exists(__DIR__ . '/../vendor/autoload.php');
}

/** Lazily connected database handle (one client per request). */
function mongo_db(): MongoDB\Database {
    static $db = null;
    if ($db === null) {
        require_once __DIR__ . '/../vendor/autoload.php';
        $client = new MongoDB\Client(MONGO_URI, [
            'serverSelectionTimeoutMS' => 5000,
            'connectTimeoutMS'         => 5000,
            'socketTimeoutMS'          => 15000,
        ], [
            // Plain PHP arrays instead of BSONDocument objects
            'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
        ]);
        $db = $client->selectDatabase(MONGO_DB);
    }
    return $db;
}

function mongo_users(): MongoDB\Collection {
    return mongo_db()->selectCollection('users');
}

/** Random RFC 4122 version 4 UUID — ids of users, club members and their times. */
function uuid_v4(): string {
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

function is_uuid(string $s): bool {
    return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $s);
}
