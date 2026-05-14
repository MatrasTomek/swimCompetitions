<?php
/**
 * AJAX endpoint: checks and fetches pending results.
 * POST /api/fetch_result.php
 * Returns: {"updated": N, "results": [...]}
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/athlete.php';
require_once __DIR__ . '/../includes/result_fetch.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Basic protection: accept only same-origin requests
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host   = $_SERVER['HTTP_HOST']   ?? '';
if ($origin && parse_url($origin, PHP_URL_HOST) !== $host) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$updated = process_pending_results();

echo json_encode([
    'updated' => $updated,
    'time'    => date('c'),
]);
