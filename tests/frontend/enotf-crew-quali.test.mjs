import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

// Crew-Anmeldung im eNOTF (v1 und v2): Wer seinen Namen aus der
// Personalliste wählt, bekommt die hinterlegte RD-Quali ins Feld daneben.
// Die Funktion steht inline im Template, der Test schneidet sie dort aus.
const templates = {
    v1: '../../plugins/enotf/templates/enotf/login.php',
    v2: '../../plugins/enotf-v2/templates/login.php',
};

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

for (const [version, path] of Object.entries(templates)) {
    test(`${version}: setzt die Quali eines exakt gewählten Mitarbeiters`, () => {
        const { select, pick } = setup(path, quali);
        pick('Erika Muster');
        assert.equal(select.value, 'NFS');
        assert.equal(select.events, 1);
    });

    test(`${version}: lässt eine gesetzte Quali ohne Treffer stehen`, () => {
        const { select, pick } = setup(path, quali);
        select.value = 'RS';
        pick('Erika Must');
        pick('Unbekannt [Nachbarwache]');
        assert.equal(select.value, 'RS');
        assert.equal(select.events, 0);
    });

    test(`${version}: übernimmt keine Quali, die nicht als Option existiert`, () => {
        const { select, pick } = setup(path, quali);
        pick('Max Muster');
        assert.equal(select.value, '');
    });

    test(`${version}: Namen wie constructor treffen nicht den Prototyp`, () => {
        const { select, pick } = setup(path, {});
        pick('constructor');
        pick('toString');
        assert.equal(select.value, '');
    });
}
