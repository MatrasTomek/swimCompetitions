<?php
/**
 * Live config endpoints (auth required):
 *   GET /api/v1/live → current live config
 *   PUT /api/v1/live → save live config
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/result_fetch.php';
require_once __DIR__ . '/require_auth.php';

function handle_live(string $method): void {
    api_require_auth();

    if ($method === 'GET') {
        $config = load_live_config();
        echo json_encode($config ?: (object)[], JSON_UNESCAPED_UNICODE);
        return;
    }

    if ($method === 'PUT') {
        $body        = json_decode(file_get_contents('php://input'), true) ?? [];
        $contest_url = trim($body['contest_url'] ?? '');
        $json_file   = preg_replace('/\.json$/', '', basename(trim($body['json_file'] ?? '')));

        if ($contest_url === '') { echo json_encode(['error' => 'Podaj URL zawodów.']); return; }
        if ($json_file   === '') { echo json_encode(['error' => 'Podaj plik JSON.']); return; }
        if (!is_allowed_contest_host($contest_url)) {
            echo json_encode(['error' => 'Niedozwolony host — dozwolone są tylko adresy livetiming.pl.']);
            return;
        }

        // Resolve competition name from file
        $path  = safe_json_path($json_file . '.json');
        $nazwa = '';
        if ($path) {
            $data  = json_decode(file_get_contents($path), true);
            $nazwa = $data['nazwa'] ?? '';
        }

        $config = [
            'contest_url'         => $contest_url,
            'json_file'           => $json_file,
            'nazwa'               => $nazwa,
            'ostatnia_aktualizacja' => date('c'),
        ];
        if (!save_live_config($config)) {
            http_response_code(500);
            echo json_encode(['error' => 'Nie udało się zapisać konfiguracji.']);
            return;
        }
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        return;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
