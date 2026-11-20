import { test } from 'node:test';
import assert from 'node:assert/strict';
import { isDrawingClub } from './drawing-club.ts';

test('isDrawingClub: BOSiR and BOSiR Brzesko', () => {
  assert.equal(isDrawingClub('BOSiR'), true);
  assert.equal(isDrawingClub('BOSiR Brzesko'), true);
});

test('isDrawingClub: case and whitespace ignored', () => {
  assert.equal(isDrawingClub('  bosir  '), true);
  assert.equal(isDrawingClub('BOSIR   brzesko'), true);
});

test('isDrawingClub: other clubs', () => {
  assert.equal(isDrawingClub('Olimpijczyk Brzesko'), false);
  assert.equal(isDrawingClub('BOSiR Bochnia'), false);
  assert.equal(isDrawingClub(''), false);
});
