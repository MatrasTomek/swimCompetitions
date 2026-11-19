<?php
/**
 * Start list import from an uploaded PDF file (build_startlist_from_pdf_file): the file is parsed the same
 * way as a downloaded PDF, name/city/dates come from the PDF header, pool length from the chosen 25m/50m.
 * A real start list fixture is needed — pass its path, or skip if absent (the fixture is not in the repo).
 */
require __DIR__ . '/_assert.php';
require __DIR__ . '/../../includes/startlist_parse.php';

// Non-PDF / too-small input is rejected before any parsing
$r = build_startlist_from_pdf_file('not a pdf at all', 'Olimpijczyk', '25m');
check('rejects non-PDF: ok', $r['ok'], false);
check('rejects non-PDF: message', str_contains($r['error'], 'PDF'), true);

$r = build_startlist_from_pdf_file('%PDF-1.4 but basically empty', 'Olimpijczyk', '25m');
check('rejects tiny PDF', $r['ok'], false);

$fixture = getenv('STARTLIST_PDF') ?: (__DIR__ . '/../../fixtures/startowa.pdf');
if (!is_file($fixture)) {
    echo "skip  real-PDF checks (no fixture at $fixture; set STARTLIST_PDF=/path/to/startowa.pdf)\n";
    finish();
}

$data = file_get_contents($fixture);
$r = build_startlist_from_pdf_file($data, 'Olimpijczyk', '50m');
check('real PDF: ok', $r['ok'], true);
check('real PDF: pool length from arg', $r['zawody']['basen'], '50m');
check('real PDF: name from header', $r['zawody']['nazwa'] !== '', true);
check('real PDF: has starts', $r['starts'] > 0, true);
check('real PDF: no pdf_url (uploaded)', $r['pdf_url'] ?? null, null);

$r = build_startlist_from_pdf_file($data, 'Nieistniejący Klub XYZ', '25m');
check('real PDF: unknown club', $r['ok'], false);

finish();
