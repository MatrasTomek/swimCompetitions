import { test } from 'node:test';
import assert from 'node:assert/strict';
import { escapeHtml, rankingTooltip, resultTooltip } from './chart-tooltip.ts';

test('escapeHtml neutralises markup in text taken from LENEX or typed by users', () => {
  assert.equal(escapeHtml('<img src=x onerror=alert(1)>'), '&lt;img src=x onerror=alert(1)&gt;');
  assert.equal(escapeHtml('"><script>alert(1)</script>'), '&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;');
  assert.equal(escapeHtml("O'Neil & Sons"), 'O&#39;Neil &amp; Sons');
});

test('escapeHtml escapes & first, so existing entities are not double-decoded', () => {
  assert.equal(escapeHtml('&lt;b&gt;'), '&amp;lt;b&amp;gt;');
});

test('escapeHtml leaves ordinary text alone and accepts numbers / missing values', () => {
  assert.equal(escapeHtml('Mistrzostwa Okręgu — Oświęcim 2026'), 'Mistrzostwa Okręgu — Oświęcim 2026');
  assert.equal(escapeHtml(420), '420');
  assert.equal(escapeHtml(null), '');
  assert.equal(escapeHtml(undefined), '');
});

const hostile = '<img src=x onerror=alert(1)>';

test('resultTooltip: contest name, time and date from LENEX never become markup', () => {
  const html = resultTooltip({ contestName: hostile, date: '2026-03-14', poolLength: 25, time: '<b>1:05.32</b>', points: 420 });
  assert.equal(html.includes('<img'), false);
  assert.equal(html, '&lt;img src=x onerror=alert(1)&gt;<br/>14.03.2026 · basen 25 m<br/><b>&lt;b&gt;1:05.32&lt;/b&gt;</b> · 420 pkt');
});

test('resultTooltip: ordinary result, with and without points', () => {
  assert.equal(resultTooltip({ contestName: 'Mityng Wiosenny', date: '2026-03-14', poolLength: 25, time: '33.05', points: 420 }),
    'Mityng Wiosenny<br/>14.03.2026 · basen 25 m<br/><b>33.05</b> · 420 pkt');
  assert.equal(resultTooltip({ contestName: 'Puchar', date: '2026-05-09', poolLength: 50, time: '1:11.03', points: null }),
    'Puchar<br/>09.05.2026 · basen 50 m<br/><b>1:11.03</b>');
});

test('resultTooltip: zero points are shown — only a missing value (null) hides the points', () => {
  assert.equal(resultTooltip({ contestName: 'Puchar', date: '2026-05-09', poolLength: 50, time: '1:11.03', points: 0 }),
    'Puchar<br/>09.05.2026 · basen 50 m<br/><b>1:11.03</b> · 0 pkt');
});

test('rankingTooltip: a member name typed by the user never becomes markup', () => {
  assert.equal(rankingTooltip(hostile, '9 startów'), '&lt;img src=x onerror=alert(1)&gt;<br/><b>9 startów</b>');
  assert.equal(rankingTooltip('Amelia Wąs', '<i>9</i>'), 'Amelia Wąs<br/><b>&lt;i&gt;9&lt;/i&gt;</b>');
});
