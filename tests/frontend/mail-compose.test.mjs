import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

// plugins/mail/assets/mail-compose.js: alle Schreib-Requests laufen durch
// eine Kette. Senden wartet einen laufenden Autosave ab, statt neben ihm
// zu laufen (in Lex stritten sich beide um den CSRF-Token), und nach dem
// Senden schreibt kein Autosave mehr.
const source = readFileSync(new URL('../../plugins/mail/assets/mail-compose.js', import.meta.url), 'utf8');

function element(extra = {}) {
    const listeners = {};
    return {
        listeners,
        dataset: {},
        hidden: false,
        value: '',
        addEventListener(type, fn) { (listeners[type] ||= []).push(fn); },
        getAttribute(name) { return (extra.attrs || {})[name] ?? null; },
        setAttribute() {},
        querySelector: () => null,
        querySelectorAll: () => [],
        closest: () => null,
        ...extra,
    };
}

function compose({ draftId = '', inDrawer = false, onMailPage = false } = {}) {
    const timers = [];
    const requests = [];
    const snacks = [];
    const stored = new Map();
    let reloads = 0;
    let closed = 0;
    let inFlight = 0;
    let maxInFlight = 0;
    let editorOptions = null;

    const closeButton = element({ click() { closed++; } });
    const drawer = inDrawer ? element({
        classList: { add() {}, remove() {} },
        querySelector: (selector) => (selector === '[data-ignis-drawer-close]' ? closeButton : null),
    }) : null;
    const token = element({ value: 'tok-1' });
    const form = element({
        attrs: { 'data-base': '/', 'data-draft-id': draftId, 'data-editor-src': '/editor.js' },
        querySelector: (selector) => (selector === 'input[name="csrf_token"]' ? token : null),
        closest: (selector) => (selector === '.ignis-drawer' ? drawer : null),
    });
    const elements = {
        'mail-compose-form': form,
        'mail-compose-subject': element(),
        'mail-compose-editor': element({ attrs: { 'data-efe-content': '{"type":"doc","content":[]}' } }),
        'mail-compose-attachments': element(),
        'mail-compose-discard': element(),
    };

    class FormData {
        constructor() { this.values = new Map(); }
        set(key, value) { this.values.set(key, value); }
        delete(key) { this.values.delete(key); }
    }

    function fetch(url, init) {
        inFlight++;
        maxInFlight = Math.max(maxInFlight, inFlight);
        let resolve;
        const done = new Promise((r) => { resolve = r; });
        const request = {
            url,
            body: init.body,
            respond(data) {
                inFlight--;
                resolve({ ok: true, status: 200, json: () => Promise.resolve({ success: true, csrf_token: 'tok-2', ...data }) });
            },
        };
        requests.push(request);
        return done;
    }

    const window = {
        EmergencyForgeEditor: {
            createEditor(mount, options) {
                editorOptions = options;
                return { getJSON: () => ({ type: 'doc', content: [] }) };
            },
        },
        confirm: () => true,
        location: { href: '', reload() { reloads++; } },
        ignis: { snack: { success: (text) => snacks.push(['success', text]), warning: (text) => snacks.push(['warning', text]) } },
    };

    vm.runInNewContext(source, {
        window,
        document: {
            readyState: 'complete',
            getElementById: (id) => elements[id] ?? null,
            querySelector: (selector) => (onMailPage && selector === '.ignis-mail[data-ignis-workbench]' ? element() : null),
            body: element(),
            createElement: () => element(),
        },
        sessionStorage: { setItem: (key, value) => stored.set(key, value) },
        fetch,
        FormData,
        Promise,
        JSON,
        setTimeout: (fn) => { timers.push(fn); return timers.length; },
        clearTimeout: (id) => { timers[id - 1] = null; },
    });

    const settle = () => new Promise((r) => setImmediate(r));

    return {
        requests,
        token,
        snacks,
        stored,
        location: window.location,
        reloads: () => reloads,
        closed: () => closed,
        maxInFlight: () => maxInFlight,
        type() { editorOptions.onUpdate(); },
        runTimers() {
            const pending = timers.splice(0);
            pending.forEach((fn) => fn && fn());
        },
        submit() { form.listeners.submit.forEach((fn) => fn({ preventDefault() {} })); },
        settle,
    };
}

test('Senden wartet den laufenden Autosave ab und läuft nie parallel', async () => {
    const c = compose();
    c.type();
    c.runTimers(); // Autosave startet: Entwurf anlegen
    await c.settle();
    assert.deepEqual(c.requests.map((r) => r.url), ['/mail/drafts']);

    c.submit(); // Senden, während der Autosave noch läuft
    await c.settle();
    assert.equal(c.requests.length, 1, 'Senden startet nicht neben dem Autosave.');

    c.requests[0].respond({ messageId: 5 });
    await c.settle();
    assert.equal(c.requests[1].url, '/mail/drafts/5');
    c.requests[1].respond({ messageId: 5 });
    await c.settle();
    assert.equal(c.requests[2].url, '/mail/drafts/5/send');
    assert.equal(c.requests[2].body.values.get('csrf_token'), 'tok-2', 'Der Token aus der letzten Antwort geht mit.');
    c.requests[2].respond({ messageId: 5, unresolvedAddresses: [] });
    await c.settle();

    // Nach dem Senden schreibt kein Autosave mehr.
    c.type();
    c.runTimers();
    await c.settle();
    assert.equal(c.requests.length, 3);
    assert.equal(c.maxInFlight(), 1);
});

test('Ein geplanter Autosave entfällt beim Senden', async () => {
    const c = compose({ draftId: '9' });
    c.type(); // Autosave geplant, noch nicht gelaufen
    c.submit();
    await c.settle();
    c.runTimers(); // der abgebrochene Timer tut nichts mehr
    await c.settle();

    assert.deepEqual(c.requests.map((r) => r.url), ['/mail/drafts/9/send']);
    c.requests[0].respond({ messageId: 9, unresolvedAddresses: [] });
    await c.settle();
    assert.equal(c.maxInFlight(), 1);
});

// Nach dem Senden kennen Liste und Zähler der Mail-Seite die neue Mail noch
// nicht: dort lädt die Seite neu, die Meldung überdauert im sessionStorage.
async function sendOnce(c) {
    c.submit();
    await c.settle();
    c.requests[0].respond({ messageId: 7, unresolvedAddresses: [] });
    await c.settle();
}

test('Im Drawer auf der Mail-Seite lädt die Liste nach dem Senden neu', async () => {
    const c = compose({ draftId: '7', inDrawer: true, onMailPage: true });
    await sendOnce(c);

    assert.equal(c.reloads(), 1);
    assert.deepEqual(JSON.parse(c.stored.get('ignis.mail.snack')), { kind: 'success', text: 'Mail gesendet.' });
    assert.deepEqual(c.snacks, [], 'Die Meldung zeigt erst die neu geladene Seite.');
});

test('Im Drawer auf einer anderen Seite schließt nur der Drawer', async () => {
    const c = compose({ draftId: '7', inDrawer: true });
    await sendOnce(c);

    assert.equal(c.reloads(), 0);
    assert.equal(c.closed(), 1);
    assert.deepEqual(c.snacks, [['success', 'Mail gesendet.']]);
});

test('Ohne Drawer geht es nach „Gesendet“, die Meldung kommt mit', async () => {
    const c = compose({ draftId: '7' });
    await sendOnce(c);

    assert.equal(c.location.href, '/mail/sent');
    assert.equal(JSON.parse(c.stored.get('ignis.mail.snack')).text, 'Mail gesendet.');
});
