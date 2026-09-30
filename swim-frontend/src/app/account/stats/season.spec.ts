import { test } from 'node:test';
import assert from 'node:assert/strict';
import { SEASON_FIRST, seasonYears, parseSeason } from './season.ts';

const in2026 = new Date('2026-09-29T12:00:00');
const in2028 = new Date('2028-01-02T12:00:00');

test('seasonYears', () => {
  assert.equal(SEASON_FIRST, 2026);
  assert.deepEqual(seasonYears(in2026), [2026]);
  assert.deepEqual(seasonYears(in2028), [2028, 2027, 2026]);
  assert.deepEqual(seasonYears(new Date('2025-06-01')), [2026]);
});

test('parseSeason', () => {
  assert.equal(parseSeason(null, in2028), 2028);
  assert.equal(parseSeason('2027', in2028), 2027);
  assert.equal(parseSeason('2025', in2028), 2028);
  assert.equal(parseSeason('abc', in2028), 2028);
  assert.equal(parseSeason('2029', in2028), 2028);
  assert.equal(parseSeason('', in2028), 2028);
  assert.equal(parseSeason(null, new Date('2025-06-01')), 2026);
});
