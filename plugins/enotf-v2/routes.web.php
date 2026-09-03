<?php

declare(strict_types=1);

/**
 * eNOTF v2 — Web-Routen.
 *
 * Dieselben Middleware-Gruppen wie v1:
 *   • Entry (Login/Loggedout) — AuthMiddleware('ENOTF_REQUIRE_USER_AUTH')
 *     + FiveMCsp, aber KEIN PIN-Lockscreen (sonst Redirect-Loop).
 *   • Crew — zusätzlich PinLockscreenMiddleware, hier mit dem v2-eigenen
 *     Lockscreen (/enotf-v2/lockscreen) als Redirect-Ziel.
 *     pin_verified/pin_last_activity/pin_return_url sind dieselben
 *     Session-Keys wie in v1 — EIN entsperrter PIN gilt für beide Welten,
 *     die pin_return_url bringt den User zurück auf die v2-Seite.
 *
 * Logout: DB-Write NUR auf POST. GET /enotf-v2/loggedout
 * ist eine reine Anzeige-/Bestätigungsseite.
 *
 * HINWEIS FiveM-iframe: SessionManager führt '/enotf-v2/' in seiner
 * iframe-Pfadliste — Session-Cookies auf allen v2-Pfaden (auch
 * /api/enotf-v2/…) kommen mit SameSite=None; Secure, unabhängig davon,
 * ob der CEF-Client den Sec-Fetch-Dest-Header mitschickt. GENAU deshalb
 * hängt auf allen Web-Gruppen die CsrfMiddleware: SameSite=None schaltet
 * den Browser-CSRF-Schutz für die Form-POSTs ab. Alle v2-Formulare
 * senden ein Session-CSRF-Token als Hidden-Field `_csrf` (qm.js als
 * Header X-Csrf-Token); ohne gültiges Token greift die Same-Origin-
 * Heuristik (fremder Origin/Referer-Host → 403, headerlose Requests —
 * CEF, ältere Clients — passieren). Die JSON-API braucht das nicht
 * (application/json erzwingt cross-site einen CORS-Preflight).
 *
 * @var \App\Http\Router $router
 */

use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\FiveMCspMiddleware;
use App\Http\Middleware\PinLockscreenMiddleware;
use Plugin\EnotfV2\Controllers\CreateController;
use Plugin\EnotfV2\Controllers\LockscreenController;
use Plugin\EnotfV2\Controllers\LoginController;
use Plugin\EnotfV2\Controllers\OverviewController;
use Plugin\EnotfV2\Controllers\ProtokollController;
use Plugin\EnotfV2\Http\CsrfMiddleware;

$enotfV2Entry = [CsrfMiddleware::class, new AuthMiddleware('ENOTF_REQUIRE_USER_AUTH'), FiveMCspMiddleware::class];
$enotfV2Crew  = [CsrfMiddleware::class, new AuthMiddleware('ENOTF_REQUIRE_USER_AUTH'), new PinLockscreenMiddleware('enotf-v2/lockscreen'), FiveMCspMiddleware::class];

// Einstieg: immer zur Overview (die leitet ohne Crew-Session zur Login-Seite)
$enotfV2Home = static function (): \App\Http\Response {
    $base = defined('BASE_PATH') ? (string) BASE_PATH : '/';
    return \App\Http\Response::redirect($base . 'enotf-v2/overview');
};
$router->get('/enotf-v2/',      $enotfV2Home, $enotfV2Crew);
$router->get('/enotf-v2/index', $enotfV2Home, $enotfV2Crew);

// Login-Flow — KEIN PIN-Lockscreen (wäre Loop)
$router->get('/enotf-v2/login',  [LoginController::class, 'form'],  $enotfV2Entry);
$router->post('/enotf-v2/login', [LoginController::class, 'login'], $enotfV2Entry);

// Lockscreen selbst darf NICHT durch PinLockscreenMiddleware — Redirect-Loop
$router->match(['GET', 'POST'], '/enotf-v2/lockscreen', [LockscreenController::class, 'lockscreen'], $enotfV2Entry);

// Logout: GET = Bestätigungs-/Abgemeldet-Seite, POST = DB-Write (mode=self|all)
$router->get('/enotf-v2/loggedout',  [LoginController::class, 'loggedOut'], $enotfV2Entry);
$router->post('/enotf-v2/loggedout', [LoginController::class, 'logout'],    $enotfV2Entry);

// Overview: GET Liste, POST delete_all
$router->get('/enotf-v2/overview',  [OverviewController::class, 'index'],     $enotfV2Crew);
$router->post('/enotf-v2/overview', [OverviewController::class, 'deleteAll'], $enotfV2Crew);

// Protokoll anlegen: GET Formular, POST Anlage (enrbridge-Semantik)
$router->get('/enotf-v2/create',  [CreateController::class, 'form'],  $enotfV2Crew);
$router->post('/enotf-v2/create', [CreateController::class, 'store'], $enotfV2Crew);

// Protokoll-Editor-Shell mit Section-Templates
$router->get('/enotf-v2/p/{enr:[\w._-]+}',                     [ProtokollController::class, 'show'], $enotfV2Crew);
$router->get('/enotf-v2/p/{enr:[\w._-]+}/{section:[\w-]+}',    [ProtokollController::class, 'show'], $enotfV2Crew);

// ----------------------------------------------------------------------------
//  QM-Fragmente
//
//  Wrapper um die v1-Admin-Fragmente (Plugin\Enotf\...\EnotfAdminController):
//  gleiche Templates, gleiche Panel-User-Gates (requireAuth + edivi.view im
//  Controller). Eigene v2-Routen sind nötig, weil die v1-Routen GET-only
//  registriert sind — der Speichern-Submit des Actions-Fragments POSTet
//  aber auf dieselbe URL (dort 405). Hier nimmt /qm/actions beide Methoden
//  an; der POST (DB-Write) wird zusätzlich auf edivi.edit gegated, das
//  GET-Rendering bleibt wie in v1 bei edivi.view.
// ----------------------------------------------------------------------------

// FiveMCsp auch hier: die Fragmente werden aus v2-Seiten geladen, die im
// FiveM-CEF laufen können — ohne die Middleware bekämen sie die normalen
// Security-Header und wären die einzigen v2-Routen mit abweichendem CSP.
$enotfV2QmAuth = [CsrfMiddleware::class, new AuthMiddleware(), FiveMCspMiddleware::class];

$router->match(['GET', 'POST'], '/enotf-v2/qm/actions/{id:\d+}', function (\App\Http\Request $request, string $id): \App\Http\Response {
    $_GET['id'] = $id; // das v1-Fragment liest die Protokoll-ID aus $_GET
    if (strtoupper($request->method) === 'POST' && !\App\Auth\Gate::allows('enotf.editProtocol')) {
        return \App\Http\Response::json(['success' => false, 'message' => 'Keine Berechtigung'], 403);
    }
    app(\Plugin\Enotf\Controllers\EnotfAdminController::class)->qmActionsModal();
    return \App\Http\Response::empty();
}, $enotfV2QmAuth);

$router->get('/enotf-v2/qm/log/{id:\d+}', function (\App\Http\Request $request, string $id): \App\Http\Response {
    $_GET['id'] = $id;
    app(\Plugin\Enotf\Controllers\EnotfAdminController::class)->qmLogModal();
    return \App\Http\Response::empty();
}, $enotfV2QmAuth);
