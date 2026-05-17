<?php
require_once __DIR__ . '/lenex_fetch.php';

function load_live_config(): array {
    if (!file_exists(LIVE_CONFIG_FILE)) return [];
    $data = json_decode(file_get_contents(LIVE_CONFIG_FILE), true);
    return is_array($data) ? $data : [];
}

function save_live_config(array $config): void {
    file_put_contents(
        LIVE_CONFIG_FILE,
        json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
}

/**
 * Derives the LENEX download URL from a livetiming.pl contest URL.
 * E.g. "https://livetiming.pl/contest/UUID" → "https://livetiming.pl/contest/UUID/results.lxf"
 */
function lenex_url_from_contest(string $contest_url): string {
    return rtrim($contest_url, '/') . '/results.lxf';
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
function fetch_and_apply_lenex(): array {
    $config = load_live_config();
    if (empty($config['contest_url']) || empty($config['json_file'])) {
        return ['updated' => 0, 'not_found' => 0, 'total' => 0, 'errors' => ['Brak konfiguracji — zapisz URL zawodów najpierw.']];
    }

    $zawody_path = safe_json_path($config['json_file'] . '.json');
    if (!$zawody_path) {
        return ['updated' => 0, 'not_found' => 0, 'total' => 0, 'errors' => ['Nieprawidłowy plik zawodów.']];
    }

    $zawody = json_decode(file_get_contents($zawody_path), true);
    if (!$zawody) {
        return ['updated' => 0, 'not_found' => 0, 'total' => 0, 'errors' => ['Błąd odczytu JSON zawodów.']];
    }

    $lxf_url = lenex_url_from_contest($config['contest_url']);
    $lenex   = lenex_download($lxf_url);
    if (!$lenex['ok']) {
        return ['updated' => 0, 'not_found' => 0, 'total' => 0, 'errors' => ['Błąd pobierania LENEX: ' . ($lenex['error'] ?? '')]];
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
            save_athlete_result($start['imie'], array_merge($start, $kp, [
                'data'          => $blok_data,
                'rok_urodzenia' => $result['rok_urodzenia'] ?? null,
            ]), $zawody_meta);

            $updated++;
        }
        unset($start);
    }
    unset($blok);

    file_put_contents(
        $zawody_path,
        json_encode($zawody, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );

    $config['ostatnia_aktualizacja'] = date('c');
    save_live_config($config);

    return ['updated' => $updated, 'not_found' => $not_found, 'total' => $total, 'errors' => []];
}
