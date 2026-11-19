import { test } from 'node:test';
import assert from 'node:assert/strict';
import { startlistLinkKind } from './startlist-link.ts';

test('startlistLinkKind: PDF on live.livetiming.pl', () => {
  assert.equal(startlistLinkKind('https://live.livetiming.pl/zak/2026/11_21_oswiecim/startowa.pdf'), 'pdf');
  assert.equal(startlistLinkKind('  https://live.livetiming.pl/zak/2026/11_21_oswiecim/STARTOWA.PDF  '), 'pdf');
  assert.equal(startlistLinkKind('https://livetiming.pl/files/lista.pdf?v=2'), 'pdf');
});

test('startlistLinkKind: contest page', () => {
  assert.equal(startlistLinkKind('https://livetiming.pl/contest/e90a051a-e507-45d5-a67e-e8b412394eb0'), 'contest');
  assert.equal(startlistLinkKind('https://www.livetiming.pl/contest/E90A051A-E507-45D5-A67E-E8B412394EB0/'), 'contest');
});

test('startlistLinkKind: anything else', () => {
  assert.equal(startlistLinkKind(''), null);
  assert.equal(startlistLinkKind('http://live.livetiming.pl/zak/startowa.pdf'), null);        // https only
  assert.equal(startlistLinkKind('https://evil.com/livetiming.pl/startowa.pdf'), null);
  assert.equal(startlistLinkKind('https://livetiming.pl.evil.com/startowa.pdf'), null);
  assert.equal(startlistLinkKind('https://user@evil.com/x.livetiming.pl/a.pdf'), null);
  assert.equal(startlistLinkKind('https://live.livetiming.pl/zak/2026/11_21_oswiecim/wyniki.lxf'), null);
  assert.equal(startlistLinkKind('https://live.livetiming.pl/zak/'), null);
});
