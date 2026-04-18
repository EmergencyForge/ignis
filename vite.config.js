import { defineConfig } from 'vite';
import { resolve } from 'node:path';

/**
 * Vite-Build-Konfiguration für intraRP.
 *
 * Output landet committed in public/assets/dist/ — der PHP-Stack
 * verweist darauf, kein Node/npm auf dem Webspace nötig.
 *
 * Entry-Points sind aktuell nur die Tailwind-Ausgabe. SCSS-Builds
 * für bestehende Admin/Style-Sheets laufen weiter über den etablierten
 * (manuellen) Sass-Workflow — die werden später in eigenen Commits
 * hier mit eingezogen.
 */
export default defineConfig({
    // Vite's publicDir feature ist für SPA-Apps gedacht — bei uns liefert
    // der PHP-Router public/index.php selbst aus, Vite soll da nichts reinkopieren.
    publicDir: false,
    build: {
        outDir: resolve(__dirname, 'public/assets/dist'),
        emptyOutDir: false,
        manifest: false,
        cssCodeSplit: false,
        rollupOptions: {
            input: {
                tailwind: resolve(__dirname, 'assets/js/tailwind.js'),
            },
            output: {
                // CSS-Assets mit festem Namen (für stabile link-Tags),
                // sonstige Assets (Fonts, Icons) mit Content-Hash für Caching.
                // Vite nennt den CSS-Output bei JS-Entries nach dem Entry-Key
                // in rollupOptions.input — deshalb heisst das Bundle hier
                // `tailwind.css`, passend zur tailwind.js-Entry-Konvention.
                assetFileNames: (info) => {
                    const name = info.name ?? '';
                    if (name.endsWith('.css')) {
                        return 'tailwind.css';
                    }
                    return 'assets/[name]-[hash][extname]';
                },
                entryFileNames: '[name].js',
            },
        },
    },
});
