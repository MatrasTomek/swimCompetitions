<?php
/**
 * JWT auth middleware.
 * Include in protected endpoints. Sets $GLOBALS['jwt_payload'] on success.
 * Sends 401 and exits on failure.
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/jwt.php';

function api_require_auth(): array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
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
