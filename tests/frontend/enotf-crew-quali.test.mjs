import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

// Crew-Anmeldung im eNOTF: Wer seinen Namen aus der Personalliste wählt,
// bekommt die hinterlegte RD-Quali ins Feld daneben. Die Funktion steht
// inline im Template, der Test schneidet sie dort aus.
const LOGIN = '../../plugins/enotf/templates/login.php';

function extractBind(path) {
    const source = readFileSync(new URL(path, import.meta.url), 'utf8');
    const m = /^( *)function bindPersonnelQuali\([\s\S]*?^\1\}$/m.exec(source);
    assert.ok(m, 'bindPersonnelQuali fehlt in ' + path);
    return m[0];
}

function setup(path, personnelQuali) {
    const listeners = {};
    const name = {
        value: '',
        addEventListener(type, fn) { listeners[type] = fn; },
    };
    const select = {
        tagName: 'SELECT',
        value: '',
        options: [{ value: '' }, { value: 'RS' }, { value: 'NFS' }],
        events: 0,
    };
    const sandbox = {
        personnelQuali,
        document: { getElementById: (id) => ({ name, quali: select })[id] ?? null },
        Array,
        refreshSelect: () => { select.events++; },
        eNOTFCustomDropdown: { refresh: () => { select.events++; } },
    };
    vm.runInNewContext(extractBind(path) + '\nbindPersonnelQuali("name", "quali");', sandbox);
    const pick = (value) => { name.value = value; listeners.change?.(); };
    return { select, pick };
}

const quali = { 'Erika Muster': 'NFS', 'Max Muster': 'NA' };

test(`setzt die Quali eines exakt gewählten Mitarbeiters`, () => {
    const { select, pick } = setup(LOGIN, quali);
    pick('Erika Muster');
    assert.equal(select.value, 'NFS');
    assert.equal(select.events, 1);
});

test(`lässt eine gesetzte Quali ohne Treffer stehen`, () => {
    const { select, pick } = setup(LOGIN, quali);
    select.value = 'RS';
    pick('Erika Must');
    pick('Unbekannt [Nachbarwache]');
    assert.equal(select.value, 'RS');
    assert.equal(select.events, 0);
});

test(`übernimmt keine Quali, die nicht als Option existiert`, () => {
    const { select, pick } = setup(LOGIN, quali);
    pick('Max Muster');
    assert.equal(select.value, '');
});

test(`Namen wie constructor treffen nicht den Prototyp`, () => {
    const { select, pick } = setup(LOGIN, {});
    pick('constructor');
    pick('toString');
    assert.equal(select.value, '');
});
