import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

// eNOTF-Autosave vor der PIN-Sperre: getippter Text, dessen change noch
// aussteht, landet als Entwurf im localStorage, flushAll() schickt ihn mit,
// und pagehide schickt Offenes per keepalive hinterher.
const script = readFileSync(new URL('../../plugins/enotf/assets/autosave.js', import.meta.url), 'utf8');

// Gerade genug Selektor-Logik für die Selektoren in autosave.js
function matches(el, selector) {
    return selector.split(',').some((raw) => {
        const part = raw.trim();
        if (part.startsWith('.')) return false;
        const tag = /^[a-z]+/.exec(part);
        if (tag && tag[0] !== el.tag) return false;
        const has = (name, value) => (value === undefined ? name in el.attrs : el.attrs[name] === value);
        const nots = [...part.matchAll(/:not\(\[([\w-]+)(?:="([^"]*)")?\]\)/g)];
        const attrs = [...part.replace(/:not\(\[[^\]]*\]\)/g, '').matchAll(/\[([\w-]+)(?:="([^"]*)")?\]/g)];
        return attrs.every(([, n, v]) => has(n, v)) && nots.every(([, n, v]) => !has(n, v));
    });
}

class FakeElement {
    constructor(tag, attrs, value = '') {
        this.tag = tag;
        this.attrs = attrs;
        this.value = value;
        this.readOnly = 'readonly' in attrs;
        this.disabled = false;
        this.classList = { add() {}, remove() {} };
    }
    get name() { return this.attrs.name || ''; }
    get type() { return this.attrs.type || (this.tag === 'textarea' ? 'textarea' : 'text'); }
    matches(selector) { return matches(this, selector); }
}

function setup({ fields, storage = {}, locked = false, respond }) {
    const listeners = {};
    const windowListeners = {};
    const store = new Map(Object.entries(storage));
    const requests = [];
    const root = {
        dataset: { enr: '123', saveUrl: '/api/enotf/save-fields', ...(locked ? { locked: '1' } : {}) },
        querySelectorAll: (sel) => (sel.startsWith('.') ? [] : fields.filter((el) => el.matches(sel))),
        querySelector: (sel) => fields.find((el) => sel === '[name="' + el.name + '"]') || null,
        addEventListener: (type, fn) => { listeners[type] = fn; },
        contains: (el) => fields.includes(el),
    };
    const document = {
        readyState: 'complete',
        activeElement: null,
        querySelector: (sel) => (sel === '[data-enotf-autosave]' ? root : null),
    };
    const window = { addEventListener: (type, fn) => { windowListeners[type] = fn; } };
    const context = {
        document,
        window,
        HTMLElement: FakeElement,
        CSS: { escape: (s) => s },
        localStorage: {
            getItem: (k) => (store.has(k) ? store.get(k) : null),
            setItem: (k, v) => store.set(k, String(v)),
            removeItem: (k) => store.delete(k),
        },
        fetch: (url, init) => {
            const body = JSON.parse(init.body);
            requests.push({ url, keepalive: init.keepalive === true, fields: body.fields });
            const data = respond ? respond(body.fields) : { ok: true, updated: Object.keys(body.fields), errors: {} };
            return Promise.resolve({ status: 200, json: () => Promise.resolve(data) });
        },
        // Nur die Warteschleife von flushAll() läuft, der Debounce bleibt stehen
        setTimeout: (fn, ms) => (ms === 100 ? setTimeout(fn, 0) : 0),
        clearTimeout() {},
        Promise,
        Object,
        JSON,
        String,
        Array,
        Map,
        Set,
    };
    vm.runInNewContext(script, context);
    return {
        api: window.EnotfV2Autosave,
        document,
        requests,
        store,
        type: (el, value) => { el.value = value; listeners.input({ target: el }); },
        pagehide: () => windowListeners.pagehide(),
    };
}

const settle = () => new Promise((resolve) => setImmediate(resolve));

test('flushAll saves text that was typed but never left the field', async () => {
    const text = new FakeElement('textarea', { name: 'anmerkungen' }, 'alt');
    const env = setup({ fields: [text] });
    env.document.activeElement = text;

    env.type(text, 'Patient klagt über Brustschmerz');
    assert.deepEqual(JSON.parse(env.store.get('enotf-draft:123')), { anmerkungen: 'Patient klagt über Brustschmerz' });

    await env.api.flushAll();
    assert.equal(env.requests.length, 1);
    assert.deepEqual(env.requests[0].fields, { anmerkungen: 'Patient klagt über Brustschmerz' });
    assert.equal(env.store.has('enotf-draft:123'), false, 'bestätigter Entwurf bleibt nicht liegen');
});

test('restores a draft from an earlier page load into the field and saves it', async () => {
    const text = new FakeElement('textarea', { name: 'anmerkungen' }, 'alt');
    const env = setup({ fields: [text], storage: { 'enotf-draft:123': JSON.stringify({ anmerkungen: 'neu' }) } });
    await settle();

    assert.equal(text.value, 'neu');
    assert.equal(env.requests.length, 1);
    assert.deepEqual(env.requests[0].fields, { anmerkungen: 'neu' });
    assert.equal(env.store.has('enotf-draft:123'), false);
});

test('keeps the draft when the save fails, drops it when the server rejects the value', async () => {
    const text = new FakeElement('input', { name: 'patname', type: 'text' }, '');
    const failing = setup({ fields: [text], respond: () => ({ ok: false, updated: [], errors: { _request: 'Datenbankfehler' } }) });
    failing.type(text, 'Muster');
    await failing.api.flushAll();
    assert.equal(failing.store.has('enotf-draft:123'), true);

    const other = new FakeElement('input', { name: 'unbekannt', type: 'text' }, '');
    const rejected = setup({ fields: [other], respond: () => ({ ok: false, updated: [], errors: { unbekannt: 'Unbekanntes Feld' } }) });
    rejected.type(other, 'x');
    await rejected.api.flushAll();
    assert.equal(rejected.store.has('enotf-draft:123'), false);
});

test('ignores fields excluded from autosave and date inputs', async () => {
    const ignored = new FakeElement('input', { name: 'spo2', type: 'text', 'data-autosave-ignore': '' }, '');
    const date = new FakeElement('input', { name: 'edatum', type: 'text', 'data-date-input': 'true' }, '');
    const env = setup({ fields: [ignored, date] });
    env.type(ignored, '96');
    env.type(date, '0101');
    assert.equal(env.store.has('enotf-draft:123'), false);
});

test('pagehide sends open changes with keepalive', async () => {
    const text = new FakeElement('textarea', { name: 'anmerkungen' }, '');
    const env = setup({ fields: [text] });
    env.type(text, 'unterwegs');
    env.pagehide();

    assert.equal(env.requests.length, 1);
    assert.equal(env.requests[0].keepalive, true);
    assert.deepEqual(env.requests[0].fields, { anmerkungen: 'unterwegs' });
});

test('a locked protocol discards drafts and sends nothing', async () => {
    const text = new FakeElement('textarea', { name: 'anmerkungen', readonly: '' }, 'alt');
    const env = setup({ fields: [text], locked: true, storage: { 'enotf-draft:123': JSON.stringify({ anmerkungen: 'neu' }) } });
    await env.api.flushAll();
    env.pagehide();

    assert.equal(text.value, 'alt');
    assert.equal(env.requests.length, 0);
    assert.equal(env.store.has('enotf-draft:123'), false);
});
