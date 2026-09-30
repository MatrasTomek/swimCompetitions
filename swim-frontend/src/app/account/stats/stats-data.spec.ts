import { test } from 'node:test';
import assert from 'node:assert/strict';
import { seasonSummary, memberTotals, topBy, countWith, filterResults } from './stats-data.ts';

const row = (memberId: string, contestUuid: string, distance: number, stroke: string, poolLength: number, timeMs: number, points: number | null, date = '2026-03-14') =>
  ({ memberId, contestUuid, distance, stroke, poolLength, timeMs, points, date });

const rows = [
  row('m1', 'c1', 100, 'dowolny', 25, 66000, 300),
  row('m1', 'c2', 100, 'dowolny', 25, 65000, 320, '2026-05-01'),
  row('m1', 'c2', 50, 'grzbietowy', 25, 40000, null, '2026-05-01'),
  row('m2', 'c1', 100, 'dowolny', 50, 70000, 250),
  row('m3', 'c1', 50, 'dowolny', 25, 35000, null),
];
const names = new Map([['m1', 'Wąs Amelia'], ['m2', 'Łukasik Jan'], ['m3', 'Nowak Ola']]);

test('seasonSummary counts members, starts, contests and personal bests', () => {
  assert.deepEqual(seasonSummary(rows), { members: 3, starts: 5, contests: 2, personalBests: 4 });
  assert.deepEqual(seasonSummary([]), { members: 0, starts: 0, contests: 0, personalBests: 0 });
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
