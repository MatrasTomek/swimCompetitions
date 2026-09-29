<?php
/**
 * Club members without the removed hand-entered times. Needs a local MongoDB — run in the dev api container:
 *   docker compose -f dev/docker-compose.yml exec -e TEST_MONGO_URI=mongodb://mongo:27017 api php scripts/tests/test_members.php
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

mongo_users()->drop();

$legacyMember = ['memberId' => uuid_v4(), 'memberName' => 'Jan Kowalski', 'memberSex' => 'M', 'memberBirthYear' => 2013,
                 'memberTimes' => [['competitionId' => uuid_v4(), 'competitionTime' => '1:05.32']]];
$uid = uuid_v4();
mongo_users()->insertOne([
    'userId' => $uid, 'userEmail' => 'legacy@test.pl', 'status' => 'active',
    'clubItems' => ['clubMembers' => [$legacyMember]],
]);
$other = uuid_v4();
mongo_users()->insertOne(['userId' => $other, 'userEmail' => 'fresh@test.pl', 'status' => 'active', 'clubItems' => ['clubMembers' => []]]);

// API output never carries memberTimes, even for accounts that still store them
$public = user_public(user_find_by_id($uid));
check('public member fields', $public['clubItems']['clubMembers'][0], [
    'memberId' => $legacyMember['memberId'], 'memberName' => 'Jan Kowalski', 'memberSex' => 'M', 'memberBirthYear' => 2013,
]);

// New members are stored without memberTimes
$m = member_add($other, ['memberName' => 'Ola Nowak', 'memberSex' => 'K', 'memberBirthYear' => 2014]);
check('member_add returns no memberTimes', array_key_exists('memberTimes', $m), false);
$stored = user_find_by_id($other)['clubItems']['clubMembers'][0];
check('stored member has no memberTimes', array_key_exists('memberTimes', $stored), false);

// Hand-entered time functions are gone
check('member_time_add removed', function_exists('member_time_add'), false);
check('member_time_delete removed', function_exists('member_time_delete'), false);
check('member_time_validate removed', function_exists('member_time_validate'), false);

// One-off cleanup of stored memberTimes (scripts/drop_member_times.php)
check('drop: accounts changed', user_drop_member_times(), 1);
check('drop: memberTimes removed', array_key_exists('memberTimes', user_find_by_id($uid)['clubItems']['clubMembers'][0]), false);
check('drop: member kept', user_find_by_id($uid)['clubItems']['clubMembers'][0]['memberName'], 'Jan Kowalski');
check('drop: idempotent', user_drop_member_times(), 0);

mongo_users()->drop();
finish();
