<?php
/**
 * AJAX endpoint: manually triggers LENEX result fetch.
 * POST /api/fetch_result.php — requires active admin session.
 */
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/athlete.php';
require_once __DIR__ . '/../includes/result_fetch.php';

$body        = json_decode(file_get_contents('php://input'), true) ?? [];
$contest_url = trim($body['contest_url'] ?? '');
$json_file   = preg_replace('/\.json$/', '', basename(trim($body['json_file'] ?? '')));

$result = fetch_and_apply_lenex($contest_url, $json_file);

echo json_encode(array_merge($result, ['time' => date('c')]));
