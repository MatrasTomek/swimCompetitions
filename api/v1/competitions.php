<?php
/**
 * Competition endpoints:
 *   GET    /api/v1/competitions              → list (public)
 *   GET    /api/v1/competitions/{slug}       → single competition (public)
 *   GET    /api/v1/competitions/{slug}/pdf   → generate PDF (public)
 *   POST   /api/v1/competitions              → create (auth)
 *   PUT    /api/v1/competitions/{slug}       → update metadata (auth)
 *   DELETE /api/v1/competitions/{slug}       → delete (auth)
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/require_auth.php';

function handle_competitions(string $slug, string $sub, string $method): void {
    // ── GET /competitions ────────────────────────────────────────────
    if ($slug === '' && $method === 'GET') {
        echo json_encode(load_all_zawody(), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        return;
    }

    // ── GET /competitions/{slug}/pdf ─────────────────────────────────
    if ($slug !== '' && $sub === 'pdf' && $method === 'GET') {
        require_once __DIR__ . '/../../fpdf/fpdf.php';
        require_once __DIR__ . '/../../includes/athlete.php';

        $path = safe_json_path($slug . '.json');
        if (!$path) { http_response_code(404); echo json_encode(['error' => 'Nie znaleziono zawodów.']); return; }

        // Reuse the existing PDF generation logic from api/generuj_pdf.php
        ob_start();
        $_GET['zawody'] = preg_replace('/\.json$/', '', $slug);
        include __DIR__ . '/../generuj_pdf.php';
        ob_end_flush();
        return;
    }

    // ── GET /competitions/{slug} ─────────────────────────────────────
    if ($slug !== '' && $sub === '' && $method === 'GET') {
        $path = safe_json_path($slug . '.json');
        if (!$path) { http_response_code(404); echo json_encode(['error' => 'Nie znaleziono zawodów.']); return; }
        $data = json_decode(file_get_contents($path), true);
        $data['file'] = basename($path);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        return;
    }

    // ── POST /competitions ───────────────────────────────────────────
    if ($slug === '' && $method === 'POST') {
        api_require_auth();

        // Multipart: JSON file upload
        if (!empty($_FILES['file'])) {
            $check = validate_json_upload($_FILES['file']);
            if (!$check['ok']) { echo json_encode(['error' => $check['msg']]); return; }
            $zawody = $check['decoded'];

            // Optional metadata overrides from form fields
            foreach (['nazwa','miejsce','data','klub','basen'] as $f) {
                if (isset($_POST[$f]) && $_POST[$f] !== '') $zawody[$f] = trim($_POST[$f]);
            }

            $filename = unique_filename(slugify($zawody['nazwa'] ?? 'zawody'));
            $dest     = ZAWODY_DIR . '/' . $filename;
            if (!write_json_atomic($dest, $zawody)) {
                http_response_code(500);
                echo json_encode(['error' => 'Nie udało się zapisać pliku zawodów.']);
                return;
            }
            echo json_encode(['ok' => true, 'file' => $filename], JSON_UNESCAPED_UNICODE);
            return;
        }

        // JSON body: plain metadata (creates announcement)
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $nazwa   = mb_substr(trim($body['nazwa']   ?? ''), 0, 255, 'UTF-8');
        $miejsce = mb_substr(trim($body['miejsce'] ?? ''), 0, 100, 'UTF-8');
        $data    = mb_substr(trim($body['data']    ?? ''), 0,  30, 'UTF-8');
        $klub    = mb_substr(trim($body['klub']    ?? ''), 0, 255, 'UTF-8');

        if ($nazwa === '') { echo json_encode(['error' => 'Pole "nazwa" jest wymagane.']); return; }

        // If bloki provided → save as full competition
        if (is_array($body['bloki'] ?? null)) {
            $filename = unique_filename(slugify($nazwa ?: 'zawody'));
            $dest     = ZAWODY_DIR . '/' . $filename;
            $zawody   = array_intersect_key($body, array_flip(['nazwa','miejsce','data','klub','basen','bloki']));
            if (!write_json_atomic($dest, $zawody)) {
                http_response_code(500);
                echo json_encode(['error' => 'Nie udało się zapisać pliku zawodów.']);
                return;
            }
            echo json_encode(['ok' => true, 'file' => $filename], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Otherwise → announcement
        $id = save_zapowiedz($nazwa, $miejsce, $data, $klub);
        if ($id === null) {
            http_response_code(500);
            echo json_encode(['error' => 'Nie udało się zapisać zapowiedzi.']);
            return;
        }
        echo json_encode(['ok' => true, 'id' => $id, 'announcement' => true]);
        return;
    }

    // ── PUT /competitions/{slug} ─────────────────────────────────────
    if ($slug !== '' && $sub === '' && $method === 'PUT') {
        api_require_auth();

        $path = safe_json_path($slug . '.json');
        if (!$path) { http_response_code(404); echo json_encode(['error' => 'Nie znaleziono zawodów.']); return; }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $ok = with_file_lock($path . '.lock', function () use ($path, $body) {
            $zawody = json_decode(file_get_contents($path), true) ?? [];
            foreach (['nazwa','miejsce','data','klub','basen'] as $f) {
                if (array_key_exists($f, $body)) $zawody[$f] = mb_substr(trim((string)$body[$f]), 0, 255, 'UTF-8');
            }
            return write_json_atomic($path, $zawody);
        });

        if (!$ok) {
            http_response_code(500);
            echo json_encode(['error' => 'Nie udało się zapisać zawodów.']);
            return;
        }
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        return;
    }

    // ── DELETE /competitions/{slug} ──────────────────────────────────
    if ($slug !== '' && $sub === '' && $method === 'DELETE') {
        api_require_auth();

        $path = safe_json_path($slug . '.json');
        if (!$path) { http_response_code(404); echo json_encode(['error' => 'Nie znaleziono zawodów.']); return; }

        unlink($path);
        echo json_encode(['ok' => true]);
        return;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
