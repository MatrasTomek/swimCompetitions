<?php
/**
 * GET /api/list_athletes.php — returns list of all athlete JSON profiles.
 * Requires active admin session.
 */
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../includes/config.php';

$athletes = [];
$dir = ZAWODNICY_DIR;

if (!is_dir($dir)) {
    echo json_encode(['athletes' => [], 'total' => 0]);
    exit;
}

foreach (glob($dir . '/*.json') as $path) {
    $raw = file_get_contents($path);
    $data = json_decode($raw, true);
    if (!is_array($data)) continue;

    $athletes[] = [
        'file'          => basename($path),
        'imie'          => $data['imie']          ?? '',
        'nazwisko'      => $data['nazwisko']       ?? '',
        'rok_urodzenia' => $data['rok_urodzenia']  ?? null,
        'klub'          => $data['klub']           ?? '',
        'starty'        => count($data['starty']   ?? []),
    ];
}

usort($athletes, fn($a, $b) => strcmp($a['nazwisko'] . $a['imie'], $b['nazwisko'] . $b['imie']));

echo json_encode(['athletes' => $athletes, 'total' => count($athletes)], JSON_UNESCAPED_UNICODE);
