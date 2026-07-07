<?php
/**
 * Admin-only AJAX endpoint: rebuild livetiming.pl competition cache.
 * POST /api/ltcache_refresh.php
 * Returns: {ok, status: {exists, count, age_hours, is_fresh, updated_at}}
 */
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/livetiming_cache.php';

set_time_limit(300);
$result = ltcache_refresh(30, true);
$status = ltcache_status();

echo json_encode([
    'ok'     => (bool)($result['ok'] ?? false),
    'result' => $result,
    'status' => $status,
], JSON_UNESCAPED_UNICODE);
