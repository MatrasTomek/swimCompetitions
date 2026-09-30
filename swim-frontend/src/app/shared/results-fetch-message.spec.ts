import { test } from 'node:test';
import assert from 'node:assert/strict';
import { plural } from './plural.ts';
import { resultsFetchMessage } from './results-fetch-message.ts';

const base = { saved: 0, members_matched: 0, not_found: [], ambiguous: [], no_results: [], competition: { name: 'Mityng', date: '2026-03-14' } };

test('counts only members that got results — matched members with DNS/DSQ only are listed apart', () => {
  const m = resultsFetchMessage({ ...base, saved: 4, members_matched: 3, no_results: ['Aleksandra Ziarko'], not_found: ['Jan Nowak'] }, plural);
  assert.equal(m.severity, 'success');
  assert.equal(m.detail, 'Zapisano 4 wyniki dla 2 zawodników. Bez ważnych wyników (np. DNS/DSQ): Aleksandra Ziarko. Nie znaleziono w zawodach: Jan Nowak.');
});

test('polish plurals and the plain success message', () => {
  assert.equal(resultsFetchMessage({ ...base, saved: 1, members_matched: 1 }, plural).detail, 'Zapisano 1 wynik dla 1 zawodnika.');
  assert.equal(resultsFetchMessage({ ...base, saved: 12, members_matched: 5 }, plural).detail, 'Zapisano 12 wyników dla 5 zawodników.');
});

test('nothing saved is a warning that still says why', () => {
  const m = resultsFetchMessage({ ...base, members_matched: 1, no_results: ['Ola Kot'], ambiguous: ['Adam Kot'] }, plural);
  assert.equal(m.severity, 'warn');
  assert.equal(m.detail, 'Nie zapisano żadnych wyników Twoich zawodników z tych zawodów. Bez ważnych wyników (np. DNS/DSQ): Ola Kot. Niejednoznaczni (pominięci): Adam Kot.');
});

test('an older API without no_results still works', () => {
  const { no_results, ...old } = { ...base, saved: 2, members_matched: 1 };
  assert.equal(resultsFetchMessage(old as never, plural).detail, 'Zapisano 2 wyniki dla 1 zawodnika.');
});
