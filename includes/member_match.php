<?php
/**
 * Matching a club's members ("Moi zawodnicy") to athletes from a LENEX file:
 * same name (word order, case and Polish diacritics ignored) and the same birth year.
 * Pure functions — no I/O.
 */

const PERSON_NAME_FOLD = [
    'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
];

/** "WĄS  Amelia" → "amelia was": lowercase ASCII words, sorted, single spaces. */
function normalize_person_name(string $name): string {
    $s = strtr(mb_strtolower($name, 'UTF-8'), PERSON_NAME_FOLD);
    if (function_exists('iconv')) {
        // Other Latin letters (é, ü, …); ł etc. are already folded above, since iconv on some systems gives "?"
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($t !== false) $s = $t;
    }
    $words = preg_split('/[^a-z]+/', $s, -1, PREG_SPLIT_NO_EMPTY);
    sort($words, SORT_STRING);
    return implode(' ', $words);
}

/** "amelia was|2014", or null when the name is empty or the birth year unknown. */
function person_key(string $name, ?int $birthYear): ?string {
    $n = normalize_person_name($name);
    if ($n === '' || !$birthYear) return null;
    return $n . '|' . $birthYear;
}

/**
 * @param array $members  club members: memberId, memberName, memberBirthYear
 * @param array $athletes LENEX athletes: lastname, firstname, birthYear (?int), …
 * @return array{matched: array<string, array>, not_found: string[], ambiguous: string[]}
 */
function match_members(array $members, array $athletes): array {
    $byKey = [];
    foreach ($athletes as $a) {
        $key = person_key(($a['lastname'] ?? '') . ' ' . ($a['firstname'] ?? ''), $a['birthYear'] ?? null);
        if ($key !== null) $byKey[$key][] = $a;
    }

    $memberKeyCount = [];
    foreach ($members as $m) {
        $key = person_key((string)$m['memberName'], (int)$m['memberBirthYear']);
        if ($key !== null) $memberKeyCount[$key] = ($memberKeyCount[$key] ?? 0) + 1;
    }

    $out = ['matched' => [], 'not_found' => [], 'ambiguous' => []];
    foreach ($members as $m) {
        $key   = person_key((string)$m['memberName'], (int)$m['memberBirthYear']);
        $found = $key !== null ? ($byKey[$key] ?? []) : [];
        if (!$found) {
            $out['not_found'][] = $m['memberName'];
        } elseif (count($found) > 1 || $memberKeyCount[$key] > 1) {
            $out['ambiguous'][] = $m['memberName'];
        } else {
            $out['matched'][$m['memberId']] = $found[0];
        }
    }
    return $out;
}
