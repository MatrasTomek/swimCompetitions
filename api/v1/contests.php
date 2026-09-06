<?php
/**
 * Contest cache endpoints:
 *   GET  /api/v1/contests/search?q=...  — search local livetiming.pl cache (no auth)
 *   GET  /api/v1/contests/cache-status  — return cache metadata (no auth)
 *   POST /api/v1/contests/cache-refresh — rebuild cache (auth required)
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/livetiming_cache.php';

function handle_contests(string $sub, string $method): void {
    if ($sub === 'search' && $method === 'GET') {
        $q = trim($_GET['q'] ?? '');
        echo json_encode(ltcache_search($q, 20), JSON_UNESCAPED_UNICODE);
        return;
    }

    if ($sub === 'cache-status' && $method === 'GET') {
        echo json_encode(ltcache_status(), JSON_UNESCAPED_UNICODE);
        return;
    }

    if ($sub === 'cache-refresh' && $method === 'POST') {
        require_once __DIR__ . '/require_auth.php';
        api_require_auth();
        set_time_limit(300);
        $result = ltcache_refresh(30, true);
        $status = ltcache_status();
        echo json_encode([
            'ok'     => (bool)($result['ok'] ?? false),
            'status' => $status,
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Unknown sub-resource. Available: search, cache-status, cache-refresh']);
}
