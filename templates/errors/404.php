<?php

/**
 * 404: Seite nicht gefunden. Der Router rendert sie über den Haken aus
 * App\Http\RouterFactory, wenn FastRoute nichts findet. Erwartet optional
 * `$path` von App\Http\ErrorPage.
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

// Die Adresse steht dekodiert auf der Seite, eine sehr lange gekürzt.
$errPath = isset($path) && $path !== '' ? rtrim($errBase, '/') . rawurldecode($path) : null;
if ($errPath !== null && mb_strlen($errPath) > 120) {
    $errPath = mb_substr($errPath, 0, 119) . '…';
}

$errPage      = 'error-404';
$errCode      = '404';
$errTitle     = 'Seite nicht gefunden (404)';
$errHeadline  = 'Seite nicht gefunden';
$errText      = 'Unter dieser Adresse gibt es keine Seite. Vielleicht ist der Link veraltet, oder die Adresse enthält einen Tippfehler.';
$errBackUrl   = $errBase;
$errBackLabel = 'Zur Startseite';
$errBackIcon  = 'fa-house';

require __DIR__ . '/_shell.php';
