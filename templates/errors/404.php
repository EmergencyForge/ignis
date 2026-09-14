<?php

/**
 * 404 — Seite nicht gefunden. Der Router rendert sie über den Haken aus
 * App\Http\RouterFactory, wenn FastRoute nichts findet.
 */

// Der Bootstrap wird versucht, aber nicht vorausgesetzt: eine Fehlerseite
// muss gerade dann tragen, wenn die Konfiguration nicht steht.
if (!defined('BASE_PATH') && is_file(dirname(__DIR__, 2) . '/assets/config/config.php')) {
    try {
        require_once dirname(__DIR__, 2) . '/assets/config/config.php';
    } catch (\Throwable) {
        // Ohne Konfiguration bleibt der Pfad-Rückfall unten.
    }
}

$errBase = defined('BASE_PATH') ? (string) BASE_PATH : '/';

$errPage      = 'error-404';
$errTitle     = '404 — Seite nicht gefunden';
$errHeadline  = 'Seite nicht gefunden.';
$errText      = 'Diese Seite konnte nicht geladen werden — vielleicht wurde sie verschoben oder ist nicht mehr verfügbar.';
$errBackUrl   = $errBase;
$errBackLabel = 'Zurück zum Dashboard';

require __DIR__ . '/_shell.php';
