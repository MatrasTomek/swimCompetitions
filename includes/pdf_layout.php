<?php
/**
 * Pure PHP PDF text extraction that keeps the page layout — a stand-in for `pdftotext -layout`
 * on servers without poppler-utils (e.g. local Windows PHP).
 *
 * Reads the PDF object table (including compressed object streams), resolves each page's fonts
 * (ToUnicode CMaps, encodings, glyph widths) and runs the page content stream through a minimal
 * text-state interpreter. Every shown string becomes a positioned fragment; fragments are then grouped
 * into lines by baseline and joined left to right, with ≥2 spaces between separate columns — the
 * format the start list parser expects ("    2 Kowalska Anna        16     Klub      1:02.34").
 */

/** Extracts the text of all pages as layout-preserving lines ('' when the PDF can't be read). */
function pdf_layout_extract(string $pdf_data): string {
    $doc   = pdf_doc_load($pdf_data);
    $pages = [];
    foreach (pdf_doc_pages($doc) as $page) {
        $frags = pdf_page_fragments($doc, $page);
        if ($frags) $pages[] = pdf_fragments_to_text($frags);
    }
    return implode("\n\n", $pages);
}

// ─── Object table ──────────────────────────────────────────────────────────────────────────────

/**
 * Builds [num => ['val' => parsed object, 'raw' => raw stream bytes|null]] for every object in the file,
 * later definitions (incremental updates) winning, plus the objects packed in /ObjStm streams.
 */
function pdf_doc_load(string $pdf): array {
    $doc = ['objs' => [], 'decoded' => [], 'fonts' => []];

    if (!preg_match_all('/(?<![0-9])(\d+)\s+\d+\s+obj\b/', $pdf, $m, PREG_OFFSET_CAPTURE)) return $doc;

    foreach ($m[0] as $k => $match) {
        $num = (int)$m[1][$k][0];
        $i   = $match[1] + strlen($match[0]);
        try {
            $val = pdf_parse_value($pdf, $i);
        } catch (Throwable $e) {
            continue;
        }
        $raw = null;
        pdf_skip_ws($pdf, $i);
        if (substr($pdf, $i, 6) === 'stream') {
            $i += 6;
            if (($pdf[$i] ?? '') === "\r") $i++;
            if (($pdf[$i] ?? '') === "\n") $i++;
            $len = is_array($val) && isset($val['dict']['Length']) && is_int($val['dict']['Length'])
                 ? $val['dict']['Length'] : -1;
            if ($len >= 0 && preg_match('/^\s*endstream/', substr($pdf, $i + $len, 20))) {
                $raw = substr($pdf, $i, $len);
            } else {
                $end = strpos($pdf, 'endstream', $i);
                if ($end === false) continue;
                $raw = rtrim(substr($pdf, $i, $end - $i), "\r\n");
            }
        }
        $doc['objs'][$num] = ['val' => $val, 'raw' => $raw];
    }

    // Objects compressed inside object streams (PDF 1.5+) — a direct definition takes precedence
    foreach (array_keys($doc['objs']) as $num) {
        $o = $doc['objs'][$num];
        if ($o['raw'] === null || pdf_dict_get($doc, $o['val'], 'Type') !== '/ObjStm') continue;
        $data = pdf_stream_data($doc, $num);
        if ($data === null) continue;
        $n     = (int)pdf_dict_get($doc, $o['val'], 'N');
        $first = (int)pdf_dict_get($doc, $o['val'], 'First');
        $nums  = preg_split('/\s+/', trim(substr($data, 0, $first)));
        for ($k = 0; $k + 1 < count($nums) && $k / 2 < $n; $k += 2) {
            $onum = (int)$nums[$k];
            if (isset($doc['objs'][$onum])) continue;
            $i = $first + (int)$nums[$k + 1];
            try {
                $doc['objs'][$onum] = ['val' => pdf_parse_value($data, $i), 'raw' => null];
            } catch (Throwable $e) {
                continue;
            }
        }
    }

    return $doc;
}

/** Follows indirect references ("12 0 R") to the object itself. */
function pdf_resolve(array $doc, $v) {
    for ($guard = 0; is_array($v) && isset($v['ref']) && $guard < 32; $guard++) {
        $v = $doc['objs'][$v['ref']]['val'] ?? null;
    }
    return $v;
}

/** Value of a dictionary entry (resolved), or null. Accepts a dict or a reference to one. */
function pdf_dict_get(array $doc, $dict, string $key) {
    $dict = pdf_resolve($doc, $dict);
    if (!is_array($dict) || !isset($dict['dict'][$key])) return null;
    return pdf_resolve($doc, $dict['dict'][$key]);
}

/** Decoded bytes of a stream object (FlateDecode or unfiltered), or null when it can't be decoded. */
function pdf_stream_data(array &$doc, int $num): ?string {
    if (array_key_exists($num, $doc['decoded'])) return $doc['decoded'][$num];
    $o    = $doc['objs'][$num] ?? null;
    $data = $o['raw'] ?? null;
    if ($data !== null) {
        $filter  = pdf_dict_get($doc, $o['val'], 'Filter');
        $filters = is_array($filter) ? ($filter['arr'] ?? []) : ($filter === null ? [] : [$filter]);
        foreach ($filters as $f) {
            $f = pdf_resolve($doc, $f);
            if ($f !== '/FlateDecode' && $f !== '/Fl') { $data = null; break; }
            $d = @gzuncompress($data);
            if ($d === false) $d = @gzinflate($data);
            if ($d === false) $d = @gzinflate(substr($data, 2));
            if ($d === false) { $data = null; break; }
            $data = $d;
        }
    }
    return $doc['decoded'][$num] = $data;
}

/** Decoded bytes of a stream given as a reference (content streams, CMaps, form XObjects). */
function pdf_ref_stream(array &$doc, $ref): ?string {
    return is_array($ref) && isset($ref['ref']) ? pdf_stream_data($doc, $ref['ref']) : null;
}

// ─── Value parser ──────────────────────────────────────────────────────────────────────────────
// Objects: numbers → int/float, names → '/Name', strings → ['str' => bytes], arrays → ['arr' => […]],
// dictionaries → ['dict' => [key => value]], references → ['ref' => num], true/false/null → PHP values.

function pdf_skip_ws(string $s, int &$i): void {
    $len = strlen($s);
    while ($i < $len) {
        $c = $s[$i];
        if ($c === ' ' || $c === "\n" || $c === "\r" || $c === "\t" || $c === "\f" || $c === "\0") {
            $i++;
        } elseif ($c === '%') {
            while ($i < $len && $s[$i] !== "\n" && $s[$i] !== "\r") $i++;
        } else {
            break;
        }
    }
}

function pdf_is_delim(string $c): bool {
    return $c === '' || strpbrk($c, " \t\r\n\f\0()<>[]{}/%") !== false;
}

/** Parses one PDF object at $i (advancing $i); $refs = false in content streams, where "N G R" can't occur. */
function pdf_parse_value(string $s, int &$i, bool $refs = true) {
    pdf_skip_ws($s, $i);
    $c = $s[$i] ?? '';

    if ($c === '<' && ($s[$i + 1] ?? '') === '<') {
        $i += 2;
        $dict = [];
        while (true) {
            pdf_skip_ws($s, $i);
            if (!isset($s[$i])) throw new RuntimeException('unterminated dict');
            if ($s[$i] === '>' && ($s[$i + 1] ?? '') === '>') { $i += 2; break; }
            $key = pdf_parse_value($s, $i, $refs);
            if (!is_string($key) || $key === '' || $key[0] !== '/') throw new RuntimeException('bad dict key');
            $dict[substr($key, 1)] = pdf_parse_value($s, $i, $refs);
        }
        return ['dict' => $dict];
    }
    if ($c === '<') {
        $end = strpos($s, '>', $i);
        if ($end === false) throw new RuntimeException('unterminated hex string');
        $hex = preg_replace('/[^0-9a-fA-F]/', '', substr($s, $i + 1, $end - $i - 1));
        $i   = $end + 1;
        if (strlen($hex) % 2) $hex .= '0';
        return ['str' => (string)hex2bin($hex)];
    }
    if ($c === '[') {
        $i++;
        $arr = [];
        while (true) {
            pdf_skip_ws($s, $i);
            if (!isset($s[$i])) throw new RuntimeException('unterminated array');
            if ($s[$i] === ']') { $i++; break; }
            $arr[] = pdf_parse_value($s, $i, $refs);
        }
        return ['arr' => $arr];
    }
    if ($c === '(') return ['str' => pdf_parse_literal($s, $i)];
    if ($c === '/') {
        $j = $i + 1;
        while (isset($s[$j]) && !pdf_is_delim($s[$j])) $j++;
        $name = preg_replace_callback('/#([0-9a-fA-F]{2})/', fn($h) => chr(hexdec($h[1])), substr($s, $i + 1, $j - $i - 1));
        $i = $j;
        return '/' . $name;
    }
    if (preg_match('/\G[+-]?(?:\d+\.?\d*|\.\d+)/', $s, $nm, 0, $i)) {
        $i += strlen($nm[0]);
        if (strpbrk($nm[0], '.') !== false) return (float)$nm[0];
        $num = (int)$nm[0];
        if ($refs && preg_match('/\G\s+(\d+)\s+R(?=[\s\/\[\]<>()%]|$)/', $s, $rm, 0, $i)) {
            $i += strlen($rm[0]);
            return ['ref' => $num];
        }
        return $num;
    }
    if (preg_match('/\G(true|false|null)(?![A-Za-z])/', $s, $km, 0, $i)) {
        $i += strlen($km[1]);
        return $km[1] === 'true' ? true : ($km[1] === 'false' ? false : null);
    }
    throw new RuntimeException('unexpected token at ' . $i);
}

/** Literal string "(…)" at $i: balanced parentheses, escapes, octal codes. */
function pdf_parse_literal(string $s, int &$i): string {
    $len   = strlen($s);
    $out   = '';
    $depth = 0;
    $i++;
    while ($i < $len) {
        $c = $s[$i];
        if ($c === '\\') {
            $n = $s[$i + 1] ?? '';
            if ($n >= '0' && $n <= '7') {
                preg_match('/\G[0-7]{1,3}/', $s, $om, 0, $i + 1);
                $out .= chr(octdec($om[0]) & 0xFF);
                $i   += 1 + strlen($om[0]);
                continue;
            }
            $i += 2;
            switch ($n) {
                case 'n': $out .= "\n"; break;
                case 'r': $out .= "\r"; break;
                case 't': $out .= "\t"; break;
                case 'b': $out .= "\x08"; break;
                case 'f': $out .= "\f"; break;
                case "\r": if (($s[$i] ?? '') === "\n") $i++; break; // line continuation
                case "\n": break;
                default:  $out .= $n;
            }
            continue;
        }
        if ($c === '(') $depth++;
        if ($c === ')' && $depth-- === 0) { $i++; break; }
        $out .= $c;
        $i++;
    }
    return $out;
}

// ─── Pages ─────────────────────────────────────────────────────────────────────────────────────

/** Pages in document order, each as ['dict' => page dict, 'resources' => inherited resources dict]. */
function pdf_doc_pages(array $doc): array {
    $root = null;
    foreach ($doc['objs'] as $o) {
        if (pdf_dict_get($doc, $o['val'], 'Type') === '/Catalog') $root = $o['val'];
    }
    $pages = [];
    if ($root !== null) {
        pdf_collect_pages($doc, $root['dict']['Pages'] ?? null, null, $pages, 0);
    }
    if (!$pages) {
        // No usable page tree — take the page objects in object-number order
        $nums = array_keys($doc['objs']);
        sort($nums);
        foreach ($nums as $num) {
            $v = $doc['objs'][$num]['val'];
            if (pdf_dict_get($doc, $v, 'Type') === '/Page') {
                $pages[] = ['dict' => $v, 'resources' => pdf_dict_get($doc, $v, 'Resources')];
            }
        }
    }
    return $pages;
}

function pdf_collect_pages(array $doc, $node_ref, $resources, array &$pages, int $depth): void {
    $node = pdf_resolve($doc, $node_ref);
    if (!is_array($node) || !isset($node['dict']) || $depth > 32) return;
    $resources = pdf_dict_get($doc, $node, 'Resources') ?? $resources;
    $kids      = pdf_dict_get($doc, $node, 'Kids');
    if (is_array($kids) && isset($kids['arr'])) {
        foreach ($kids['arr'] as $kid) pdf_collect_pages($doc, $kid, $resources, $pages, $depth + 1);
        return;
    }
    $pages[] = ['dict' => $node, 'resources' => $resources];
}

/** Text fragments shown on a page: [['x','y','x1','size','text'], …] in device space. */
function pdf_page_fragments(array &$doc, array $page): array {
    $contents = $page['dict']['dict']['Contents'] ?? null;
    $parts    = [];
    $resolved = pdf_resolve($doc, $contents);
    $refs     = is_array($resolved) && isset($resolved['arr']) ? $resolved['arr'] : [$contents];
    foreach ($refs as $ref) {
        $data = pdf_ref_stream($doc, $ref);
        if ($data !== null) $parts[] = $data;
    }
    $frags = [];
    pdf_run_content($doc, implode("\n", $parts), $page['resources'], [1, 0, 0, 1, 0, 0], $frags, 0);
    return $frags;
}

// ─── Fonts ─────────────────────────────────────────────────────────────────────────────────────

/**
 * Everything needed to decode a font's strings: ['bytes' => code length, 'map' => [code => utf8],
 * 'widths' => [code => width/1000 em], 'dw' => default width, 'enc' => fallback iconv charset].
 */
function pdf_font(array &$doc, $font_ref): array {
    $key = is_array($font_ref) && isset($font_ref['ref']) ? 'r' . $font_ref['ref'] : md5(serialize($font_ref));
    if (isset($doc['fonts'][$key])) return $doc['fonts'][$key];

    $font = [
        'bytes'  => 1,
        'map'    => [],
        'widths' => [],
        'dw'     => 0.5,
        'enc'    => 'windows-1250',
    ];
    $subtype = pdf_dict_get($doc, $font_ref, 'Subtype');

    if ($subtype === '/Type0') {
        $font['bytes'] = 2;
        $font['dw']    = 1.0;
        $desc = pdf_dict_get($doc, $font_ref, 'DescendantFonts');
        $cid  = is_array($desc) && isset($desc['arr'][0]) ? $desc['arr'][0] : null;
        if ($cid !== null) {
            $dw = pdf_dict_get($doc, $cid, 'DW');
            if (is_numeric($dw)) $font['dw'] = $dw / 1000;
            $w = pdf_dict_get($doc, $cid, 'W');
            $w = is_array($w) ? array_map(fn($x) => pdf_resolve($doc, $x), $w['arr'] ?? []) : [];
            for ($k = 0; $k < count($w); ) {
                $start = $w[$k] ?? null;
                $next  = $w[$k + 1] ?? null;
                if (!is_numeric($start)) break;
                if (is_array($next) && isset($next['arr'])) {
                    foreach ($next['arr'] as $o => $width) {
                        $font['widths'][(int)$start + $o] = (float)pdf_resolve($doc, $width) / 1000;
                    }
                    $k += 2;
                } else {
                    $width = $w[$k + 2] ?? 0;
                    for ($c = (int)$start; $c <= (int)$next && $c - (int)$start < 65536; $c++) {
                        $font['widths'][$c] = (float)$width / 1000;
                    }
                    $k += 3;
                }
            }
        }
    } else {
        $first  = (int)(pdf_dict_get($doc, $font_ref, 'FirstChar') ?? 0);
        $widths = pdf_dict_get($doc, $font_ref, 'Widths');
        foreach (($widths['arr'] ?? []) as $o => $width) {
            $font['widths'][$first + $o] = (float)pdf_resolve($doc, $width) / 1000;
        }
        $encoding = pdf_dict_get($doc, $font_ref, 'Encoding');
        $base     = is_array($encoding) ? pdf_dict_get($doc, $encoding, 'BaseEncoding') : $encoding;
        if ($base === '/WinAnsiEncoding') $font['enc'] = 'windows-1252';
        if (is_array($encoding)) {
            $diffs = pdf_dict_get($doc, $encoding, 'Differences');
            $code  = 0;
            foreach (($diffs['arr'] ?? []) as $d) {
                if (is_int($d)) { $code = $d; continue; }
                if (is_string($d) && ($u = pdf_glyph_to_utf8(substr($d, 1))) !== null) $font['map'][$code] = $u;
                $code++;
            }
        }
    }

    $to_unicode = pdf_ref_stream($doc, pdf_resolve($doc, $font_ref)['dict']['ToUnicode'] ?? null);
    if ($to_unicode !== null) {
        [$bytes, $map] = pdf_parse_cmap($to_unicode);
        if ($map) {
            $font['map'] = $map + $font['map'];
            if ($subtype !== '/Type0' && $bytes) $font['bytes'] = $bytes;
        }
    }

    return $doc['fonts'][$key] = $font;
}

/** ToUnicode CMap → [code byte length (0 = unknown), [code => utf8]]; reads every bfchar/bfrange section. */
function pdf_parse_cmap(string $cmap): array {
    $map   = [];
    $bytes = 0;
    if (preg_match('/begincodespacerange\s*<([0-9a-fA-F]+)>/', $cmap, $cs)) $bytes = intdiv(strlen($cs[1]), 2);

    preg_match_all('/beginbfchar(.*?)endbfchar/s', $cmap, $sections);
    foreach ($sections[1] as $sec) {
        preg_match_all('/<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]*)>/', $sec, $rows, PREG_SET_ORDER);
        foreach ($rows as $r) $map[hexdec($r[1])] = pdf_utf16_hex_to_utf8($r[2]);
    }
    preg_match_all('/beginbfrange(.*?)endbfrange/s', $cmap, $sections);
    foreach ($sections[1] as $sec) {
        preg_match_all('/<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]+)>\s*(<[0-9a-fA-F]*>|\[[^\]]*\])/', $sec, $rows, PREG_SET_ORDER);
        foreach ($rows as $r) {
            $lo = hexdec($r[1]);
            $hi = min(hexdec($r[2]), $lo + 65535);
            if ($r[3][0] === '[') {
                preg_match_all('/<([0-9a-fA-F]*)>/', $r[3], $dst);
                foreach ($dst[1] as $o => $hex) {
                    if ($lo + $o > $hi) break;
                    $map[$lo + $o] = pdf_utf16_hex_to_utf8($hex);
                }
                continue;
            }
            $hex  = substr($r[3], 1, -1);
            $head = substr($hex, 0, -4);
            $last = hexdec(substr($hex, -4) ?: '0');
            for ($c = $lo; $c <= $hi; $c++) {
                $map[$c] = pdf_utf16_hex_to_utf8($head . sprintf('%04x', $last + $c - $lo));
            }
        }
    }
    return [$bytes, $map];
}

function pdf_utf16_hex_to_utf8(string $hex): string {
    if ($hex === '') return '';
    if (strlen($hex) % 4) $hex = str_pad($hex, (int)ceil(strlen($hex) / 4) * 4, '0', STR_PAD_LEFT);
    return (string)mb_convert_encoding((string)hex2bin($hex), 'UTF-8', 'UTF-16BE');
}

/** Glyph name from an /Encoding /Differences array → UTF-8 (only the names Polish start lists need). */
function pdf_glyph_to_utf8(string $name): ?string {
    static $glyphs = [
        'aogonek' => 'ą', 'Aogonek' => 'Ą', 'cacute' => 'ć', 'Cacute' => 'Ć', 'eogonek' => 'ę', 'Eogonek' => 'Ę',
        'lslash' => 'ł', 'Lslash' => 'Ł', 'nacute' => 'ń', 'Nacute' => 'Ń', 'oacute' => 'ó', 'Oacute' => 'Ó',
        'sacute' => 'ś', 'Sacute' => 'Ś', 'zacute' => 'ź', 'Zacute' => 'Ź', 'zdotaccent' => 'ż', 'Zdotaccent' => 'Ż',
        'space' => ' ', 'period' => '.', 'comma' => ',', 'colon' => ':', 'hyphen' => '-', 'plus' => '+',
        'slash' => '/', 'parenleft' => '(', 'parenright' => ')', 'quotesingle' => "'", 'quotedbl' => '"',
        'zero' => '0', 'one' => '1', 'two' => '2', 'three' => '3', 'four' => '4',
        'five' => '5', 'six' => '6', 'seven' => '7', 'eight' => '8', 'nine' => '9',
    ];
    if (isset($glyphs[$name])) return $glyphs[$name];
    if (preg_match('/^[A-Za-z]$/', $name)) return $name;
    if (preg_match('/^uni([0-9A-Fa-f]{4})$/', $name, $m)) return pdf_utf16_hex_to_utf8($m[1]);
    return null;
}

/** Decodes a shown string with the font: [[utf8 text, width in em, is single-byte space], …] per code. */
function pdf_font_decode(array $font, string $bytes): array {
    $out  = [];
    $step = $font['bytes'];
    $len  = strlen($bytes);
    for ($k = 0; $k + $step <= $len; $k += $step) {
        $code = $step === 2 ? (ord($bytes[$k]) << 8) | ord($bytes[$k + 1]) : ord($bytes[$k]);
        if (isset($font['map'][$code])) {
            $text = $font['map'][$code];
        } elseif ($step === 1) {
            $text = (string)@iconv($font['enc'], 'UTF-8//IGNORE', $bytes[$k]);
        } else {
            $text = '';
        }
        $out[] = [$text, $font['widths'][$code] ?? $font['dw'], $step === 1 && $code === 32];
    }
    return $out;
}

// ─── Content stream interpreter ────────────────────────────────────────────────────────────────

/** PDF matrix product a × b (row-vector convention: a is applied first). */
function pdf_mat_mul(array $a, array $b): array {
    return [
        $a[0] * $b[0] + $a[1] * $b[2],
        $a[0] * $b[1] + $a[1] * $b[3],
        $a[2] * $b[0] + $a[3] * $b[2],
        $a[2] * $b[1] + $a[3] * $b[3],
        $a[4] * $b[0] + $a[5] * $b[2] + $b[4],
        $a[4] * $b[1] + $a[5] * $b[3] + $b[5],
    ];
}

/**
 * Runs a content stream, appending every shown string to $frags as a positioned fragment.
 * Tracks the graphics (q/Q/cm) and text state (Tf, Tm/Td/TD/T*, TL, Tc, Tw, Tz) and descends into form XObjects.
 */
function pdf_run_content(array &$doc, string $data, $resources, array $ctm, array &$frags, int $depth): void {
    if ($depth > 8) return;
    $fonts_dict = pdf_dict_get($doc, $resources, 'Font');
    $xobjects   = pdf_dict_get($doc, $resources, 'XObject');

    $gs    = ['ctm' => $ctm, 'font' => null, 'size' => 0.0, 'tc' => 0.0, 'tw' => 0.0, 'th' => 1.0, 'tl' => 0.0];
    $stack = [];
    $tm    = $tlm = [1, 0, 0, 1, 0, 0];
    $ops   = [];
    $len   = strlen($data);
    $i     = 0;

    $show = function (string $bytes) use (&$doc, &$gs, &$tm, &$frags): void {
        if ($gs['font'] === null) return;
        $m     = pdf_mat_mul($tm, $gs['ctm']);
        $size  = $gs['size'] * hypot($m[2], $m[3]);
        $x0    = $m[4];
        $y0    = $m[5];
        $text  = '';
        foreach (pdf_font_decode($gs['font'], $bytes) as [$ch, $w, $is_space]) {
            $text .= $ch;
            $tx    = ($w * $gs['size'] + $gs['tc'] + ($is_space ? $gs['tw'] : 0)) * $gs['th'];
            $tm    = pdf_mat_mul([1, 0, 0, 1, $tx, 0], $tm);
        }
        if (trim($text) === '' || $size <= 0) return;
        $x1 = pdf_mat_mul($tm, $gs['ctm'])[4];
        $frags[] = ['x' => $x0, 'y' => $y0, 'x1' => max($x0, $x1), 'size' => $size, 'text' => $text];
    };

    while ($i < $len) {
        pdf_skip_ws($data, $i);
        if ($i >= $len) break;
        $c = $data[$i];

        if (strpbrk($c, '/(<[+-.0123456789') !== false) {
            try {
                $ops[] = pdf_parse_value($data, $i, false);
            } catch (Throwable $e) {
                $i++;
                $ops = [];
            }
            continue;
        }
        if (!preg_match('/\G[^\s()<>\[\]{}\/%]+/', $data, $om, 0, $i)) { $i++; continue; }
        $op = $om[0];
        $i += strlen($op);
        $n  = array_map(fn($v) => is_int($v) || is_float($v) ? (float)$v : 0.0, $ops);

        switch ($op) {
            case 'q':  $stack[] = $gs; break;
            case 'Q':  if ($stack) $gs = array_pop($stack); break;
            case 'cm': if (count($n) >= 6) $gs['ctm'] = pdf_mat_mul(array_slice($n, -6), $gs['ctm']); break;
            case 'BT': $tm = $tlm = [1, 0, 0, 1, 0, 0]; break;
            case 'Tf':
                $name = $ops[count($ops) - 2] ?? null;
                if (is_string($name) && $fonts_dict !== null && isset($fonts_dict['dict'][substr($name, 1)])) {
                    $gs['font'] = pdf_font($doc, $fonts_dict['dict'][substr($name, 1)]);
                }
                $gs['size'] = end($n) ?: 0.0;
                break;
            case 'Tc': $gs['tc'] = end($n) ?: 0.0; break;
            case 'Tw': $gs['tw'] = end($n) ?: 0.0; break;
            case 'Tz': $gs['th'] = (end($n) ?: 100.0) / 100; break;
            case 'TL': $gs['tl'] = end($n) ?: 0.0; break;
            case 'Td':
            case 'TD':
                if (count($n) >= 2) {
                    [$tx, $ty] = array_slice($n, -2);
                    if ($op === 'TD') $gs['tl'] = -$ty;
                    $tm = $tlm = pdf_mat_mul([1, 0, 0, 1, $tx, $ty], $tlm);
                }
                break;
            case 'Tm': if (count($n) >= 6) $tm = $tlm = array_slice($n, -6); break;
            case 'T*': $tm = $tlm = pdf_mat_mul([1, 0, 0, 1, 0, -$gs['tl']], $tlm); break;
            case "'":
            case '"':
                if ($op === '"' && count($n) >= 3) { $gs['tw'] = $n[count($n) - 3]; $gs['tc'] = $n[count($n) - 2]; }
                $tm = $tlm = pdf_mat_mul([1, 0, 0, 1, 0, -$gs['tl']], $tlm);
                // fall through to Tj
            case 'Tj':
                $s = end($ops);
                if (is_array($s) && isset($s['str'])) $show($s['str']);
                break;
            case 'TJ':
                $arr = end($ops);
                foreach (($arr['arr'] ?? []) as $el) {
                    if (is_array($el) && isset($el['str'])) {
                        $show($el['str']);
                    } elseif (is_int($el) || is_float($el)) {
                        $tm = pdf_mat_mul([1, 0, 0, 1, -$el / 1000 * $gs['size'] * $gs['th'], 0], $tm);
                    }
                }
                break;
            case 'Do':
                $name = end($ops);
                $ref  = is_string($name) && $xobjects !== null ? ($xobjects['dict'][substr($name, 1)] ?? null) : null;
                if ($ref !== null && pdf_dict_get($doc, $ref, 'Subtype') === '/Form' && ($form = pdf_ref_stream($doc, $ref)) !== null) {
                    $matrix = pdf_dict_get($doc, $ref, 'Matrix');
                    $fm     = array_map(fn($v) => (float)pdf_resolve($doc, $v), $matrix['arr'] ?? [1, 0, 0, 1, 0, 0]);
                    $res    = pdf_dict_get($doc, $ref, 'Resources') ?? $resources;
                    pdf_run_content($doc, $form, $res, pdf_mat_mul($fm, $gs['ctm']), $frags, $depth + 1);
                }
                break;
            case 'BI':
                // Inline image: skip its binary data up to "EI"
                if (preg_match('/\sEI(?=\s|$)/', $data, $em, PREG_OFFSET_CAPTURE, $i)) $i = $em[0][1] + 3;
                break;
        }
        $ops = [];
    }
}

// ─── Layout ────────────────────────────────────────────────────────────────────────────────────

/**
 * Joins a page's fragments into text lines: fragments on the same baseline form one line (top to bottom),
 * ordered left to right. Touching fragments are glued, a word gap becomes one space and a column gap
 * at least two, padded to the fragment's column so columns roughly line up like `pdftotext -layout`.
 */
function pdf_fragments_to_text(array $frags): string {
    $sizes = array_column($frags, 'size');
    sort($sizes);
    $cw   = max(1.0, $sizes[intdiv(count($sizes), 2)] * 0.5); // approximate character width
    $minx = min(array_column($frags, 'x'));

    usort($frags, fn($a, $b) => [$b['y'], $a['x']] <=> [$a['y'], $b['x']]);
    $lines = [];
    foreach ($frags as $f) {
        $last = count($lines) - 1;
        if ($last >= 0 && abs($lines[$last]['y'] - $f['y']) <= 0.4 * min($lines[$last]['size'], $f['size'])) {
            $lines[$last]['frags'][] = $f;
            continue;
        }
        $lines[] = ['y' => $f['y'], 'size' => $f['size'], 'frags' => [$f]];
    }

    $out    = [];
    $prev_y = null;
    foreach ($lines as $line) {
        $row = $line['frags'];
        usort($row, fn($a, $b) => $a['x'] <=> $b['x']);
        $text = '';
        $end  = null;
        $seen = [];
        foreach ($row as $f) {
            // Text drawn twice at (almost) the same spot, e.g. simulated bold
            $dup = $f['text'] . '@' . round($f['x']);
            if (isset($seen[$dup])) continue;
            $seen[$dup] = true;

            $col = (int)round(($f['x'] - $minx) / $cw);
            if ($end === null) {
                $text = str_repeat(' ', max(0, $col)) . $f['text'];
            } else {
                $gap = $f['x'] - $end;
                if ($gap < 0.12 * $f['size'])    $sep = '';
                elseif ($gap < 0.8 * $f['size']) $sep = ' ';
                else                              $sep = str_repeat(' ', max(2, $col - mb_strlen($text, 'UTF-8')));
                $text .= $sep . $f['text'];
            }
            $end = max($end ?? $f['x1'], $f['x1']);
        }
        if ($prev_y !== null && $prev_y - $line['y'] > 2 * $line['size']) $out[] = '';
        $out[]  = rtrim($text);
        $prev_y = $line['y'];
    }
    return implode("\n", $out);
}
