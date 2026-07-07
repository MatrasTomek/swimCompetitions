<?php
/**
 * livetiming.pl competition list scraper + local cache.
 * Cache file: livetiming_cache.json in the project root.
 */

define('LT_CACHE_FILE',    __DIR__ . '/../livetiming_cache.json');
define('LT_CACHE_TTL',     86400);   // 24 h
define('LT_SCRAPE_TIMEOUT', 10);

/**
 * Scrapes one category page and returns an array of competition entries.
 * Each entry: {uuid, name, date, city, category}
 */
function ltcache_scrape_page(string $category, int $page): array {
    $url = "https://livetiming.pl/contests/{$category}/{$page}";
    $ctx = stream_context_create([
        'http' => [
            'header'  => "User-Agent: Mozilla/5.0 SwimResults/1.0\r\n",
            'timeout' => LT_SCRAPE_TIMEOUT,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $html = @file_get_contents($url, false, $ctx);
    if ($html === false || strlen($html) < 500) return [];

    $competitions = [];

    // Each competition block: href="/contest/{UUID}" ... <h3>NAME</h3> ... <span class="red">DATE</span> ... column-center...>CITY</div>
    $pattern = '/href="\/contest\/([0-9a-f\-]{36})"[^>]*>\s*<h3[^>]*>(.*?)<\/h3>.*?' .
               '<span class="red"[^>]*>([\d\/]+)<\/span>.*?' .
               'column-center[^>]*>(.*?)<\/div>/s';

    if (!preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) return [];

    foreach ($matches as $m) {
        $uuid  = trim($m[1]);
        $name  = html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $date  = trim($m[3]);
        $city  = html_entity_decode(strip_tags($m[4]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($uuid === '' || $name === '') continue;

        $competitions[] = [
            'uuid'     => $uuid,
            'name'     => trim($name),
            'date'     => trim($date),
            'city'     => trim($city),
            'category' => $category,
        ];
    }

    return $competitions;
}

/**
 * Detects whether a listing page is the last page (no competition links found).
 */
function ltcache_is_last_page(string $category, int $page): bool {
    $url = "https://livetiming.pl/contests/{$category}/{$page}";
    $ctx = stream_context_create([
        'http' => [
            'header'  => "User-Agent: Mozilla/5.0 SwimResults/1.0\r\n",
            'timeout' => LT_SCRAPE_TIMEOUT,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $html = @file_get_contents($url, false, $ctx);
    if ($html === false || strlen($html) < 500) return true;
    return !preg_match('/href="\/contest\/[0-9a-f\-]{36}"/', $html);
}

/**
 * Builds/refreshes the cache by scraping livetiming.pl.
 *
 * @param int $max_pages_per_category  Max pages to scrape per category (0 = unlimited)
 * @param bool $force  Skip TTL check and always refresh
 * @return array {ok, count, duration, skipped}
 */
function ltcache_refresh(int $max_pages_per_category = 30, bool $force = false): array {
    if (!$force && file_exists(LT_CACHE_FILE)) {
        $age = time() - filemtime(LT_CACHE_FILE);
        if ($age < LT_CACHE_TTL) {
            return ['ok' => true, 'skipped' => true, 'reason' => 'Cache is fresh (' . round($age / 3600, 1) . 'h old)'];
        }
    }

    $categories   = ['regional', 'national', 'calendar', 'international'];
    $all           = [];
    $seen_uuids    = [];
    $start         = microtime(true);

    foreach ($categories as $cat) {
        for ($page = 1; $max_pages_per_category === 0 || $page <= $max_pages_per_category; $page++) {
            $entries = ltcache_scrape_page($cat, $page);
            if (empty($entries)) break; // no more results on this page → stop

            foreach ($entries as $e) {
                if (isset($seen_uuids[$e['uuid']])) continue;
                $seen_uuids[$e['uuid']] = true;
                $all[] = $e;
            }
        }
    }

    $cache = [
        'updated_at'   => date('c'),
        'competitions' => $all,
    ];
    file_put_contents(LT_CACHE_FILE, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    return [
        'ok'       => true,
        'skipped'  => false,
        'count'    => count($all),
        'duration' => round(microtime(true) - $start, 1),
    ];
}

/**
 * Returns cache metadata without loading all competitions.
 */
function ltcache_status(): array {
    if (!file_exists(LT_CACHE_FILE)) {
        return ['exists' => false];
    }
    $age  = time() - filemtime(LT_CACHE_FILE);
    $data = json_decode(file_get_contents(LT_CACHE_FILE), true);
    return [
        'exists'     => true,
        'updated_at' => $data['updated_at'] ?? '',
        'count'      => count($data['competitions'] ?? []),
        'age_hours'  => round($age / 3600, 1),
        'is_fresh'   => $age < LT_CACHE_TTL,
    ];
}

/**
 * Searches the local cache for competitions matching the query.
 * Normalises both query and fields to ASCII lowercase for comparison.
 *
 * @return array  Up to 20 matches: [{uuid, name, date, city, category}]
 */
function ltcache_search(string $q, int $limit = 20): array {
    if (!file_exists(LT_CACHE_FILE)) return [];

    $q    = trim($q);
    if (strlen($q) < 2) return [];

    $norm = '_ltcache_normalize';

    $q_norm = $norm($q);
    $tokens = array_filter(explode(' ', $q_norm), fn($t) => strlen($t) >= 2);

    $data = json_decode(file_get_contents(LT_CACHE_FILE), true);
    if (empty($data['competitions'])) return [];

    $results = [];
    foreach ($data['competitions'] as $c) {
        $haystack = $norm($c['name'] . ' ' . $c['city'] . ' ' . $c['date']);

        $matched = 0;
        foreach ($tokens as $t) {
            if (str_contains($haystack, $t)) $matched++;
        }
        if ($matched === 0) continue;

        $results[] = ['score' => $matched, 'entry' => $c];
    }

    usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

    return array_column(array_slice($results, 0, $limit), 'entry');
}

function _ltcache_normalize(string $s): string {
    static $map = [
        'ą'=>'a','ć'=>'c','ę'=>'e','ł'=>'l','ń'=>'n','ó'=>'o','ś'=>'s','ź'=>'z','ż'=>'z',
        'Ą'=>'a','Ć'=>'c','Ę'=>'e','Ł'=>'l','Ń'=>'n','Ó'=>'o','Ś'=>'s','Ź'=>'z','Ż'=>'z',
    ];
    return mb_strtolower(strtr($s, $map), 'UTF-8');
}
