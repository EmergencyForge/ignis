<?php

/**
 * 500: unerwarteter Fehler. App\Logging\ErrorHandler rendert diese Seite
 * für alle, die nicht im Entwicklungsmodus sind; dort bleibt die
 * ausführliche Seite mit Stacktrace (assets/components/error-page.php).
 *
 * Die Konfiguration wird hier nicht nachgeladen: der Fehler kann gerade
 * aus ihr kommen. Ohne BASE_PATH greift der Pfad-Rückfall der Hülle.
 *
 * Erwartet `$errorId`, den Fehlercode aus dem Log (kann leer sein).
 */

$errBase = defined('BASE_PATH') ? (string) BASE_PATH : '/';

$errPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
if (mb_strlen($errPath) > 120) {
    $errPath = mb_substr($errPath, 0, 119) . '…';
}
$errId = isset($errorId) && $errorId !== '' ? (string) $errorId : null;

$errPage      = 'error-500';
$errCode      = '500';
$errTitle     = 'Serverfehler (500)';
$errHeadline  = 'Ein unerwarteter Fehler ist aufgetreten';
$errText      = $errId !== null
    ? 'Der Fehler wurde protokolliert. Nenne der Verwaltung den Fehlercode, damit sie ihn findet.'
    : 'Der Fehler wurde protokolliert. Versuche es gleich noch einmal.';
$errBackUrl   = $errBase;
$errBackLabel = 'Zur Startseite';
$errBackIcon  = 'fa-house';

require __DIR__ . '/_shell.php';
