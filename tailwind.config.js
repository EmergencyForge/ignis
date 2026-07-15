/**
 * intraRP Tailwind-Konfiguration.
 *
 * Design-Tokens spiegeln die Brand-Richtlinien aus CLAUDE.md:
 * Orange-Accent, Dark-Palette (#2b2930/#232128), Schriftfamilien
 * (Poppins/Rubik/Maven Pro/PT Sans/Inconsolata), Border-Radius-Skala
 * 4/6/8/10px, Shadow-System in drei Tiers.
 *
 * Content-Globs erfassen sowohl bestehende Bootstrap-Templates
 * (damit Tailwind-Klassen dort inkrementell verwendet werden können)
 * als auch künftige reine-Tailwind-Templates.
 */

/** @type {import('tailwindcss').Config} */
export default {
    // Tailwind nutzt die default-Extraction für alle Content-Files. Ein
    // explizites `extract: {}` würde — wie früher fehlerhaft hier gesetzt —
    // die Class-Erkennung in PHP-Templates abschalten und u.a. responsive
    // Variants (`md:`, `sm:`) komplett purgen.
    content: [
        'templates/**/*.php',
        // Plugin-Templates nutzen dieselben Utility-Klassen und müssen
        // mitgescannt werden, sonst purged Tailwind ihre Klassen.
        'plugins/*/templates/**/*.php',
        'assets/components/**/*.php',
        'public/*.php',
        // Root-Level PHP-Entry-Points (login.php, dashboard.php, index.php, ...)
        // enthalten auch Markup und müssen von Tailwind gescannt werden.
        '*.php',
    ],
    theme: {
        extend: {
            colors: {
                // Brand-Orange — Haupt-Akzentfarbe des Systems
                brand: {
                    DEFAULT: '#FF4D00',
                    light:   '#FF6A33',
                    dark:    '#CC3D00',
                },
                // Dark-Surface-Palette (purplish-dark grays)
                surface: {
                    DEFAULT: '#2b2930',
                    deep:    '#232128',
                    soft:    '#37343e',
                },
            },
            fontFamily: {
                sans:    ['Rubik', 'system-ui', 'sans-serif'],
                heading: ['Poppins', 'system-ui', 'sans-serif'],
                display: ['"Maven Pro"', 'system-ui', 'sans-serif'],
                ui:      ['"PT Sans"', 'system-ui', 'sans-serif'],
                mono:    ['Inconsolata', 'ui-monospace', 'monospace'],
            },
            fontSize: {
                // Dichte Admin-UI-Skala aus bestehenden SCSS-Files
                'xxs': ['0.72rem', { lineHeight: '1rem' }],
                'xs':  ['0.78rem', { lineHeight: '1.1rem' }],
                'sm':  ['0.82rem', { lineHeight: '1.2rem' }],
                'md':  ['0.88rem', { lineHeight: '1.3rem' }],
            },
            borderRadius: {
                'sm':  '4px',
                'md':  '6px',
                'lg':  '8px',
                'xl':  '10px',
            },
            boxShadow: {
                'soft':   '0 1px 2px rgba(0, 0, 0, 0.15), 0 1px 3px rgba(0, 0, 0, 0.1)',
                'medium': '0 4px 8px rgba(0, 0, 0, 0.2), 0 2px 4px rgba(0, 0, 0, 0.15)',
                'strong': '0 10px 20px rgba(0, 0, 0, 0.3), 0 3px 6px rgba(0, 0, 0, 0.2)',
            },
        },
    },
    // Preflight (Tailwinds Base-Reset) ist aus, damit Bootstrap-Komponenten
    // daneben weiterlaufen. Tailwind-Klassen sind nur Utilities — kein CSS-
    // Layer-Konflikt mit Bootstrap mehr. Kann re-aktiviert werden, sobald
    // Bootstrap komplett raus ist.
    corePlugins: {
        preflight: false,
    },
    // (Früher hier: blocklist: ['collapse']. Das hat einen Tailwind-Bug
    // getriggert, der alle Responsive-Variants — `md:`, `sm:`, `lg:`, `xl:` —
    // mit-purgen ließ. Stattdessen wird Tailwinds `.collapse {visibility:collapse}`
    // im eNOTF-Scope via divi.scss neutralisiert, wo Bootstraps Collapse-State
    // das letzte Wort braucht.)
    plugins: [
        // Strategy 'class' statt der Default-Base-Layer-Variante: das Plugin
        // schreibt seine Reset-Styles dann nur bei expliziten `.form-input`/
        // `.form-select`/... Klassen, nicht global an jedes <input>/<select>/
        // <textarea>. Sonst überschreibt es das Custom-Styling aus
        // assets/css/style.scss, divi.scss, ui.scss & co.
        require('@tailwindcss/forms')({ strategy: 'class' }),
        require('@tailwindcss/typography'),
    ],
};
