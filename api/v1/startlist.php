<?php
/**
 * Start list import:
 *   POST /api/v1/startlist/preview → parse PDF, return zawody JSON + stats (public, rate-limited per IP)
 *
 * The imported start list is kept only in the visitor's browser — it is never written to zawody/.
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/startlist_parse.php';

function handle_startlist(string $sub, string $method): void {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
        return;
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    if ($sub === 'preview') {
        if (STARTLIST_PREVIEW_RATE_LIMIT && startlist_preview_rate_limited($_SERVER['REMOTE_ADDR'] ?? 'unknown')) {
            http_response_code(429);
            echo json_encode(['error' => 'Zbyt wiele zapytań. Spróbuj ponownie później.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $contest_url = trim($body['contest_url'] ?? '');
        $klub        = trim($body['klub']        ?? '');

        if ($contest_url === '') { echo json_encode(['error' => 'Podaj URL zawodów.']); return; }
        if ($klub        === '') { echo json_encode(['error' => 'Podaj nazwę klubu.']); return; }

        $result = build_startlist_from_pdf($contest_url, $klub);

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

    http_response_code(404);
    echo json_encode(['error' => 'Unknown action. Use: preview']);
}
