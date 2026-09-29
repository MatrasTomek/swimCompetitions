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
            $event = $parsed['events'][$res['eventNr']] ?? null;
            if ($event === null) continue;
            $date = $event['date'] !== '' ? $event['date'] : $parsed['meet']['date'];
            if ($date === '') continue;
            $rows[] = [
                'memberId'    => (string)$memberId,
                'contestUuid' => $contestUuid,
                'contestName' => $parsed['meet']['name'],
                'contestCity' => $parsed['meet']['city'],
                'eventNr'     => $res['eventNr'],
                'date'        => $date,
                'poolLength'  => $parsed['meet']['poolLength'],
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
        return [400, ['error' => 'Najpierw dodaj zawodników w „Moi zawodnicy”.']];
    }
    if (ip_rate_limited(ACCOUNT_RESULTS_RATE_FILE, ACCOUNT_RESULTS_FETCH_MAX, ACCOUNT_RESULTS_FETCH_WINDOW, 'u:' . $user['userId'])) {
        return [429, ['error' => 'Zbyt wiele pobrań wyników. Spróbuj ponownie za kilka minut.']];
    }

    $download = ($fetchXml ?? 'results_download_contest_xml')($uuid);
    $parsed   = $download['ok'] ? lenex_parse_full($download['xml']) : $download;
    if (!$parsed['ok']) {
        error_log('results_import_contest ' . $uuid . ': ' . $parsed['error']);
        return [409, ['error' => 'Wyniki nie są jeszcze dostępne na livetiming.pl.']];
    }

    // An entry list or invitation (athletes, but no results yet) must not wipe the stored results
    if (!array_filter($parsed['athletes'], fn($a) => $a['results'])) {
        error_log('results_import_contest ' . $uuid . ': LENEX without any results');
        return [409, ['error' => 'Wyniki nie są jeszcze dostępne na livetiming.pl.']];
    }

    $match = match_members($members, $parsed['athletes']);
    $rows  = results_build_rows($parsed, $match['matched'], $uuid);
    $saved = results_replace_contest($user['userId'], $uuid, $rows);

    return [200, [
        'saved'           => $saved,
        'members_matched' => count($match['matched']),
        'not_found'       => $match['not_found'],
        'ambiguous'       => $match['ambiguous'],
        'competition'     => ['name' => $parsed['meet']['name'], 'date' => $parsed['meet']['date']],
    ]];
}
