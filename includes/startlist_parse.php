<?php
/**
 * Start list import from livetiming.pl PDF.
 * Downloads the start list PDF, extracts text, parses into competition JSON structure.
 */
require_once __DIR__ . '/pdf_extract.php';

/**
 * Fetches the contest page and reads what the start list import needs from it:
 * the start list PDF link, pool length and the competition name/city/date as livetiming.pl lists them
 * (the same name the contest search shows — the PDF header often has a shortened one).
 *
 * @return array{pdf_url: string, basen: string, nazwa: string, miejsce: string, data: string}
 *         basen is '25m'/'50m'; any field is '' when the page doesn't say
 */
function resolve_contest_page(string $contest_url): array {
    $base = rtrim($contest_url, '/');
    $ctx  = stream_context_create([
        'http' => [
            'header'  => "User-Agent: Mozilla/5.0 SwimResults/1.0\r\n",
            'timeout' => 10,
        ],
    ]);
    $html = @file_get_contents($base, false, $ctx);
    if ($html === false) $html = '';

    $contest = sl_contest_page_data($html);
    if ($contest === null) {
        return [
            'pdf_url' => sl_find_startlist_pdf_url($html),
            'basen'   => sl_find_pool_length($html),
            'nazwa'   => '',
            'miejsce' => '',
            'data'    => '',
        ];
    }

    $length = (string)($contest['pool']['length'] ?? '');
    return [
        'pdf_url' => sl_contest_startlist_pdf($contest),
        'basen'   => preg_match('/^(25|50)\s*m$/i', trim($length), $lm) ? $lm[1] . 'm' : '',
        'nazwa'   => trim((string)($contest['name'] ?? '')),
        'miejsce' => trim((string)($contest['pool']['city'] ?? '')),
        'data'    => sl_format_date_range(
            sl_iso_to_date_str((string)($contest['startDate'] ?? '')),
            sl_iso_to_date_str((string)($contest['endDate'] ?? ''))
        ),
    ];
}

/**
 * Contest UUID from a livetiming.pl contest page URL ("https://livetiming.pl/contest/{uuid}"), or null
 * for anything else — the start list is imported only from the contest page, never from a pasted PDF link.
 */
function sl_contest_uuid(string $url): ?string {
    if (!preg_match('~^https?://(?:www\.)?livetiming\.pl/contest/([0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12})/?(?:[?#].*)?$~i',
                    trim($url), $m)) {
        return null;
    }
    return strtolower($m[1]);
}

/**
 * Reads the contest object livetiming.pl embeds in `window.__data`
 * (`"contests":{"<uuid>":{"name":…,"pool":{…},"startDate":…,"files":{…}}}`).
 * `window.__data` also holds JS functions, so only the contest object is cut out (by brace matching) and decoded.
 */
function sl_contest_page_data(string $html): ?array {
    if (!preg_match('/"[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}"\s*:\s*(\{)"name"\s*:/i', $html, $m, PREG_OFFSET_CAPTURE)) {
        return null;
    }
    $start  = $m[1][1];
    $len    = strlen($html);
    $depth  = 0;
    $in_str = false;
    for ($i = $start; $i < $len; $i++) {
        $ch = $html[$i];
        if ($in_str) {
            if ($ch === '\\')    $i++;
            elseif ($ch === '"') $in_str = false;
        } elseif ($ch === '"') {
            $in_str = true;
        } elseif ($ch === '{') {
            $depth++;
        } elseif ($ch === '}' && --$depth === 0) {
            $data = json_decode(substr($html, $start, $i - $start + 1), true);
            return is_array($data) ? $data : null;
        }
    }
    return null;
}

/**
 * Picks the start list PDF from the contest's file list (titled "Lista startowa").
 * Returns '' when the start list isn't published yet — other PDFs (komunikat, regulamin) aren't start lists.
 */
function sl_contest_startlist_pdf(array $contest): string {
    foreach (($contest['files'] ?? []) as $group) {
        if (!is_array($group)) continue;
        foreach ($group as $file) {
            $url   = (string)($file['url'] ?? '');
            $title = (string)($file['title'] ?? '');
            if (preg_match('/\.pdf(\?.*)?$/i', $url) && preg_match('/start/i', $title . ' ' . basename($url))) {
                return str_starts_with($url, 'http') ? $url : 'https://livetiming.pl' . $url;
            }
        }
    }
    return '';
}

/**
 * Reads the pool length from the contest page — the start list PDF itself doesn't state it.
 * livetiming.pl embeds it as `"pool":{"name":"Gdańsk - 25m",…,"length":"25m",…}`.
 * Returns '25m', '50m' or ''.
 */
function sl_find_pool_length(string $html): string {
    if (preg_match('/"pool"\s*:\s*\{[^{}]*"length"\s*:\s*"(25|50)\s*m"/i', $html, $m)) {
        return $m[1] . 'm';
    }
    return '';
}

/**
 * Finds the start list PDF link in the contest page HTML.
 * Returns the resolved absolute URL, or '' when the page has none.
 */
function sl_find_startlist_pdf_url(string $html): string {
    // Only links that look like start lists — another PDF (komunikat, regulamin) isn't one
    if (preg_match_all('/href=["\']([^"\']*\.pdf)["\']/', $html, $m)) {
        foreach ($m[1] as $href) {
            if (preg_match('/start|lista|list/i', basename($href))) {
                return str_starts_with($href, 'http') ? $href : 'https://livetiming.pl' . $href;
            }
        }
    }
    return '';
}

function sl_int_to_roman(int $n): string {
    static $map = ['I','II','III','IV','V','VI','VII','VIII','IX','X'];
    return $map[$n - 1] ?? (string)$n;
}

/**
 * A date as start lists print it: "20.09.2026", "20/9/2026" or ISO "2026-09-20"
 * (Splash uses the date format of the PC it runs on).
 */
const SL_DATE_RE = '\d{1,2}[.\/]\d{1,2}[.\/]\d{4}|\d{4}-\d{1,2}-\d{1,2}';

/** Any SL_DATE_RE date → "d/m/Y". */
function sl_normalize_date_str(string $raw): string {
    $raw = trim($raw);
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $raw, $m)) {
        return (int)$m[3] . '/' . (int)$m[2] . '/' . $m[1];
    }
    if (preg_match('/^(\d{1,2})[.\/](\d{1,2})[.\/](\d{4})$/', $raw, $m)) {
        return (int)$m[1] . '/' . (int)$m[2] . '/' . $m[3];
    }
    return $raw;
}

/** "2026-09-20" → "20/9/2026" ('' when not an ISO date). */
function sl_iso_to_date_str(string $iso): string {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', trim($iso), $m)) return '';
    return (int)$m[3] . '/' . (int)$m[2] . '/' . $m[1];
}

/**
 * Joins two "d/m/Y" dates into the competition date format:
 * "9/5/2026", "9-10/5/2026" (same month) or "30/4-2/5/2026" (month boundary).
 */
function sl_format_date_range(string $first, string $last): string {
    if ($last === '' || $last === $first) return $first;
    if ($first === '') return $last;
    $a = explode('/', $first);
    $b = explode('/', $last);
    if (count($a) !== 3 || count($b) !== 3) return $first;
    if ($a[2] !== $b[2]) return $first . '-' . $last;
    if ($a[1] !== $b[1]) return "{$a[0]}/{$a[1]}-{$b[0]}/{$b[1]}/{$b[2]}";
    return "{$a[0]}-{$b[0]}/{$b[1]}/{$b[2]}";
}

/** Timestamp of a "d/m/Y" date, for ordering block dates (0 when unparsable). */
function sl_date_str_ts(string $d): int {
    if (!preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $d, $m)) return 0;
    return (int)mktime(0, 0, 0, (int)$m[2], (int)$m[1], (int)$m[3]);
}

/**
 * Event date/time line printed under each event header by Splash Meet Manager:
 * "20.09.2026 - 9:30", "20/9/2026 - 13:45" or "2026-09-20 - 8:00". Returns [date "d/m/Y", time "H:MM"] or null.
 */
function sl_match_event_when(string $trimmed): ?array {
    if (!preg_match('/^(' . SL_DATE_RE . ')\s+-\s+(\d{1,2}:\d{2})\b/', $trimmed, $m)) return null;
    return [sl_normalize_date_str($m[1]), $m[2]];
}

/**
 * Starts a new block (session), moving the current one — with its buffered heat — into $sessions.
 * $nr is the session number printed in the PDF ("3 - BLOK III") — the start list may skip sessions
 * (e.g. finals, published later), so the block keeps that number instead of a running count.
 */
function sl_open_session(array &$sessions, ?array &$cur_session, ?array &$cur_heat, int &$session_idx,
                         string $data = '', string $godz = '', int $nr = 0): void {
    if ($cur_session !== null) {
        sl_flush_heat($cur_session, $cur_heat);
        $sessions[] = $cur_session;
    }
    $cur_heat = null;
    $session_idx = $nr > $session_idx ? $nr : $session_idx + 1;
    $cur_session = [
        'blok'       => sl_int_to_roman($session_idx),
        'data'       => $data,
        'godz_start' => $godz,
        'starty'     => [],
    ];
}

/**
 * Applies an event's date/time line: stamps the event and, when the PDF has no session headers,
 * starts a new block on a new day (Splash lists without sessions only group events by date).
 */
function sl_apply_event_when(array $when, array &$cur_event, bool $explicit_sessions, array &$sessions,
                             ?array &$cur_session, ?array &$cur_heat, int &$session_idx): void {
    [$data, $godz] = $when;
    if ($cur_event['godz'] === '') {
        $cur_event['data'] = $data;
        $cur_event['godz'] = $godz;
    }
    if ($cur_session === null
        || (!$explicit_sessions && $cur_session['data'] !== '' && $cur_session['data'] !== $data)) {
        sl_open_session($sessions, $cur_session, $cur_heat, $session_idx);
    }
    if ($cur_session['data'] === '')       $cur_session['data']       = $data;
    if ($cur_session['godz_start'] === '') $cur_session['godz_start'] = $godz;
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
    $tokens = array_values(array_filter(explode(' ', $f), fn($t) => strlen($t) >= 2));
    if (count($tokens) < 2) return str_contains($c, $f);
    $matched = 0;
    foreach ($tokens as $t) { if (str_contains($c, $t)) $matched++; }
    return $matched >= 2;
}

/**
 * Tries to parse a single athlete entry line from a PDF start list.
 * Returns a start entry array or null if the line doesn't match.
 *
 * Expected layout (pdftotext -layout):
 *   "  4   WAS AMELIA         10  OLI BRZESKO    5:23.71"
 *   leading spaces + lane(1-10) + 1+spaces + NAME + spaces + YOB + spaces + CLUB + optional [code] + optional time
 */
function sl_parse_entry_line(string $line, array $event, array $heat, string $club_filter): ?array {
    $trimmed = trim($line);
    if (strlen($trimmed) < 8) return null;

    // Must begin with (optional spaces +) 1-2 digit lane number + ≥1 space
    if (!preg_match('/^\s{0,6}(\d{1,2})\s+/u', $line, $lm)) return null;
    $tor = (int)$lm[1];
    if ($tor < 1 || $tor > 10) return null;

    // Rest of line after lane
    $rest = substr($line, (int)strpos($line, $lm[0]) + strlen($lm[0]));

    // Name: Unicode letters (upper or lower, incl. Polish), hyphens, followed by ≥2 spaces
    if (!preg_match('/^([\p{L}][\p{L}\-\s]+?)\s{2,}/u', $rest, $nm)) return null;
    $raw_name = trim($nm[1]);
    if (strlen($raw_name) < 3) return null;

    $after_name = substr($rest, strlen($nm[0]));

    // Birth year: 2 or 4 digits; masters lists print the age group instead ("65+")
    if (!preg_match('/^(\d{2,4}\+?)\s+/u', $after_name, $ym)) return null;
    $after_yob = ltrim(substr($after_name, strlen($ym[0])));

    // Club + optional time at end.
    // Some PDFs (Splash Meet Manager) insert an extra numeric code (e.g. "06") and optional
    // qualifier (e.g. "PK") between the club name and the seed time. Try that format first.
    $czas     = null;
    $club_raw = '';
    if (preg_match('/^(.*?)\s{2,}\d{1,3}(?:\s+[A-Z]+)?\s{2,}(NT|\d[\d:.]+)\s*$/u', $after_yob, $cm)) {
        $club_raw = trim($cm[1]);
        $t_raw    = trim($cm[2]);
        $czas     = ($t_raw === 'NT') ? null : sl_normalize_time($t_raw);
    } elseif (preg_match('/^(.*?)\s{2,}(NT|\d[\d:.]+)\s*$/u', $after_yob, $cm)) {
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
    // Page header/footer boilerplate — Splash prints "Splash Meet Manager, 11.85099 / Registered to …"
    // and the organiser's contact lines above the meet name.
    $skip_re = '/start ?list|lista startowa|startliste|\b(page|strona|seite)\s+\d|session|sesja|event |wydarzenie |konkurencja'
             . '|splash meet manager|registered to|livetiming|e-mail|www\.|@|obsługa zawodów|informatyk|\btel\b|kont\.|rok ur/iu';

    $candidates = []; // lines that could be the meet name, in order
    foreach ($first_lines as $idx => $line) {
        if (strlen($line) < 4 && !str_starts_with($line, ',')) continue;
        if (preg_match($skip_re, $line)) continue;
        if (sl_match_event_when($line) !== null) continue;
        if (preg_match('/^\d{1,2}\s+-\s+\S/', $line)) continue; // Splash session header "1 - Blok 1"

        // "Venue, 10.05.2026", "Venue, 19. - 20.9.2026" — the meet name is the line above it.
        // The PHP extractor splits it into "Venue" + ", 10.05.2026".
        if (preg_match('/^(.*?),\s*\d{1,2}\.?(?:\s*-\s*\d{1,2})?[.\/]\d{1,2}[.\/]\d{4}/', $line, $vm)) {
            $venue = trim($vm[1]);
            if ($venue === '' && $candidates) $venue = array_pop($candidates);
            if ($zawody['miejsce'] === '' && $venue !== '') $zawody['miejsce'] = $venue;
            if ($zawody['nazwa'] === '' && $candidates)     $zawody['nazwa']   = end($candidates);
            if ($zawody['data'] === '' && preg_match('/(?:(\d{1,2})\.?\s*-\s*)?(\d{1,2}[.\/]\d{1,2}[.\/]\d{4})/', $line, $dm)) {
                $last  = sl_normalize_date_str($dm[2]);
                $first = $dm[1] !== '' ? preg_replace('/^\d+/', (string)(int)$dm[1], $last) : $last;
                $zawody['data'] = sl_format_date_range($first, $last);
            }
            break;
        }
        if (strlen($line) > 5) $candidates[] = $line;
    }
    if ($zawody['nazwa'] === '' && $candidates) $zawody['nazwa'] = $candidates[0];

    // The blocks' dates give the full range ("19-20/9/2026"), which the header line may not
    $dates = array_values(array_filter(array_column($zawody['bloki'], 'data')));
    if ($dates) {
        usort($dates, fn($a, $b) => sl_date_str_ts($a) <=> sl_date_str_ts($b));
        $zawody['data'] = sl_format_date_range($dates[0], end($dates));
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
    // PDF has its own block headers — then blocks follow them, not the event dates
    $explicit_sessions = false;

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed !== '' && count($first_lines) < 25) {
            $first_lines[] = $trimmed;
        }

        // --- Session / Block ---
        // "Sesja II …" or Splash's "2 - Blok 2          20.09.2026 - 11:00"
        $splash_session = preg_match(
            '/^(\d{1,2})\s+-\s+\S.*?\s{2,}(?:' . SL_DATE_RE . ')\s+-\s+\d{1,2}:\d{2}\s*$/u', $trimmed, $sm);
        if ($splash_session || preg_match('/^(Sesja|Session)\s+([IVX]+|\d+)/iu', $trimmed)) {
            $explicit_sessions = true;
            sl_open_session($sessions, $cur_session, $cur_heat, $session_idx, '', '', $splash_session ? (int)$sm[1] : 0);
            $cur_event = null;
            if (preg_match('/(' . SL_DATE_RE . ')/', $trimmed, $dm)) {
                $cur_session['data'] = sl_normalize_date_str($dm[1]);
            }
            if (preg_match('/\b(\d{1,2}:\d{2})\s*$/', $trimmed, $hm)) {
                $cur_session['godz_start'] = $hm[1];
            }
            continue;
        }

        // --- Event date/time line ("20.09.2026 - 9:30"), right under the event header ---
        if ($cur_event !== null && ($when = sl_match_event_when($trimmed)) !== null) {
            sl_apply_event_when($when, $cur_event, $explicit_sessions, $sessions, $cur_session, $cur_heat, $session_idx);
            continue;
        }

        // --- Event ---
        // Matches "Event N desc", "Wydarzenie N desc", "Konkurencja N desc" or "Konkurencja N, desc"
        if (preg_match('/^(Event|Wydarzenie|Konkurencja)\s+(\d+)(?:[\s,]+(.*))?/iu', $trimmed, $m)) {
            $new_nr = (int)$m[2];
            // Same event repeated at page-break: don't reset current heat, just skip
            if ($cur_event !== null && $cur_event['nr'] === $new_nr) {
                continue;
            }
            if ($cur_session && $cur_heat) {
                sl_flush_heat($cur_session, $cur_heat);
                $cur_heat = null;
            }
            // Splash layout: "Konkurencja 1        Dziewcząt, 50m motylkowy        15 lat i młodsi" —
            // the age group after a wide gap isn't part of the event name
            $desc = preg_split('/\s{3,}/', trim($m[3] ?? ''))[0];
            $cur_event = [
                'nr'   => $new_nr,
                'name' => sl_normalize_event_name($desc),
                'data' => '',
                'godz' => '',
            ];
            continue;
        }

        // --- Heat ---
        if (preg_match('/^(Heat|Seria)\s+(\d+)\s+(of|z)\s+(\d+)/iu', $trimmed, $m)) {
            if ($cur_session && $cur_heat) {
                sl_flush_heat($cur_session, $cur_heat);
            }
            if ($cur_session === null) {
                sl_open_session($sessions, $cur_session, $cur_heat, $session_idx);
            }
            $heat_time = '';
            if (preg_match('/\b(\d{1,2}:\d{2})\s*$/', $trimmed, $hm)) {
                $heat_time = $hm[1];
                if ($cur_session['godz_start'] === '') $cur_session['godz_start'] = $heat_time;
            }
            $cur_heat = [
                'nr'      => (int)$m[2],
                'total'   => (int)$m[4],
                // Heats without their own time start with the event (Splash prints only the event time)
                'godz'    => $heat_time !== '' ? $heat_time : ($cur_event['godz'] ?? ''),
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
    $explicit_sessions = false;

    $state  = 'IDLE'; // IDLE | LANE | NAME | YOB | CLUB
    $e_lane = 0;
    $e_name = '';
    $e_yob  = '';
    $e_club = '';

    // Index of the next non-empty line after $from (or $n)
    $next_filled = function (int $from) use ($lines, $n): int {
        for ($j = $from + 1; $j < $n; $j++) {
            if (trim($lines[$j]) !== '') return $j;
        }
        return $n;
    };

    for ($i = 0; $i < $n; $i++) {
        $trimmed = trim($lines[$i]);

        if ($trimmed !== '' && count($first_lines) < 25) {
            $first_lines[] = $trimmed;
        }

        // ── Session ──────────────────────────────────────────────────────────
        if (preg_match('/^(Sesja|Session)\s+([IVX]+|\d+)/iu', $trimmed)) {
            $state = 'IDLE';
            $explicit_sessions = true;
            sl_open_session($sessions, $cur_session, $cur_heat, $session_idx);
            $cur_event = null;
            if (preg_match('/(' . SL_DATE_RE . ')/', $trimmed, $dm)) {
                $cur_session['data'] = sl_normalize_date_str($dm[1]);
            }
            continue;
        }

        // Splash session header split over lines: "2 - Blok 2" / "20.09.2026 - 11:00" / "Konkurencja …".
        // An age group ("50 - 79 lat") is followed by the same date line too, but then by "Lista startowa".
        if (preg_match('/^\d{1,2}\s+-\s+\S/', $trimmed)) {
            $j = $next_filled($i);
            $k = $j < $n ? $next_filled($j) : $n;
            $when = $j < $n ? sl_match_event_when(trim($lines[$j])) : null;
            if ($when !== null && $k < $n && preg_match('/^(Konkurencja|Event)\b/iu', trim($lines[$k]))) {
                $state = 'IDLE';
                $explicit_sessions = true;
                sl_open_session($sessions, $cur_session, $cur_heat, $session_idx, $when[0], $when[1], (int)$trimmed);
                $cur_event = null;
                $i = $j;
                continue;
            }
        }

        // ── Event date/time ("20.09.2026 - 9:30"), a few lines below the event header ──
        if ($cur_event !== null && ($when = sl_match_event_when($trimmed)) !== null) {
            $state = 'IDLE';
            sl_apply_event_when($when, $cur_event, $explicit_sessions, $sessions, $cur_session, $cur_heat, $session_idx);
            continue;
        }

        // ── Event ─────────────────────────────────────────────────────────────
        // "Konkurencja 2, Kobiet, 100m dowolny"  OR  "Konkurencja 1" + desc on next line
        if (preg_match('/^Konkurencja\s+(\d+)(?:[,\s]+(.+))?/iu', $trimmed, $m)) {
            $state      = 'IDLE';
            $event_nr   = (int)$m[1];
            // Same event repeated at a page break — keep it (and its date/time)
            if ($cur_event !== null && $cur_event['nr'] === $event_nr) {
                continue;
            }
            $event_desc = trim($m[2] ?? '');
            if ($event_desc === '') {
                // Description may span 2 lines (gender on one, distance+stroke on next)
                $parts = [];
                for ($j = $i + 1; $j < min($n, $i + 8); $j++) {
                    $next = trim($lines[$j]);
                    if ($next === '') continue;
                    if (preg_match('/^(Seria|Heat|Sesja|Session|Konkurencja|Lista)\b/iu', $next)) break;
                    if (sl_match_event_when($next) !== null) break;
                    $parts[] = ltrim($next, ', ');
                    $i = $j;
                    if (count($parts) >= 2) break; // gender + distance/stroke is enough
                }
                $event_desc = trim(implode(', ', array_filter($parts)));
            }
            $cur_event = ['nr' => $event_nr, 'name' => sl_normalize_event_name($event_desc), 'data' => '', 'godz' => ''];
            continue;
        }

        // ── Heat ──────────────────────────────────────────────────────────────
        if (preg_match('/^(Seria|Heat)\s+(\d+)\s+(z|of)\s+(\d+)/iu', $trimmed, $m)) {
            $state = 'IDLE';
            if ($cur_session !== null && $cur_heat !== null) {
                sl_flush_heat($cur_session, $cur_heat);
            }
            if ($cur_session === null) {
                sl_open_session($sessions, $cur_session, $cur_heat, $session_idx);
            }
            $heat_time = '';
            if (preg_match('/\b(\d{1,2}:\d{2})\s*$/', $trimmed, $hm)) {
                $heat_time = $hm[1];
                if ($cur_session['godz_start'] === '') $cur_session['godz_start'] = $heat_time;
            }
            $cur_heat = [
                'nr'      => (int)$m[2],
                'total'   => (int)$m[4],
                'godz'    => $heat_time !== '' ? $heat_time : ($cur_event['godz'] ?? ''),
                'entries' => [],
            ];
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
                } elseif (preg_match('/^\d{2,4}\+?$/', $trimmed)) {
                    // no name line — this is already the YOB
                    $e_yob = $trimmed;
                    $state = 'YOB';
                } else {
                    $e_name = $trimmed;
                    $state  = 'NAME';
                }
                break;

            case 'NAME':
                if (preg_match('/^\d{2,4}\+?$/', $trimmed)) {
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
                    break; // extraneous dot — skip
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
                } elseif (preg_match('/^\d{1,3}$/', $trimmed) || preg_match('/^[A-Z]{1,4}$/', $trimmed)) {
                    // Skip meeting-code (e.g. "06") or short qualifier (e.g. "PK") — stay in CLUB
                    break;
                } else {
                    // not a time, not a skip-code — reset and re-process
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
 * Only a livetiming.pl contest page is accepted (no direct PDF links): name, city, date and pool length
 * come from it (so the name matches the contest search); the PDF header is only a fallback.
 */
function build_startlist_from_pdf(string $contest_url, string $club): array {
    $uuid = sl_contest_uuid($contest_url);
    if ($uuid === null) {
        return ['ok' => false, 'error' => 'Nieprawidłowy adres zawodów — podaj stronę zawodów z livetiming.pl (https://livetiming.pl/contest/…).'];
    }

    $page    = resolve_contest_page('https://livetiming.pl/contest/' . $uuid);
    $pdf_url = $page['pdf_url'];
    $basen   = $page['basen'] ?: '25m';

    if ($pdf_url === '') {
        return [
            'ok'    => false,
            'error' => 'Nie znaleziono linku do PDF z listą startową na stronie zawodów. Lista może jeszcze nie być opublikowana na livetiming.pl.',
        ];
    }
    // The PDF link comes from the page — still fetch it only from livetiming.pl (SSRF guard)
    if (!is_allowed_contest_host($pdf_url)) {
        return ['ok' => false, 'error' => 'Lista startowa jest poza livetiming.pl — nie można jej pobrać.', 'pdf_url' => $pdf_url];
    }

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
    foreach (['nazwa', 'miejsce', 'data'] as $field) {
        if ($page[$field] !== '') $zawody[$field] = $page[$field];
    }
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
