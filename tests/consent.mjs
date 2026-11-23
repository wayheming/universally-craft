import assert from 'node:assert/strict';
import {test} from 'node:test';

const base = process.env.CRAFT_URL ?? 'http://craft.localhost:18182';

test('a configured key leaves the Craft site untouched until the owner opts in', async () => {
  const source = await fetch(new URL('/about', base));
  assert.equal(source.status, 200);
  const html = await source.text();
  assert.match(html, /<h1>About our studio<\/h1>/);
  assert.doesNotMatch(html, /\/p\/[0-9a-f]{32}\/s\.js/);

  const localized = await fetch(new URL('/fr/about', base), {redirect:'manual'});
  assert.equal(localized.status, 404);
});
