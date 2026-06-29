<?php
/**
 * Result fetch endpoint (auth required):
 *   POST /api/v1/results/fetch → trigger LENEX result download
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/athlete.php';
require_once __DIR__ . '/../../includes/result_fetch.php';
require_once __DIR__ . '/require_auth.php';

function handle_results(string $sub, string $method): void {
    api_require_auth();

    if ($sub === 'fetch' && $method === 'POST') {
        $body        = json_decode(file_get_contents('php://input'), true) ?? [];
        $contest_url = trim($body['contest_url'] ?? '');
        $json_file   = preg_replace('/\.json$/', '', basename(trim($body['json_file'] ?? '')));

        if ($contest_url === '') { echo json_encode(['error' => 'Podaj URL zawodów.']); return; }
        if ($json_file   === '') { echo json_encode(['error' => 'Podaj plik JSON.']); return; }

        $result = fetch_and_apply_lenex($contest_url, $json_file);
        echo json_encode(array_merge($result, ['time' => date('c')]), JSON_UNESCAPED_UNICODE);
        return;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Not found. Use: POST /results/fetch']);
}
