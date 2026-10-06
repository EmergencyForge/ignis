import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

// update-profile überschreibt jedes Grundfeld, das im Payload fehlt, mit
// leer. So verlor die Charakter-ID bei jedem Speichern im Profil ihren Wert.
const source = readFileSync(new URL('../../assets/js/modules/mitarbeiter-profile.js', import.meta.url), 'utf8');

const currentData = {
    fullname: 'Max Muster',
    gebdatum: '1990-01-15',
    geschlecht: '0',
    charakterid: 'CHAR-42',
    discordtag: 'max',
    telefonnr: '555-0100',
    dienstnr: '042',
    zusatzqual: 'Praxisanleiter',
    dienstgrad: '1',
    qualird: '1',
    qualifw2: '1',
};

function savedPayload() {
    const elements = {
        qualiSaveBtn: { addEventListener(type, fn) { this.onclick = fn; } },
        qualiEditForm: { dienstgrad: { value: '3' }, qualird: { value: '2' }, qualifw2: { value: '4' } },
    };
    const requests = [];
    const context = {
        document: {
            getElementById: (id) => elements[id] ?? null,
            querySelector: () => null,
            querySelectorAll: () => [],
            addEventListener() {},
        },
        fetch: (url, options) => {
            requests.push(JSON.parse(options.body));
            return new Promise(() => {});
        },
    };
    context.window = context;

    vm.runInNewContext(source, context);
    context.initMitarbeiterProfile({ basePath: '/', profileId: 7, canEdit: true, canInvite: false, currentData });
    elements.qualiSaveBtn.onclick();

    assert.equal(requests.length, 1);
    return requests[0];
}

test('Speichern im Qualifikationsfenster schickt alle Grundfelder mit', () => {
    const payload = savedPayload();

    for (const [key, value] of Object.entries(currentData)) {
        if (['dienstgrad', 'qualird', 'qualifw2'].includes(key)) continue;
        assert.equal(payload[key], value, key + ' fehlt oder ist verändert');
    }
    assert.equal(payload.id, 7);
    assert.equal(payload.dienstgrad, '3');
    assert.equal(payload.pfp, '');
});
