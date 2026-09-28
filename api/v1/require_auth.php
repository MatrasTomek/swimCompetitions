<?php
/**
 * JWT auth middleware.
 * Include in protected endpoints. Sets $GLOBALS['jwt_payload'] on success.
 * Sends 401 and exits on failure.
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/jwt.php';

function api_auth_header(): string {
    // CGI/FastCGI potrafi zgubić lub przemianować nagłówek Authorization,
    // dlatego sprawdzamy wszystkie miejsca, w których może wylądować.
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        return $_SERVER['HTTP_AUTHORIZATION'];
    }
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                return $value;
            }
        }
    }
    return '';
}

/** Returns the JWT payload when a valid Bearer token is present, null otherwise (never exits). */
function api_optional_auth(): ?array {
    if (!preg_match('/^Bearer\s+(.+)$/i', api_auth_header(), $m)) {
        return null;
    }
    return jwt_decode($m[1], JWT_SECRET);
}

function api_require_auth(): array {
    $header = api_auth_header();
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        api_auth_fail(401, 'Unauthorized');
    }
    $payload = jwt_decode($m[1], JWT_SECRET);
    if ($payload === null) {
        api_auth_fail(401, 'Token invalid or expired');
    }
    return $payload;
}

/** Sends a JSON error and stops the request. */
function api_auth_fail(int $code, string $error): never {
    http_response_code($code);
    echo json_encode(['error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * True for the admin's token. Tokens issued before roles existed carry no
 * 'role' claim — they are admin tokens only when sub is the admin login.
 */
function jwt_is_admin(array $payload): bool {
    if (isset($payload['role'])) {
        return $payload['role'] === 'admin';
    }
    return ($payload['sub'] ?? null) === ADMIN_USER;
}

/** Requires the admin's token (every endpoint that changes competitions, athletes, live config, …). */
function api_require_admin(): array {
    $payload = api_require_auth();
    if (!jwt_is_admin($payload)) {
        api_auth_fail(403, 'Brak uprawnień.');
    }
    return $payload;
}

/** Admin's JWT payload when a valid admin Bearer token is present, null otherwise (never exits). */
function api_optional_admin(): ?array {
    $payload = api_optional_auth();
    return ($payload !== null && jwt_is_admin($payload)) ? $payload : null;
}

/** 503 when user accounts are not configured (no MONGO_URI / extension / vendor). */
function api_require_accounts(): void {
    require_once __DIR__ . '/../../includes/mongo.php';
    if (!mongo_available()) {
        api_auth_fail(503, 'Konta użytkowników są chwilowo niedostępne.');
    }
    require_once __DIR__ . '/../../includes/user_repo.php';
}

/**
 * Requires a club user's token and returns the current account document.
 * The account is re-read on every request, so a blocked account or a changed
 * password (tokenVersion) ends existing sessions immediately.
 */
function api_require_user(): array {
    $payload = api_require_auth();
    if (($payload['role'] ?? null) !== 'user' || !is_string($payload['sub'] ?? null)) {
        api_auth_fail(403, 'Brak uprawnień.');
    }
    api_require_accounts();
    $user = user_find_by_id($payload['sub']);
    if ($user === null || $user['status'] !== 'active' || (int)$user['tokenVersion'] !== (int)($payload['tv'] ?? 0)) {
        api_auth_fail(401, 'Sesja wygasła — zaloguj się ponownie.');
    }
    return $user;
}
