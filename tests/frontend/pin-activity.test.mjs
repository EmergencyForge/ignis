import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const script = readFileSync(new URL('../../assets/js/pin_activity.js', import.meta.url), 'utf8');

// Führt das Skript in einer gefälschten Browser-Umgebung aus und liefert die
// vom ersten setInterval() registrierte Funktion (der Timeout-Checker) sowie
// das location-Objekt, an dem der Redirect landet.
function run({ pathname, search = '', basePath = '/', autosave }) {
  let now = 0;
  const intervalCallbacks = [];
  const timeouts = [];
  const location = { pathname, href: '', search, origin: 'https://ignis.test' };
  const context = {
    document: {
      body: { dataset: { pinEnabled: 'true', basePath }, appendChild() {} },
      addEventListener() {},
      createElement: () => ({ style: {} }),
      getElementById: () => null,
      head: { appendChild() {} },
    },
    window: { location, addEventListener() {}, EnotfV2Autosave: autosave },
    console: { log() {} },
    setInterval(fn) { intervalCallbacks.push(fn); return intervalCallbacks.length; },
    setTimeout(fn, ms) { timeouts.push({ fn, ms }); return timeouts.length; },
    clearTimeout() {},
    clearInterval() {},
    Date: { now: () => now },
  };
  vm.runInNewContext(script, context);
  return {
    checkTimeout: intervalCallbacks[0],
    location,
    advance: (ms) => { now = ms; },
    fireTimeout: (ms) => timeouts.filter((t) => t.ms === ms).forEach((t) => t.fn()),
  };
}

// Promise-Ketten im Skript abarbeiten lassen
const settle = () => new Promise((resolve) => setImmediate(resolve));

test('redirects to the lockscreen under the matching plugin sub-path', async () => {
  const cases = [
    ['/intra/enotf/protokoll/index.php', '/intra/enotf/lockscreen'],
    ['/intra/enotf/overview', '/intra/enotf/lockscreen'],
    ['/enotf/overview', '/enotf/lockscreen'],
  ];
  for (const [pathname, expected] of cases) {
    const { checkTimeout, location, advance } = run({ pathname });
    advance(300000);
    checkTimeout();
    await settle();
    assert.equal(location.href.split('?')[0], expected, pathname);
  }
});

test('falls back to body.dataset.basePath when the path has no plugin segment', async () => {
  const { checkTimeout, location, advance } = run({ pathname: '/other/page', basePath: '/intra/' });
  advance(300000);
  checkTimeout();
  await settle();
  assert.equal(location.href.split('?')[0], '/intra/enotf/lockscreen');
});

test('passes the current page with its query string as return target', async () => {
  const { checkTimeout, location, advance } = run({ pathname: '/enotf/p/123/anamnese', search: '?t=anamnese' });
  advance(300000);
  checkTimeout();
  await settle();
  assert.equal(location.href, '/enotf/lockscreen?return=' + encodeURIComponent('/enotf/p/123/anamnese?t=anamnese'));
});

test('does not lock before the timeout', async () => {
  const { checkTimeout, location, advance } = run({ pathname: '/enotf/overview' });
  advance(299999);
  checkTimeout();
  await settle();
  assert.equal(location.href, '');
});

test('waits for the autosave before it redirects', async () => {
  let finish;
  let calls = 0;
  const autosave = { flushAll: () => { calls++; return new Promise((resolve) => { finish = resolve; }); } };
  const { checkTimeout, location, advance } = run({ pathname: '/enotf/p/123/anamnese', autosave });
  advance(300000);
  checkTimeout();
  checkTimeout();
  await settle();
  assert.equal(calls, 1);
  assert.equal(location.href, '');

  finish();
  await settle();
  assert.match(location.href, /^\/enotf\/lockscreen\?return=/);
});

test('locks after three seconds even if the save hangs or fails', async () => {
  const hanging = run({ pathname: '/enotf/p/123/anamnese', autosave: { flushAll: () => new Promise(() => {}) } });
  hanging.advance(300000);
  hanging.checkTimeout();
  await settle();
  assert.equal(hanging.location.href, '');
  hanging.fireTimeout(3000);
  await settle();
  assert.match(hanging.location.href, /^\/enotf\/lockscreen\?return=/);

  const failing = run({ pathname: '/enotf/p/123/anamnese', autosave: { flushAll: () => { throw new Error('kaputt'); } } });
  failing.advance(300000);
  failing.checkTimeout();
  await settle();
  assert.match(failing.location.href, /^\/enotf\/lockscreen\?return=/);
});
