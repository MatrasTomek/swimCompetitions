<?php
/**
 * PDF text extraction — no external Python packages.
 * Method 1: pdftotext (poppler-utils, commonly available on Linux servers).
 * Method 2: pure PHP — zlib stream decompression + BT/ET parsing.
 */

function pdf_download(string $url) {
    $ctx = stream_context_create([
        'http' => [
            'header'  => "User-Agent: Mozilla/5.0 SwimResults/1.0\r\n",
            'timeout' => 15,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    return @file_get_contents($url, false, $ctx);
}

function pdf_extract_text(string $pdf_data): string {
    // Method 1: pdftotext (poppler-utils)
    if (is_callable('shell_exec')) {
        $tmp = tempnam(sys_get_temp_dir(), 'swim_') . '.pdf';
        file_put_contents($tmp, $pdf_data);
        $out = @shell_exec('pdftotext -layout ' . escapeshellarg($tmp) . ' - 2>/dev/null');
        @unlink($tmp);
        if ($out && strlen(trim($out)) > 20) {
            return $out;
        }
    }

    // Method 2: pure PHP — stream decompression + BT/ET
    return pdf_extract_text_php($pdf_data);
}

function pdf_extract_text_php(string $pdf_data): string {
    $text = '';
    foreach (pdf_iter_streams($pdf_data) as $s) {
        $text .= pdf_bt_et($s['decoded']);
    }
    return $text;
}

/**
 * Iterates over all PDF streams, yielding decoded content and metadata.
 * Used by both text extraction and diagnostics.
 */
function pdf_iter_streams(string $pdf_data): array {
    $streams = [];
    $offset  = 0;

    while (($stream_pos = strpos($pdf_data, 'stream', $offset)) !== false) {
        $skip = $stream_pos + 6;
        if (isset($pdf_data[$skip]) && $pdf_data[$skip] === "\r") $skip++;
        if (!isset($pdf_data[$skip]) || $pdf_data[$skip] !== "\n") {
            $offset = $stream_pos + 6;
            continue;
        }
        $skip++;

        $end_pos = strpos($pdf_data, "\nendstream", $skip);
        if ($end_pos === false) break;

        // Strip trailing \r that appears when stream ends with \r\n before endstream
        $raw    = rtrim(substr($pdf_data, $skip, $end_pos - $skip), "\r\n");
        $offset = $end_pos + 10;

        // Check if the object uses FlateDecode — use 1000-char lookback
        $header_chunk = substr($pdf_data, max(0, $stream_pos - 1000), 1000);
        $is_flat = strpos($header_chunk, 'FlateDecode') !== false;

        $decoded   = $raw;
        $decomp_ok = null;
        if ($is_flat && strlen($raw) > 0) {
            $d = @gzuncompress($raw);
            if ($d === false) $d = @gzinflate($raw);
            if ($d === false) $d = @gzinflate(substr($raw, 2));
            if ($d !== false) {
                $decoded   = $d;
                $decomp_ok = true;
            } else {
                $decomp_ok = false;
            }
        }

        $streams[] = [
            'raw_len'    => strlen($raw),
            'is_flat'    => $is_flat,
            'decomp_ok'  => $decomp_ok,
            'decoded_len'=> strlen($decoded),
            'decoded'    => $decoded,
        ];
    }

    return $streams;
}

function pdf_bt_et(string $data): string {
    $text   = '';
    $offset = 0;

    while (($bt = strpos($data, 'BT', $offset)) !== false) {
        $et = strpos($data, 'ET', $bt + 2);
        if ($et === false) break;
        $block  = substr($data, $bt + 2, $et - $bt - 2);
        $offset = $et + 2;
        $text  .= pdf_extract_strings($block) . "\n";
    }

    return $text;
}

/**
 * Extracts text strings from a BT/ET block.
 * Handles (text) Tj and [(text)] TJ operators.
 * Converts windows-1250 → UTF-8 (standard in Splash Meet Manager).
 */
function pdf_extract_strings(string $block): string {
    $parts = [];
    $i     = 0;
    $len   = strlen($block);

    while ($i < $len) {
        if ($block[$i] !== '(') { $i++; continue; }

        $str = '';
        $i++;
        while ($i < $len) {
            $c = $block[$i];
            if ($c === ')') { $i++; break; }
            if ($c === '\\' && $i + 1 < $len) {
                $n = $block[$i + 1];
                switch ($n) {
                    case 'n':  $str .= "\n"; break;
                    case 'r':  $str .= "\r"; break;
                    case 't':  $str .= "\t"; break;
                    case '(':  $str .= '(';  break;
                    case ')':  $str .= ')';  break;
                    case '\\': $str .= '\\'; break;
                    default:   $str .= $n;
                }
                $i += 2;
            } else {
                $str .= $c;
                $i++;
            }
        }

        // Try windows-1250 → UTF-8, then ISO-8859-2 → UTF-8
        if (function_exists('iconv')) {
            $utf = @iconv('windows-1250', 'UTF-8//IGNORE', $str);
            if ($utf === false || $utf === '') {
                $utf = @iconv('ISO-8859-2', 'UTF-8//IGNORE', $str);
            }
            $parts[] = $utf ?: $str;
        } else {
            $parts[] = $str;
        }
    }

    return implode('', $parts);
}

/**
 * Searches for an athlete result in PDF text.
 * Line format: "Wąs Amelia 12 Olimpijczyk Brzesko 5:23.29 542"
 */
function pdf_find_athlete(string $text, string $athlete_name): array {
    $lines      = preg_split('/\r?\n/', $text);
    $name_lower = mb_strtolower(trim($athlete_name), 'UTF-8');

    foreach ($lines as $line) {
        if (mb_strpos(mb_strtolower($line, 'UTF-8'), $name_lower) === false) continue;

        // Time: m:ss.dd or ss.dd
        if (!preg_match('/(\d{1,2}:\d{2}\.\d{2}|\d{2}\.\d{2})/', $line, $tm)) continue;

        $czas = $tm[1];

        // Birth year: 2-digit number after the athlete name
        $escaped = preg_quote(trim($athlete_name), '/');
        $rok_ur  = null;
        if (preg_match('/' . $escaped . '\s+(\d{2})\s+/ui', $line, $rm)) {
            $rok_ur = 2000 + (int)$rm[1];
        }

        // Points: 3-4 digit number after the time
        $after = substr($line, (int)strpos($line, $czas) + strlen($czas));
        $punkty = null;
        if (preg_match('/\b(\d{3,4})\b/', $after, $pm)) {
            $punkty = (int)$pm[1];
        }

        return [
            'found'         => true,
            'imie'          => trim($athlete_name),
            'czas'          => $czas,
            'rok_urodzenia' => $rok_ur,
            'punkty'        => $punkty,
        ];
    }

    return ['found' => false, 'error' => 'Zawodnik nie znaleziony w PDF'];
}
