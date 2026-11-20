<?php
require __DIR__ . '/_assert.php';
require __DIR__ . '/../../includes/startlist_parse.php';

$event = ['name' => '50m dowolny', 'nr' => 1];
$heat  = ['nr' => 4, 'total' => 15, 'godz' => '9:00'];

// "NAZWISKO Imię" (Splash, most lists)
$e = sl_parse_entry_line('    4 Gancarz Iga                  15     Jedynka Solex Lębork                                 NT', $event, $heat, 'Solex Lębork');
check('plain name', $e['imie'] ?? null, 'Gancarz Iga');
check('NT → no time', array_key_exists('czas', $e ?? []), false);

// "NAZWISKO, Imię" (e.g. ZSOMS Kraków 2026) — the comma used to reject every line
$line = '    3 JUREK, Hanna                                 13      Stowarzyszenie Olimpijczyk Proszówki                   33.32';
$e = sl_parse_entry_line($line, $event, $heat, 'Olimpijczyk Proszówki');
check('comma name parsed', $e['imie'] ?? null, 'Jurek Hanna');
check('comma name lane', $e['tor'] ?? null, 3);
check('comma name time', $e['czas'] ?? null, sl_normalize_time('33.32'));
check('other club filtered out', sl_parse_entry_line($line, $event, $heat, 'Jordan Kraków'), null);

check('apostrophe name', sl_parse_entry_line("    2 O'NEIL, Mia                                  12      UKS Delfin                                             NT", $event, $heat, 'Delfin')['imie'] ?? null, "O'neil Mia");

// sl_club_matches
check('full name', sl_club_matches('Stowarzyszenie Olimpijczyk Proszówki', 'Stowarzyszenie Olimpijczyk Proszówki'), true);
check('without legal form', sl_club_matches('Olimpijczyk Proszówki', 'Stowarzyszenie Olimpijczyk Proszówki'), true);
check('no diacritics, lowercase', sl_club_matches('olimpijczyk proszowki', 'Stowarzyszenie Olimpijczyk Proszówki'), true);
check('other city not matched', sl_club_matches('Stowarzyszenie Olimpijczyk Proszówki', 'Stowarzyszenie "OLIMPIJCZYK KRAKÓW"'), false);
check('two words', sl_club_matches('BOSiR Brzesko', 'BOSiR Brzesko'), true);
check('one word', sl_club_matches('Brzesko', 'BOSiR Brzesko'), true);
check('extra legal form in filter', sl_club_matches('UKS Solex Lębork', 'Jedynka Solex Lębork'), true);
check('quoted club name', sl_club_matches('Feniks Dębica', 'UKS "Feniks" Dębica'), true);
check('only generic words', sl_club_matches('UKS', 'UKS "Feniks" Dębica'), true);
check('different club', sl_club_matches('Jordan Kraków', 'KS Korona 1919 Kraków'), false);

finish();
