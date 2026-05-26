<?php
/**
 * GET /api/download_athletes.php
 * Downloads all athlete JSON profiles merged into one file.
 * Requires active admin session.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    exit('Unauthorized');
}

require_once __DIR__ . '/../includes/config.php';

$athletes = [];
$dir = ZAWODNICY_DIR;

if (is_dir($dir)) {
    foreach (glob($dir . '/*.json') as $path) {
        $raw  = file_get_contents($path);
        $data = json_decode($raw, true);
        if (is_array($data)) {
            $athletes[basename($path, '.json')] = $data;
        }
    }
}

$date     = date('Y-m-d');
$filename = 'zawodnicy_' . $date . '.json';

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo json_encode($athletes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
