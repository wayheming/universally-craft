import assert from 'node:assert/strict';
import {test} from 'node:test';

const base = process.env.CRAFT_URL ?? 'http://craft.localhost:18182';

/** Fetch the actual HTML over HTTP, without running page JavaScript. */
function page(path, options = {}) {
  return fetch(new URL(path, base), {redirect: 'manual', ...options});
}

test('Craft serves the original document on the original URL', async () => {
  const response = await page('/about');
  assert.equal(response.status, 200);
  assert.match(await response.text(), /<h1>About our studio<\/h1>/);
});

test('Craft serves translated HTML and localized navigation on French URLs', async () => {
  for (const [path, heading] of [['/fr', 'Bienvenue dans notre studio'], ['/fr/about', 'À propos de notre studio']]) {
    const response = await page(path);
    assert.equal(response.status, 200);
    const html = await response.text();
    assert.ok(html.includes(`<h1>${heading}</h1>`), `Expected the translated heading on ${path}`);
    assert.match(html, /<html[^>]+lang="fr(?:-FR)?"/i);
    assert.match(html, /href="(?:https?:\/\/[^/"]+)?\/fr\/about\/?"/);
    assert.match(html, /<script[^>]+\/p\/[0-9a-f]{32}\/s\.js/);
    if (process.env.UNIVERSALLY_API_KEY) {
      assert.ok(!html.includes(process.env.UNIVERSALLY_API_KEY.slice(32)));
    }
  }
});

test('missing routes and disabled language prefixes keep their Craft status', async () => {
  for (const path of ['/fr/missing-page', '/de/about', '/fr/admin/login', '/fr/actions/graphql/api']) {
    assert.equal((await page(path)).status, 404, path);
  }
});

test('the control panel and requests with query data stay outside translation', async () => {
  const admin = await page('/admin/login');
  assert.ok([200, 302].includes(admin.status));
  assert.doesNotMatch(await admin.text(), /\/p\/[0-9a-f]{32}\/s\.js/);
  const source = await page('/about?token=invalid-test-token');
  assert.doesNotMatch(await source.text(), /\/p\/[0-9a-f]{32}\/s\.js/);
});

test('Craft preview and authorization headers bypass the language hook', async () => {
  for (const header of ['X-Craft-Token', 'X-Craft-Preview-Token', 'X-Craft-Authorization', 'Authorization']) {
    const response = await page('/about', {headers: {[header]: 'local-test-value'}});
    assert.doesNotMatch(await response.text(), /\/p\/[0-9a-f]{32}\/s\.js/, header);
  }
});

test('private HTML stays in Craft and its localized URL cannot be indexed', async () => {
  const response = await page('/fr/private');
  assert.equal(response.status, 200);
  assert.match(response.headers.get('cache-control') ?? '', /no-store|no-cache/);
  assert.match(response.headers.get('x-robots-tag') ?? '', /noindex/);
  assert.match(await response.text(), /<h1>Private content<\/h1>/);
});
