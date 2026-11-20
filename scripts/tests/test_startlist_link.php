<?php
/**
 * Start list import links: a livetiming.pl contest page or a direct PDF link on livetiming.pl
 * (e.g. live.livetiming.pl — the contest pages are blocked for the hosting by Cloudflare).
 */
require __DIR__ . '/_assert.php';
require __DIR__ . '/../../includes/startlist_parse.php';

$pdf = 'https://live.livetiming.pl/zak/2026/11_21_oswiecim/startowa.pdf';
check('pdf on live.livetiming.pl', sl_startlist_pdf_url($pdf), $pdf);
check('pdf trimmed, upper-case extension', sl_startlist_pdf_url("  https://live.livetiming.pl/zak/A.PDF "), 'https://live.livetiming.pl/zak/A.PDF');
check('pdf with query', sl_startlist_pdf_url('https://livetiming.pl/files/lista.pdf?v=2'), 'https://livetiming.pl/files/lista.pdf?v=2');
check('pdf fragment dropped', sl_startlist_pdf_url('https://live.livetiming.pl/a/startowa.pdf#page=2'), 'https://live.livetiming.pl/a/startowa.pdf');

check('http rejected', sl_startlist_pdf_url('http://live.livetiming.pl/zak/startowa.pdf'), null);
check('other host rejected', sl_startlist_pdf_url('https://evil.com/livetiming.pl/startowa.pdf'), null);
check('suffix trick rejected', sl_startlist_pdf_url('https://livetiming.pl.evil.com/startowa.pdf'), null);
check('userinfo trick rejected', sl_startlist_pdf_url('https://user@evil.com/x.livetiming.pl/a.pdf'), null);
check('port rejected', sl_startlist_pdf_url('https://live.livetiming.pl:8443/a.pdf'), null);
check('not a pdf', sl_startlist_pdf_url('https://live.livetiming.pl/zak/wyniki.lxf'), null);
check('contest page is not a pdf', sl_startlist_pdf_url('https://livetiming.pl/contest/e90a051a-e507-45d5-a67e-e8b412394eb0'), null);

check('pool 50m kept', sl_pool_param('50m'), '50m');
check('pool 25m kept', sl_pool_param('25m'), '25m');
check('pool other → 25m', sl_pool_param('33m'), '25m');
check('pool missing → 25m', sl_pool_param(null), '25m');

finish();
