<?php

declare(strict_types=1);

/**
 * fireTab — Web-Routen.
 *
 * Wird im FiveM-In-Game-Browser (CitizenFX CEF) angezeigt, also brauchen
 * die interaktiven Pages die FiveMCspMiddleware (CSP / X-Frame-Options
 * je nach User-Agent). Die Session-Cookie-Konfiguration für iframe
 * (SameSite=None + Secure) ist im SessionManager gekapselt — der
 * erkennt `/einsatz/` in REQUEST_URI automatisch.
 *
 * Authentifizierung: Config-Flag FIRE_INCIDENT_REQUIRE_USER_AUTH entscheidet,
 * ob zusätzlich zum FireTab-Fahrzeug-Login ein System-User-Login nötig ist.
 * Der Controller selbst ruft intern `ensure('fireIncident.xxx')` für Policy-
 * Checks auf, deshalb hier nur AuthMiddleware als äußeres Gate.
 *
 * Admin-Liste läuft auf einer eigenen Route mit hartem AuthMiddleware —
 * das ist kein FireTab-Browser, sondern das Back-Office.
 *
 * API-Endpoints `lagekarte-api.php` und `status-api.php` werden mit 308
 * auf die kanonischen `/api/fire/...`-Routes umgeleitet, damit Legacy-JS
 * mit alten URLs weiter funktioniert.
 *
 * @var \App\Http\Router $router
 */

use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\FiveMCspMiddleware;
use Plugin\Firetab\Controllers\FiretabController;

$einsatzAuth      = [new AuthMiddleware('FIRE_INCIDENT_REQUIRE_USER_AUTH'), FiveMCspMiddleware::class];
$einsatzAdminAuth = [new AuthMiddleware()];

$router->get('/firetab/',          [FiretabController::class, 'index'], $einsatzAuth);
$router->get('/firetab/index',     [FiretabController::class, 'index'], $einsatzAuth);

$router->get('/firetab/list',     [FiretabController::class, 'list'], $einsatzAuth);

$router->get('/firetab/view',     [FiretabController::class, 'view'], $einsatzAuth);

$router->get('/firetab/create',      [FiretabController::class, 'createForm'], $einsatzAuth);
$router->post('/firetab/create',     [FiretabController::class, 'store'],     $einsatzAuth);

$router->get('/firetab/login-vehicle',      [FiretabController::class, 'loginForm'], $einsatzAuth);
$router->post('/firetab/login-vehicle',     [FiretabController::class, 'login'],     $einsatzAuth);

$router->post('/firetab/actions',     [FiretabController::class, 'dispatchAction'], $einsatzAuth);

$router->get('/firetab/asu',     [FiretabController::class, 'asuForm'], $einsatzAuth);

$router->get('/firetab/logbook',     [FiretabController::class, 'fireTabFahrtenbuch'], $einsatzAuth);

$router->get('/firetab/status-reports',     [FiretabController::class, 'statusmeldungen'], $einsatzAuth);

$router->get('/firetab/admin/list',     [FiretabController::class, 'adminList'], $einsatzAdminAuth);

// Legacy-API-URL-Kompatibilität: alte JS-POSTs auf die neuen Endpoints
// weiterreichen. 308 bewahrt Methode + Body.
$einsatzApiRedirect = function (string $target): \Closure {
    return function (\App\Http\Request $request) use ($target): \App\Http\Response {
        $qs = $request->server['QUERY_STRING'] ?? '';
        $base = defined('BASE_PATH') ? (string) BASE_PATH : '/';
        $url = rtrim($base, '/') . $target . ($qs !== '' ? '?' . $qs : '');
        return \App\Http\Response::redirect($url, 308);
    };
};
$router->match(['GET', 'POST'], '/firetab/lagekarte-api.php', $einsatzApiRedirect('/api/fire/lagekarte'));
$router->match(['GET', 'POST'], '/firetab/status-api.php',    $einsatzApiRedirect('/api/fire/status'));
