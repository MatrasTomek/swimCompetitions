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
    $cid_map = pdf_extract_cmap($pdf_data);
    $text    = '';
    foreach (pdf_iter_streams($pdf_data) as $s) {
        $text .= pdf_bt_et($s['decoded'], $cid_map);
    }
    return $text;
}

/**
 * Parses the ToUnicode CMap from a PDF, returns [cid_hex4 => utf8_char].
 * Handles both bfrange and bfchar sections.
 */
function pdf_extract_cmap(string $pdf_data): array {
    $cmap = [];
    foreach (pdf_iter_streams($pdf_data) as $s) {
        if (strpos($s['decoded'], 'begincmap') === false) continue;

        foreach (['beginbfrange' => 'endbfrange', 'beginbfchar' => 'endbfchar'] as $start => $end) {
            if (!preg_match('/' . $start . '\s*(.*?)\s*' . $end . '/s', $s['decoded'], $m)) continue;
            preg_match_all('/<([0-9a-fA-F]{2,4})>\s*<([0-9a-fA-F]{2,4})>\s*<([0-9a-fA-F]{2,4})>/',
                $m[1], $rows, PREG_SET_ORDER);
            foreach ($rows as $r) {
                $cid_start  = hexdec($r[1]);
                $cid_end    = hexdec($r[2]);
                $uni_start  = hexdec($r[3]);
                for ($c = $cid_start; $c <= $cid_end; $c++) {
                    $key        = strtolower(str_pad(dechex($c), 4, '0', STR_PAD_LEFT));
                    $cmap[$key] = _pdf_cp_to_utf8($uni_start + ($c - $cid_start));
                }
            }
            // bfchar: two-token form <cid> <unicode>
            preg_match_all('/<([0-9a-fA-F]{2,4})>\s*<([0-9a-fA-F]{2,4})>(?!\s*<)/',
                $m[1], $chars, PREG_SET_ORDER);
            foreach ($chars as $r) {
                $key        = strtolower(str_pad($r[1], 4, '0', STR_PAD_LEFT));
                $cmap[$key] = _pdf_cp_to_utf8(hexdec($r[2]));
            }
        }

        if (!empty($cmap)) break;
    }
    return $cmap;
}

function _pdf_cp_to_utf8(int $cp): string {
    if ($cp < 0x80)   return chr($cp);
    if ($cp < 0x800)  return chr(0xC0 | ($cp >> 6))  . chr(0x80 | ($cp & 0x3F));
    return chr(0xE0 | ($cp >> 12)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
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

        $streams[] = ['decoded' => $decoded];
    }

    return $streams;
}

function pdf_bt_et(string $data, array $cid_map = []): string {
    $text   = '';
    $offset = 0;

    while (($bt = strpos($data, 'BT', $offset)) !== false) {
        $et = strpos($data, 'ET', $bt + 2);
        if ($et === false) break;
        $block  = substr($data, $bt + 2, $et - $bt - 2);
        $offset = $et + 2;
        $text  .= pdf_extract_strings($block, $cid_map) . "\n";
    }

    return $text;
}

/**
 * Extracts text strings from a BT/ET block.
 * Handles (text) Tj, <hexstring> Tj, and [(text)] TJ operators.
 * Parenthesized strings → windows-1250; hex strings → CID map (CID fonts).
 */
function pdf_extract_strings(string $block, array $cid_map = []): string {
    $parts = [];
    $i     = 0;
    $len   = strlen($block);

    while ($i < $len) {
        // Hex string: <hexdata> (skip << dictionary markers)
        if ($block[$i] === '<' && ($i + 1 >= $len || $block[$i + 1] !== '<')) {
            $j   = $i + 1;
            $hex = '';
            while ($j < $len && $block[$j] !== '>') {
                if (ctype_xdigit($block[$j])) $hex .= $block[$j];
                $j++;
            }
            $i = $j + 1;
            if (!empty($cid_map) && strlen($hex) >= 4 && strlen($hex) % 4 === 0) {
                $str = '';
                for ($k = 0; $k < strlen($hex); $k += 4) {
                    $cid  = strtolower(substr($hex, $k, 4));
                    $str .= $cid_map[$cid] ?? '';
                }
                if ($str !== '') $parts[] = $str;
            }
            continue;
        }

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
