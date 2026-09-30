<?php
require __DIR__ . '/_assert.php';
require __DIR__ . '/../../includes/lenex_fetch.php';
require __DIR__ . '/../../includes/results_import.php';

$parsed  = lenex_parse_full(file_get_contents(__DIR__ . '/fixtures/sample.lef'));
$matched = ['m1' => $parsed['athletes'][0], 'm2' => $parsed['athletes'][1]];
$rows    = results_build_rows($parsed, $matched, 'c0ffee00-0000-4000-8000-000000000001');

check('row count', count($rows), 4);
check('first row', $rows[0], [
    'memberId' => 'm1', 'contestUuid' => 'c0ffee00-0000-4000-8000-000000000001',
    'contestName' => 'Mityng Testowy 2026', 'contestCity' => 'Kraków', 'eventId' => 101, 'eventNr' => 1, 'date' => '2026-03-14',
    'poolLength' => 25, 'distance' => 100, 'stroke' => 'dowolny', 'time' => '1:05.32', 'timeMs' => 65320, 'points' => 312,
]);
check('medley row date from session 2', [$rows[2]['memberId'], $rows[2]['eventNr'], $rows[2]['date']], ['m2', 4, '2026-03-15']);

// Session without a date falls back to the meet date; no date at all → row skipped
$noSessionDate = $parsed;
$noSessionDate['events'][101]['date'] = '';
check('fallback to meet date', results_build_rows($noSessionDate, ['m1' => $parsed['athletes'][0]], 'u')[0]['date'], '2026-03-14');
$noDate = $noSessionDate;
$noDate['meet']['date'] = '';
check('no date → skipped', count(results_build_rows($noDate, ['m1' => $parsed['athletes'][0]], 'u')), 1);

// Prelims and the final share the event number: two rows, each with the date of its own session
$rounds = [
    'meet'   => ['name' => 'X', 'city' => 'Y', 'poolLength' => 25, 'date' => '2026-09-26'],
    'events' => [
        1059 => ['nr' => 1, 'distance' => 25, 'stroke' => 'dowolny', 'date' => '2026-09-26'],
        4822 => ['nr' => 1, 'distance' => 25, 'stroke' => 'dowolny', 'date' => '2026-09-27'],
    ],
];
$swimmer = ['results' => [
    ['eventId' => 1059, 'eventNr' => 1, 'time' => '15.10', 'timeMs' => 15100, 'points' => null],
    ['eventId' => 4822, 'eventNr' => 1, 'time' => '14.90', 'timeMs' => 14900, 'points' => null],
]];
check('two rounds of one event number', array_map(fn($r) => [$r['eventId'], $r['eventNr'], $r['date'], $r['time']], results_build_rows($rounds, ['m1' => $swimmer], 'u')), [
    [1059, 1, '2026-09-26', '15.10'],
    [4822, 1, '2026-09-27', '14.90'],
]);

finish();
