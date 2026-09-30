<?php
/**
 * User accounts in MongoDB (collection "users") — the only place that talks
 * to the database, so the storage can be swapped without touching the API.
 *
 * Document: userId, userEmail (login), userPassword (hash), userClub,
 * userInvoice (invoice details: companyName, street, postalCode, city, nip), status,
 * tokenVersion, e-mail/reset token hashes, timestamps and
 * clubItems.clubMembers[] (see CLAUDE.md).
 * Collection "results": one document per start of a club member, fetched from LENEX.
 */

require_once __DIR__ . '/mongo.php';
require_once __DIR__ . '/functions.php'; // with_file_lock()

use MongoDB\BSON\UTCDateTime;

const USER_STATUSES = ['pending_email', 'pending_approval', 'active', 'disabled'];
const MEMBER_SEXES  = ['M', 'K'];
const USER_INVOICE_FIELDS = ['companyName', 'street', 'postalCode', 'city', 'nip'];

// ── Helpers ──────────────────────────────────────────────────────────────────

function user_normalize_email(string $email): string {
    return mb_strtolower(trim($email), 'UTF-8');
}

/** A random one-time token (sent by e-mail) — only its SHA-256 is stored. */
function user_new_token(): string {
    return bin2hex(random_bytes(32));
}

function user_token_hash(string $token): string {
    return hash('sha256', $token);
}

function mongo_now(int $offset = 0): UTCDateTime {
    return new UTCDateTime((time() + $offset) * 1000);
}

/**
 * Runs $fn while holding the account's lock. Storing fetched results and removing a member / the account
 * touch two collections without a transaction; the lock keeps them from interleaving (a result stored for
 * a member that is being removed would be orphaned). The lock file lives in the system temp directory.
 */
function account_lock(string $userId, callable $fn) {
    return with_file_lock(sys_get_temp_dir() . '/swim_account_' . preg_replace('/[^A-Za-z0-9-]/', '', $userId) . '.lock', $fn);
}

/** Account as sent to the browser — never the password hash, tokens or tokenVersion. */
function user_public(array $u): array {
    $iso = fn($d) => $d instanceof UTCDateTime ? $d->toDateTime()->format('c') : null;
    return [
        'userId'      => $u['userId'],
        'userEmail'   => $u['userEmail'],
        'userClub'    => $u['userClub'] ?? '',
        'userInvoice' => user_invoice_public($u['userInvoice'] ?? null),
        'status'      => $u['status'],
        'createdAt'   => $iso($u['createdAt'] ?? null),
        'lastLoginAt' => $iso($u['lastLoginAt'] ?? null),
        'clubItems'   => ['clubMembers' => array_map('member_public', array_values($u['clubItems']['clubMembers'] ?? []))],
    ];
}

/** Club member for the browser — old accounts may still carry the removed memberTimes. */
function member_public(array $m): array {
    return [
        'memberId'        => $m['memberId'],
        'memberName'      => $m['memberName'],
        'memberSex'       => $m['memberSex'],
        'memberBirthYear' => $m['memberBirthYear'],
    ];
}

/** Invoice details for the browser — accounts created before they were required have none (null). */
function user_invoice_public($inv): ?array {
    if (!is_array($inv) || empty($inv['nip'])) return null;
    $out = [];
    foreach (USER_INVOICE_FIELDS as $k) $out[$k] = (string)($inv[$k] ?? '');
    return $out;
}

/** Short admin listing row (no club members). */
function user_summary(array $u): array {
    $p = user_public($u);
    $p['memberCount'] = count($p['clubItems']['clubMembers']);
    unset($p['clubItems']);
    return $p;
}

/** Unique index violation (e-mail already registered). */
function is_duplicate_key(Throwable $e): bool {
    return $e instanceof MongoDB\Driver\Exception\ServerException
        && ($e->getCode() === 11000 || str_contains($e->getMessage(), 'E11000'));
}

/** Creates the unique indexes (idempotent) — run by scripts/mongo_init.php. */
function user_ensure_indexes(): void {
    mongo_users()->createIndex(['userId' => 1], ['unique' => true, 'name' => 'userId_unique']);
    mongo_users()->createIndex(['userEmail' => 1], ['unique' => true, 'name' => 'userEmail_unique']);
    mongo_users()->createIndex(['emailVerifyTokenHash' => 1], ['sparse' => true, 'name' => 'verify_token']);
    mongo_users()->createIndex(['resetTokenHash' => 1], ['sparse' => true, 'name' => 'reset_token']);
    results_ensure_indexes();
}

// ── Accounts ─────────────────────────────────────────────────────────────────

function user_find_by_email(string $email): ?array {
    return mongo_users()->findOne(['userEmail' => user_normalize_email($email)]);
}

function user_find_by_id(string $userId): ?array {
    return mongo_users()->findOne(['userId' => $userId]);
}

/**
 * Creates a pending_email account. Returns the e-mail confirmation token,
 * or null when the e-mail is already taken.
 */
function user_create(string $email, string $passwordHash, string $club, array $invoice): ?string {
    $token = user_new_token();
    try {
        mongo_users()->insertOne([
            'userId'               => uuid_v4(),
            'userEmail'            => user_normalize_email($email),
            'userPassword'         => $passwordHash,
            'userClub'             => $club,
            'userInvoice'          => $invoice,
            'status'               => 'pending_email',
            'tokenVersion'         => 1,
            'emailVerifyTokenHash' => user_token_hash($token),
            'emailVerifyExpires'   => mongo_now(ACCOUNT_VERIFY_TTL),
            'createdAt'            => mongo_now(),
            'updatedAt'            => mongo_now(),
            'clubItems'            => ['clubMembers' => []],
        ]);
    } catch (Throwable $e) {
        if (is_duplicate_key($e)) return null;
        throw $e;
    }
    return $token;
}

/** New confirmation link for an account that has not confirmed its e-mail yet. */
function user_reissue_verify_token(string $userId): ?string {
    $token = user_new_token();
    $r = mongo_users()->updateOne(
        ['userId' => $userId, 'status' => 'pending_email'],
        ['$set' => [
            'emailVerifyTokenHash' => user_token_hash($token),
            'emailVerifyExpires'   => mongo_now(ACCOUNT_VERIFY_TTL),
            'updatedAt'            => mongo_now(),
        ]]
    );
    return $r->getMatchedCount() === 1 ? $token : null;
}

/** Confirms the e-mail: pending_email → pending_approval. Returns the account or null (bad/expired token). */
function user_verify_email(string $token): ?array {
    return mongo_users()->findOneAndUpdate(
        [
            'emailVerifyTokenHash' => user_token_hash($token),
            'emailVerifyExpires'   => ['$gt' => mongo_now()],
            'status'               => 'pending_email',
        ],
        [
            '$set'   => ['status' => 'pending_approval', 'updatedAt' => mongo_now()],
            '$unset' => ['emailVerifyTokenHash' => '', 'emailVerifyExpires' => ''],
        ],
        ['returnDocument' => MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
    );
}

/** Starts a password reset for a confirmed, not blocked account. Returns the token or null. */
function user_start_reset(string $email): ?string {
    $token = user_new_token();
    $r = mongo_users()->updateOne(
        ['userEmail' => user_normalize_email($email), 'status' => ['$in' => ['pending_approval', 'active']]],
        ['$set' => [
            'resetTokenHash' => user_token_hash($token),
            'resetExpires'   => mongo_now(ACCOUNT_RESET_TTL),
            'updatedAt'      => mongo_now(),
        ]]
    );
    return $r->getMatchedCount() === 1 ? $token : null;
}

/** Sets a new password from a reset link (one-time) and logs out every session. */
function user_finish_reset(string $token, string $passwordHash): bool {
    $r = mongo_users()->updateOne(
        [
            'resetTokenHash' => user_token_hash($token),
            'resetExpires'   => ['$gt' => mongo_now()],
            'status'         => ['$in' => ['pending_approval', 'active']],
        ],
        [
            '$set'   => ['userPassword' => $passwordHash, 'updatedAt' => mongo_now()],
            '$unset' => ['resetTokenHash' => '', 'resetExpires' => ''],
            '$inc'   => ['tokenVersion' => 1],
        ]
    );
    return $r->getModifiedCount() === 1;
}

/** New password; bumps tokenVersion so other sessions' tokens stop working. Returns the new tokenVersion. */
function user_change_password(string $userId, string $passwordHash): ?int {
    $u = mongo_users()->findOneAndUpdate(
        ['userId' => $userId],
        [
            '$set'   => ['userPassword' => $passwordHash, 'updatedAt' => mongo_now()],
            '$unset' => ['resetTokenHash' => '', 'resetExpires' => ''],
            '$inc'   => ['tokenVersion' => 1],
        ],
        ['returnDocument' => MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
    );
    return $u !== null ? (int)$u['tokenVersion'] : null;
}

/** Records a successful login; replaces the hash when the algorithm/cost changed. */
function user_record_login(string $userId, ?string $newHash = null): void {
    $set = ['lastLoginAt' => mongo_now()];
    if ($newHash !== null) $set['userPassword'] = $newHash;
    mongo_users()->updateOne(['userId' => $userId], ['$set' => $set]);
}

/** Updates the given profile fields ($fields: userClub and/or userInvoice). */
function user_update_profile(string $userId, array $fields): bool {
    $r = mongo_users()->updateOne(
        ['userId' => $userId],
        ['$set' => $fields + ['updatedAt' => mongo_now()]]
    );
    return $r->getMatchedCount() === 1;
}

/** Polish NIP: 10 digits, the last one a mod-11 checksum of the others. */
function nip_valid(string $nip): bool {
    if (!preg_match('/^\d{10}$/', $nip)) return false;
    $sum = 0;
    foreach ([6, 5, 7, 2, 3, 4, 5, 6, 7] as $i => $w) $sum += $w * (int)$nip[$i];
    return $sum % 11 === (int)$nip[9];
}

/**
 * Validates invoice details (all required): full company name, street address,
 * postal code (00-000), city and NIP (dashes/spaces/"PL" prefix accepted, stored as 10 digits).
 * Returns [fields, error].
 */
function user_invoice_validate($in): array {
    if (!is_array($in)) return [null, 'Podaj dane do faktury.'];
    $text = fn($v, int $max) => is_string($v) ? mb_substr(trim(preg_replace('/\p{C}/u', '', preg_replace('/\s+/u', ' ', $v))), 0, $max, 'UTF-8') : '';

    $name = $text($in['companyName'] ?? null, 200);
    if ($name === '') return [null, 'Podaj pełną nazwę firmy.'];
    $street = $text($in['street'] ?? null, 150);
    if ($street === '') return [null, 'Podaj ulicę i numer.'];
    $postal = $text($in['postalCode'] ?? null, 6);
    if (!preg_match('/^\d{2}-\d{3}$/', $postal)) return [null, 'Podaj kod pocztowy w formacie 00-000.'];
    $city = $text($in['city'] ?? null, 100);
    if ($city === '') return [null, 'Podaj miejscowość.'];
    $nip = is_string($in['nip'] ?? null) ? preg_replace('/^PL|[\s-]/i', '', trim($in['nip'])) : '';
    if (!nip_valid($nip)) return [null, 'Podaj poprawny NIP (10 cyfr).'];

    return [['companyName' => $name, 'street' => $street, 'postalCode' => $postal, 'city' => $city, 'nip' => $nip], null];
}

/**
 * Removes the account with all its members' results. The results go first, so a failed second write
 * leaves the account in place and a retry finishes the job (no orphaned results; no transactions —
 * they would need a replica set, which the local dev MongoDB is not).
 */
function user_delete(string $userId): bool {
    return account_lock($userId, function () use ($userId) {
        if (mongo_users()->countDocuments(['userId' => $userId], ['limit' => 1]) === 0) return false;
        results_delete_user($userId);
        return mongo_users()->deleteOne(['userId' => $userId])->getDeletedCount() === 1;
    });
}

/** All accounts for the admin panel, newest first. */
function user_list(): array {
    $cursor = mongo_users()->find([], [
        'sort'       => ['createdAt' => -1],
        'projection' => ['userPassword' => 0, 'emailVerifyTokenHash' => 0, 'resetTokenHash' => 0],
    ]);
    return array_map('user_summary', iterator_to_array($cursor, false));
}

/** Admin status change (activate after payment, block). Every user request re-checks the status, so blocking takes effect at once. */
function user_set_status(string $userId, string $status): ?array {
    if (!in_array($status, USER_STATUSES, true)) return null;
    return mongo_users()->findOneAndUpdate(
        ['userId' => $userId],
        ['$set' => ['status' => $status, 'updatedAt' => mongo_now()]],
        ['returnDocument' => MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
    );
}

// ── Club members ─────────────────────────────────────────────────────────────

/**
 * Validates club member fields. With $partial only the given fields are checked.
 * Returns [fields, error].
 */
function member_validate(array $in, bool $partial = false): array {
    $out = [];

    if (!$partial || array_key_exists('memberName', $in)) {
        $name = is_string($in['memberName'] ?? null) ? trim(preg_replace('/\s+/u', ' ', preg_replace('/\p{C}/u', '', $in['memberName']))) : '';
        if ($name === '' || mb_strlen($name, 'UTF-8') > 100) return [null, 'Podaj imię i nazwisko zawodnika (do 100 znaków).'];
        $out['memberName'] = $name;
    }
    if (!$partial || array_key_exists('memberSex', $in)) {
        if (!in_array($in['memberSex'] ?? null, MEMBER_SEXES, true)) return [null, 'Wybierz płeć zawodnika.'];
        $out['memberSex'] = $in['memberSex'];
    }
    if (!$partial || array_key_exists('memberBirthYear', $in)) {
        $year = $in['memberBirthYear'] ?? null;
        if (!is_int($year) || $year < 1920 || $year > (int)date('Y')) return [null, 'Podaj poprawny rok urodzenia.'];
        $out['memberBirthYear'] = $year;
    }
    return [$out, null];
}

/** Adds a club member (atomically, up to ACCOUNT_MAX_MEMBERS). Returns the member or null when the limit is reached. */
function member_add(string $userId, array $fields): ?array {
    $member = ['memberId' => uuid_v4()] + $fields;
    $r = mongo_users()->updateOne(
        ['userId' => $userId, 'clubItems.clubMembers.' . (ACCOUNT_MAX_MEMBERS - 1) => ['$exists' => false]],
        ['$push' => ['clubItems.clubMembers' => $member], '$set' => ['updatedAt' => mongo_now()]]
    );
    return $r->getModifiedCount() === 1 ? $member : null;
}

function member_update(string $userId, string $memberId, array $fields): bool {
    $set = ['updatedAt' => mongo_now()];
    foreach ($fields as $k => $v) {
        $set['clubItems.clubMembers.$.' . $k] = $v;
    }
    $r = mongo_users()->updateOne(
        ['userId' => $userId, 'clubItems.clubMembers.memberId' => $memberId],
        ['$set' => $set]
    );
    return $r->getMatchedCount() === 1;
}

/** Removes a club member with the member's results — results first, for the same reason as user_delete(). */
function member_delete(string $userId, string $memberId): bool {
    return account_lock($userId, function () use ($userId, $memberId) {
        $filter = ['userId' => $userId, 'clubItems.clubMembers.memberId' => $memberId];
        if (mongo_users()->countDocuments($filter, ['limit' => 1]) === 0) return false;
        results_delete_member($userId, $memberId);
        $r = mongo_users()->updateOne(
            $filter,
            ['$pull' => ['clubItems.clubMembers' => ['memberId' => $memberId]], '$set' => ['updatedAt' => mongo_now()]]
        );
        return $r->getModifiedCount() === 1;
    });
}

/** One-off cleanup (scripts/drop_member_times.php): removes the old hand-entered memberTimes. Returns changed accounts. */
function user_drop_member_times(): int {
    return mongo_users()->updateMany(
        ['clubItems.clubMembers.memberTimes' => ['$exists' => true]],
        ['$unset' => ['clubItems.clubMembers.$[].memberTimes' => '']]
    )->getModifiedCount();
}

// ── Results (collection "results") ──────────────────────────────────────────

function results_ensure_indexes(): void {
    mongo_results()->createIndex(
        ['userId' => 1, 'memberId' => 1, 'contestUuid' => 1, 'eventNr' => 1],
        ['unique' => true, 'name' => 'result_unique']
    );
    mongo_results()->createIndex(['userId' => 1, 'date' => 1], ['name' => 'user_date']);
}

/** Inserts or overwrites (same member + contest + event) the given rows of one account. Returns the row count. */
function results_upsert_many(string $userId, array $rows): int {
    if (!$rows) return 0;
    $ops = [];
    foreach ($rows as $r) {
        $key = ['userId' => $userId, 'memberId' => $r['memberId'], 'contestUuid' => $r['contestUuid'], 'eventNr' => $r['eventNr']];
        $ops[] = ['updateOne' => [$key, ['$set' => $r + $key + ['fetchedAt' => mongo_now()]], ['upsert' => true]]];
    }
    mongo_results()->bulkWrite($ops, ['ordered' => false]);
    return count($rows);
}

/**
 * Removes the account's rows of one contest that are not among $rows (same member + event): results withdrawn
 * from LENEX, turned into DSQ/DNS, or of members that no longer match. Rows are chosen by their keys, not by
 * which fetch wrote them, so two fetches of the same contest running at once can never delete the current results.
 */
function results_delete_stale(string $userId, string $contestUuid, array $rows): int {
    $keep = [];
    foreach ($rows as $r) $keep[$r['memberId'] . '|' . $r['eventNr']] = true;

    $stale  = [];
    $cursor = mongo_results()->find(
        ['userId' => $userId, 'contestUuid' => $contestUuid],
        ['projection' => ['memberId' => 1, 'eventNr' => 1]]
    );
    foreach ($cursor as $doc) {
        if (!isset($keep[$doc['memberId'] . '|' . $doc['eventNr']])) $stale[] = $doc['_id'];
    }
    return $stale ? mongo_results()->deleteMany(['_id' => ['$in' => $stale]])->getDeletedCount() : 0;
}

/**
 * Makes the account's results of one contest exactly $rows. Upsert first, stale rows second, so a failure in
 * between leaves old rows a retry removes — never a contest without its current results.
 * Returns the number of current rows.
 */
function results_replace_contest(string $userId, string $contestUuid, array $rows): int {
    $rows  = array_map(fn($r) => ['contestUuid' => $contestUuid] + $r, $rows);
    $saved = results_upsert_many($userId, $rows);
    results_delete_stale($userId, $contestUuid, $rows);
    return $saved;
}

/** Results of an account (optionally one member) from one calendar year, newest first. */
function results_list(string $userId, int $year, ?string $memberId = null): array {
    $filter = ['userId' => $userId, 'date' => ['$gte' => sprintf('%04d-01-01', $year), '$lt' => sprintf('%04d-01-01', $year + 1)]];
    if ($memberId !== null) $filter['memberId'] = $memberId;
    $cursor = mongo_results()->find($filter, [
        'sort'       => ['date' => -1, 'eventNr' => 1],
        'projection' => ['_id' => 0, 'userId' => 0, 'fetchId' => 0], // fetchId: left on rows written before it was dropped
    ]);
    $out = [];
    foreach ($cursor as $r) {
        $r['fetchedAt'] = ($r['fetchedAt'] ?? null) instanceof UTCDateTime ? $r['fetchedAt']->toDateTime()->format('c') : null;
        $out[] = $r;
    }
    return $out;
}

function results_delete_member(string $userId, string $memberId): int {
    return mongo_results()->deleteMany(['userId' => $userId, 'memberId' => $memberId])->getDeletedCount();
}

function results_delete_user(string $userId): int {
    return mongo_results()->deleteMany(['userId' => $userId])->getDeletedCount();
}
