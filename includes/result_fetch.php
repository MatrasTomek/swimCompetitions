<?php
/**
 * Logic for fetching and saving results from livetiming.pl.
 */
require_once __DIR__ . '/pdf_extract.php';

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
 * Extracts the base PDF directory URL from the livetiming.pl index.html URL.
 * E.g. "https://live.livetiming.pl/zak/2026/05_10_oswiecim/index.html"
 *  → "https://live.livetiming.pl/zak/2026/05_10_oswiecim/"
 */
function get_base_pdf_url(string $index_url): string {
    return preg_replace('/[^\/]+$/', '', rtrim($index_url, '/') . '/');
}

/**
 * Parses block date and start time into a Unix timestamp.
 * Block date e.g. "9/5/2026", time e.g. "8:41"
 */
function parse_start_timestamp(string $blok_data, string $godz): int|false {
    // Normalize date: "9/5/2026" → "2026-05-09"
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $blok_data, $m)) {
        $normalized = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    } else {
        return false;
    }
    $dt = DateTime::createFromFormat('Y-m-d H:i', $normalized . ' ' . $godz);
    return $dt ? $dt->getTimestamp() : false;
}

/**
 * Extracts distance and stroke from the event name.
 * E.g. "Kobiet, 400m zmienny" → ['dystans' => '400m', 'styl' => 'zmienny', 'plec' => 'Kobiet']
 */
function parse_konkurencja(string $k): array {
    $parts = array_map('trim', explode(',', $k, 3));
    $plec  = $parts[0] ?? '';
    $rest  = $parts[1] ?? '';
    preg_match('/(\d+m)\s+(.+)/', $rest, $m);
    return [
        'plec'   => $plec,
        'dystans'=> $m[1] ?? $rest,
        'styl'   => $m[2] ?? '',
    ];
}

/**
 * Downloads a PDF and extracts the athlete result — pure PHP, no Python.
 */
function fetch_result_from_pdf(string $pdf_url, string $athlete_name): array {
    $pdf_data = pdf_download($pdf_url);
    if (!$pdf_data) {
        return ['found' => false, 'error' => 'Nie udało się pobrać PDF: ' . $pdf_url];
    }
    $text = pdf_extract_text($pdf_data);
    if (!$text) {
        return ['found' => false, 'error' => 'Brak tekstu w PDF'];
    }
    return pdf_find_athlete($text, $athlete_name);
}

/**
 * Iterates over all entries in the active competition and fetches results
 * for those that started more than 5 minutes ago and have no result yet.
 * Returns the number of updated entries.
 */
function process_pending_results(): int {
    $config = load_live_config();
    if (empty($config['aktywna']) || empty($config['url']) || empty($config['json_file'])) {
        return 0;
    }

    $zawody_path = safe_json_path($config['json_file'] . '.json');
    if (!$zawody_path) return 0;

    $zawody = json_decode(file_get_contents($zawody_path), true);
    if (!$zawody) return 0;

    $base_url   = get_base_pdf_url($config['url']);
    $now        = time();
    $updated    = 0;
    $zawody_meta = [
        'nazwa'   => $zawody['nazwa']   ?? '',
        'miejsce' => $zawody['miejsce'] ?? '',
        'klub'    => $zawody['klub']    ?? '',
        'basen'   => $zawody['basen']   ?? '50m',
    ];

    foreach ($zawody['bloki'] as &$blok) {
        $blok_data = $blok['data'] ?? '';
        foreach ($blok['starty'] as &$start) {
            if (!empty($start['result_fetched'])) continue;

            $ts = parse_start_timestamp($blok_data, $start['godz'] ?? '');
            if ($ts === false) continue;
            if ($now < $ts + RESULT_DELAY_SECONDS) continue;

            $nr      = (int)($start['konkurencja_nr'] ?? 0);
            $pdf_url = $base_url . 'ResultList_' . $nr . '.pdf';
            $result  = fetch_result_from_pdf($pdf_url, $start['imie']);

            if (empty($result['found'])) continue;

            $start['czas_result']      = $result['czas'];
            $start['punkty']           = $result['punkty'] ?? null;
            $start['result_fetched']   = true;
            $start['result_fetched_at'] = date('c');

            $kp = parse_konkurencja($start['konkurencja'] ?? '');
            $start_for_athlete = array_merge($start, $kp, [
                'data'          => $blok_data,
                'rok_urodzenia' => $result['rok_urodzenia'] ?? null,
            ]);
            save_athlete_result($start['imie'], $start_for_athlete, $zawody_meta);

            $updated++;
        }
        unset($start);
    }
    unset($blok);

    if ($updated > 0) {
        file_put_contents(
            $zawody_path,
            json_encode($zawody, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
        $config['ostatnia_aktualizacja'] = date('c');
        save_live_config($config);
    }

    return $updated;
}
