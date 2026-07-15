import { defineConfig } from 'vite';
import { resolve } from 'node:path';

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
 * Entry in einem eigenen Durchlauf: `vite build --mode <entry>`.
 */

const entries = {
    tailwind:       'assets/js/tailwind.js',
    vendor:         'assets/js/vendor.js',
    'vendor-enotf': 'assets/js/vendor-enotf.js',
};

export default defineConfig(({ mode }) => {
    const singleEntry = Object.hasOwn(entries, mode) ? mode : null;

    return {
        // Vite's publicDir feature ist für SPA-Apps gedacht — bei uns liefert
        // der PHP-Router public/index.php selbst aus, Vite soll da nichts reinkopieren.
        publicDir: false,
        // Relative Asset-URLs im CSS (z.B. `url(assets/fa-solid-900.woff2)`),
        // damit der Browser die Fonts immer relativ zur CSS-Datei sucht — unabhängig
        // davon, ob die App unter `/`, einer Subdomain oder einem Subdirectory läuft.
        base: './',
        build: {
            outDir: resolve(__dirname, 'public/assets/dist'),
            emptyOutDir: false,
            manifest: false,
            // Getrennte CSS-Bundles pro Entry (tailwind.css + vendor.css)
            // statt einem monolithischen style.css. Im Single-Entry-Pass
            // muss das Splitting AUS sein, sonst inlined Vite das CSS als
            // <style>-Injection ins JS und die verlinkten .css-Dateien
            // veralten still.
            cssCodeSplit: !singleEntry,
            rollupOptions: {
                input: singleEntry
                    ? { [singleEntry]: resolve(__dirname, entries[singleEntry]) }
                    : Object.fromEntries(
                        Object.entries(entries).map(([k, v]) => [k, resolve(__dirname, v)])
                    ),
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
