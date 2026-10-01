import { test } from 'node:test';
import assert from 'node:assert/strict';
import { seasonSummary, timeImprovements, memberTotals, topBy, countWith, filterResults } from './stats-data.ts';

const row = (memberId: string, contestUuid: string, distance: number, stroke: string, poolLength: number, timeMs: number, points: number | null, date = '2026-03-14', eventNr = 1) =>
  ({ memberId, contestUuid, distance, stroke, poolLength, timeMs, points, date, eventNr });

const rows = [
  row('m1', 'c1', 100, 'dowolny', 25, 66000, 300),
  row('m1', 'c2', 100, 'dowolny', 25, 65000, 320, '2026-05-01'),
  row('m1', 'c2', 50, 'grzbietowy', 25, 40000, null, '2026-05-01'),
  row('m2', 'c1', 100, 'dowolny', 50, 70000, 250),
  row('m3', 'c1', 50, 'dowolny', 25, 35000, null),
];
const names = new Map([['m1', 'Wąs Amelia'], ['m2', 'Łukasik Jan'], ['m3', 'Nowak Ola']]);

test('seasonSummary counts members, starts, contests and time improvements', () => {
  assert.deepEqual(seasonSummary(rows), { members: 3, starts: 5, contests: 2, improvements: 1 });
  assert.deepEqual(seasonSummary([]), { members: 0, starts: 0, contests: 0, improvements: 0 });
});

test('timeImprovements: a single start or an equal time is no improvement', () => {
  assert.equal(timeImprovements([row('m1', 'c1', 50, 'dowolny', 25, 33000, null)]), 0);
  assert.equal(timeImprovements([
    row('m1', 'c1', 50, 'dowolny', 25, 33000, null, '2026-03-01'),
    row('m1', 'c2', 50, 'dowolny', 25, 33000, null, '2026-04-01'),
  ]), 0);
});

test('timeImprovements: counted in date order against the best so far, not the previous swim', () => {
  assert.equal(timeImprovements([
    row('m1', 'c3', 100, 'dowolny', 25, 65500, null, '2026-06-01'),  // slower than the best so far
    row('m1', 'c1', 100, 'dowolny', 25, 66000, null, '2026-03-01'),  // first start
    row('m1', 'c2', 100, 'dowolny', 25, 65000, null, '2026-05-01'),  // improvement
    row('m1', 'c4', 100, 'dowolny', 25, 64000, null, '2026-07-01'),  // improvement
  ]), 2);
});

test('timeImprovements: heats before the final on the same day (event number)', () => {
  assert.equal(timeImprovements([
    row('m1', 'c1', 50, 'motylkowy', 50, 30000, null, '2026-03-01', 20),  // final
    row('m1', 'c1', 50, 'motylkowy', 50, 31000, null, '2026-03-01', 4),   // heat
  ]), 1);
  assert.equal(timeImprovements([
    row('m1', 'c1', 50, 'motylkowy', 50, 31000, null, '2026-03-01', 20),  // slower final
    row('m1', 'c1', 50, 'motylkowy', 50, 30000, null, '2026-03-01', 4),
  ]), 0);
});

test('timeImprovements: starts with the same date and event number do not depend on the row order', () => {
  const a = row('m1', 'c1', 50, 'motylkowy', 50, 31000, null, '2026-03-01', 4);
  const b = row('m1', 'c1', 50, 'motylkowy', 50, 30000, null, '2026-03-01', 4);
  assert.equal(timeImprovements([a, b]), 0);
  assert.equal(timeImprovements([b, a]), 0);
  // After an earlier best the tied starts give one improvement at most, whichever comes first.
  const before = row('m1', 'c0', 50, 'motylkowy', 50, 32000, null, '2026-02-01', 9);
  assert.equal(timeImprovements([before, a, b]), 1);
  assert.equal(timeImprovements([b, a, before]), 1);
  // Neither tied start beats the earlier best.
  const fast = row('m1', 'c0', 50, 'motylkowy', 50, 29000, null, '2026-02-01', 9);
  assert.equal(timeImprovements([fast, a, b]), 0);
});

test('timeImprovements: two contests on the same day are one moment, whatever their event numbers', () => {
  const a = row('m1', 'c1', 50, 'dowolny', 25, 31000, null, '2026-03-01', 4);
  const b = row('m1', 'c2', 50, 'dowolny', 25, 30000, null, '2026-03-01', 4);
  const c = row('m1', 'c2', 50, 'dowolny', 25, 30000, null, '2026-03-01', 20);
  assert.equal(timeImprovements([a, b]), 0);
  assert.equal(timeImprovements([a, c]), 0);
  assert.equal(timeImprovements([c, a]), 0);
  // An earlier best: the day improves it once at most.
  const before = row('m1', 'c0', 50, 'dowolny', 25, 32000, null, '2026-02-01');
  assert.equal(timeImprovements([before, a, c]), 1);
  assert.equal(timeImprovements([c, a, before]), 1);
  // The next day is compared with the day's fastest time.
  assert.equal(timeImprovements([a, c, row('m1', 'c3', 50, 'dowolny', 25, 30500, null, '2026-03-02')]), 0);
  assert.equal(timeImprovements([a, c, row('m1', 'c3', 50, 'dowolny', 25, 29500, null, '2026-03-02')]), 1);
});

test('timeImprovements: pools and members are separate', () => {
  assert.equal(timeImprovements([
    row('m1', 'c1', 100, 'dowolny', 50, 70000, null, '2026-03-01'),
    row('m1', 'c2', 100, 'dowolny', 25, 66000, null, '2026-04-01'),  // first in a 25 m pool
    row('m2', 'c2', 100, 'dowolny', 50, 60000, null, '2026-04-01'),  // another member
  ]), 0);
});

test('memberTotals: starts and best points per member; unknown member gets a dash', () => {
  assert.deepEqual(memberTotals(rows, names), [
    { memberId: 'm1', name: 'Wąs Amelia', starts: 3, bestPoints: 320 },
    { memberId: 'm2', name: 'Łukasik Jan', starts: 1, bestPoints: 250 },
    { memberId: 'm3', name: 'Nowak Ola', starts: 1, bestPoints: null },
  ]);
  assert.equal(memberTotals([row('gone', 'c1', 50, 'dowolny', 25, 1, null)], names)[0].name, '—');
});

test('topBy: highest first, ties by name, members without the value left out, limited', () => {
  const totals = memberTotals(rows, names);
  assert.deepEqual(topBy(totals, 'starts', 10).map(t => t.name), ['Wąs Amelia', 'Łukasik Jan', 'Nowak Ola']);
  assert.deepEqual(topBy(totals, 'bestPoints', 10).map(t => t.name), ['Wąs Amelia', 'Łukasik Jan']);
  assert.deepEqual(topBy(totals, 'starts', 1).map(t => t.name), ['Wąs Amelia']);
});

test('countWith: how many members have the value — the chart says when it shows only the top of them', () => {
  const totals = memberTotals(rows, names);
  assert.equal(countWith(totals, 'starts'), 3);
  assert.equal(countWith(totals, 'bestPoints'), 2);
});

test('filterResults: every name word must match; stroke, distance and pool narrow further', () => {
  const named = rows.map(r => ({ ...r, memberName: names.get(r.memberId)! }));
  const f = (q: string, stroke: string | null = null, distance: number | null = null, pool: number | null = null) =>
    filterResults(named, { query: q, stroke, distance, pool }).length;
  assert.equal(f(''), 5);
  assert.equal(f('amelia wąs'), 3);
  assert.equal(f('AMELIA nowak'), 0);
  assert.equal(f('', 'grzbietowy'), 1);
  assert.equal(f('', null, 100), 3);
  assert.equal(f('', null, null, 50), 1);
  assert.equal(f('łuk', 'dowolny', 100, 50), 1);
});
