import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const script = readFileSync(new URL('../../assets/js/pin_activity.js', import.meta.url), 'utf8');

// Führt das Skript in einer gefälschten Browser-Umgebung aus und liefert die
// vom ersten setInterval() registrierte Funktion (der Timeout-Checker) sowie
// das location-Objekt, an dem der Redirect landet.
function run({ pathname, basePath = '/' }) {
  let now = 0;
  const intervalCallbacks = [];
  const location = { pathname, href: '', search: '', origin: 'https://ignis.test' };
  const context = {
    document: {
      body: { dataset: { pinEnabled: 'true', basePath }, appendChild() {} },
      addEventListener() {},
      createElement: () => ({ style: {} }),
      getElementById: () => null,
      head: { appendChild() {} },
    },
    window: { location, addEventListener() {} },
    console: { log() {} },
    setInterval(fn) { intervalCallbacks.push(fn); return intervalCallbacks.length; },
    setTimeout: () => 0,
    clearTimeout() {},
    clearInterval() {},
    Date: { now: () => now },
  };
  vm.runInNewContext(script, context);
  return { checkTimeout: intervalCallbacks[0], location, advance: (ms) => { now = ms; } };
}

test('redirects to the lockscreen under the matching plugin sub-path', () => {
  const cases = [
    ['/intra/enotf/protokoll/index.php', '/intra/enotf/lockscreen'],
    ['/intra/enotf-v2/overview', '/intra/enotf-v2/lockscreen'],
    ['/enotf/overview', '/enotf/lockscreen'],
  ];
  for (const [pathname, expected] of cases) {
    const { checkTimeout, location, advance } = run({ pathname });
    advance(300000);
    checkTimeout();
    assert.equal(location.href, expected, pathname);
  }
});

test('falls back to body.dataset.basePath when the path has no plugin segment', () => {
  const { checkTimeout, location, advance } = run({ pathname: '/other/page', basePath: '/intra/' });
  advance(300000);
  checkTimeout();
  assert.equal(location.href, '/intra/enotf/lockscreen');
});
