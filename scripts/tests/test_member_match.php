<?php
require __DIR__ . '/_assert.php';
require __DIR__ . '/../../includes/member_match.php';

// normalize_person_name
check('lowercase + diacritics', normalize_person_name('WĄS Amelia'), 'amelia was');
check('ł is folded', normalize_person_name('Łukasik Jan'), 'jan lukasik');
check('order independent', normalize_person_name('Jan Łukasik'), normalize_person_name('ŁUKASIK  jan'));
check('hyphen splits words', normalize_person_name('Kowalska-Nowak Anna'), 'anna kowalska nowak');
check('all polish letters', normalize_person_name('ąćęłńóśźż ĄĆĘŁŃÓŚŹŻ'), 'acelnoszz acelnoszz');

// person_key
check('key with year', person_key('Wąs Amelia', 2014), 'amelia was|2014');
check('key without year', person_key('Wąs Amelia', null), null);
check('key empty name', person_key('  ', 2014), null);

$members = [
    ['memberId' => 'm1', 'memberName' => 'Amelia Wąs',      'memberBirthYear' => 2014],
    ['memberId' => 'm2', 'memberName' => 'jan łukasik',     'memberBirthYear' => 2013],
    ['memberId' => 'm3', 'memberName' => 'Piotr Zieliński', 'memberBirthYear' => 2012], // not in LENEX
    ['memberId' => 'm4', 'memberName' => 'Ola Nowak',       'memberBirthYear' => 2015], // LENEX has no birthdate
    ['memberId' => 'm5', 'memberName' => 'Adam Kot',        'memberBirthYear' => 2011], // two LENEX athletes
    ['memberId' => 'm6', 'memberName' => 'Wąs Amelia',      'memberBirthYear' => 2010], // same name, other year
];
$athletes = [
    ['lastname' => 'WĄS',     'firstname' => 'Amelia', 'birthYear' => 2014, 'id' => 'a1'],
    ['lastname' => 'Łukasik', 'firstname' => 'Jan',    'birthYear' => 2013, 'id' => 'a2'],
    ['lastname' => 'Nowak',   'firstname' => 'Ola',    'birthYear' => null, 'id' => 'a3'],
    ['lastname' => 'Kot',     'firstname' => 'Adam',   'birthYear' => 2011, 'id' => 'a4'],
    ['lastname' => 'KOT',     'firstname' => 'ADAM',   'birthYear' => 2011, 'id' => 'a5'],
];
$r = match_members($members, $athletes);
check('matched ids', array_map(fn($a) => $a['id'], $r['matched']), ['m1' => 'a1', 'm2' => 'a2']);
check('not found', $r['not_found'], ['Piotr Zieliński', 'Ola Nowak', 'Wąs Amelia']);
check('ambiguous', $r['ambiguous'], ['Adam Kot']);

// Two club members with the same key are both ambiguous (we cannot tell whose result it is)
$dup = match_members(
    [['memberId' => 'x1', 'memberName' => 'Jan Łukasik', 'memberBirthYear' => 2013],
     ['memberId' => 'x2', 'memberName' => 'Łukasik Jan', 'memberBirthYear' => 2013]],
    $athletes
);
check('duplicate members matched', $dup['matched'], []);
check('duplicate members ambiguous', $dup['ambiguous'], ['Jan Łukasik', 'Łukasik Jan']);

finish();
