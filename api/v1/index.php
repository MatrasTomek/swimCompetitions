<?php
/**
 * REST API v1 — main router
 *
 * Routes:
 *   /api/v1/auth/{sub}
 *   /api/v1/competitions[/{slug}[/pdf]]
 *   /api/v1/athletes[/{slug|export}]
 *   /api/v1/startlist/{preview|save}
 *   /api/v1/results/fetch
 *   /api/v1/live
 *   /api/v1/announcements[/{id}]
 */

require_once __DIR__ . '/cors.php';      // Must be first — sets CORS headers + handles OPTIONS

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/config.php';

// ── Parse path ───────────────────────────────────────────────────────────────
// PATH_INFO works without mod_rewrite (e.g. /api/v1/index.php/competitions)
if (!empty($_SERVER['PATH_INFO'])) {
    $path = $_SERVER['PATH_INFO'];
} else {
    $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $prefix = rtrim(BASE_URL, '/') . '/api/v1';
    $path   = (strpos($uri, $prefix) === 0) ? substr($uri, strlen($prefix)) : $uri;
}
$path = '/' . trim($path, '/');

// Split path into segments: ['competitions', 'slug', 'pdf']
$segs = array_values(array_filter(explode('/', trim($path, '/'))));
$method   = $_SERVER['REQUEST_METHOD'];
$resource = $segs[0] ?? '';
$seg1     = $segs[1] ?? '';   // slug / sub-resource / id
$seg2     = $segs[2] ?? '';   // sub-sub (e.g. 'pdf')


// ── Dispatch ─────────────────────────────────────────────────────────────────
switch ($resource) {
    case 'auth':
        require_once __DIR__ . '/auth.php';
        handle_auth($seg1, $method);
        break;

    case 'competitions':
        require_once __DIR__ . '/competitions.php';
        handle_competitions($seg1, $seg2, $method);
        break;

    case 'athletes':
        require_once __DIR__ . '/athletes.php';
        handle_athletes($seg1, $method);
        break;

    case 'startlist':
        require_once __DIR__ . '/startlist.php';
        handle_startlist($seg1, $method);
        break;

    case 'results':
        require_once __DIR__ . '/results.php';
        handle_results($seg1, $method);
        break;

    case 'live':
        require_once __DIR__ . '/live.php';
        handle_live($method);
        break;

    case 'announcements':
        require_once __DIR__ . '/announcements.php';
        handle_announcements($seg1, $method);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Unknown resource: ' . $resource]);
        break;
}
