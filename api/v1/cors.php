<?php
/**
 * CORS headers for Angular SPA.
 * Include this at the very top of api/v1/index.php.
 */

require_once __DIR__ . '/../../includes/config.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($origin === CORS_ALLOWED_ORIGIN) {
    header('Access-Control-Allow-Origin: ' . CORS_ALLOWED_ORIGIN);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
