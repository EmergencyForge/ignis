import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../src/helpers.php', import.meta.url), 'utf8');
const script = source.slice(source.indexOf('function csrf_head()')).match(/<script>([\s\S]*?)<\/script>/)[1];

function browser({ fetch = true } = {}) {
    const sent = [];
    class XMLHttpRequest {
        open(method, url, async = true) {
            this.method = method;
            this.url = url;
            this.async = async;
            this.headers = new Headers();
        }
        setRequestHeader(name, value) { this.headers.append(name, value); }
        send(body) { sent.push({ method: this.method, url: this.url, headers: this.headers, body }); }
    }
    const window = { XMLHttpRequest };
    if (fetch) {
        window.fetch = (input, init) => {
            sent.push({ input, ...init });
            return Promise.resolve('response');
        };
    }
    vm.runInNewContext(script, {
        window,
        document: { querySelector: () => ({ content: 'session-token' }) },
        location: { href: 'https://ignis.test/enotf/login', origin: 'https://ignis.test' },
        URL,
        Headers,
        WeakMap,
    });
    return { window, sent };
}

test('XHR sends the session token on same-origin writes and preserves request data', () => {
    const { window, sent } = browser();
    for (const method of ['POST', 'PUT', 'PATCH', 'DELETE']) {
        const xhr = new window.XMLHttpRequest();
        xhr.open(method, '/api/enotf/save-fields');
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send('enr=1&field=notes&value=ok');
        const request = sent.at(-1);
        assert.equal(request.headers.get('X-CSRF-Token'), 'session-token');
        assert.equal(request.headers.get('Content-Type'), 'application/x-www-form-urlencoded');
        assert.equal(request.body, 'enr=1&field=notes&value=ok');
    }
});

test('XHR never adds the token to GET or another origin', () => {
    const { window, sent } = browser();
    for (const [method, url] of [
        ['GET', '/api/enotf/save-fields'],
        ['POST', 'https://other.test/api'],
        ['POST', '//other.test/api'],
        ['POST', 'http://ignis.test/api'],
        ['POST', 'https://ignis.test:8443/api'],
    ]) {
        const xhr = new window.XMLHttpRequest();
        xhr.open(method, url);
        xhr.send();
        assert.equal(sent.at(-1).headers.has('X-CSRF-Token'), false, `${method} ${url}`);
    }
});

test('XHR preserves an explicit plugin token and resets state when reused', () => {
    const { window, sent } = browser();
    const xhr = new window.XMLHttpRequest();
    xhr.open('POST', '/enotf-v2/qm');
    xhr.setRequestHeader('x-CsRf-ToKeN', 'plugin-token');
    xhr.send();
    assert.equal(sent.at(-1).headers.get('X-CSRF-Token'), 'plugin-token');
    xhr.open('POST', '/api/enotf/save-fields');
    xhr.send();
    assert.equal(sent.at(-1).headers.get('X-CSRF-Token'), 'session-token');
    xhr.open('POST', 'https://other.test/api');
    xhr.send();
    assert.equal(sent.at(-1).headers.has('X-CSRF-Token'), false);
});

test('XHR protection works even when fetch is unavailable', () => {
    const { window, sent } = browser({ fetch: false });
    const xhr = new window.XMLHttpRequest();
    xhr.open('POST', '/api/manv/api');
    xhr.send();
    assert.equal(sent[0].headers.get('X-CSRF-Token'), 'session-token');
});

test('fetch retains its token behavior and does not overwrite explicit headers', async () => {
    const { window, sent } = browser();
    assert.equal(await window.fetch('/api/enotf-v2/save-fields', { method: 'POST' }), 'response');
    assert.equal(sent.at(-1).headers.get('X-CSRF-Token'), 'session-token');
    await window.fetch('/enotf-v2/qm', { method: 'POST', headers: { 'X-CSRF-Token': 'plugin-token' } });
    assert.equal(sent.at(-1).headers.get('X-CSRF-Token'), 'plugin-token');
    await window.fetch('/api/enotf-v2/save-fields');
    assert.equal(sent.at(-1).headers, undefined);
});

test('fetch URL objects and Request objects do not leak tokens to another origin', async () => {
    const { window, sent } = browser();
    for (const input of [
        'https://other.test/api',
        new URL('https://other.test/api'),
        new Request('https://other.test/api', { method: 'POST' }),
    ]) {
        await window.fetch(input, { method: 'POST' });
        assert.equal(sent.at(-1).headers, undefined);
    }
    await window.fetch(new URL('https://ignis.test/api'), { method: 'POST' });
    assert.equal(sent.at(-1).headers.get('X-CSRF-Token'), 'session-token');
});
