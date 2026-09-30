import { test } from 'node:test';
import assert from 'node:assert/strict';
import { supportLink } from './support-link.ts';

test('supportLink: valid https address', () => {
  assert.equal(supportLink('https://buycoffee.to/ndsoft'), 'https://buycoffee.to/ndsoft');
});

test('supportLink: trims and normalizes', () => {
  assert.equal(supportLink('  https://buycoffee.to/ndsoft \n'), 'https://buycoffee.to/ndsoft');
  assert.equal(supportLink('HTTPS://Buycoffee.to/ndsoft'), 'https://buycoffee.to/ndsoft');
});

test('supportLink: empty → null', () => {
  assert.equal(supportLink(''), null);
  assert.equal(supportLink('   '), null);
});

test('supportLink: other protocols → null', () => {
  assert.equal(supportLink('http://buycoffee.to/ndsoft'), null);
  assert.equal(supportLink('javascript:alert(1)'), null);
  assert.equal(supportLink('data:text/html,<b>x</b>'), null);
  assert.equal(supportLink('mailto:info@nd-soft.pl'), null);
});

test('supportLink: not an absolute address → null', () => {
  assert.equal(supportLink('buycoffee.to/ndsoft'), null);
  assert.equal(supportLink('//buycoffee.to/ndsoft'), null);
  assert.equal(supportLink('https://'), null);
  assert.equal(supportLink('wpłaty wkrótce'), null);
});
