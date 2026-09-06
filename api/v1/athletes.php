<?php
/**
 * Athlete endpoints (all require auth):
 *   GET /api/v1/athletes              → paginated/filtered list
 *   GET /api/v1/athletes/export       → all profiles merged (download)
 *   GET /api/v1/athletes/{slug}       → single athlete profile
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/require_auth.php';

function handle_athletes(string $slug, string $method): void {
    api_require_auth();

    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
        return;
    }

    // ── GET /athletes/export ─────────────────────────────────────────
    if ($slug === 'export') {
        if (!is_dir(ZAWODNICY_DIR)) { echo json_encode([], JSON_UNESCAPED_UNICODE); return; }
        $all = [];
        foreach (glob(ZAWODNICY_DIR . '/*.json') as $path) {
            $key  = basename($path, '.json');
            $data = json_decode(file_get_contents($path), true);
            if (is_array($data)) $all[$key] = $data;
        }
        $filename = 'zawodnicy_' . date('Y-m-d') . '.json';
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($all, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return;
    }

    // ── GET /athletes/{slug} ─────────────────────────────────────────
    if ($slug !== '') {
        $name = basename($slug);
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid athlete slug.']);
            return;
        }
        $path = safe_path(ZAWODNICY_DIR, $name . '.json');
        if ($path === '') {
            http_response_code(404);
            echo json_encode(['error' => 'Nie znaleziono zawodnika.']);
            return;
        }
        $raw     = file_get_contents($path);
        $decoded = json_decode($raw, true);
        echo $decoded !== null ? $raw : json_encode([]);
        return;
    }

    // ── GET /athletes ────────────────────────────────────────────────
    if (!is_dir(ZAWODNICY_DIR)) {
        echo json_encode(['athletes' => [], 'total' => 0]);
        return;
    }

    $q       = strtolower(trim($_GET['q'] ?? ''));
    $page    = max(1, (int)($_GET['page']     ?? 1));
    $perPage = min(200, max(1, (int)($_GET['per_page'] ?? 50)));

    $athletes = [];
    foreach (glob(ZAWODNICY_DIR . '/*.json') as $path) {
        $data = json_decode(file_get_contents($path), true);
        if (!is_array($data)) {
            error_log('GET /athletes: nie udało się odczytać/sparsować ' . basename($path));
            continue;
        }
        $row = [
            'file'          => basename($path),
            'imie'          => $data['imie']         ?? '',
            'nazwisko'      => $data['nazwisko']      ?? '',
            'rok_urodzenia' => $data['rok_urodzenia'] ?? null,
            'klub'          => $data['klub']          ?? '',
            'starty'        => count($data['starty']  ?? []),
        ];
        if ($q !== '') {
            $haystack = strtolower($row['imie'] . ' ' . $row['nazwisko'] . ' ' . $row['klub']);
            if (strpos($haystack, $q) === false) continue;
        }
        $athletes[] = $row;
    }

    usort($athletes, fn($a, $b) => strcmp($a['nazwisko'] . $a['imie'], $b['nazwisko'] . $b['imie']));

    $total   = count($athletes);
    $offset  = ($page - 1) * $perPage;
    $paged   = array_slice($athletes, $offset, $perPage);

    echo json_encode([
        'athletes' => $paged,
        'total'    => $total,
        'page'     => $page,
        'per_page' => $perPage,
    ], JSON_UNESCAPED_UNICODE);
}
