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

/** The client could not be built — e.g. the SRV lookup of the Atlas host failed. */
class MongoUnavailableException extends RuntimeException {}

/**
 * True when $e means MongoDB cannot be reached right now (server down, network, timeout, DNS) —
 * the API answers 503 then. Anything else (a bad query, a write error) is a bug and stays a 500.
 */
function mongo_is_outage(Throwable $e): bool {
    return $e instanceof MongoUnavailableException
        || $e instanceof MongoDB\Driver\Exception\ConnectionException      // incl. server selection timeout
        || $e instanceof MongoDB\Driver\Exception\ExecutionTimeoutException;
}

/** Lazily connected database handle (one client per request). */
function mongo_db(): MongoDB\Database {
    static $db = null;
    if ($db === null) {
        require_once __DIR__ . '/../vendor/autoload.php';
        try {
            $client = new MongoDB\Client(MONGO_URI, [
                'serverSelectionTimeoutMS' => 5000,
                'connectTimeoutMS'         => 5000,
                'socketTimeoutMS'          => 15000,
            ], [
                // Plain PHP arrays instead of BSONDocument objects
                'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
            ]);
        } catch (MongoDB\Driver\Exception\Exception $e) {
            // The message may quote the connection string — it goes to the log only, never to the client
            throw new MongoUnavailableException('MongoDB client: ' . $e->getMessage(), 0, $e);
        }
        $db = $client->selectDatabase(MONGO_DB);
    }
    return $db;
}

function mongo_users(): MongoDB\Collection {
    return mongo_db()->selectCollection('users');
}

/** Club members' results fetched from LENEX (see results_* in user_repo.php). */
function mongo_results(): MongoDB\Collection {
    return mongo_db()->selectCollection('results');
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
