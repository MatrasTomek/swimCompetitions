<?php
/**
 * Public endpoint: fetch and filter a start list from livetiming.pl by club name.
 * POST /api/search_startlist.php
 * Body JSON: {contest_url, club, basen?}
 *
 * Returns:
 *   {status:"ok", zawody:{...}}               — filtered start list
 *   {status:"no_startlist", error:"..."}       — PDF not available
 *   {status:"no_athletes", message:"..."}      — PDF OK but no athletes from this club
 *   {status:"error", error:"..."}              — bad request / validation error
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/startlist_parse.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'error' => 'Method Not Allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$contest_url = trim($body['contest_url'] ?? '');
$club        = trim($body['club']        ?? '');
$basen       = in_array($body['basen'] ?? '', ['25m', '50m'], true) ? $body['basen'] : '25m';

if ($contest_url === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Podaj URL zawodów.']);
    exit;
}
if ($club === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Podaj nazwę klubu.']);
    exit;
}

// SSRF guard: only allow livetiming.pl domains
$parsed = parse_url($contest_url);
$host   = strtolower($parsed['host'] ?? '');
$allowed_hosts = ['livetiming.pl', 'live.livetiming.pl', 'www.livetiming.pl'];
if (!in_array($host, $allowed_hosts, true) && !str_ends_with($host, '.livetiming.pl')) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Dozwolone są tylko zawody z livetiming.pl.']);
    exit;
}

$result = build_startlist_from_pdf($contest_url, $club, $basen);

if (!$result['ok']) {
    echo json_encode([
        'status' => 'no_startlist',
        'error'  => $result['error'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($result['zawody']['bloki'])) {
    echo json_encode([
        'status'  => 'no_athletes',
        'message' => 'Nie znaleziono zawodników z klubu „' . $club . '" na liście startowej.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'status' => 'ok',
    'zawody' => $result['zawody'],
], JSON_UNESCAPED_UNICODE);
