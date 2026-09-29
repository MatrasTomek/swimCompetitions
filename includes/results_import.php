<?php
/**
 * Club accounts: turning a LENEX file into result rows of the club's members (collection "results").
 */

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
