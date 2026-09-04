import { defineConfig } from 'vite';
import { pathToFileURL } from 'node:url';
import { resolve, extname, basename, sep } from 'node:path';
import { cpSync, existsSync, statSync } from 'node:fs';
import tailwindcss from '@tailwindcss/vite';

/**
 * Vite-Build-Konfiguration für ignis.
 *
 * Output landet committed in public/assets/dist/ — der PHP-Stack
 * verweist darauf, kein Node/npm auf dem Webspace nötig.
 *
 * Die Templates laden die Bundles mit klassischen <script>-Tags
 * (ohne type="module"), weil Legacy-Inline-Scripts synchron auf
 * jQuery angewiesen sind. Deshalb MUSS jedes Bundle ein
 * self-contained IIFE sein: Seit Vite 8 teilt ein Multi-Entry-Build
 * gemeinsamen Code in ESM-Chunks auf (import-Statements im Bundle),
 * was in klassischen Script-Tags mit "Cannot use import statement
 * outside a module" stirbt. Darum baut `npm run vite:build` jede
 * JS-Entry in einem eigenen Durchlauf: `vite build --mode <entry>`.
 *
 * Die SCSS-Stylesheets laufen als eigener Pass (`--mode styles`) und
 * ersetzen den früheren manuellen Sass-Workflow: ein Build-Befehl,
 * ein Output-Verzeichnis, der CI-Asset-Check deckt alles ab.
 *
 * Die UI-Module (Drawer, Dialog, Toasts, Arbeitsbereich …) laufen als
 * dritter Pass (`--mode ui`) nach public/assets/js/ui/, siehe unten.
 */

const root = import.meta.dirname;

const scriptEntries = {
    tailwind:       'assets/js/tailwind.js',
    vendor:         'assets/js/vendor.js',
    'vendor-enotf': 'assets/js/vendor-enotf.js',
    'vendor-chart': 'assets/js/vendor-chart.js',
};

// Jede .scss-Datei wird zu public/assets/dist/<name>.css. Die
// eNOTF-Standalone-CSS (enotf-toast.css usw.) haben keine SCSS-Quelle
// und bleiben unangetastet unter assets/css/ liegen.
const styleEntries = {
    admin:             'assets/css/admin.scss',
    style:             'assets/css/style.scss',
    divi:              'assets/css/divi.scss',
    personal:          'assets/css/personal.scss',
    print:             'assets/css/print.scss',
    'template-editor': 'assets/css/template-editor.scss',
    ui:                'assets/css/ui.scss',
    'legacy-utilities': 'assets/css/legacy-utilities.scss',
};

// Die gemeinsamen Pakete der EmergencyForge-Anwendungen liegen als
// Checkout neben diesem Repo (F:\Github Projects\WebPackages); EF_PACKAGES_DIR
// zeigt auf einen anderen Ort. Kein npm-Paket, das Plugin ist reines
// Node-ESM und die Module kommen als Quelle mit dem Platzhalter `__pfx__`.
const packagesDir = process.env.EF_PACKAGES_DIR ?? resolve(root, '../WebPackages');
const uiSrc = resolve(packagesDir, 'packages/ui/js/src');
const { emergencyForgeUi } = await import(pathToFileURL(resolve(packagesDir, 'packages/ui/js/vite-plugin.js')).href);

// UI-Pass (`--mode ui`): Warum kein Bundle wie lex.js in Lex? Die
// eNOTF-Seiten (assets/components/enotf/_head.php, plugins/enotf-v2)
// binden die Module einzeln als <script type="module"> unter
// assets/js/ui/<name>.js ein und sind eingefroren. Ein Bundle für die
// Hülle plus Einzeldateien für eNOTF hieße denselben Code zweimal
// ausliefern und, sobald eine Seite beides lädt, jedes Modul zweimal
// ausführen (doppelte Click-Handler). Also bleibt es bei einer Datei
// je Modul: jedes Paket-Modul ist eine eigene Entry, Ausgabe als ES-Modul
// mit festem Namen nach public/assets/js/ui/, die Templates ändern sich
// nicht. Rollup löst `import './drawer.js'` in drawer-form.js auf die
// Entry drawer.js auf, es entstehen keine Zwischen-Chunks.
//
// dialog und dropdown laufen über die Kompatibilitätsdateien unter
// assets/js/ui/, die das Paket-Modul importieren und die alten Aliase
// (intraConfirm, eNOTFCustomDropdown) daneben setzen; shell.js und
// palette.js sind Produktdateien (Routen, Sidebar, Suche) und bleiben
// hier. tests/Unit/Assets/UiPackageTest.php hält den Ordner sauber.
const uiPackageModules = [
    'accordion', 'alert', 'chip', 'colorpicker', 'combobox', 'datepicker',
    'datetimepicker', 'dialog', 'drawer', 'drawer-form', 'dropdown', 'file',
    'form', 'multi-select', 'snackbar', 'tabs', 'tooltip', 'workbench',
];
const uiProductFiles = {
    dialog:   'assets/js/ui/dialog-compat.js',
    dropdown: 'assets/js/ui/dropdown-compat.js',
    shell:    'assets/js/ui/shell.js',
    palette:  'assets/js/ui/palette.js',
};
const uiEntries = {
    ...Object.fromEntries(uiPackageModules.map((name) => [name, resolve(uiSrc, `${name}.js`)])),
    ...Object.fromEntries(Object.entries(uiProductFiles).map(([name, file]) => [name, resolve(root, file)])),
};

// Rollup erzeugt zu jeder Entry einen JS-Chunk — bei reinen
// SCSS-Entries ist das ein leerer Stub, der nicht ins dist gehört.
function dropStyleStubs() {
    return {
        name: 'ignis-drop-style-stubs',
        generateBundle(_options, bundle) {
            for (const [fileName, chunk] of Object.entries(bundle)) {
                if (chunk.type === 'chunk' && fileName.endsWith('.js') && Object.hasOwn(styleEntries, chunk.name)) {
                    delete bundle[fileName];
                }
            }
        },
    };
}

// Alles unter assets/, was der Browser per URL lädt, ins Docroot spiegeln.
// Der Webserver liefert nur noch aus public/ aus; Bilder, Webfonts, die
// unbundled ES-Module unter assets/js/modules, die Standalone-CSS und die
// Fremdbibliotheken unter assets/_ext lagen aber im Repo-Root. publicDir
// ist bewusst aus (siehe unten), also kopiert dieser Schritt. Das
// Ergebnis wird wie die dist-Bundles committet, weil auf dem Webspace
// kein Node läuft.
//
// Nicht kopiert werden die Quellen, die Vite selbst verarbeitet: SCSS,
// die Tailwind-Quelle, die vier Bundle-Entries und assets/js/ui (der
// UI-Pass baut den Ordner public/assets/js/ui komplett).
const viteSources = new Set([
    ...Object.values(scriptEntries).map((p) => basename(p)),
    'tailwind.css',
]);
const uiSourceDir = resolve(root, 'assets/js/ui');

function publishStaticAssets() {
    const copies = [
        ['assets/img',     'public/assets/img'],
        ['assets/fonts',   'public/assets/fonts'],
        ['assets/favicon', 'public/assets/favicon'],
        ['assets/_ext',    'public/assets/_ext'],
        ['assets/json',    'public/assets/json'],
        ['assets/css',     'public/assets/css'],
        ['assets/js',      'public/assets/js'],
    ];
    const filter = (src) => {
        if (src === uiSourceDir || src.startsWith(uiSourceDir + sep)) return false;
        if (statSync(src).isDirectory()) return true;
        if (extname(src) === '.scss') return false;
        return !viteSources.has(basename(src));
    };

    return {
        name: 'ignis-publish-static-assets',
        apply: 'build',
        closeBundle() {
            for (const [from, to] of copies) {
                const src = resolve(root, from);
                if (!existsSync(src)) continue;
                cpSync(src, resolve(root, to), { recursive: true, filter });
            }
        },
    };
}

export default defineConfig(({ mode }) => {
    if (mode === 'ui') {
        return {
            publicDir: false,
            base: './',
            plugins: [emergencyForgeUi({ prefix: 'ignis', dir: uiSrc })],
            build: {
                outDir: resolve(root, 'public/assets/js/ui'),
                // Der Ordner gehört ganz diesem Pass; was nicht mehr gebaut
                // wird, verschwindet auch aus public/.
                emptyOutDir: true,
                manifest: false,
                // Kein modulepreload-Polyfill in jeder Entry — die Seiten
                // binden die Module direkt als <script type="module"> ein.
                modulePreload: false,
                cssCodeSplit: false,
                rollupOptions: {
                    input: uiEntries,
                    output: {
                        format: 'es',
                        entryFileNames: '[name].js',
                        chunkFileNames: '_[name].js',
                    },
                },
            },
        };
    }

    const singleEntry = Object.hasOwn(scriptEntries, mode) ? mode : null;
    const stylesPass  = mode === 'styles';

    const input = singleEntry
        ? { [singleEntry]: resolve(root, scriptEntries[singleEntry]) }
        : Object.fromEntries(
            Object.entries(stylesPass ? styleEntries : scriptEntries)
                .map(([k, v]) => [k, resolve(root, v)])
        );

    return {
        // Vite's publicDir feature ist für SPA-Apps gedacht — bei uns liefert
        // der PHP-Router public/index.php selbst aus, Vite soll da nichts reinkopieren.
        publicDir: false,
        // Relative Asset-URLs im CSS (z.B. `url(assets/fa-solid-900.woff2)`),
        // damit der Browser die Fonts immer relativ zur CSS-Datei sucht — unabhängig
        // davon, ob die App unter `/`, einer Subdomain oder einem Subdirectory läuft.
        base: './',
        // Der styles-Pass läuft als letzter (siehe package.json) und
        // spiegelt danach die statischen Dateien nach public/assets.
        plugins: [tailwindcss(), ...(stylesPass ? [dropStyleStubs(), publishStaticAssets()] : [])],
        build: {
            outDir: resolve(root, 'public/assets/dist'),
            emptyOutDir: false,
            manifest: false,
            // Getrennte CSS-Bundles pro Entry (tailwind.css + vendor.css)
            // statt einem monolithischen style.css. Im Single-Entry-Pass
            // muss das Splitting AUS sein, sonst inlined Vite das CSS als
            // <style>-Injection ins JS und die verlinkten .css-Dateien
            // veralten still.
            cssCodeSplit: !singleEntry,
            rollupOptions: {
                input,
                output: {
                    // Klassisches, self-contained Script — kein Code-Splitting,
                    // keine import-Statements (siehe Kopf-Kommentar). Vite 8
                    // deaktiviert Code-Splitting bei iife-Format automatisch.
                    ...(singleEntry ? { format: 'iife' } : {}),
                    // CSS-Bundles nach Entry-Key benennen (tailwind.css / vendor.css),
                    // damit Templates mit festen Pfaden verlinken können.
                    // Fonts/Icons bekommen Content-Hash für Caching.
                    assetFileNames: (info) => {
                        const name = info.name ?? '';
                        if (name.endsWith('.css')) {
                            // Im Single-Entry-Pass heißt das CSS-Asset generisch
                            // („style.css") — auf den Entry-Namen zurückmappen.
                            return singleEntry ? `${singleEntry}[extname]` : '[name][extname]';
                        }
                        return 'assets/[name]-[hash][extname]';
                    },
                    entryFileNames: '[name].js',
                },
            },
        },
    };
});
