<?php
/**
 * Club accounts: turning a LENEX file into result rows of the club's members (collection "results").
 */

require_once __DIR__ . '/functions.php';        // is_allowed_contest_host(), ip_rate_limited()
require_once __DIR__ . '/result_fetch.php';     // resolve_lenex_url(), lenex_* (lenex_fetch.php)
require_once __DIR__ . '/startlist_parse.php';  // sl_contest_uuid()
require_once __DIR__ . '/member_match.php';

/**
 * @param array  $parsed   lenex_parse_full() output
 * @param array  $matched  memberId => LENEX athlete (match_members()['matched'])
 * @return array rows for results_upsert_many()
 */
function results_build_rows(array $parsed, array $matched, string $contestUuid): array {
    $rows = [];
    foreach ($matched as $memberId => $athlete) {
        foreach ($athlete['results'] as $res) {
            $event = $parsed['events'][$res['eventId']] ?? null;
            if ($event === null) continue;
            $date = $event['date'] !== '' ? $event['date'] : $parsed['meet']['date'];
            if ($date === '') continue;
            $rows[] = [
                'memberId'    => (string)$memberId,
                'contestUuid' => $contestUuid,
                'contestName' => $parsed['meet']['name'],
                'contestCity' => $parsed['meet']['city'],
                'eventId'     => $res['eventId'],
                'eventNr'     => $res['eventNr'],
                'date'      => $date,
                'poolLength'  => $event['poolLength'],
                'distance'    => $event['distance'],
                'stroke'      => $event['stroke'],
                'time'        => $res['time'],
                'timeMs'      => $res['timeMs'],
                'points'      => $res['points'],
            ];
        }
    }
    return $rows;
}

/** Downloads the LENEX XML of a livetiming.pl contest: ['ok'=>true,'xml'=>…] or ['ok'=>false,'error'=>…]. */
function results_download_contest_xml(string $uuid): array {
    $resolved = resolve_lenex_url('https://livetiming.pl/contest/' . $uuid);
    return empty($resolved['error']) ? lenex_download_xml($resolved['url']) : ['ok' => false, 'error' => $resolved['error']];
}

/**
 * Fetches a contest's LENEX and stores the results of the account's club members.
 * $fetchXml (uuid → results_download_contest_xml() shape) is replaceable for tests.
 * Returns [HTTP status, response body].
 */
function results_import_contest(array $user, string $contestUrl, ?callable $fetchXml = null): array {
    $uuid = sl_contest_uuid($contestUrl);
    if ($uuid === null || !is_allowed_contest_host($contestUrl)) {
        return [400, ['error' => 'Podaj link do strony zawodów z livetiming.pl (https://livetiming.pl/contest/…).']];
    }
    $members = array_values($user['clubItems']['clubMembers'] ?? []);
    if (!$members) {
        // `code` lets the SPA link to „Moi zawodnicy” without matching the message text
        return [400, ['error' => 'Najpierw dodaj zawodników w „Moi zawodnicy”.', 'code' => 'no_members']];
    }
    // A fetch answered 409 (results not published yet) gives its hit back, so waiting for results does not use up
    // the limit — but such fetches still download a file, so they have their own, looser limit ('p:' key).
    // A slot in both is reserved before the download (concurrent requests cannot overshoot either limit);
    // the one that does not apply to the answer is given back afterwards
    $limitKey   = 'u:' . $user['userId'];
    $pendingKey = 'p:' . $user['userId'];
    $token = rate_limit_reserve(ACCOUNT_RESULTS_RATE_FILE, ACCOUNT_RESULTS_FETCH_WINDOW,
                                [$limitKey => ACCOUNT_RESULTS_FETCH_MAX, $pendingKey => ACCOUNT_RESULTS_PENDING_MAX]);
    if ($token === null) {
        return [429, ['error' => 'Zbyt wiele pobrań wyników. Spróbuj ponownie za kilka minut.']];
    }
    $notPublished = false;
    try {
        [$status, $body] = results_import_downloaded($user, $uuid, $fetchXml);
        $notPublished = $status === 409;
        return [$status, $body];
    } finally {
        // also on an exception: only a 409 keeps the pending slot
        rate_limit_refund(ACCOUNT_RESULTS_RATE_FILE, $notPublished ? $limitKey : $pendingKey, $token);
    }
}

/** results_import_contest() after validation and the rate limit: download, match, store. */
function results_import_downloaded(array $user, string $uuid, ?callable $fetchXml): array {
    $notPublished = function (string $reason) use ($uuid): array {
        error_log('results_import_contest ' . $uuid . ': ' . $reason);
        return [409, ['error' => 'Wyniki nie są jeszcze dostępne na livetiming.pl.']];
    };

    $download = ($fetchXml ?? 'results_download_contest_xml')($uuid);
    $parsed   = $download['ok'] ? lenex_parse_full($download['xml']) : $download;
    if (!$parsed['ok']) {
        return $notPublished($parsed['error']);
    }

    // An entry list or invitation (athletes, but no results yet) must not wipe the stored results
    if (!array_filter($parsed['athletes'], fn($a) => $a['results'])) {
        return $notPublished('LENEX without any results');
    }

    // The download may take a while: store results for the members the account has now, not when the request
    // started — and under the account's lock, so a member / the account removed meanwhile leaves no orphans
    return account_lock($user['userId'], function () use ($user, $parsed, $uuid) {
        $account = user_find_by_id($user['userId']);
        if ($account === null) {
            return [404, ['error' => 'Konto nie istnieje.']];
        }
        $members = array_values($account['clubItems']['clubMembers'] ?? []);

        $match = match_members($members, $parsed['athletes']);
        $rows  = results_build_rows($parsed, $match['matched'], $uuid);
        $saved = results_replace_contest($user['userId'], $uuid, $rows);

        // Members found in the file, but with nothing to store (only DNS/DSQ starts, relays or unknown events)
        $withRows  = array_flip(array_column($rows, 'memberId'));
        $noResults = [];
        foreach ($members as $m) {
            if (isset($match['matched'][$m['memberId']]) && !isset($withRows[$m['memberId']])) $noResults[] = $m['memberName'];
        }

        return [200, [
            'saved'           => $saved,
            'members_matched' => count($match['matched']),
            'not_found'       => $match['not_found'],
            'ambiguous'       => $match['ambiguous'],
            'no_results'      => $noResults,
            'competition'     => ['name' => $parsed['meet']['name'], 'date' => $parsed['meet']['date']],
        ]];
    });
}
