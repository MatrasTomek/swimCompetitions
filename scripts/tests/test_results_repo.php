<?php
/**
 * Needs a local MongoDB — run in the dev api container:
 *   docker compose -f dev/docker-compose.yml exec -e TEST_MONGO_URI=mongodb://mongo:27017 api php scripts/tests/test_results_repo.php
 * The test drops the "results" collection of the swim_test database, so it pins both the URI and the database
 * before config.php loads (includes/secrets.php may point MONGO_URI at a real cluster).
 */
require __DIR__ . '/_assert.php';

$uri = getenv('TEST_MONGO_URI');
if (!$uri) { fwrite(STDERR, "Ustaw TEST_MONGO_URI (np. mongodb://mongo:27017) — test nie łączy się z bazą z secrets.php.\n"); exit(1); }
define('MONGO_URI', $uri);
define('MONGO_DB', 'swim_test');

// secrets.php defines MONGO_URI too — keep ours and hide only that warning
set_error_handler(fn(int $no, string $msg) => str_contains($msg, 'MONGO_URI already defined'));
require __DIR__ . '/../../includes/config.php';
restore_error_handler();

require __DIR__ . '/../../includes/mongo.php';
if (!mongo_available()) { fwrite(STDERR, "Brak rozszerzenia mongodb albo vendor/ — uruchom w kontenerze api (dev/docker-compose.yml).\n"); exit(1); }
require __DIR__ . '/../../includes/user_repo.php';

mongo_results()->drop();
// An index left by an older version (unique on eventNr) is replaced
mongo_results()->createIndex(['userId' => 1, 'memberId' => 1, 'contestUuid' => 1, 'eventNr' => 1], ['unique' => true, 'name' => 'result_unique']);
results_ensure_indexes();
results_ensure_indexes(); // idempotent
$indexNames = array_map(fn($i) => $i->getName(), iterator_to_array(mongo_results()->listIndexes()));
check('old eventNr index dropped', in_array('result_unique', $indexNames, true), false);
check('eventId index present', in_array('result_event_unique', $indexNames, true), true);

$row = fn(string $member, int $nr, string $date, string $time, int $ms) => [
    'memberId' => $member, 'contestUuid' => 'c1', 'contestName' => 'Mityng', 'contestCity' => 'Kraków',
    'eventId' => "e$nr", 'eventNr' => $nr, 'date' => $date, 'poolLength' => 25, 'distance' => 100, 'stroke' => 'dowolny',
    'time' => $time, 'timeMs' => $ms, 'points' => null,
];

check('insert', results_upsert_many('uA', [$row('m1', 1, '2026-03-14', '1:05.32', 65320), $row('m1', 2, '2026-12-31', '1:04.00', 64000)]), 2);
check('re-fetch overwrites', results_upsert_many('uA', [$row('m1', 1, '2026-03-14', '1:05.00', 65000)]), 1);
check('no duplicates', mongo_results()->countDocuments(['userId' => 'uA']), 2);
check('overwritten time', results_list('uA', 2026)[1]['time'], '1:05.00');
check('empty upsert', results_upsert_many('uA', []), 0);

results_upsert_many('uA', [$row('m2', 1, '2027-01-01', '59.00', 59000)]);
results_upsert_many('uB', [$row('m9', 1, '2026-05-05', '58.00', 58000)]);

$list = results_list('uA', 2026);
check('year boundary 2026', array_map(fn($r) => $r['date'], $list), ['2026-12-31', '2026-03-14']);
check('year boundary 2027', count(results_list('uA', 2027)), 1);
check('no userId/_id in rows', array_key_exists('userId', $list[0]) || array_key_exists('_id', $list[0]), false);
check('fetchedAt iso', (bool)preg_match('/^\d{4}-\d{2}-\d{2}T/', (string)$list[0]['fetchedAt']), true);
check('isolation between accounts', count(results_list('uB', 2026)), 1);
check('member filter', count(results_list('uA', 2026, 'm2')), 0);
check('member filter 2027', count(results_list('uA', 2027, 'm2')), 1);

check('delete member', results_delete_member('uA', 'm1'), 2);
check('delete user', results_delete_user('uA'), 1);
check('other account untouched', mongo_results()->countDocuments(['userId' => 'uB']), 1);

// Two fetches of the same contest running at once: whatever the interleaving, the current results survive
mongo_results()->drop(); results_ensure_indexes();
$a = [$row('m1', 1, '2026-03-14', '1:05.32', 65320), $row('m1', 2, '2026-03-14', '40.00', 40000)];
$b = $a; // the other request parsed the same file
results_upsert_many('uA', $a); results_upsert_many('uA', $b);           // A writes, B writes…
results_delete_stale('uA', 'c1', $a); results_delete_stale('uA', 'c1', $b); // …then A cleans up, then B
check('interleaved fetches keep the results', mongo_results()->countDocuments(['userId' => 'uA', 'contestUuid' => 'c1']), 2);

$newer = [$a[0]]; // B saw a newer file, where event 2 was withdrawn
results_upsert_many('uA', $a); results_upsert_many('uA', $newer);
results_delete_stale('uA', 'c1', $a); results_delete_stale('uA', 'c1', $newer);
check('interleaved fetches: the last cleanup wins, nothing else is lost', array_map(fn($r) => $r['eventNr'], results_list('uA', 2026)), [1]);
// Two events with the same number in one LENEX file (different eventid) are two results, not one
mongo_results()->drop(); results_ensure_indexes();
$heat  = $row('m1', 1, '2026-03-14', '1:05.32', 65320);
$other = ['eventId' => 'e1b', 'distance' => 50, 'stroke' => 'motylkowy', 'time' => '31.00', 'timeMs' => 31000] + $row('m1', 1, '2026-03-15', '', 0);
check('same number: both stored', results_replace_contest('uA', 'c1', [$heat, $other]), 2);
check('same number: both listed', array_map(fn($r) => [$r['eventNr'], $r['stroke'], $r['time']], results_list('uA', 2026)),
    [[1, 'motylkowy', '31.00'], [1, 'dowolny', '1:05.32']]);

// Rows stored before eventId existed are replaced by the next fetch of their contest
mongo_results()->drop(); results_ensure_indexes();
$legacy = $row('m1', 1, '2026-03-14', '1:06.00', 66000); unset($legacy['eventId']);
mongo_results()->insertOne(['userId' => 'uA'] + $legacy);
results_replace_contest('uA', 'c1', [$row('m1', 1, '2026-03-14', '1:05.32', 65320)]);
check('legacy row replaced', array_map(fn($r) => [$r['eventId'] ?? null, $r['time']], results_list('uA', 2026)), [['e1', '1:05.32']]);

check('stale cleanup leaves other contests and accounts alone', results_delete_stale('uZ', 'c1', []) + results_delete_stale('uA', 'other', []), 0);

// Deleting a club member or an account removes their results (no orphaned documents)
mongo_users()->drop();
mongo_results()->drop();
$uid = uuid_v4();
mongo_users()->insertOne(['userId' => $uid, 'userEmail' => 'cascade@test.pl', 'clubItems' => ['clubMembers' => []]]);
$kept    = member_add($uid, ['memberName' => 'Jan Kowalski', 'memberSex' => 'M', 'memberBirthYear' => 2013]);
$removed = member_add($uid, ['memberName' => 'Ola Nowak',    'memberSex' => 'K', 'memberBirthYear' => 2014]);
results_upsert_many($uid, [$row($kept['memberId'], 1, '2026-03-14', '1:05.32', 65320), $row($removed['memberId'], 2, '2026-03-14', '40.00', 40000)]);
results_upsert_many('uB', [$row('m9', 1, '2026-05-05', '58.00', 58000)]);

check('member_delete ok', member_delete($uid, $removed['memberId']), true);
check('member results removed', mongo_results()->countDocuments(['userId' => $uid, 'memberId' => $removed['memberId']]), 0);
check('other member results kept', mongo_results()->countDocuments(['userId' => $uid, 'memberId' => $kept['memberId']]), 1);
check('unknown member_delete false', member_delete($uid, uuid_v4()), false);
check('results unchanged after failed delete', mongo_results()->countDocuments(['userId' => $uid]), 1);

check('user_delete ok', user_delete($uid), true);
check('account results removed', mongo_results()->countDocuments(['userId' => $uid]), 0);
check('other account results kept', mongo_results()->countDocuments(['userId' => 'uB']), 1);

mongo_users()->drop();
mongo_results()->drop();
finish();
