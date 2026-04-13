<?php

declare(strict_types=1);

/**
 * intraRP — Front-Controller (Single Entry Point, Phase 3.1)
 *
 * Verantwortlich für:
 *   1. Bootstrap laden (Container, Session, ErrorHandler, Config)
 *   2. Routen aus routes/web.php und routes/api.php registrieren
 *   3. Request aus Superglobals bauen, Router dispatchen lassen
 *   4. Response emittieren
 *
 * Die .htaccess des Projekt-Roots leitet alle nicht-existierenden Pfade
 * hierher weiter (Fallback nach !-f/!-d Check). Bestehende Modul-Stubs
 * unter `benutzer/`, `einsatz/`, `enotf/` etc. werden weiterhin direkt
 * von Apache gehandhabt — sie sind echte Files und MultiViews/RewriteRules
 * matchen sie, bevor die Fallback-Regel greift. So läuft der Router
 * PARALLEL zur Legacy-Welt, ohne sie zu stören.
 *
 * Migrierte Module registrieren ihre Routen in routes/web.php oder
 * routes/api.php und löschen danach ihre Stubs — dann greift der Router
 * für diese URLs.
 */

use App\Http\Request;
use App\Http\Router;

require_once __DIR__ . '/../assets/config/config.php';

$router = app(Router::class);

// Route-Dateien laden. Jede Datei bekommt $router als lokale Variable
// und registriert dort ihre Routen. Reihenfolge: web.php zuerst,
// api.php danach — API-Routen dürfen HTML-Routen shadowen, aber
// nicht umgekehrt (ist eh unmöglich wegen /api/-Prefix).
$routeFiles = [
    __DIR__ . '/../routes/web.php',
    __DIR__ . '/../routes/api.php',
];

foreach ($routeFiles as $file) {
    if (is_file($file)) {
        require $file;
    }
}

try {
    $request  = Request::fromGlobals();
    $response = $router->dispatch($request);
    $response->send();
} catch (\Throwable $e) {
    // Unerwartete Exceptions landen beim globalen ErrorHandler (Logger),
    // der bereits in config.php via ErrorHandler::register() aktiv ist.
    // Hier reichen wir die Exception weiter, damit sie dort sauber
    // erfasst und mit Error-ID versehen wird.
    throw $e;
}
