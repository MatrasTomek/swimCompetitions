<?php
/**
 * livetiming.pl contest cache: a scrape that brings nothing (livetiming.pl unreachable from the
 * hosting) must not leave an empty cache that counts as fresh — that hid the contest search for 24 h.
 * No network: the scraper is injected.
 */
require __DIR__ . '/_assert.php';

$dir = sys_get_temp_dir() . '/ltcache_test_' . bin2hex(random_bytes(4));
mkdir($dir);
define('LT_CACHE_FILE', $dir . '/livetiming_cache.json');

require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/livetiming_cache.php';

$year = (int)date('Y');
$contest = ['uuid' => 'e90a051a-e507-45d5-a67e-e8b412394eb0', 'name' => 'Zawody', 'date' => "19/11/$year", 'city' => 'Wrocław', 'category' => 'regional'];

// 1. Every page fails → nothing written, refresh reports failure
$failing = fn(string $cat, int $page): array => [];
$r = ltcache_refresh(30, true, $failing);
check('failed scrape: ok=false', $r['ok'], false);
check('failed scrape: no cache file', file_exists(LT_CACHE_FILE), false);

// 2. A failed scrape keeps the previous, non-empty cache
write_json_atomic(LT_CACHE_FILE, ['updated_at' => date('c'), 'year' => $year, 'competitions' => [$contest]]);
$r = ltcache_refresh(30, true, $failing);
check('failed scrape keeps old cache: ok=false', $r['ok'], false);
check('failed scrape keeps old cache: still 1 contest', count(ltcache_load()), 1);

// 3. An empty cache already on disk (written by the old code) is not fresh → the SPA offers a refresh
write_json_atomic(LT_CACHE_FILE, ['updated_at' => date('c'), 'year' => $year, 'competitions' => []]);
check('empty cache is not fresh', ltcache_is_fresh(), false);
check('empty cache status: is_fresh=false', ltcache_status()['is_fresh'], false);

// 4. A working scrape writes the cache and it is fresh
$pages = fn(string $cat, int $page): array => ($cat === 'regional' && $page === 1) ? [$contest] : [];
$r = ltcache_refresh(30, true, $pages);
check('good scrape: ok', $r['ok'], true);
check('good scrape: count', $r['count'], 1);
check('good scrape: fresh', ltcache_is_fresh(), true);
check('good scrape: searchable', count(ltcache_search('wroclaw')), 1);

array_map('unlink', glob($dir . '/*'));
rmdir($dir);
finish();
