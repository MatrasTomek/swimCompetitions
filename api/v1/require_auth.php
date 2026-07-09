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

function api_require_auth(): array {
    $header = api_auth_header();
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $payload = jwt_decode($m[1], JWT_SECRET);
    if ($payload === null) {
        http_response_code(401);
        echo json_encode(['error' => 'Token invalid or expired']);
        exit;
    }
    return $payload;
}
