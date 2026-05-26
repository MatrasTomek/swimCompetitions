<?php
/**
 * AJAX endpoint: preview and save imported start list from livetiming.pl PDF.
 * POST /api/import_startlist.php — requires active admin session.
 *
 * action=preview: download + parse PDF, return zawody JSON + stats
 * action=save:    write zawody JSON to zawody/ directory
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
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/startlist_parse.php';

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim($body['action'] ?? 'preview');

// ── preview ──────────────────────────────────────────────────────────────────
if ($action === 'preview') {
    $contest_url = trim($body['contest_url'] ?? '');
    $klub        = trim($body['klub']        ?? '');
    $basen       = in_array($body['basen'] ?? '', ['25m','50m'], true) ? $body['basen'] : '25m';

    if ($contest_url === '') { echo json_encode(['error' => 'Podaj URL zawodów.']); exit; }
    if ($klub        === '') { echo json_encode(['error' => 'Podaj nazwę klubu.']); exit; }

    $result = build_startlist_from_pdf($contest_url, $klub, $basen);

    if (!$result['ok']) {
        $out = ['error' => $result['error']];
        if (!empty($result['pdf_url']))  $out['pdf_url']  = $result['pdf_url'];
        if (!empty($result['raw_text'])) $out['raw_text'] = $result['raw_text'];
        echo json_encode($out);
        exit;
    }

    echo json_encode([
        'ok'       => true,
        'zawody'   => $result['zawody'],
        'raw_text' => $result['raw_text'],
        'pdf_url'  => $result['pdf_url'],
        'stats'    => [
            'starts'   => $result['starts'],
            'athletes' => $result['athletes'],
            'blocks'   => $result['blocks'],
        ],
    ]);
    exit;
}

// ── save ─────────────────────────────────────────────────────────────────────
if ($action === 'save') {
    $zawody = $body['zawody'] ?? null;
    if (!is_array($zawody) || empty($zawody['bloki'])) {
        echo json_encode(['error' => 'Brak danych do zapisania.']);
        exit;
    }

    // Sanitize metadata fields
    $zawody['nazwa']   = mb_substr(trim($zawody['nazwa']   ?? ''), 0, 255, 'UTF-8');
    $zawody['miejsce'] = mb_substr(trim($zawody['miejsce'] ?? ''), 0, 100, 'UTF-8');
    $zawody['data']    = mb_substr(trim($zawody['data']    ?? ''), 0,  30, 'UTF-8');
    $zawody['klub']    = mb_substr(trim($zawody['klub']    ?? ''), 0, 255, 'UTF-8');
    $zawody['basen']   = in_array($zawody['basen'] ?? '', ['25m','50m'], true) ? $zawody['basen'] : '25m';

    $base     = slugify($zawody['nazwa'] ?: 'zawody');
    $filename = unique_filename($base ?: 'zawody');
    $dest     = ZAWODY_DIR . '/' . $filename;

    $json = json_encode($zawody, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (file_put_contents($dest, $json) === false) {
        echo json_encode(['error' => 'Nie udało się zapisać pliku. Sprawdź uprawnienia katalogu zawody/.']);
        exit;
    }

    echo json_encode([
        'ok'       => true,
        'filename' => $filename,
        'redirect' => BASE_URL . '/admin/edytuj.php?f=' . urlencode($filename),
    ]);
    exit;
}

echo json_encode(['error' => 'Nieznana akcja.']);
