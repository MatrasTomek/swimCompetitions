import { test } from 'node:test';
import assert from 'node:assert/strict';
import { Subject } from 'rxjs';
import { latestRequest } from './latest-request.ts';

type Outcome = { key: number; data?: string; error?: unknown };

function setup() {
  const pending = new Map<number, Subject<string>>();
  const started: number[] = [];
  const req = latestRequest<number, string>(key => {
    started.push(key);
    const s = new Subject<string>();
    pending.set(key, s);
    return s;
  });
  const seen: Outcome[] = [];
  const sub = req.outcome$.subscribe(o => seen.push(o));
  return { req, pending, started, seen, sub };
}

test('a slower answer for an earlier key never overwrites the current one', () => {
  const { req, pending, seen } = setup();
  req.load(2026);
  req.load(2027);                      // user switched season before 2026 answered
  pending.get(2027)!.next('results 2027');
  pending.get(2026)!.next('results 2026 (late)');
  assert.deepEqual(seen, [{ key: 2027, data: 'results 2027' }]);
});

test('the earlier request is cancelled (unsubscribed) when a new key is loaded', () => {
  const { req, pending } = setup();
  req.load(2026);
  assert.equal(pending.get(2026)!.observed, true);
  req.load(2027);
  assert.equal(pending.get(2026)!.observed, false);
  assert.equal(pending.get(2027)!.observed, true);
});

test('an error is reported for its key and later loads still work', () => {
  const { req, pending, seen } = setup();
  req.load(2026);
  pending.get(2026)!.error('boom');
  req.load(2027);
  pending.get(2027)!.next('ok');
  assert.deepEqual(seen, [{ key: 2026, error: 'boom' }, { key: 2027, data: 'ok' }]);
});

test('loading the same key again starts a fresh request', () => {
  const { req, pending, started, seen } = setup();
  req.load(2026);
  pending.get(2026)!.next('first');
  req.load(2026);
  pending.get(2026)!.next('second');
  assert.deepEqual(started, [2026, 2026]);
  assert.deepEqual(seen.map(o => o.data), ['first', 'second']);
});
