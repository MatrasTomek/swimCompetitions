<?php
/**
 * Athlete profile management functions.
 * Each athlete = one JSON file in ZAWODNICY_DIR.
 */

function athlete_slug(string $imie): string {
    return slugify($imie);
}

function athlete_path(string $imie): string {
    return ZAWODNICY_DIR . '/' . athlete_slug($imie) . '.json';
}

/**
 * Splits "Wąs Amelia" → ['Wąs', 'Amelia']
 */
function split_imie_nazwisko(string $imie): array {
    $parts = explode(' ', trim($imie), 2);
    return [
        'nazwisko' => $parts[0] ?? '',
        'imie'     => $parts[1] ?? '',
    ];
}

function load_athlete(string $imie): array {
    $path = athlete_path($imie);
    if (!file_exists($path)) return [];
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function save_athlete(array $data, string $imie): bool {
    if (!is_dir(ZAWODNICY_DIR)) {
        mkdir(ZAWODNICY_DIR, 0755, true);
    }
    return write_json_atomic(athlete_path($imie), $data);
}

/**
 * Returns an athlete profile or creates an empty skeleton.
 */
function load_or_create_athlete(string $imie, ?int $rok_urodzenia, string $klub): array {
    $data = load_athlete($imie);
    if (!empty($data)) {
        // Fill in birth year if it was missing before
        if ($rok_urodzenia && empty($data['rok_urodzenia'])) {
            $data['rok_urodzenia'] = $rok_urodzenia;
            save_athlete($data, $imie);
        }
        return $data;
    }
    $parts = split_imie_nazwisko($imie);
    $data = [
        'imie'          => $parts['imie'],
        'nazwisko'      => $parts['nazwisko'],
        'rok_urodzenia' => $rok_urodzenia,
        'klub'          => $klub,
        'starty'        => [],
    ];
    save_athlete($data, $imie);
    return $data;
}

/**
 * Saves a race result to the athlete profile.
 * Does not duplicate (identified by competition+date+event_nr).
 * Returns false if the write failed (e.g. invalid UTF-8 in the profile).
 */
function save_athlete_result(string $imie, array $start_data, array $zawody_meta): bool {
    return with_file_lock(athlete_path($imie) . '.lock', function () use ($imie, $start_data, $zawody_meta) {
        $rok_ur = isset($start_data['rok_urodzenia']) ? (int)$start_data['rok_urodzenia'] : null;
        $klub   = $zawody_meta['klub'] ?? '';

        $athlete = load_or_create_athlete($imie, $rok_ur, $klub);

        $nowy_start = [
            'zawody'        => $zawody_meta['nazwa']   ?? '',
            'data'          => $start_data['data']      ?? '',
            'miejscowosc'   => $zawody_meta['miejsce']  ?? '',
            'basen'         => $zawody_meta['basen']    ?? '50m',
            'konkurencja_nr'=> (int)($start_data['konkurencja_nr'] ?? 0),
            'dystans'       => $start_data['dystans']   ?? '',
            'styl'          => $start_data['styl']      ?? '',
            'plec'          => $start_data['plec']      ?? '',
            'tor'           => (int)($start_data['tor'] ?? 0),
            'czas'          => $start_data['czas_result'] ?? '',
            'punkty'        => $start_data['punkty']    ?? null,
            'timestamp_pobrania' => date('c'),
        ];

        // Look for an existing entry by competition+date+event_nr
        $found = false;
        foreach ($athlete['starty'] as &$s) {
            if (
                $s['zawody']         === $nowy_start['zawody'] &&
                $s['data']           === $nowy_start['data']   &&
                $s['konkurencja_nr'] === $nowy_start['konkurencja_nr']
            ) {
                $s = $nowy_start;
                $found = true;
                break;
            }
        }
        unset($s);

        if (!$found) {
            $athlete['starty'][] = $nowy_start;
        }

        return save_athlete($athlete, $imie);
    });
}
