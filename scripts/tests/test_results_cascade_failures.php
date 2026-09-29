<?php
/**
 * Deleting a club member / account together with their results must stay recoverable when the second
 * MongoDB write fails: the member/account may not disappear while its results are left behind.
 * Needs a throwaway MongoDB with test commands (failCommand) — never the dev or production database:
 *   docker run -d --rm --name swim-mongo-failpoint --network dev_default mongo:7 --setParameter enableTestCommands=1
 *   docker compose -f dev/docker-compose.yml exec -e TEST_MONGO_FAILPOINT_URI=mongodb://swim-mongo-failpoint:27017 api php scripts/tests/test_results_cascade_failures.php
 *   docker stop swim-mongo-failpoint
 */
require __DIR__ . '/_assert.php';

$uri = getenv('TEST_MONGO_FAILPOINT_URI');
if (!$uri) { fwrite(STDERR, "Ustaw TEST_MONGO_FAILPOINT_URI (tymczasowy MongoDB z enableTestCommands=1).\n"); exit(1); }
define('MONGO_URI', $uri);
define('MONGO_DB', 'swim_test');

// secrets.php defines MONGO_URI too — keep ours and hide only that warning
set_error_handler(fn(int $no, string $msg) => str_contains($msg, 'MONGO_URI already defined'));
require __DIR__ . '/../../includes/config.php';
restore_error_handler();

require __DIR__ . '/../../includes/mongo.php';
if (!mongo_available()) { fwrite(STDERR, "Brak rozszerzenia mongodb albo vendor/ — uruchom w kontenerze api (dev/docker-compose.yml).\n"); exit(1); }
require __DIR__ . '/../../includes/user_repo.php';

/** Lets the first of the given commands through and fails every later one until failpoint_off(). */
function fail_second(array $commands): void {
    mongo_db()->getManager()->executeCommand('admin', new MongoDB\Driver\Command([
        'configureFailPoint' => 'failCommand',
        'mode'               => ['skip' => 1],
        'data'               => ['failCommands' => $commands, 'errorCode' => 91, 'errorMessage' => 'injected failure'],
    ]));
}

function failpoint_off(): void {
    mongo_db()->getManager()->executeCommand('admin', new MongoDB\Driver\Command(['configureFailPoint' => 'failCommand', 'mode' => 'off']));
}

/** Runs $fn and reports whether it threw (the injected failure must surface, not be swallowed). */
function throws(callable $fn): bool {
    try { $fn(); return false; } catch (Throwable $e) { return true; }
}

$row = fn(string $member) => [
    'memberId' => $member, 'contestUuid' => 'c1', 'contestName' => 'Mityng', 'contestCity' => 'Kraków',
    'eventNr' => 1, 'date' => '2026-03-14', 'poolLength' => 25, 'distance' => 100, 'stroke' => 'dowolny',
    'time' => '1:05.32', 'timeMs' => 65320, 'points' => null,
];
$memberExists = fn(string $uid, string $mid) => mongo_users()->countDocuments(['userId' => $uid, 'clubItems.clubMembers.memberId' => $mid]) === 1;

failpoint_off();
mongo_db()->drop();
results_ensure_indexes();

// ── member_delete: the second of its two writes fails ──
$uid = uuid_v4();
mongo_users()->insertOne(['userId' => $uid, 'userEmail' => 'fail@test.pl', 'clubItems' => ['clubMembers' => []]]);
$m = member_add($uid, ['memberName' => 'Ola Nowak', 'memberSex' => 'K', 'memberBirthYear' => 2014]);
results_upsert_many($uid, [$row($m['memberId'])]);

fail_second(['update', 'delete']);
check('member_delete surfaces the failure', throws(fn() => member_delete($uid, $m['memberId'])), true);
failpoint_off();
check('member still there after failure (retry possible)', $memberExists($uid, $m['memberId']), true);
check('retry member_delete ok', member_delete($uid, $m['memberId']), true);
check('member gone after retry', $memberExists($uid, $m['memberId']), false);
check('member results gone after retry', mongo_results()->countDocuments(['userId' => $uid]), 0);

// ── user_delete: the second of its two deletes fails ──
$m2 = member_add($uid, ['memberName' => 'Jan Kowalski', 'memberSex' => 'M', 'memberBirthYear' => 2013]);
results_upsert_many($uid, [$row($m2['memberId'])]);

fail_second(['delete']);
check('user_delete surfaces the failure', throws(fn() => user_delete($uid)), true);
failpoint_off();
check('account still there after failure (retry possible)', mongo_users()->countDocuments(['userId' => $uid]), 1);
check('retry user_delete ok', user_delete($uid), true);
check('account gone after retry', mongo_users()->countDocuments(['userId' => $uid]), 0);
check('account results gone after retry', mongo_results()->countDocuments(['userId' => $uid]), 0);

// ── nothing is deleted for an entity that does not exist ──
results_upsert_many('uX', [$row('mX')]);
check('unknown member_delete false', member_delete('uX', 'mX'), false);
check('unknown user_delete false', user_delete('uX'), false);
check('results of unknown entities untouched', mongo_results()->countDocuments(['userId' => 'uX']), 1);

mongo_db()->drop();
finish();
