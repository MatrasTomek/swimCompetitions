<?php
/**
 * results_import_contest(): validation, rate limit, LENEX errors and storing matched members' results.
 * LENEX download is replaced by a stub, so no network is needed. Needs a local MongoDB — run in the dev api container:
 *   docker compose -f dev/docker-compose.yml exec -e TEST_MONGO_URI=mongodb://mongo:27017 api php scripts/tests/test_results_import.php
 */
require __DIR__ . '/_assert.php';

$uri = getenv('TEST_MONGO_URI');
if (!$uri) { fwrite(STDERR, "Ustaw TEST_MONGO_URI (np. mongodb://mongo:27017) — test nie łączy się z bazą z secrets.php.\n"); exit(1); }
define('MONGO_URI', $uri);
define('MONGO_DB', 'swim_test');
// Own rate-limit file, so the test never touches the app's counters
define('ACCOUNT_RESULTS_RATE_FILE', sys_get_temp_dir() . '/swim_test_results_rate.json');
@unlink(ACCOUNT_RESULTS_RATE_FILE);

// secrets.php defines MONGO_URI too — keep ours and hide only that warning
set_error_handler(fn(int $no, string $msg) => str_contains($msg, 'MONGO_URI already defined'));
require __DIR__ . '/../../includes/config.php';
restore_error_handler();

require __DIR__ . '/../../includes/mongo.php';
if (!mongo_available()) { fwrite(STDERR, "Brak rozszerzenia mongodb albo vendor/ — uruchom w kontenerze api (dev/docker-compose.yml).\n"); exit(1); }
require __DIR__ . '/../../includes/user_repo.php';
require __DIR__ . '/../../includes/results_import.php';

mongo_db()->drop();
results_ensure_indexes();

const CONTEST = 'https://livetiming.pl/contest/c0ffee00-0000-4000-8000-000000000001';
$xml     = file_get_contents(__DIR__ . '/fixtures/sample.lef');
$fetchOk = function (string $uuid) use ($xml, &$askedUuid): array { $askedUuid = $uuid; return ['ok' => true, 'xml' => $xml]; };
$fetchNo = fn(string $uuid): array => ['ok' => false, 'error' => 'Wyniki LENEX jeszcze niedostępne'];

$user = [
    'userId'    => 'u-import',
    'clubItems' => ['clubMembers' => [
        ['memberId' => 'm-was', 'memberName' => 'Amelia Wąs',   'memberSex' => 'K', 'memberBirthYear' => 2014],
        ['memberId' => 'm-luk', 'memberName' => 'Jan Łukasik',  'memberSex' => 'M', 'memberBirthYear' => 2013],
        ['memberId' => 'm-zie', 'memberName' => 'Piotr Zieliński', 'memberSex' => 'M', 'memberBirthYear' => 2012],
    ]],
];

// The import stores results for the members the account has when the file arrives, so the accounts exist in the database
mongo_users()->insertOne(['userEmail' => 'import@test.pl'] + $user);
mongo_users()->insertOne(['userId' => 'u-other', 'userEmail' => 'other@test.pl', 'clubItems' => $user['clubItems']]);

// Validation — nothing is downloaded
foreach (['', 'https://evil.pl/contest/c0ffee00-0000-4000-8000-000000000001', 'https://livetiming.pl/contest/abc',
          'https://livetiming.pl/contest/c0ffee00-0000-4000-8000-000000000001/results.lxf'] as $bad) {
    [$status] = results_import_contest($user, $bad, fn() => throw new LogicException('must not download'));
    check("bad url 400: '$bad'", $status, 400);
}
[$status, $body] = results_import_contest(['userId' => 'u-empty', 'clubItems' => ['clubMembers' => []]], CONTEST, $fetchOk);
check('no members 400', [$status, $body['error'], $body['code']], [400, 'Najpierw dodaj zawodników w „Moi zawodnicy”.', 'no_members']);

// LENEX not available yet → 409, nothing stored
[$status, $body] = results_import_contest($user, CONTEST, $fetchNo);
check('lenex unavailable 409', [$status, $body['error']], [409, 'Wyniki nie są jeszcze dostępne na livetiming.pl.']);
check('nothing stored after 409', mongo_results()->countDocuments(), 0);

// Success
[$status, $body] = results_import_contest($user, CONTEST, $fetchOk);
check('success 200', $status, 200);
check('downloaded by contest uuid', $askedUuid, 'c0ffee00-0000-4000-8000-000000000001');
check('response', $body, [
    'saved' => 4, 'members_matched' => 2, 'not_found' => ['Piotr Zieliński'], 'ambiguous' => [], 'no_results' => [],
    'competition' => ['name' => 'Mityng Testowy 2026', 'date' => '2026-03-14'],
]);
check('stored rows', mongo_results()->countDocuments(['userId' => 'u-import']), 4);
check('stored for members', count(results_list('u-import', 2026, 'm-was')) . '/' . count(results_list('u-import', 2026, 'm-luk')), '2/2');

// Re-fetch overwrites, no duplicates
[$status, $body] = results_import_contest($user, CONTEST, $fetchOk);
check('re-fetch 200 same count', [$status, $body['saved']], [200, 4]);
check('no duplicates', mongo_results()->countDocuments(['userId' => 'u-import']), 4);

// A member found in the file whose starts are all DNS/DSQ is reported separately — not counted as "saved for"
$allDns = str_replace(['<RESULT eventid="104" swimtime="00:02:45.99" points="280"/>', '<RESULT eventid="106" swimtime="01:02:03.45" points="10"/>'],
                      ['<RESULT eventid="104" swimtime="00:00:00.00" status="DNS"/>', '<RESULT eventid="106" swimtime="00:00:00.00" status="DNS"/>'], $xml);
[$status, $body] = results_import_contest($user, CONTEST, fn(string $uuid): array => ['ok' => true, 'xml' => $allDns]);
check('matched without valid results', [$status, $body['saved'], $body['members_matched'], $body['no_results'], $body['not_found']],
      [200, 2, 2, ['Jan Łukasik'], ['Piotr Zieliński']]);
[$status] = results_import_contest($user, CONTEST, $fetchOk); // back to the full file for the sections below

@unlink(ACCOUNT_RESULTS_RATE_FILE); // fresh rate-limit window for this section
// ── Re-fetch reconciles the contest: results that left the LENEX file disappear ──
$fetchXmlOf = fn(string $x) => fn(string $uuid): array => ['ok' => true, 'xml' => $x];
$rowsOf     = fn(string $member) => array_map(fn($r) => $r['eventNr'], results_list('u-import', 2026, $member));
$otherContest = ['memberId' => 'm-was', 'contestUuid' => 'other-contest', 'contestName' => 'Inne', 'contestCity' => 'X',
                 'eventId' => '909', 'eventNr' => 9, 'date' => '2026-04-01', 'poolLength' => 25, 'distance' => 50, 'stroke' => 'dowolny',
                 'time' => '40.00', 'timeMs' => 40000, 'points' => null];
results_upsert_many('u-import', [$otherContest]);
results_upsert_many('u-bystander', [$otherContest + ['contestUuid' => 'c0ffee00-0000-4000-8000-000000000001']]);

$removed = str_replace('<RESULT eventid="101" swimtime="00:01:05.32" points="312"/>', '', $xml);
[$status, $body] = results_import_contest($user, CONTEST, $fetchXmlOf($removed));
check('result removed from LENEX: 200, saved 3', [$status, $body['saved']], [200, 3]);
check('removed result gone, other kept', $rowsOf('m-was'), [9, 2]);

$dsq = str_replace('<RESULT eventid="102" swimtime="0:34.10" points="0"/>', '<RESULT eventid="102" swimtime="0:34.10" status="DSQ"/>', $removed);
[$status] = results_import_contest($user, CONTEST, $fetchXmlOf($dsq));
check('result changed to DSQ disappears', [$status, $rowsOf('m-was')], [200, [9]]);

member_update('u-import', 'm-luk', ['memberBirthYear' => 2012]); // Łukasik no longer matches
[$status, $body] = results_import_contest($user, CONTEST, $fetchXmlOf($dsq));
check('member no longer matching: rows removed', [$status, $body['not_found'], $rowsOf('m-luk')], [200, ['Jan Łukasik', 'Piotr Zieliński'], []]);
check('other contest of the account untouched', $rowsOf('m-was'), [9]);
check('same contest of another account untouched', mongo_results()->countDocuments(['userId' => 'u-bystander']), 1);

member_update('u-import', 'm-luk', ['memberBirthYear' => 2013]);

// A file with athletes but no results at all (entry list) must not wipe the history
[$status] = results_import_contest($user, CONTEST, $fetchOk);
check('full results back', count(results_list('u-import', 2026)), 5);
$entriesOnly = preg_replace('~<RESULTS>.*?</RESULTS>~s', '', $xml);
[$status, $body] = results_import_contest($user, CONTEST, $fetchXmlOf($entriesOnly));
check('entry list without results → 409', [$status, $body['error']], [409, 'Wyniki nie są jeszcze dostępne na livetiming.pl.']);
check('history kept after entry list', count(results_list('u-import', 2026)), 5);
check('internal fetchId not listed', array_key_exists('fetchId', results_list('u-import', 2026)[0]), false);

// Rate limit per account — fresh window for this section
@unlink(ACCOUNT_RESULTS_RATE_FILE);
for ($i = 0; $i < ACCOUNT_RESULTS_FETCH_MAX; $i++) results_import_contest($user, CONTEST, $fetchOk);
[$status, $body] = results_import_contest($user, CONTEST, $fetchOk);
check('rate limited 429', [$status, $body['error']], [429, 'Zbyt wiele pobrań wyników. Spróbuj ponownie za kilka minut.']);
[$status] = results_import_contest(['userId' => 'u-other'] + $user, CONTEST, $fetchOk);
check('other account not limited', $status, 200);

// ── A member or the account removed while the LENEX file is being downloaded leaves no orphaned results ──
@unlink(ACCOUNT_RESULTS_RATE_FILE);
$removeMemberMidDownload = function (string $uuid) use ($xml): array { member_delete('u-import', 'm-luk'); return ['ok' => true, 'xml' => $xml]; };
[$status, $body] = results_import_contest($user, CONTEST, $removeMemberMidDownload); // $user still lists m-luk, as the request saw it
check('member removed mid-download: not stored', [$status, $body['saved'], $body['members_matched'], mongo_results()->countDocuments(['userId' => 'u-import', 'memberId' => 'm-luk'])],
      [200, 2, 1, 0]);

$removeAccountMidDownload = function (string $uuid) use ($xml): array { user_delete('u-import'); return ['ok' => true, 'xml' => $xml]; };
[$status, $body] = results_import_contest($user, CONTEST, $removeAccountMidDownload);
check('account removed mid-download: 404', [$status, $body['error']], [404, 'Konto nie istnieje.']);
check('account removed mid-download: nothing stored', mongo_results()->countDocuments(['userId' => 'u-import']), 0);

mongo_db()->drop();
@unlink(ACCOUNT_RESULTS_RATE_FILE);
@unlink(ACCOUNT_RESULTS_RATE_FILE . '.lock');
finish();
