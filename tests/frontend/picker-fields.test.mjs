import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

// Felder mit Picker aus dem UI-Paket: Das Paket behält das native Feld als
// versteckten Wertspeicher. force-24h-time.js und force-german-date.js
// dürfen es nicht auf type="text" umstellen, sonst liest der Picker nichts
// mehr. Und die Inline-Bearbeitung im Profil baut ihre Auswahl und ihr
// Datum über die Paket-Komponenten und speichert bei change.
const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');

// Versteht genau die Selektoren der beiden Skripte.
function matcher(selector) {
    const m = /^input\[type="([\w-]+)"\](?::not\(\[([\w-]+)\]\))?$/.exec(selector);
    assert.ok(m, 'unerwarteter Selektor: ' + selector);
    return (el) => el.tag === 'input' && el.type === m[1] && !(m[2] && m[2] in el.attrs);
}

function input(type, attrs = {}) {
    return {
        tag: 'input', nodeType: 1, type, attrs: { ...attrs }, value: '', className: '', name: '', id: '',
        getAttribute(name) { return this.attrs[name] ?? null; },
        setAttribute(name, value) { this.attrs[name] = String(value); },
        addEventListener() {},
        matches(selector) { return matcher(selector)(this); },
        querySelectorAll() { return []; },
    };
}

function runForceScript(file, inputs) {
    let observe = null;
    const sandbox = {
        console,
        document: {
            readyState: 'complete',
            body: {},
            querySelectorAll: (selector) => inputs.filter(matcher(selector)),
        },
        MutationObserver: class {
            constructor(cb) { observe = cb; }
            observe() {}
        },
    };
    vm.runInNewContext(read(file), sandbox);
    return (nodes) => observe([{ addedNodes: nodes }]);
}

for (const [file, type, attribute] of [
    ['../../assets/js/force-24h-time.js', 'time', 'data-ignis-timepicker'],
    ['../../assets/js/force-german-date.js', 'date', 'data-ignis-datepicker'],
]) {
    test(`${file.split('/').pop()} lässt Felder mit ${attribute} in Ruhe`, () => {
        const plain = input(type);
        const picker = input(type, { [attribute]: '' });

        const added = runForceScript(file, [plain, picker]);
        assert.equal(plain.type, 'text');
        assert.equal(picker.type, type);

        const laterPlain = input(type);
        const laterPicker = input(type, { [attribute]: '' });
        const wrapper = { nodeType: 1, matches: () => false, querySelectorAll: (s) => [laterPlain, laterPicker].filter(matcher(s)) };
        added([laterPicker, wrapper]);
        assert.equal(laterPlain.type, 'text');
        assert.equal(laterPicker.type, type);
    });
}

function element(tag) {
    const listeners = {};
    return {
        tag, listeners, dataset: {}, children: [], className: '', value: '', type: '',
        addEventListener(type, fn) { (listeners[type] ||= []).push(fn); },
        appendChild(child) { this.children.push(child); return child; },
        fire(type, event = {}) { for (const fn of listeners[type] || []) fn({ key: '', preventDefault() {}, ...event }); },
        focus() { this.focused = true; },
    };
}

async function editCell(dataset) {
    const requests = [];
    const cell = element('td');
    Object.assign(cell.dataset, dataset);
    const classes = new Set(['inline-edit-cell']);
    cell.classList = { add: (...c) => c.forEach((x) => classes.add(x)), remove: (...c) => c.forEach((x) => classes.delete(x)), contains: (c) => classes.has(c) };
    let text = 'Weiblich';
    Object.defineProperty(cell, 'textContent', { get: () => text, set: (v) => { text = v; cell.children = []; } });
    cell.innerHTML = text;
    // Steht für den Auslöser, den das Paket im MutationObserver davorsetzt.
    const trigger = element('button');
    cell.querySelector = (selector) => (selector === 'button' && cell.children.length ? trigger : null);
    cell.contains = (node) => node === cell || node === trigger || cell.children.includes(node);
    const docListeners = { mousedown: new Set() };

    const sandbox = {
        console,
        queueMicrotask,
        JSON,
        Object,
        String,
        setTimeout,
        document: {
            querySelectorAll: (selector) => (selector === '.inline-edit-cell' ? [cell] : []),
            querySelector: () => null,
            getElementById: () => null,
            createElement: (tag) => element(tag),
            addEventListener: (type, fn) => docListeners[type]?.add(fn),
            removeEventListener: (type, fn) => docListeners[type]?.delete(fn),
        },
        fetch: (url, options) => {
            requests.push({ url, body: JSON.parse(options.body) });
            return Promise.resolve({ ok: true, json: () => Promise.resolve({ success: true, display: {} }) });
        },
    };
    sandbox.window = sandbox;
    vm.runInNewContext(read('../../assets/js/modules/mitarbeiter-profile.js'), sandbox);
    sandbox.initMitarbeiterProfile({ basePath: '/', profileId: 7, canEdit: true, canInvite: false, showToast() {}, currentData: { geschlecht: '1', gebdatum: '1990-01-01' } });

    cell.fire('click');
    await new Promise((resolve) => setTimeout(resolve, 0));
    const mousedown = (target) => [...docListeners.mousedown].forEach((fn) => fn({ target }));
    return { cell, field: cell.children[0], trigger, requests, docListeners, mousedown };
}

test('Profil: Auswahl kommt aus dem Paket-Dropdown und speichert bei change', async () => {
    const { field, trigger, requests } = await editCell({ field: 'geschlecht', type: 'select', raw: '1', options: '{"0":"Männlich","1":"Weiblich","2":"Divers"}' });

    assert.equal(field.tag, 'select');
    assert.equal(field.dataset.customDropdown, 'true');
    assert.equal(trigger.focused, true, 'Der Auslöser bekommt den Fokus.');

    // Das versteckte Select verliert den Fokus, sobald man den Auslöser anklickt; das darf nichts speichern.
    field.fire('blur');
    await new Promise((resolve) => setTimeout(resolve, 150));
    assert.equal(requests.length, 0);

    field.value = '0';
    field.fire('change');
    assert.equal(requests.length, 1);
    assert.equal(requests[0].body.geschlecht, '0');
});

test('Profil: Datum kommt aus dem Paket-DatePicker, Escape am Auslöser bricht ab', async () => {
    const { cell, field, trigger, requests } = await editCell({ field: 'gebdatum', type: 'date', raw: '1990-01-01' });

    assert.equal(field.type, 'date');
    assert.equal(field.dataset.ignisDatepicker, '');

    trigger.fire('keydown', { key: 'Escape' });
    assert.equal(cell.classList.contains('inline-editing'), false);
    assert.equal(requests.length, 0);
});

test('Profil: ein Klick außerhalb von Zelle und Auswahlfenster bricht ab', async () => {
    const { cell, requests, docListeners, mousedown } = await editCell({ field: 'gebdatum', type: 'date', raw: '1990-01-01' });

    mousedown({ closest: (selector) => (selector.includes('.ignis-datepicker__panel') ? {} : null) });
    assert.equal(cell.classList.contains('inline-editing'), true, 'Ein Klick im Kalender lässt die Zelle offen.');

    mousedown({ closest: () => null });
    assert.equal(cell.classList.contains('inline-editing'), false);
    assert.equal(docListeners.mousedown.size, 0);
    assert.equal(requests.length, 0);
});
