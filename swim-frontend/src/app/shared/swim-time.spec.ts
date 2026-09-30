import { test } from 'node:test';
import assert from 'node:assert/strict';
import { formatSwimTime, eventLabel, eventKey, pbKey, bestTimes, eventOptions, progression, sortedBests } from './swim-time.ts';

test('formatSwimTime', () => {
  assert.equal(formatSwimTime(65320), '1:05.32');
  assert.equal(formatSwimTime(27340), '27.34');
  assert.equal(formatSwimTime(3723450), '62:03.45');
  assert.equal(formatSwimTime(5), '0.00');
});

test('labels and keys', () => {
  assert.equal(eventLabel(100, 'dowolny'), '100 m dowolny');
  assert.equal(eventKey({ distance: 50, stroke: 'motylkowy' }), '50|motylkowy');
  assert.equal(pbKey({ memberId: 'm1', distance: 50, stroke: 'motylkowy', poolLength: 25 }), 'm1|50|motylkowy|25');
});

test('bestTimes per member, event and pool; tie → earlier date', () => {
  const rows = [
    { memberId: 'm1', distance: 100, stroke: 'dowolny', poolLength: 25, timeMs: 66000, date: '2026-03-01' },
    { memberId: 'm1', distance: 100, stroke: 'dowolny', poolLength: 25, timeMs: 65000, date: '2026-05-01' },
    { memberId: 'm1', distance: 100, stroke: 'dowolny', poolLength: 50, timeMs: 67000, date: '2026-06-01' },
    { memberId: 'm2', distance: 100, stroke: 'dowolny', poolLength: 25, timeMs: 70000, date: '2026-06-01' },
    { memberId: 'm2', distance: 100, stroke: 'dowolny', poolLength: 25, timeMs: 70000, date: '2026-04-01' },
  ];
  const best = bestTimes(rows);
  assert.equal(best.size, 3);
  assert.equal(best.get('m1|100|dowolny|25'), rows[1]);
  assert.equal(best.get('m1|100|dowolny|50'), rows[2]);
  assert.equal(best.get('m2|100|dowolny|25'), rows[4]);
});

test('bestTimes of nothing is empty', () => {
  assert.equal(bestTimes([]).size, 0);
});

// ── One member's season (the member statistics page) ──
const swim = (distance: number, stroke: string, poolLength: number, timeMs: number, date: string) =>
  ({ memberId: 'm1', distance, stroke, poolLength, timeMs, date });
const season = [
  swim(100, 'dowolny', 25, 66000, '2026-05-01'),
  swim(100, 'dowolny', 25, 67000, '2026-03-01'),
  swim(100, 'dowolny', 50, 68000, '2026-04-01'),
  swim(50, 'grzbietowy', 25, 40000, '2026-03-01'),
  swim(50, 'dowolny', 25, 33000, '2026-03-01'),
  swim(200, 'zmienny', 50, 170000, '2026-04-01'),
  swim(200, 'zmienny', 50, 168000, '2026-06-01'),
];

test('eventOptions: most swum first, ties in stroke order then by distance', () => {
  assert.deepEqual(eventOptions(season), [
    { value: '100|dowolny', label: '100 m dowolny', count: 3 },
    { value: '200|zmienny', label: '200 m zmienny', count: 2 },
    { value: '50|dowolny', label: '50 m dowolny', count: 1 },
    { value: '50|grzbietowy', label: '50 m grzbietowy', count: 1 },
  ]);
  assert.deepEqual(eventOptions([]), []);
});

test('progression: one series per pool that has swims, each in date order', () => {
  const series = progression(season, '100|dowolny');
  assert.deepEqual(series.map(s => s.pool), [25, 50]);
  assert.deepEqual(series[0].rows.map(r => r.date), ['2026-03-01', '2026-05-01']);
  assert.deepEqual(series[1].rows.map(r => r.timeMs), [68000]);
  assert.deepEqual(progression(season, '200|zmienny').map(s => s.pool), [50]);
  assert.deepEqual(progression(season, '800|dowolny'), []);
});

test('sortedBests: best row per event and pool, in stroke order, then distance, then pool', () => {
  assert.deepEqual(sortedBests(season).map(r => `${r.distance} ${r.stroke} ${r.poolLength} ${r.timeMs}`), [
    '50 dowolny 25 33000',
    '100 dowolny 25 66000',
    '100 dowolny 50 68000',
    '50 grzbietowy 25 40000',
    '200 zmienny 50 168000',
  ]);
});
