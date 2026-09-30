<?php
/**
 * MongoDB failing in the middle of a request: every account endpoint answers 503 JSON (no stack trace),
 * the rest of the API keeps working. Starts the API on PHP's built-in server with an unreachable MongoDB —
 * run in the dev api container (needs ext-mongodb and vendor/):
 *   docker compose -f dev/docker-compose.yml exec api php scripts/tests/test_mongo_outage.php
 */
require __DIR__ . '/_assert.php';

require __DIR__ . '/../../includes/config.php';
require __DIR__ . '/../../includes/jwt.php';
require __DIR__ . '/../../includes/mongo.php';

if (!extension_loaded('mongodb') || !file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    fwrite(STDERR, "Brak rozszerzenia mongodb albo vendor/ — uruchom w kontenerze api (dev/docker-compose.yml).\n");
    exit(1);
}

/** Calls the API started by with_outage_server(); returns [status, content type, decoded body or null]. */
function outage_call(int $port, string $method, string $path, ?array $body = null, ?string $token = null): array {
    $headers = ['Content-Type: application/json'];
    if ($token !== null) $headers[] = 'Authorization: Bearer ' . $token;
    $ch = curl_init("http://127.0.0.1:$port/api/v1/$path");
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $body === null ? null : json_encode($body),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $raw = curl_exec($ch);
    $out = [curl_getinfo($ch, CURLINFO_RESPONSE_CODE), (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE), json_decode((string)$raw, true)];
    curl_close($ch);
    return $out;
}

/** Runs $fn(port) against the API whose MongoDB is $mongoUri. */
function with_outage_server(string $mongoUri, int $port, callable $fn): void {
    $proc = proc_open(
        [PHP_BINARY, '-S', "127.0.0.1:$port", __DIR__ . '/fixtures/outage_router.php'],
        [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
        $pipes,
        __DIR__ . '/../..',
        ['OUTAGE_MONGO_URI' => $mongoUri] + getenv()
    );
    try {
        for ($i = 0; $i < 50 && !($s = @fsockopen('127.0.0.1', $port)); $i++) usleep(100000);
        if (!empty($s)) fclose($s);
        $fn($port);
    } finally {
        proc_terminate($proc);
        proc_close($proc);
    }
}

$now       = time();
$userToken = jwt_encode(['sub' => uuid_v4(), 'role' => 'user', 'tv' => 1, 'iat' => $now, 'exp' => $now + 60], JWT_SECRET);
$adminToken = jwt_encode(['sub' => ADMIN_USER, 'role' => 'admin', 'iat' => $now, 'exp' => $now + 60], JWT_SECRET);
$unavailable = ['error' => 'Konta użytkowników są chwilowo niedostępne.'];

$outages = [
    'server down'      => 'mongodb://127.0.0.1:1',                  // server selection times out
    'SRV lookup fails' => 'mongodb+srv://cluster0.outage.invalid',  // the client cannot even be built
];
$port = 8951;
foreach ($outages as $label => $uri) {
    with_outage_server($uri, $port++, function (int $port) use ($label, $userToken, $adminToken, $unavailable) {
        $calls = [
            'login'           => ['POST', 'auth/login', ['username' => 'klub@test.pl', 'password' => 'x'], null],
            'forgot-password' => ['POST', 'account/forgot-password', ['email' => 'klub@test.pl'], null],
            'account/me'      => ['GET', 'account/me', null, $userToken],
            'users'           => ['GET', 'users', null, $adminToken],
        ];
        foreach ($calls as $name => [$method, $path, $body, $token]) {
            [$status, $type, $json] = outage_call($port, $method, $path, $body, $token);
            check("$label: $name status", $status, 503);
            check("$label: $name content type", $type, 'application/json; charset=utf-8');
            check("$label: $name body", $json, $unavailable);
        }

        // The rest of the API does not need MongoDB
        [$status] = outage_call($port, 'GET', 'competitions');
        check("$label: competitions still work", $status, 200);
    });
}

finish();
