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
        // Getrennte CSS-Bundles pro Entry (tailwind.css + vendor.css)
        // statt einem monolithischen style.css
        cssCodeSplit: true,
        rollupOptions: {
            input: {
                tailwind: resolve(__dirname, 'assets/js/tailwind.js'),
                vendor:   resolve(__dirname, 'assets/js/vendor.js'),
            },
            output: {
                // CSS-Bundles nach Entry-Key benennen (tailwind.css / vendor.css),
                // damit Templates mit festen Pfaden verlinken können.
                // Fonts/Icons bekommen Content-Hash für Caching.
                assetFileNames: (info) => {
                    const name = info.name ?? '';
                    // Vite liefert bei Multi-Entry-Builds nur „tailwind.css" bzw.
                    // „vendor.css" als Source-Name — wir reichen den Namen direkt
                    // durch und hängen nur bei Binär-Assets einen Hash an.
                    if (name.endsWith('.css')) {
                        return '[name][extname]';
                    }
                    return 'assets/[name]-[hash][extname]';
                },
                entryFileNames: '[name].js',
            },
        },
    },
});
