/**
 * intraRP Tailwind-Konfiguration.
 *
 * Design-Tokens spiegeln die Brand-Richtlinien aus CLAUDE.md:
 * Orange-Accent, Dark-Palette (#2b2930/#232128), Schriftfamilien
 * (Poppins/Rubik/Maven Pro/PT Sans/Inconsolata). Radien und Schatten
 * kommen aus den Tokens des gemeinsamen Looks.
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
                // Akzentfarbe des Systems: kommt aus assets/css/_tokens.scss
                // bzw. SYSTEM_COLOR (head.php), deshalb hier nur die Variable.
                brand: {
                    DEFAULT: 'var(--accent)',
                    light:   'var(--accent)',
                    dark:    'var(--accent-hover)',
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
            // Radien und Schatten des gemeinsamen Looks (_tokens.scss bzw.
            // Skin-Block), damit Utilities denselben Ecken folgen.
            borderRadius: {
                DEFAULT: 'var(--radius-1)',
                'sm':  'var(--radius-1)',
                'md':  'var(--radius-2)',
                'lg':  'var(--radius-3)',
                'xl':  'var(--radius-3)',
            },
            // Ohne DEFAULT und sm: mit diesen Schlüsseln erzeugt Tailwind 4 über
            // @config shadow, shadow-sm und shadow-medium gar nicht mehr.
            boxShadow: {
                'soft':   'var(--shadow-card)',
                'medium': 'var(--shadow-card)',
                'strong': 'var(--shadow-pop)',
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
