<?php
/**
 * Announcement endpoints (auth required):
 *   GET    /api/v1/announcements       → list all announcements
 *   POST   /api/v1/announcements       → create announcement
 *   DELETE /api/v1/announcements/{id}  → delete announcement
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/require_auth.php';

function handle_announcements(string $id, string $method): void {
    api_require_auth();

    if ($id === '' && $method === 'GET') {
        echo json_encode(load_zapowiedzi(), JSON_UNESCAPED_UNICODE);
        return;
    }

    if ($id === '' && $method === 'POST') {
        $body    = json_decode(file_get_contents('php://input'), true) ?? [];
        $nazwa   = mb_substr(trim($body['nazwa']   ?? ''), 0, 255, 'UTF-8');
        $miejsce = mb_substr(trim($body['miejsce'] ?? ''), 0, 100, 'UTF-8');
        $data    = mb_substr(trim($body['data']    ?? ''), 0,  30, 'UTF-8');
        $klub    = mb_substr(trim($body['klub']    ?? ''), 0, 255, 'UTF-8');

        if ($nazwa === '') { echo json_encode(['error' => 'Pole "nazwa" jest wymagane.']); return; }

        $newId = save_zapowiedz($nazwa, $miejsce, $data, $klub);
        if ($newId === null) {
            http_response_code(500);
            echo json_encode(['error' => 'Nie udało się zapisać zapowiedzi.']);
            return;
        }
        echo json_encode(['ok' => true, 'id' => $newId]);
        return;
    }

    if ($id !== '' && $method === 'DELETE') {
        $deleted = delete_zapowiedz($id);
        if (!$deleted) { http_response_code(404); echo json_encode(['error' => 'Nie znaleziono zapowiedzi.']); return; }
        echo json_encode(['ok' => true]);
        return;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
