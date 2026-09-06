<?php
require_once __DIR__ . '/lenex_fetch.php';

function load_live_config(): array {
    if (!file_exists(LIVE_CONFIG_FILE)) return [];
    $data = json_decode(file_get_contents(LIVE_CONFIG_FILE), true);
    return is_array($data) ? $data : [];
}

function save_live_config(array $config): bool {
    return write_json_atomic(LIVE_CONFIG_FILE, $config);
}

/**
 * Fetches the livetiming.pl contest page and extracts the .lxf download URL from its HTML.
 * Falls back to appending /results.lxf if extraction fails.
 * Returns ['url' => string, 'source' => 'page'|'fallback'|'blocked', 'error'? => string].
 */
function resolve_lenex_url(string $contest_url): array {
    if (!is_allowed_contest_host($contest_url)) {
        return ['url' => '', 'source' => 'blocked', 'error' => 'Niedozwolony host — dozwolone są tylko adresy livetiming.pl.'];
    }
    $ctx = stream_context_create([
        'http' => [
            'header'  => "User-Agent: Mozilla/5.0 SwimResults/1.0\r\n",
            'timeout' => 10,
        ],
    ]);
    $html = @file_get_contents(rtrim($contest_url, '/'), false, $ctx);
    if ($html !== false && $html !== '') {
        if (preg_match_all('/href=["\']([^"\']*\.lxf)["\']/', $html, $matches)) {
            foreach ($matches[1] as $href) {
                if (stripos($href, 'result') !== false) {
                    $url = (str_starts_with($href, 'http')) ? $href : 'https://livetiming.pl' . $href;
                    return ['url' => $url, 'source' => 'page'];
                }
            }
            // Any .lxf link is better than nothing
            $href = $matches[1][0];
            $url  = (str_starts_with($href, 'http')) ? $href : 'https://livetiming.pl' . $href;
            return ['url' => $url, 'source' => 'page'];
        }
    }
    return ['url' => rtrim($contest_url, '/') . '/results.lxf', 'source' => 'fallback'];
}

/**
 * Parses event name to extract plec/dystans/styl for saving athlete profiles.
 */
function parse_event_parts(string $k): array {
    $parts = array_map('trim', explode(',', $k, 3));
    $plec  = $parts[0] ?? '';
    $rest  = $parts[1] ?? '';
    preg_match('/(\d+m)\s+(.+)/', $rest, $m);
    return [
        'plec'    => $plec,
        'dystans' => $m[1] ?? $rest,
        'styl'    => $m[2] ?? '',
    ];
}

/**
 * Downloads LENEX from the configured contest URL and applies all available
 * results to the competition JSON. Always overwrites existing results with
 * the latest LENEX data.
 *
 * Returns ['updated' => N, 'not_found' => N, 'total' => N, 'errors' => [...]]
 */
function fetch_and_apply_lenex(string $contest_url = '', string $json_file = ''): array {
    if ($contest_url === '' || $json_file === '') {
        $config = load_live_config();
        if ($contest_url === '') $contest_url = $config['contest_url'] ?? '';
        if ($json_file === '') $json_file = $config['json_file'] ?? '';
    }
    if (empty($contest_url) || empty($json_file)) {
        return ['updated' => 0, 'not_found' => 0, 'total' => 0, 'errors' => ['Brak konfiguracji — podaj URL zawodów.']];
    }

    if (!is_allowed_contest_host($contest_url)) {
        return ['updated' => 0, 'not_found' => 0, 'total' => 0, 'errors' => ['Niedozwolony host — dozwolone są tylko adresy livetiming.pl.']];
    }

    $zawody_path = safe_json_path($json_file . '.json');
    if (!$zawody_path) {
        return ['updated' => 0, 'not_found' => 0, 'total' => 0, 'errors' => ['Nieprawidłowy plik zawodów.']];
    }

    $resolved = resolve_lenex_url($contest_url);
    if (!empty($resolved['error'])) {
        return ['updated' => 0, 'not_found' => 0, 'total' => 0, 'errors' => [$resolved['error']]];
    }
    $lxf_url = $resolved['url'];
    $lenex   = lenex_download($lxf_url);
    if (!$lenex['ok']) {
        return ['updated' => 0, 'not_found' => 0, 'total' => 0, 'errors' => ['Błąd pobierania LENEX: ' . ($lenex['error'] ?? '')]];
    }

    return with_file_lock($zawody_path . '.lock', function () use ($zawody_path, $lenex) {
        $zawody = json_decode(file_get_contents($zawody_path), true);
        if (!$zawody) {
            return ['updated' => 0, 'not_found' => 0, 'total' => 0, 'errors' => ['Błąd odczytu JSON zawodów.']];
        }

        $zawody_meta = [
            'nazwa'   => $zawody['nazwa']   ?? '',
            'miejsce' => $zawody['miejsce'] ?? '',
            'klub'    => $zawody['klub']    ?? '',
            'basen'   => $zawody['basen']   ?? '50m',
        ];

        $updated   = 0;
        $not_found = 0;
        $total     = 0;
        $errors    = [];

        foreach ($zawody['bloki'] as &$blok) {
            $blok_data = $blok['data'] ?? '';
            foreach ($blok['starty'] as &$start) {
                $total++;
                $nr     = (int)($start['konkurencja_nr'] ?? 0);
                $result = lenex_find_athlete($lenex, $nr, $start['imie']);

                if (!$result['found']) {
                    $not_found++;
                    continue;
                }

                $start['czas_result']       = $result['czas'];
                $start['punkty']            = $result['punkty'] ?? null;
                $start['result_fetched']    = true;
                $start['result_fetched_at'] = date('c');

                $kp = parse_event_parts($start['konkurencja'] ?? '');
                $saved = save_athlete_result($start['imie'], array_merge($start, $kp, [
                    'data'          => $blok_data,
                    'rok_urodzenia' => $result['rok_urodzenia'] ?? null,
                ]), $zawody_meta);

                if (!$saved) {
                    $errors[] = 'Nie udało się zapisać profilu zawodnika: ' . $start['imie'];
                }

                $updated++;
            }
            unset($start);
        }
        unset($blok);

        if (!write_json_atomic($zawody_path, $zawody)) {
            $errors[] = 'Nie udało się zapisać pliku zawodów.';
        }

        return ['updated' => $updated, 'not_found' => $not_found, 'total' => $total, 'errors' => $errors];
    });
}
