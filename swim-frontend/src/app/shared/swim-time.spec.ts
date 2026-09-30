import { test } from 'node:test';
import assert from 'node:assert/strict';
import { formatSwimTime, eventLabel, eventKey, pbKey, bestTimes } from './swim-time.ts';

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
