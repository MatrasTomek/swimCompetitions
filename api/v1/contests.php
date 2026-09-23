<?php
/**
 * Contest cache endpoints:
 *   GET  /api/v1/contests/search?q=...  — search local livetiming.pl cache (no auth)
 *   GET  /api/v1/contests/cache-status  — return cache metadata (no auth)
 *   POST /api/v1/contests/cache-refresh — rebuild cache (public: only when stale; admin: forced)
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
        // Visitors may only rebuild a stale cache (TTL-gated), admins can force it.
        // The lock makes concurrent requests wait for one scrape instead of each hitting livetiming.pl.
        $force = api_optional_auth() !== null;
        set_time_limit(300);
        $result = with_file_lock(LT_CACHE_FILE . '.lock', fn() => ltcache_refresh(30, $force));
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
