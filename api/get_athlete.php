<?php
/**
 * GET /api/get_athlete.php?file=nazwisko-imie.json
 * Serves a single athlete JSON file as a download.
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

$file = basename(trim($_GET['file'] ?? ''));
if (!preg_match('/^[a-z0-9_-]+\.json$/i', $file)) {
    http_response_code(400);
    exit('Invalid filename');
}

$path = ZAWODNICY_DIR . '/' . $file;
if (!file_exists($path)) {
    http_response_code(404);
    exit('Not found');
}

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
