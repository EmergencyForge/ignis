<?php

/**
 * 404 — Seite nicht gefunden.
 *
 * Standalone-Template (kein navbar/sidebar), wird vom Router gerendert
 * wenn FastRoute NOT_FOUND zurueckgibt. Bewusst minimal: das Layout
 * funktioniert auch ohne aktive Session (z.B. wenn ein nicht eingeloggter
 * User eine geschuetzte URL direkt aufruft).
 */

if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__, 2) . '/assets/config/config.php';
}
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Seite nicht gefunden</title>
    <link rel="preload" href="<?= BASE_PATH ?>assets/fonts/geist/fonts/geist-v4-latin-regular.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/fonts/geist/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/style.min.css">
    <link rel="icon" type="image/svg+xml" href="<?= BASE_PATH ?>assets/favicon/favicon.svg">
    <link rel="shortcut icon" href="<?= BASE_PATH ?>assets/favicon/favicon.ico">
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            background: var(--body-bg, #0a0a0a);
            color: var(--text-normal, #bbbac1);
            font-family: 'Geist', system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
            overflow: hidden;
        }

        .err404 {
            height: 100vh;
            height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem 1.5rem;
            gap: 1.25rem;
            background: radial-gradient(circle at 50% 60%, rgba(var(--main-color-rgb, 255, 77, 0), 0.08), transparent 65%);
        }

        .err404__signal {
            position: relative;
            width: 130px;
            height: 130px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.5rem;
        }

        .err404__ring {
            position: absolute;
            inset: 0;
            border: 2px solid var(--main-color, #ff4d00);
            border-radius: 50%;
            opacity: 0;
            animation: err404-pulse 2.4s ease-out infinite;
        }

        .err404__ring:nth-child(2) { animation-delay: 0.8s; }
        .err404__ring:nth-child(3) { animation-delay: 1.6s; }

        @keyframes err404-pulse {
            0%   { transform: scale(0.4); opacity: 0.85; }
            100% { transform: scale(1.4); opacity: 0; }
        }

        .err404__core {
            position: relative;
            z-index: 2;
            width: 60px;
            height: 60px;
            background: rgba(var(--main-color-rgb, 255, 77, 0), 0.12);
            border: 1px solid var(--main-color, #ff4d00);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--main-color, #ff4d00);
        }

        .err404__core svg {
            width: 26px;
            height: 26px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .err404__btn svg {
            width: 14px;
            height: 14px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .err404__headline {
            font-size: clamp(1.6rem, 3.5vw, 2.2rem);
            font-weight: 700;
            color: var(--text-title, #ffffff);
            margin: 0;
            letter-spacing: -0.01em;
        }

        .err404__sub {
            font-size: 1rem;
            color: var(--text-dimmed, #818189);
            max-width: 540px;
            margin: 0;
            line-height: 1.55;
        }

        .err404__actions {
            display: flex;
            gap: 0.6rem;
            margin-top: 0.75rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .err404__btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.1rem;
            border: 1px solid var(--darkgray, #2a2a2a);
            border-radius: var(--button-border-radius, 6px);
            background: transparent;
            color: var(--text-normal, #bbbac1);
            font-size: 0.85rem;
            font-weight: 500;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.15s, border-color 0.15s, color 0.15s;
        }

        .err404__btn:hover {
            border-color: var(--main-color, #ff4d00);
            color: #fff;
            background: rgba(var(--main-color-rgb, 255, 77, 0), 0.08);
            text-decoration: none;
        }

        .err404__btn--primary {
            background: var(--main-color, #ff4d00);
            border-color: var(--main-color, #ff4d00);
            color: #fff;
        }

        .err404__btn--primary:hover {
            background: var(--main-color-light, #ff6a33);
            border-color: var(--main-color-light, #ff6a33);
            color: #fff;
        }
    </style>
</head>

<body data-bs-theme="dark" data-page="error-404">
    <main class="err404">
        <div class="err404__signal" aria-hidden="true">
            <span class="err404__ring"></span>
            <span class="err404__ring"></span>
            <span class="err404__ring"></span>
            <span class="err404__core">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M5 16c-1.5-2-1.5-6 0-8M19 16c1.5-2 1.5-6 0-8M8 14c-.8-1.2-.8-3.8 0-5M16 14c.8-1.2.8-3.8 0-5"/>
                    <circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none"/>
                </svg>
            </span>
        </div>
        <h1 class="err404__headline">Seite nicht gefunden.</h1>
        <p class="err404__sub">
            Diese Seite konnte nicht geladen werden — vielleicht wurde sie verschoben oder ist nicht mehr verfügbar.
        </p>
        <div class="err404__actions">
            <a href="<?= BASE_PATH ?>" class="err404__btn err404__btn--primary">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3 12l9-9 9 9"/>
                    <path d="M5 10v10a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V10"/>
                </svg>
                Zurück zum Dashboard
            </a>
            <a href="javascript:history.back();" class="err404__btn">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Vorherige Seite
            </a>
        </div>
    </main>
</body>

</html>
