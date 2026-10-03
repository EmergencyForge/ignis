import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

// Das Sperr-Skript freigegebener eNOTF-Protokolle. Bis zur gemeinsamen
// Datei stand es kopiert in 111 Templates und brach dort an einem leeren
// Selektor ab, ohne etwas zu sperren.
const source = readFileSync(new URL('../../assets/js/enotf-lock.js', import.meta.url), 'utf8');

function field(tag, type = '') {
    return { tag, type, attrs: {}, setAttribute(name, value) { this.attrs[name] = String(value); } };
}

// Versteht genau die Selektoren des Skripts, ein unbekannter scheitert wie im Browser.
function documentWith(fields) {
    const matches = {
        input: (f) => f.tag === 'input',
        textarea: (f) => f.tag === 'textarea',
        select: (f) => f.tag === 'select',
        'input[type="checkbox"]': (f) => f.tag === 'input' && f.type === 'checkbox',
        'input[type="radio"]': (f) => f.tag === 'input' && f.type === 'radio',
    };
    return {
        querySelectorAll(selector) {
            const parts = selector.split(',').map((s) => s.trim());
            const tests = parts.map((p) => {
                if (!matches[p]) throw new SyntaxError('unbekannter Selektor: ' + p);
                return matches[p];
            });
            return fields.filter((f) => tests.some((t) => t(f)));
        },
    };
}

test('sperrt Textfelder, Auswahllisten, Ankreuzfelder und Radios', () => {
    const fields = [field('input', 'text'), field('textarea'), field('select'), field('input', 'checkbox'), field('input', 'radio')];
    vm.runInNewContext(source, { document: documentWith(fields) });

    assert.equal(fields[0].attrs.readonly, 'readonly');
    assert.equal(fields[1].attrs.readonly, 'readonly');
    assert.equal(fields[2].attrs.disabled, 'disabled');
    assert.equal(fields[3].attrs.disabled, 'disabled');
    assert.equal(fields[4].attrs.disabled, 'disabled');
});
