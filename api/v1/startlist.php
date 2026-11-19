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

    // An uploaded file comes as multipart/form-data (klub/basen in $_POST, file in $_FILES);
    // a link comes as a JSON body. Read JSON only when it isn't a file upload.
    $is_upload = !empty($_FILES['file']) || str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data');
    $body = $is_upload ? [] : (json_decode(file_get_contents('php://input'), true) ?? []);

    if ($sub === 'preview') {
        if (STARTLIST_PREVIEW_RATE_LIMIT && startlist_preview_rate_limited($_SERVER['REMOTE_ADDR'] ?? 'unknown')) {
            http_response_code(429);
            echo json_encode(['error' => 'Zbyt wiele zapytań. Spróbuj ponownie później.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $klub  = trim($is_upload ? ($_POST['klub']  ?? '') : ($body['klub']  ?? ''));
        $basen = $is_upload ? ($_POST['basen'] ?? '25m') : ($body['basen'] ?? '25m');
        if (!is_string($basen)) $basen = '25m';
        if ($klub === '') { echo json_encode(['error' => 'Podaj nazwę klubu.']); return; }

        if ($is_upload) {
            $file = $_FILES['file'] ?? null;
            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $msg = (($file['error'] ?? null) === UPLOAD_ERR_INI_SIZE || ($file['error'] ?? null) === UPLOAD_ERR_FORM_SIZE)
                    ? 'Plik jest za duży.' : 'Nie udało się wczytać pliku. Wgraj listę startową w formacie PDF.';
                echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
                return;
            }
            if (($file['size'] ?? 0) > STARTLIST_PDF_MAX_BYTES) {
                echo json_encode(['error' => 'Plik jest za duży (maks. 20 MB).'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $pdf_data = @file_get_contents($file['tmp_name']);
            if ($pdf_data === false) { echo json_encode(['error' => 'Nie udało się odczytać pliku.']); return; }
            $result = build_startlist_from_pdf_file($pdf_data, $klub, $basen);
        } else {
            $contest_url = trim($body['contest_url'] ?? '');
            if ($contest_url === '') { echo json_encode(['error' => 'Podaj link do listy startowej.']); return; }
            $result = build_startlist_from_pdf($contest_url, $klub, $basen);
        }

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
