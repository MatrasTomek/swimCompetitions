<?php
/**
 * Start list import (auth required):
 *   POST /api/v1/startlist/preview → parse PDF, return zawody JSON + stats
 *   POST /api/v1/startlist/save    → write zawody JSON to zawody/
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/startlist_parse.php';
require_once __DIR__ . '/require_auth.php';

function handle_startlist(string $sub, string $method): void {
    api_require_auth();

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
        return;
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    if ($sub === 'preview') {
        $contest_url = trim($body['contest_url'] ?? '');
        $klub        = trim($body['klub']        ?? '');
        $basen       = in_array($body['basen'] ?? '', ['25m','50m'], true) ? $body['basen'] : '25m';

        if ($contest_url === '') { echo json_encode(['error' => 'Podaj URL zawodów.']); return; }
        if ($klub        === '') { echo json_encode(['error' => 'Podaj nazwę klubu.']); return; }

        $result = build_startlist_from_pdf($contest_url, $klub, $basen);

        if (!$result['ok']) {
            $out = ['error' => $result['error']];
            if (!empty($result['pdf_url']))  $out['pdf_url']  = $result['pdf_url'];
            if (!empty($result['raw_text'])) $out['raw_text'] = $result['raw_text'];
            echo json_encode($out);
            return;
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
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    if ($sub === 'save') {
        $zawody = $body['zawody'] ?? null;
        if (!is_array($zawody) || empty($zawody['bloki'])) {
            echo json_encode(['error' => 'Brak danych do zapisania.']);
            return;
        }

        $zawody['nazwa']   = mb_substr(trim($zawody['nazwa']   ?? ''), 0, 255, 'UTF-8');
        $zawody['miejsce'] = mb_substr(trim($zawody['miejsce'] ?? ''), 0, 100, 'UTF-8');
        $zawody['data']    = mb_substr(trim($zawody['data']    ?? ''), 0,  30, 'UTF-8');
        $zawody['klub']    = mb_substr(trim($zawody['klub']    ?? ''), 0, 255, 'UTF-8');
        $zawody['basen']   = in_array($zawody['basen'] ?? '', ['25m','50m'], true) ? $zawody['basen'] : '25m';

        $filename = unique_filename(slugify($zawody['nazwa'] ?: 'zawody'));
        $dest     = ZAWODY_DIR . '/' . $filename;

        if (file_put_contents($dest, json_encode($zawody, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) === false) {
            echo json_encode(['error' => 'Nie udało się zapisać pliku.']);
            return;
        }

        echo json_encode(['ok' => true, 'filename' => $filename], JSON_UNESCAPED_UNICODE);
        return;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Unknown action. Use: preview, save']);
}
