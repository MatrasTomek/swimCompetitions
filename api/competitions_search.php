<?php
/**
 * Public endpoint: search livetiming.pl competition cache.
 * GET /api/competitions_search.php?q=text
 * Returns JSON array of up to 20 matching competitions.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/livetiming_cache.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([]);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

echo json_encode(ltcache_search($q), JSON_UNESCAPED_UNICODE);
