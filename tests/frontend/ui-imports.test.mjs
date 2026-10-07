// Seiten-Skripte importieren benannte Exporte aus den gebauten UI-Modulen
// unter public/assets/js/ui. Fehlt dort ein Export, bricht der Browser das
// ganze Modul beim Laden ab: Der Kalender startete so nach dem Umzug der
// UI-Module ins Paket gar nicht mehr, weil Rollup die Exporte der Einstiege
// entfernt hatte. Dieser Test liest die gebauten Dateien und prüft jeden
// benannten Import aus ../ui/.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { join, resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');
const pagesDir = join(root, 'public/assets/js/pages');
const uiDir = join(root, 'public/assets/js/ui');

function exportedNames(source) {
    const names = new Set();
    for (const [, list] of source.matchAll(/export\s*\{([^}]*)\}/g)) {
        for (const part of list.split(',')) {
            const name = part.trim().split(/\s+as\s+/).pop()?.trim();
            if (name) names.add(name);
        }
    }
    for (const [, name] of source.matchAll(/export\s+(?:class|const|let|var|function\*?|async\s+function)\s+([A-Za-z_$][\w$]*)/g)) {
        names.add(name);
    }
    return names;
}

test('jeder benannte Import aus ../ui/ existiert im gebauten Modul', () => {
    const missing = [];
    for (const file of readdirSync(pagesDir).filter(f => f.endsWith('.js'))) {
        const source = readFileSync(join(pagesDir, file), 'utf8');
        for (const [, list, target] of source.matchAll(/import\s*\{([^}]*)\}\s*from\s*['"]\.\.\/ui\/([^'"]+)['"]/g)) {
            const built = readFileSync(join(uiDir, target), 'utf8');
            const exported = exportedNames(built);
            for (const part of list.split(',')) {
                const name = part.trim().split(/\s+as\s+/)[0]?.trim();
                if (name && !exported.has(name)) missing.push(`${file}: ${name} aus ui/${target}`);
            }
        }
    }
    assert.deepEqual(missing, []);
});

// Plugin-Skripte liegen unter /plugins/<id>/assets/, ein relativer Import
// auf ../ui/ landet dort im Leeren und bricht das ganze Modul ab. Sie
// greifen auf die globalen Namen des UI-Pakets zu (window.Dialog usw.).
test('Plugin-Skripte importieren keine UI-Module des Kerns relativ', () => {
    const pluginsDir = join(root, 'plugins');
    const offenders = [];
    const walk = (dir) => {
        for (const entry of readdirSync(dir, { withFileTypes: true })) {
            const path = join(dir, entry.name);
            if (entry.isDirectory()) {
                walk(path);
            } else if (entry.name.endsWith('.js') && !entry.name.endsWith('.min.js')) {
                if (/from\s*['"](\.\.\/)+ui\//.test(readFileSync(path, 'utf8'))) offenders.push(path.slice(root.length + 1));
            }
        }
    };
    for (const plugin of readdirSync(pluginsDir, { withFileTypes: true })) {
        const assets = join(pluginsDir, plugin.name, 'assets');
        if (plugin.isDirectory() && existsSync(assets)) walk(assets);
    }
    assert.deepEqual(offenders, []);
});
