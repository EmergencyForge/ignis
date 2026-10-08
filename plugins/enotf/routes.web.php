<?php

declare(strict_types=1);

/**
 * eNOTF: Web-Routen.
 *
 * Läuft im FiveM-CEF-Browser (iframe) → FiveMCspMiddleware an allen
 * Crew-Routen. User-Auth ist optional und wird über das Config-Flag
 * ENOTF_REQUIRE_USER_AUTH gesteuert.
 *
 * Middleware-Gruppen:
 *   • Public:  keine Auth (nur CSP/iframe-Support)
 *   • Entry:   optionale User-Auth, KEIN PIN-Lockscreen (sonst
 *              Redirect-Loop auf den Lockscreen selbst)
 *   • Crew:    User-Auth + PIN-Lockscreen + CSP (volle Pipeline)
 *
 * Die Crew-Seiten des Protokolls hängen zusätzlich an der CsrfMiddleware
 * des Plugins: der SessionManager setzt auf allen /enotf/-Pfaden
 * SameSite=None, damit fällt der CSRF-Schutz des Browsers für
 * Form-POSTs weg. Alle Formulare senden das Token als `_csrf`.
 *
 * @var \EmergencyForge\Http\Router $router
 */

use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\FiveMCspMiddleware;
use App\Http\Middleware\PinLockscreenMiddleware;
use Plugin\Enotf\Controllers\EnotfAdminController;
use Plugin\Enotf\Controllers\EnotfController;
use Plugin\Enotf\Controllers\EnotfPrintController;
use Plugin\Enotf\Controllers\EnotfSchnittstelleController;
use Plugin\Enotf\Controllers\Settings\EnotfController as SettingsEnotfController;
use Plugin\Enotf\Controllers\Settings\MedikamenteController;
use Plugin\Enotf\Controllers\Settings\PoiController;
use Plugin\Enotf\Crew\Controllers\CreateController;
use Plugin\Enotf\Crew\Controllers\LockscreenController;
use Plugin\Enotf\Crew\Controllers\LoginController;
use Plugin\Enotf\Crew\Controllers\OverviewController;
use Plugin\Enotf\Crew\Controllers\ProtokollController;
use Plugin\Enotf\Crew\Http\CsrfMiddleware;

$enotfPublic     = [FiveMCspMiddleware::class];
$enotfEntry      = [new AuthMiddleware('ENOTF_REQUIRE_USER_AUTH'), FiveMCspMiddleware::class];
$enotfCrew       = [new AuthMiddleware('ENOTF_REQUIRE_USER_AUTH'), PinLockscreenMiddleware::class, FiveMCspMiddleware::class];
$crewEntry       = [CsrfMiddleware::class, ...$enotfEntry];
$crewPages       = [CsrfMiddleware::class, ...$enotfCrew];

// Einstieg: immer zur Overview (die leitet ohne Crew-Session zur Login-Seite)
$enotfHome = static function (): \EmergencyForge\Http\Response {
    $base = defined('BASE_PATH') ? (string) BASE_PATH : '/';
    return \EmergencyForge\Http\Response::redirect($base . 'enotf/overview');
};
$router->get('/enotf/',      $enotfHome, $crewPages);
$router->get('/enotf/index', $enotfHome, $crewPages);

$router->get('/enotf/login',  [LoginController::class, 'form'],  $crewEntry);
$router->post('/enotf/login', [LoginController::class, 'login'], $crewEntry);

$router->match(['GET', 'POST'], '/enotf/lockscreen', [LockscreenController::class, 'lockscreen'], $crewEntry);

// GET zeigt die Abmelde-Seite, nur POST schreibt (mode=self|all)
$router->get('/enotf/loggedout',  [LoginController::class, 'loggedOut'], $crewEntry);
$router->post('/enotf/loggedout', [LoginController::class, 'logout'],    $crewEntry);

$router->get('/enotf/overview',  [OverviewController::class, 'index'],     $crewPages);
$router->post('/enotf/overview', [OverviewController::class, 'deleteAll'], $crewPages);

$router->get('/enotf/create',  [CreateController::class, 'form'],  $crewPages);
$router->post('/enotf/create', [CreateController::class, 'store'], $crewPages);

$router->get('/enotf/p/{enr:[\w._-]+}',                  [ProtokollController::class, 'show'], $crewPages);
$router->get('/enotf/p/{enr:[\w._-]+}/{section:[\w-]+}', [ProtokollController::class, 'show'], $crewPages);

// Alte Protokoll-Adressen aus Benachrichtigungen, Discord und Lesezeichen:
// /enotf/protokoll/{section}/{seite}?enr=X und /enotf/p/{enr}/{section}/{seite}.
// Die Unterseiten gibt es nicht mehr, sie landen auf ihrer Section.
$protokollRedirect = static function (?string $enr, string $path): \EmergencyForge\Http\Response {
    if ($enr === null || $enr === '') {
        return \EmergencyForge\Http\Response::redirect(\Plugin\Enotf\Helpers\EnotfUrl::page('overview'), 301);
    }
    $segments = array_values(array_filter(explode('/', (string) preg_replace('/\.php$/', '', trim($path, '/')))));
    $section  = $segments[0] ?? '';
    $url      = \Plugin\Enotf\Helpers\EnotfUrl::protokoll($enr, isset(ProtokollController::SECTIONS[$section]) ? $section : '');
    if ($section === 'abschluss' && ($segments[1] ?? '') === 'freigabe') {
        $url .= '?t=freigabe';
    }
    return \EmergencyForge\Http\Response::redirect($url, 301);
};
$router->get('/enotf/protokoll', fn (\EmergencyForge\Http\Request $r) => $protokollRedirect($r->query['enr'] ?? null, ''));
$router->get('/enotf/protokoll/{path:[\w./_-]*}', fn (\EmergencyForge\Http\Request $r, string $path = '') => $protokollRedirect($r->query['enr'] ?? null, $path));
$router->get('/enotf/p/{enr:[\w._-]+}/{section:[\w-]+}/{rest:[\w/_-]+}', fn (\EmergencyForge\Http\Request $r, string $enr, string $section, string $rest) => $protokollRedirect($enr, $section . '/' . $rest));

// QM-Fragmente für den QM-Dialog auf der Protokollseite (qm.js). Dieselben
// Fragmente wie in der Prüfliste, der Controller prüft edivi.view.
$crewQm = [CsrfMiddleware::class, new AuthMiddleware(), FiveMCspMiddleware::class];

$router->match(['GET', 'POST'], '/enotf/qm/actions/{id:\d+}', function (\EmergencyForge\Http\Request $request, string $id): \EmergencyForge\Http\Response {
    $_GET['id'] = $id; // das Fragment liest die Protokoll-ID aus $_GET
    if (strtoupper($request->method) === 'POST' && !\App\Auth\Gate::allows('enotf.editProtocol')) {
        return \EmergencyForge\Http\Response::json(['success' => false, 'message' => 'Keine Berechtigung'], 403);
    }
    app(EnotfAdminController::class)->qmActionsModal();
    return \EmergencyForge\Http\Response::empty();
}, $crewQm);

$router->get('/enotf/qm/log/{id:\d+}', function (\EmergencyForge\Http\Request $request, string $id): \EmergencyForge\Http\Response {
    $_GET['id'] = $id;
    app(EnotfAdminController::class)->qmLogModal();
    return \EmergencyForge\Http\Response::empty();
}, $crewQm);

$router->get('/enotf/fahrzeuginfo',     [EnotfController::class, 'fahrzeuginfo'], $enotfCrew);

$router->get('/enotf/fahrtenbuch',     [EnotfController::class, 'fahrtenbuch'], $enotfCrew);

// hospital-availability ist public (kein Login, kein PIN)
$router->get('/enotf/hospital-availability',     [EnotfController::class, 'hospitalAvailability'], $enotfPublic);

// ----------------------------------------------------------------------------
//  Admin
//
//  EnotfAdminController prüft intern requireAuth() +
//  Permissions::check(['admin','edivi.view'|'edivi.edit']) → Routes
//  brauchen nur AuthMiddleware. Back-Office-UI, nicht im FiveM-CEF →
//  keine FiveMCspMiddleware nötig.
// ----------------------------------------------------------------------------

$enotfAdminAuth = [new AuthMiddleware()];

$router->get('/enotf/admin',          [EnotfAdminController::class, 'listAction'], $enotfAdminAuth);
$router->get('/enotf/admin/',         [EnotfAdminController::class, 'listAction'], $enotfAdminAuth);
$router->get('/enotf/admin/list',     [EnotfAdminController::class, 'listAction'], $enotfAdminAuth);

// Nur POST: CsrfMiddleware prüft keine GETs (siehe templates/enotf/admin/list.php).
$router->post('/enotf/admin/delete',     [EnotfAdminController::class, 'destroy'], $enotfAdminAuth);

// GET liefert das Formular, POST speichert es (das Formular schickt an dieselbe Adresse).
$router->match(['GET', 'POST'], '/enotf/admin/qm-actions-modal', [EnotfAdminController::class, 'qmActionsModal'], $enotfAdminAuth);

$router->get('/enotf/admin/qm-log-modal',     [EnotfAdminController::class, 'qmLogModal'], $enotfAdminAuth);

// bulk-delete-empty: 308-Redirect auf /api/enotf/bulk-delete-empty (Ziel-Route
// kommt aus routes.api.php dieses Plugins)
$enotfApiRedirect = function (string $target): \Closure {
    return function (\EmergencyForge\Http\Request $request) use ($target): \EmergencyForge\Http\Response {
        $qs   = $request->server['QUERY_STRING'] ?? '';
        $base = defined('BASE_PATH') ? (string) BASE_PATH : '/';
        $url  = rtrim($base, '/') . $target . ($qs !== '' ? '?' . $qs : '');
        return \EmergencyForge\Http\Response::redirect($url, 308);
    };
};
$router->match(['GET', 'POST'], '/enotf/admin/bulk-delete-empty', $enotfApiRedirect('/api/enotf/bulk-delete-empty'));

// Zielverwaltung: auf POI-System konsolidiert. Legacy-URLs leiten
// dauerhaft auf `/settings/pois/index` um, bis externe Bookmarks aktualisiert
// sind. Controller + Template gibt's noch im Repo, sind aber nicht mehr
// erreichbar.
$zielverwaltungRedirect = static function (\EmergencyForge\Http\Request $request) {
    $base = defined('BASE_PATH') ? (string) BASE_PATH : '/';
    return \EmergencyForge\Http\Response::redirect($base . 'settings/pois/index', 301);
};
$router->match(['GET', 'POST'], '/enotf/admin/zielverwaltung',           $zielverwaltungRedirect);
$router->match(['GET', 'POST'], '/enotf/admin/zielverwaltung/',          $zielverwaltungRedirect);
$router->match(['GET', 'POST'], '/enotf/admin/zielverwaltung/create',    $zielverwaltungRedirect);
$router->match(['GET', 'POST'], '/enotf/admin/zielverwaltung/update',    $zielverwaltungRedirect);
$router->match(['GET', 'POST'], '/enotf/admin/zielverwaltung/delete',    $zielverwaltungRedirect);

// ----------------------------------------------------------------------------
//  Print + Schnittstelle
//
//  Print:         Crew-facing, voller Middleware-Stack inkl. PIN-Lockscreen.
//                 EnotfPrintController::show() macht zusätzlich einen eigenen
//                 PIN-Check: Belt-and-Suspenders, beide Policies sind
//                 deckungsgleich.
//  Schnittstelle: Public (Klinik-Access ohne User-Login möglich) → nur
//                 FiveMCspMiddleware. `voranmeldung` prüft PIN je nach
//                 Config selbst.
// ----------------------------------------------------------------------------

// Print: ENR kommt entweder als Query (/enotf/print/index.php?enr=…)
// oder als Clean-URL-Segment (/enotf/print/{enr}).
$router->get('/enotf/print',            [EnotfPrintController::class, 'show'], $enotfCrew);
$router->get('/enotf/print/',           [EnotfPrintController::class, 'show'], $enotfCrew);
$router->get('/enotf/print/index',      [EnotfPrintController::class, 'show'], $enotfCrew);
// Clean-URL: Parameter über $_GET reichen, damit show() weiterhin ?enr= liest.
$router->get('/enotf/print/{enr:[\w._-]+}', function (\EmergencyForge\Http\Request $request, string $enr) {
    // Legacy-Aufruf /enotf/print/index.php?enr=… landet hier mit
    // enr="index.php", dann gilt der Query-Parameter, nicht das Segment.
    if ($enr !== 'index.php') {
        $_GET['enr'] = $enr;
    }
    app(EnotfPrintController::class)->show();
    return \EmergencyForge\Http\Response::empty();
}, $enotfCrew);

// Schnittstelle: public
$router->get('/enotf/schnittstelle',           [EnotfSchnittstelleController::class, 'index'], $enotfPublic);
$router->get('/enotf/schnittstelle/',          [EnotfSchnittstelleController::class, 'index'], $enotfPublic);
$router->get('/enotf/schnittstelle/index',     [EnotfSchnittstelleController::class, 'index'], $enotfPublic);

$router->match(['GET', 'POST'], '/enotf/schnittstelle/klinikcode',     [EnotfSchnittstelleController::class, 'klinikcode'], $enotfPublic);

$router->match(['GET', 'POST'], '/enotf/schnittstelle/voranmeldung',     [EnotfSchnittstelleController::class, 'voranmeldung'], $enotfPublic);

$router->get('/enotf/schnittstelle/hospital-availability',     [EnotfSchnittstelleController::class, 'hospitalAvailability'], $enotfPublic);

// api-prereg: 308 auf /api/enotf/prereg
$router->match(['GET', 'POST'], '/enotf/schnittstelle/api-prereg', $enotfApiRedirect('/api/enotf/prereg'));

// ----------------------------------------------------------------------------
//  Settings: Schnellzugriff/Kategorien, Medikamente, POIs
// ----------------------------------------------------------------------------

$enotfSettingsAuth = [new AuthMiddleware()];

// eNOTF-Settings (Schnellzugriff + Kategorien)
$router->get('/settings/enotf/index',     [SettingsEnotfController::class, 'index'],   $enotfSettingsAuth);
$router->post('/settings/enotf/create',     [SettingsEnotfController::class, 'store'],   $enotfSettingsAuth);
$router->post('/settings/enotf/update',     [SettingsEnotfController::class, 'update'],  $enotfSettingsAuth);
$router->post('/settings/enotf/delete',     [SettingsEnotfController::class, 'destroy'], $enotfSettingsAuth);
$router->get('/settings/enotf/kategorien/index',     [SettingsEnotfController::class, 'categoriesIndex'],  $enotfSettingsAuth);
$router->post('/settings/enotf/kategorien/create',     [SettingsEnotfController::class, 'categoryStore'],   $enotfSettingsAuth);
$router->post('/settings/enotf/kategorien/update',     [SettingsEnotfController::class, 'categoryUpdate'],  $enotfSettingsAuth);
$router->post('/settings/enotf/kategorien/delete',     [SettingsEnotfController::class, 'categoryDestroy'], $enotfSettingsAuth);

// Medikamente-Settings
$router->get('/settings/medications/index',     [MedikamenteController::class, 'index'],   $enotfSettingsAuth);
$router->post('/settings/medications/create',     [MedikamenteController::class, 'store'],   $enotfSettingsAuth);
$router->post('/settings/medications/update',     [MedikamenteController::class, 'update'],  $enotfSettingsAuth);
$router->post('/settings/medications/delete',     [MedikamenteController::class, 'destroy'], $enotfSettingsAuth);

// POI-Settings (Zielverwaltung, Fachabteilungen, Zugangscodes)
$router->get('/settings/pois/index',     [PoiController::class, 'index'],   $enotfSettingsAuth);
$router->post('/settings/pois/create',     [PoiController::class, 'store'],   $enotfSettingsAuth);
$router->post('/settings/pois/update',     [PoiController::class, 'update'],  $enotfSettingsAuth);
$router->post('/settings/pois/delete',     [PoiController::class, 'destroy'], $enotfSettingsAuth);
$router->get('/settings/pois/access-codes',     [PoiController::class, 'accessCodes'], $enotfSettingsAuth);
$router->get('/settings/pois/departments',     [PoiController::class, 'departmentsIndex'], $enotfSettingsAuth);
$router->post('/settings/pois/departments-create',     [PoiController::class, 'departmentStore'],   $enotfSettingsAuth);
$router->post('/settings/pois/departments-update',     [PoiController::class, 'departmentUpdate'],  $enotfSettingsAuth);
$router->post('/settings/pois/departments-delete',     [PoiController::class, 'departmentDestroy'], $enotfSettingsAuth);
$router->post('/settings/pois/departments-reset-availability',     [PoiController::class, 'departmentResetAvailability'], $enotfSettingsAuth);
$router->match(['GET', 'POST'], '/settings/pois/departments-update-sort', $enotfApiRedirect('/api/pois/departments-sort'));
