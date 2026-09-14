<?php

/**
 * 403 — keine Berechtigung.
 *
 * Vorher endete eine verweigerte Berechtigung als Hinweis-Blase auf dem
 * Dashboard. Wer einem geteilten Link folgte, den er nicht öffnen darf,
 * landete also wortlos auf der Startseite und wusste nicht, was
 * abgelehnt wurde. Diese Seite sagt es — und der Knopf führt dorthin
 * zurück, wo der Aufruf herkam.
 *
 * Erwartet `$message` und optional `$backUrl`/`$backLabel` von
 * App\Http\ErrorPage.
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

$errPage      = 'error-403';
$errTitle     = '403 — Keine Berechtigung';
$errHeadline  = 'Dafür fehlt dir die Berechtigung.';
$errText      = $message ?? 'Für diesen Bereich ist dein Konto nicht freigeschaltet.';
$errBackUrl   = $backUrl ?? $errBase;
$errBackLabel = $backLabel ?? 'Zurück zum Dashboard';

require __DIR__ . '/_shell.php';
