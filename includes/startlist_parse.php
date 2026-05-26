<?php
/**
 * Start list import from livetiming.pl PDF.
 * Downloads the start list PDF, extracts text, parses into competition JSON structure.
 */
require_once __DIR__ . '/pdf_extract.php';

/**
 * Fetches the contest page HTML and finds the start list PDF link.
 * Returns the resolved absolute URL or a fallback.
 */
function resolve_startlist_pdf_url(string $contest_url): string {
    $base = rtrim($contest_url, '/');
    $ctx  = stream_context_create([
        'http' => [
            'header'  => "User-Agent: Mozilla/5.0 SwimResults/1.0\r\n",
            'timeout' => 10,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $html = @file_get_contents($base, false, $ctx);
    if ($html !== false && $html !== '') {
        if (preg_match_all('/href=["\']([^"\']*\.pdf)["\']/', $html, $m)) {
            // Prefer links that look like start lists
            foreach ($m[1] as $href) {
                if (preg_match('/start|lista|list/i', $href)) {
                    return str_starts_with($href, 'http') ? $href : 'https://livetiming.pl' . $href;
                }
            }
            // Fall back to first PDF link
            $href = $m[1][0];
            return str_starts_with($href, 'http') ? $href : 'https://livetiming.pl' . $href;
        }
    }
    return $base . '/startlist.pdf';
}

function sl_int_to_roman(int $n): string {
    static $map = ['I','II','III','IV','V','VI','VII','VIII','IX','X'];
    return $map[$n - 1] ?? (string)$n;
}

function sl_normalize_date_str(string $raw): string {
    $raw = trim($raw);
    if (preg_match('/^(\d{1,2})[.\/](\d{1,2})[.\/](\d{4})$/', $raw, $m)) {
        return (int)$m[1] . '/' . (int)$m[2] . '/' . $m[3];
    }
    return $raw;
}

function sl_normalize_time(string $t): string {
    $t = trim($t);
    if (preg_match('/^\d+:\d{2}\.\d{2}$/', $t)) return $t;
    if (preg_match('/^\d{2}\.\d{2}$/', $t)) return $t;
    // Some exports use colons throughout: "5:23:71" → "5:23.71"
    if (preg_match('/^(\d+):(\d{2}):(\d{2})$/', $t, $m)) return $m[1] . ':' . $m[2] . '.' . $m[3];
    return $t;
}

/**
 * Normalises event description from PDF to "Kobiet, 400m zmienny" format.
 */
function sl_normalize_event_name(string $raw): string {
    $raw = trim($raw);

    if (preg_match('/^(Women|Kobiet[ya]?|Dziewcz)/iu', $raw))      $gender = 'Kobiet';
    elseif (preg_match('/^(Men|Mężczyzn|Chłop)/iu', $raw))         $gender = 'Mężczyzn';
    elseif (preg_match('/^(Mixed|Mieszane)/iu', $raw))              $gender = 'Mixed';
    else {
        $first = preg_split('/\s+/', $raw);
        $gender = $first[0] ?? $raw;
    }

    preg_match('/(\d+)\s*m/i', $raw, $dm);
    $dist = $dm[1] ?? '';

    $stroke = 'dowolny';
    foreach ([
        '/medley|zmiennym|zmienny/i'           => 'zmienny',
        '/freestyle|dowolnym|dowolny/i'         => 'dowolny',
        '/backstroke|grzbietowym|grzbietowy/i'  => 'grzbietowy',
        '/breaststroke|klasycznym|klasyczny/i'  => 'klasyczny',
        '/butterfly|motylkowym|motylkowy/i'     => 'motylkowy',
    ] as $pat => $pl) {
        if (preg_match($pat, $raw)) { $stroke = $pl; break; }
    }

    return $dist ? ($gender . ', ' . $dist . 'm ' . $stroke) : ($gender . ', ' . $raw);
}

/**
 * Converts "WĄS AMELIA" → "Wąs Amelia".
 */
function sl_titlecase(string $name): string {
    $words = preg_split('/\s+/', trim($name));
    $out   = [];
    foreach ($words as $w) {
        if ($w === '') continue;
        $out[] = mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8')
               . mb_strtolower(mb_substr($w, 1, null, 'UTF-8'), 'UTF-8');
    }
    return implode(' ', $out);
}

/**
 * Strips Polish diacritics for fuzzy club name matching.
 */
function sl_ascii(string $s): string {
    static $map = [
        'ą'=>'a','ć'=>'c','ę'=>'e','ł'=>'l','ń'=>'n','ó'=>'o','ś'=>'s','ź'=>'z','ż'=>'z',
        'Ą'=>'a','Ć'=>'c','Ę'=>'e','Ł'=>'l','Ń'=>'n','Ó'=>'o','Ś'=>'s','Ź'=>'z','Ż'=>'z',
    ];
    return mb_strtolower(strtr($s, $map), 'UTF-8');
}

function sl_club_matches(string $filter, string $club): bool {
    $f = sl_ascii($filter);
    $c = sl_ascii($club);
    return str_contains($c, $f) || str_contains($f, $c);
}

/**
 * Tries to parse a single athlete entry line from a PDF start list.
 * Returns a start entry array or null if the line doesn't match.
 *
 * Expected layout (pdftotext -layout):
 *   "  4   WAS AMELIA         10  OLI BRZESKO    5:23.71"
 *   leading spaces + lane(1-10) + 2+spaces + NAME + spaces + YOB + spaces + CLUB + optional time
 */
function sl_parse_entry_line(string $line, array $event, array $heat, string $club_filter): ?array {
    $trimmed = trim($line);
    if (strlen($trimmed) < 8) return null;

    // Must begin with (optional spaces +) 1-2 digit lane number + ≥2 spaces
    if (!preg_match('/^\s{0,6}(\d{1,2})\s{2,}/u', $line, $lm)) return null;
    $tor = (int)$lm[1];
    if ($tor < 1 || $tor > 10) return null;

    // Rest of line after lane
    $rest = substr($line, (int)strpos($line, $lm[0]) + strlen($lm[0]));

    // Name: uppercase letters (incl. Polish), hyphens, followed by ≥2 spaces
    if (!preg_match('/^([A-ZŻŹĆĄŚĘŁÓŃ][A-ZŻŹĆĄŚĘŁÓŃ\-\s]+?)\s{2,}/u', $rest, $nm)) return null;
    $raw_name = trim($nm[1]);
    if (strlen($raw_name) < 3) return null;

    $after_name = substr($rest, strlen($nm[0]));

    // Birth year: 2 or 4 digits
    if (!preg_match('/^(\d{2,4})\s+/u', $after_name, $ym)) return null;
    $after_yob = ltrim(substr($after_name, strlen($ym[0])));

    // Club + optional time at end
    $czas     = null;
    $club_raw = '';
    if (preg_match('/^(.*?)\s{2,}(NT|\d[\d:.]+)\s*$/u', $after_yob, $cm)) {
        $club_raw = trim($cm[1]);
        $t_raw    = trim($cm[2]);
        $czas     = ($t_raw === 'NT') ? null : sl_normalize_time($t_raw);
    } else {
        $club_raw = trim($after_yob);
    }

    if ($club_raw === '') return null;

    // Club filter
    if (!sl_club_matches($club_filter, $club_raw)) return null;

    $entry = [
        'imie'           => sl_titlecase($raw_name),
        'konkurencja'    => $event['name'],
        'konkurencja_nr' => $event['nr'],
        'seria'          => $heat['nr'] . ' z ' . $heat['total'],
        'godz'           => $heat['godz'],
        'tor'            => $tor,
    ];
    if ($czas !== null) $entry['czas'] = $czas;

    return $entry;
}

/**
 * Moves buffered heat entries into the session's starty array.
 */
function sl_flush_heat(array &$session, ?array $heat): void {
    if ($heat === null || empty($heat['entries'])) return;
    foreach ($heat['entries'] as $e) {
        $session['starty'][] = $e;
    }
}

/**
 * Extracts competition metadata (name, venue, date) from the first lines of extracted text.
 */
function sl_extract_metadata(array &$zawody, array $first_lines): void {
    $skip_kw = ['start list','lista startowa','startliste','page ','strona ','seite ',
                'session','sesja','event ','wydarzenie '];
    foreach ($first_lines as $line) {
        if (strlen($line) < 4) continue;
        $lc = mb_strtolower($line, 'UTF-8');
        foreach ($skip_kw as $kw) {
            if (str_starts_with($lc, $kw) || str_contains($lc, $kw)) continue 2;
        }
        // "Venue, 10.05.2026" or "Venue, 10/05/2026"
        if (preg_match('/,\s*(\d{1,2}[.\/]\d{1,2}[.\/]\d{4})/', $line, $dm)) {
            if ($zawody['data'] === '') {
                $zawody['data'] = sl_normalize_date_str($dm[1]);
            }
            $venue = trim(substr($line, 0, (int)strpos($line, ',')));
            if ($venue && $zawody['miejsce'] === '') {
                $zawody['miejsce'] = $venue;
            }
            continue;
        }
        if ($zawody['nazwa'] === '' && strlen($line) > 5) {
            $zawody['nazwa'] = $line;
        }
    }

    // Derive data from blocks if still empty
    if ($zawody['data'] === '' && !empty($zawody['bloki'])) {
        $dates = array_filter(array_column($zawody['bloki'], 'data'));
        if ($dates) $zawody['data'] = reset($dates);
    }
}

/**
 * Main parser: converts extracted PDF text into a competition bloki structure.
 * Filters athletes by club name (case-insensitive, diacritic-insensitive partial match).
 */
function parse_startlist_text(string $text, string $club_filter, string $basen): array {
    $lines = preg_split('/\r?\n/', $text);

    $zawody = [
        'nazwa'   => '',
        'miejsce' => '',
        'data'    => '',
        'klub'    => $club_filter,
        'basen'   => $basen,
        'bloki'   => [],
    ];

    $sessions    = [];
    $cur_session = null;
    $cur_event   = null;
    $cur_heat    = null;
    $session_idx = 0;
    $first_lines = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed !== '' && count($first_lines) < 25) {
            $first_lines[] = $trimmed;
        }

        // --- Session / Block ---
        if (preg_match('/^(Sesja|Session)\s+([IVX]+|\d+)/iu', $trimmed)) {
            sl_flush_heat($cur_session ?? ['starty' => []], $cur_heat);
            if ($cur_session !== null) $sessions[] = $cur_session;
            $session_idx++;
            $cur_session = [
                'blok'       => sl_int_to_roman($session_idx),
                'data'       => '',
                'godz_start' => '',
                'starty'     => [],
            ];
            $cur_event = null;
            $cur_heat  = null;
            if (preg_match('/(\d{1,2}[.\/]\d{1,2}[.\/]\d{4})/', $trimmed, $dm)) {
                $cur_session['data'] = sl_normalize_date_str($dm[1]);
            }
            if (preg_match('/\b(\d{1,2}:\d{2})\s*$/', $trimmed, $hm)) {
                $cur_session['godz_start'] = $hm[1];
            }
            continue;
        }

        // --- Event ---
        if (preg_match('/^(Event|Wydarzenie)\s+(\d+)\s+(.*)/iu', $trimmed, $m)) {
            if ($cur_session && $cur_heat) {
                sl_flush_heat($cur_session, $cur_heat);
                $cur_heat = null;
            }
            $cur_event = [
                'nr'   => (int)$m[2],
                'name' => sl_normalize_event_name(trim($m[3])),
            ];
            continue;
        }

        // --- Heat ---
        if (preg_match('/^(Heat|Seria)\s+(\d+)\s+(of|z)\s+(\d+)/iu', $trimmed, $m)) {
            if ($cur_session && $cur_heat) {
                sl_flush_heat($cur_session, $cur_heat);
            }
            if ($cur_session === null) {
                $session_idx++;
                $cur_session = [
                    'blok'       => sl_int_to_roman($session_idx),
                    'data'       => '',
                    'godz_start' => '',
                    'starty'     => [],
                ];
            }
            $heat_time = '';
            if (preg_match('/\b(\d{1,2}:\d{2})\s*$/', $trimmed, $hm)) {
                $heat_time = $hm[1];
                if ($cur_session['godz_start'] === '') $cur_session['godz_start'] = $heat_time;
            }
            $cur_heat = [
                'nr'      => (int)$m[2],
                'total'   => (int)$m[4],
                'godz'    => $heat_time,
                'entries' => [],
            ];
            continue;
        }

        // --- Athlete entry line ---
        if ($cur_heat !== null && $cur_event !== null) {
            $entry = sl_parse_entry_line($line, $cur_event, $cur_heat, $club_filter);
            if ($entry !== null) {
                $cur_heat['entries'][] = $entry;
            }
        }
    }

    // Flush last session
    if ($cur_session !== null) {
        sl_flush_heat($cur_session, $cur_heat);
        $sessions[] = $cur_session;
    }

    $zawody['bloki'] = array_values(array_filter($sessions, fn($s) => !empty($s['starty'])));

    if (empty($zawody['bloki'])) {
        return parse_startlist_text_vertical($text, $club_filter, $basen);
    }

    sl_extract_metadata($zawody, $first_lines);

    return $zawody;
}

/**
 * Parser for vertical-format PDF text produced by the PHP fallback extractor.
 * Each entry field (lane, name, yob, club, time) is on its own line.
 */
function parse_startlist_text_vertical(string $text, string $club_filter, string $basen): array {
    $lines = preg_split('/\r?\n/', $text);
    $n     = count($lines);

    $zawody = [
        'nazwa'   => '',
        'miejsce' => '',
        'data'    => '',
        'klub'    => $club_filter,
        'basen'   => $basen,
        'bloki'   => [],
    ];

    $sessions    = [];
    $cur_session = null;
    $cur_event   = null;
    $cur_heat    = null;
    $session_idx = 0;
    $first_lines = [];

    $state  = 'IDLE'; // IDLE | LANE | NAME | YOB | CLUB
    $e_lane = 0;
    $e_name = '';
    $e_yob  = '';
    $e_club = '';

    for ($i = 0; $i < $n; $i++) {
        $trimmed = trim($lines[$i]);

        if ($trimmed !== '' && count($first_lines) < 25) {
            $first_lines[] = $trimmed;
        }

        // ── Session ──────────────────────────────────────────────────────────
        if (preg_match('/^(Sesja|Session)\s+([IVX]+|\d+)/iu', $trimmed)) {
            $state = 'IDLE';
            if ($cur_session !== null) {
                sl_flush_heat($cur_session, $cur_heat);
                $sessions[] = $cur_session;
            }
            $session_idx++;
            $cur_session = ['blok' => sl_int_to_roman($session_idx), 'data' => '', 'godz_start' => '', 'starty' => []];
            $cur_event = $cur_heat = null;
            if (preg_match('/(\d{1,2}[.\/]\d{1,2}[.\/]\d{4})/', $trimmed, $dm)) {
                $cur_session['data'] = sl_normalize_date_str($dm[1]);
            }
            continue;
        }

        // ── Event ─────────────────────────────────────────────────────────────
        // "Konkurencja 2, Kobiet, 100m dowolny"  OR  "Konkurencja 1" + desc on next line
        if (preg_match('/^Konkurencja\s+(\d+)(?:[,\s]+(.+))?/iu', $trimmed, $m)) {
            $state      = 'IDLE';
            $event_nr   = (int)$m[1];
            $event_desc = trim($m[2] ?? '');
            if ($event_desc === '') {
                // Description may span 2 lines (gender on one, distance+stroke on next)
                $parts = [];
                for ($j = $i + 1; $j < min($n, $i + 8); $j++) {
                    $next = trim($lines[$j]);
                    if ($next === '') continue;
                    if (preg_match('/^(Seria|Heat|Sesja|Session|Konkurencja|Lista)\b/iu', $next)) break;
                    $parts[] = ltrim($next, ', ');
                    $i = $j;
                    if (count($parts) >= 2) break; // gender + distance/stroke is enough
                }
                $event_desc = trim(implode(', ', array_filter($parts)));
            }
            $cur_event = ['nr' => $event_nr, 'name' => sl_normalize_event_name($event_desc)];
            continue;
        }

        // ── Heat ──────────────────────────────────────────────────────────────
        if (preg_match('/^(Seria|Heat)\s+(\d+)\s+(z|of)\s+(\d+)/iu', $trimmed, $m)) {
            $state = 'IDLE';
            if ($cur_session !== null && $cur_heat !== null) {
                sl_flush_heat($cur_session, $cur_heat);
            }
            if ($cur_session === null) {
                $session_idx++;
                $cur_session = ['blok' => sl_int_to_roman($session_idx), 'data' => '', 'godz_start' => '', 'starty' => []];
            }
            $heat_time = '';
            if (preg_match('/\b(\d{1,2}:\d{2})\s*$/', $trimmed, $hm)) {
                $heat_time = $hm[1];
                if ($cur_session['godz_start'] === '') $cur_session['godz_start'] = $heat_time;
            }
            $cur_heat = ['nr' => (int)$m[2], 'total' => (int)$m[4], 'godz' => $heat_time, 'entries' => []];
            continue;
        }

        if ($cur_heat === null || $cur_event === null) {
            $state = 'IDLE';
            continue;
        }

        // ── Entry state machine ───────────────────────────────────────────────
        switch ($state) {
            case 'IDLE':
                if (preg_match('/^\d{1,2}$/', $trimmed) && (int)$trimmed >= 1 && (int)$trimmed <= 10) {
                    $e_lane = (int)$trimmed;
                    $e_name = $e_yob = $e_club = '';
                    $state  = 'LANE';
                }
                break;

            case 'LANE':
                if ($trimmed === '') {
                    $e_name = '';
                    $state  = 'NAME';
                } elseif (preg_match('/^\d{2,4}$/', $trimmed)) {
                    // no name line — this is already the YOB
                    $e_yob = $trimmed;
                    $state = 'YOB';
                } else {
                    $e_name = $trimmed;
                    $state  = 'NAME';
                }
                break;

            case 'NAME':
                if (preg_match('/^\d{2,4}$/', $trimmed)) {
                    $e_yob = $trimmed;
                    $state = 'YOB';
                } elseif ($trimmed !== '') {
                    // unexpected text (e.g. relay team-number line) — reset
                    $state = 'IDLE';
                    $i--;
                }
                // empty line: keep waiting for YOB
                break;

            case 'YOB':
                // next line is always the club (may be empty)
                $e_club = $trimmed;
                $state  = 'CLUB';
                break;

            case 'CLUB':
                if ($trimmed === '.') {
                    // extraneous dot field present in some exports — skip
                    break;
                }
                $is_time = $trimmed === 'NT'
                    || preg_match('/^\d{1,2}:\d{2}\.\d{2}$/', $trimmed)
                    || preg_match('/^\d{2}\.\d{2}$/', $trimmed);

                if ($is_time) {
                    if ($e_club !== '' && sl_club_matches($club_filter, $e_club)) {
                        $entry = [
                            'imie'           => sl_titlecase($e_name),
                            'konkurencja'    => $cur_event['name'],
                            'konkurencja_nr' => $cur_event['nr'],
                            'seria'          => $cur_heat['nr'] . ' z ' . $cur_heat['total'],
                            'godz'           => $cur_heat['godz'],
                            'tor'            => $e_lane,
                        ];
                        if ($trimmed !== 'NT') {
                            $entry['czas'] = sl_normalize_time($trimmed);
                        }
                        $cur_heat['entries'][] = $entry;
                    }
                    $state = 'IDLE';
                } else {
                    // not a time — reset and re-process this line
                    $state = 'IDLE';
                    $i--;
                }
                break;
        }
    }

    if ($cur_session !== null) {
        sl_flush_heat($cur_session, $cur_heat);
        $sessions[] = $cur_session;
    }

    $zawody['bloki'] = array_values(array_filter($sessions, fn($s) => !empty($s['starty'])));
    sl_extract_metadata($zawody, $first_lines);

    return $zawody;
}

/**
 * Main entry point: resolves PDF URL, downloads, extracts text, parses, and returns result.
 */
function build_startlist_from_pdf(string $contest_url, string $club, string $basen = '25m'): array {
    if (!filter_var($contest_url, FILTER_VALIDATE_URL)) {
        return ['ok' => false, 'error' => 'Nieprawidłowy URL zawodów.'];
    }

    $pdf_url = preg_match('/\.pdf(\?.*)?$/i', $contest_url)
        ? $contest_url
        : resolve_startlist_pdf_url($contest_url);

    $pdf_data = pdf_download($pdf_url);
    if ($pdf_data === false || strlen((string)$pdf_data) < 100) {
        return [
            'ok'      => false,
            'error'   => 'Nie można pobrać listy startowej (PDF). Sprawdź czy lista jest już dostępna.',
            'pdf_url' => $pdf_url,
        ];
    }
    if (stripos(trim((string)$pdf_data), '<!') === 0 || stripos(trim((string)$pdf_data), '<html') === 0) {
        return [
            'ok'      => false,
            'error'   => 'Lista startowa jeszcze nie jest opublikowana (strona zwróciła HTML).',
            'pdf_url' => $pdf_url,
        ];
    }

    $text = pdf_extract_text((string)$pdf_data);
    if (strlen(trim($text)) < 50) {
        return [
            'ok'       => false,
            'error'    => 'Nie udało się odczytać tekstu z PDF.',
            'raw_text' => $text,
            'pdf_url'  => $pdf_url,
        ];
    }

    $zawody = parse_startlist_text($text, $club, $basen);
    $starts = array_sum(array_map(fn($b) => count($b['starty']), $zawody['bloki']));
    $athletes = [];
    foreach ($zawody['bloki'] as $b) {
        foreach ($b['starty'] as $s) {
            $athletes[$s['imie']] = true;
        }
    }

    return [
        'ok'       => true,
        'zawody'   => $zawody,
        'raw_text' => $text,
        'pdf_url'  => $pdf_url,
        'starts'   => $starts,
        'athletes' => count($athletes),
        'blocks'   => count($zawody['bloki']),
    ];
}
