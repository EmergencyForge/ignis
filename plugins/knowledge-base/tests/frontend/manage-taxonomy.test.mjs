import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

// Kategorien & Tags: die Tag-Farbe ist der Paket-Farbwähler, den der Dialog
// per JS füllt.
const page = readFileSync(new URL('../../templates/lexicon/manage-taxonomy.php', import.meta.url), 'utf8');
const script = [...page.matchAll(/<script>([\s\S]*?)<\/script>/g)].map((m) => m[1]).find((s) => s.includes('tagColor'))
    .replace(/<\?= BASE_PATH \?>/g, '/')
    // import() braucht in vm einen Modul-Lader; der Test reicht den Farbwähler selbst herein.
    .replace('import(', 'loadModule(');

function run() {
    const els = {};
    const el = (props = {}) => Object.assign(new EventTarget(), { value: '', style: {}, ...props });
    const content = el();
    els.tagColor = el({ closest: (selector) => (selector === '.modal-content' ? content : null) });
    const imports = [];
    const colors = [];
    const ctx = {
        document: { getElementById: (id) => (els[id] ??= el()) },
        Dialog: { openElement() {}, closeElement() {} },
        loadModule: async (url) => {
            imports.push(url);
            return { getColorpicker: (input) => (input === els.tagColor ? { setValue: (hex) => colors.push(hex) } : null) };
        },
    };
    vm.runInNewContext(script, ctx);
    return { els, content, imports, colors, ctx };
}

test('Tag-Dialog setzt den Farbwähler, die Vorschau folgt dessen Ereignis', async () => {
    const { els, content, imports, colors, ctx } = run();

    ctx.editTag({ id: 1, name: 'Neu', color: '#ff0000' });
    ctx.showTagModal();
    await new Promise((resolve) => setImmediate(resolve));
    assert.deepEqual(imports, ['/assets/js/ui/colorpicker.js', '/assets/js/ui/colorpicker.js']);
    assert.deepEqual(colors, ['#ff0000', '#6c757d']);

    content.dispatchEvent(new CustomEvent('ignis:color-change', { detail: { hex: '#00FF00' } }));
    assert.equal(els.tagPreview.style.backgroundColor, '#00FF00');
});
