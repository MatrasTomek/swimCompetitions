<?php
require __DIR__ . '/_assert.php';
require __DIR__ . '/../../includes/lenex_fetch.php';

check('ms HH:MM:SS.hh', lenex_time_ms('00:01:05.32'), 65320);
check('ms M:SS.hh',     lenex_time_ms('1:05.32'), 65320);
check('ms 0:SS.hh',     lenex_time_ms('0:27.34'), 27340);
check('ms SS.hh',       lenex_time_ms('27.34'), 27340);
check('ms over 1h',     lenex_time_ms('01:02:03.45'), 3723450);
check('ms invalid',     lenex_time_ms('NT'), null);
check('ms empty',       lenex_time_ms(''), null);
// Out-of-range parts are rejected, not converted
check('ms M:SS seconds 99',     lenex_time_ms('1:99.00'), null);
check('ms MM:SS seconds 60',    lenex_time_ms('00:60.00'), null);
check('ms H:MM:SS seconds 60',  lenex_time_ms('1:02:60.00'), null);
check('ms H:MM:SS minutes 60',  lenex_time_ms('1:60:00.00'), null);
check('ms SS seconds 60',       lenex_time_ms('60.00'), null);
check('ms one-digit seconds after minutes', lenex_time_ms('1:5.32'), null);
check('ms max valid seconds',   lenex_time_ms('59.99'), 59990);
check('ms max valid H:MM:SS',   lenex_time_ms('00:59:59.99'), 3599990);
check('fmt minutes',    swim_time_format(65320), '1:05.32');
check('fmt seconds',    swim_time_format(27340), '27.34');
check('fmt long',       swim_time_format(3723450), '62:03.45');

$p = lenex_parse_full(file_get_contents(__DIR__ . '/fixtures/sample.lef'));
check('ok', $p['ok'], true);
check('meet', $p['meet'], ['name' => 'Mityng Testowy 2026', 'city' => 'Kraków', 'poolLength' => 25, 'date' => '2026-03-14']);
check('events by eventid (no relay, no unknown stroke)', $p['events'], [
    '101' => ['eventNr' => 1, 'distance' => 100,  'stroke' => 'dowolny',    'date' => '2026-03-14'],
    '102' => ['eventNr' => 2, 'distance' => 50,   'stroke' => 'grzbietowy', 'date' => '2026-03-14'],
    '104' => ['eventNr' => 4, 'distance' => 200,  'stroke' => 'zmienny',    'date' => '2026-03-15'],
    '106' => ['eventNr' => 6, 'distance' => 1500, 'stroke' => 'dowolny',    'date' => '2026-03-15'],
]);
check('athlete count', count($p['athletes']), 3);
$was = $p['athletes'][0];
check('was identity', [$was['lastname'], $was['firstname'], $was['birthYear'], $was['gender']], ['Wąs', 'Amelia', 2014, 'K']);
check('was results (relay, DSQ, unknown stroke skipped; 0 points → null)', $was['results'], [
    ['eventId' => '101', 'eventNr' => 1, 'time' => '1:05.32', 'timeMs' => 65320, 'points' => 312],
    ['eventId' => '102', 'eventNr' => 2, 'time' => '34.10',   'timeMs' => 34100, 'points' => null],
]);
check('lukasik results (NT skipped)', $p['athletes'][1]['results'], [
    ['eventId' => '104', 'eventNr' => 4, 'time' => '2:45.99',  'timeMs' => 165990,  'points' => 280],
    ['eventId' => '106', 'eventNr' => 6, 'time' => '62:03.45', 'timeMs' => 3723450, 'points' => 10],
]);
check('nowak without birthdate', $p['athletes'][2]['birthYear'], null);

$lcm = lenex_parse_full(str_replace('course="SCM"', 'course="LCM"', file_get_contents(__DIR__ . '/fixtures/sample.lef')));
check('LCM → 50', $lcm['meet']['poolLength'], 50);

// Two events with the same number (e.g. numbering restarted in session 2) stay separate — eventid identifies them
$dupNr = '<LENEX version="3.0"><MEETS><MEET name="M" city="C" course="SCM"><SESSIONS>'
       . '<SESSION number="1" date="2026-03-14"><EVENTS><EVENT eventid="10" number="1"><SWIMSTYLE distance="100" stroke="FREE" relaycount="1"/></EVENT></EVENTS></SESSION>'
       . '<SESSION number="2" date="2026-03-15"><EVENTS><EVENT eventid="20" number="1"><SWIMSTYLE distance="50" stroke="FLY" relaycount="1"/></EVENT></EVENTS></SESSION>'
       . '</SESSIONS><CLUBS><CLUB><ATHLETES><ATHLETE athleteid="1" lastname="A" firstname="B" birthdate="2014-01-01" gender="F"><RESULTS>'
       . '<RESULT eventid="10" swimtime="00:01:05.32"/><RESULT eventid="20" swimtime="00:00:31.00"/>'
       . '</RESULTS></ATHLETE></ATHLETES></CLUB></CLUBS></MEET></MEETS></LENEX>';
$dup = lenex_parse_full($dupNr);
check('same number: both events kept', $dup['events'], [
    '10' => ['eventNr' => 1, 'distance' => 100, 'stroke' => 'dowolny',   'date' => '2026-03-14'],
    '20' => ['eventNr' => 1, 'distance' => 50,  'stroke' => 'motylkowy', 'date' => '2026-03-15'],
]);
check('same number: results point at their own event', array_map(fn($r) => [$r['eventId'], $r['time']], $dup['athletes'][0]['results']),
    [['10', '1:05.32'], ['20', '31.00']]);

check('bad xml', lenex_parse_full('<nope')['ok'], false);

// Invitation files (zaproszenie.lxf) and sparse files have no CLUBS / EVENTS / RESULTS — no PHP warnings allowed
set_error_handler(function (int $no, string $msg) { throw new ErrorException($msg, 0, $no); });
$invitation = '<LENEX version="3.0"><MEETS><MEET name="Zaproszenie" city="Busko" course="SCM">'
            . '<SESSIONS><SESSION number="1" date="2026-05-31"/></SESSIONS></MEET></MEETS></LENEX>';
try { $r = lenex_parse_full($invitation); } catch (ErrorException $e) { $r = ['warning' => $e->getMessage()]; }
check('invitation without CLUBS', $r, ['ok' => false, 'error' => 'LENEX nie zawiera sekcji ATHLETES']);
$sparse = '<LENEX version="3.0"><MEETS><MEET name="X" city="Y" course="LCM"><SESSIONS><SESSION number="1" date="2026-05-31"/></SESSIONS>'
        . '<CLUBS><CLUB name="K"/><CLUB name="L"><ATHLETES><ATHLETE athleteid="1" lastname="A" firstname="B" birthdate="2014-01-01"/></ATHLETES></CLUB></CLUBS>'
        . '</MEET></MEETS></LENEX>';
try { $r = lenex_parse_full($sparse); } catch (ErrorException $e) { $r = ['warning' => $e->getMessage()]; }
check('club without ATHLETES, athlete without RESULTS', [$r['ok'] ?? null, $r['events'] ?? null, $r['athletes'][0]['results'] ?? null], [true, [], []]);
restore_error_handler();

finish();
