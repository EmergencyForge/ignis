<?php

declare(strict_types=1);

/**
 * intraRP — HTML / Web-Routes
 *
 * Wird vom Front-Controller (public/index.php) geladen, nachdem der
 * Container und die Session stehen. Die $router-Variable ist an dieser
 * Stelle bereits instanziiert.
 *
 * ==============================================================================
 * Middleware-Baukasten
 * ==============================================================================
 *
 * Stateless Middlewares (per FQCN-String, vom Container aufgelöst):
 *   - App\Http\Middleware\FiveMCspMiddleware::class   // CSP-Header-Handling
 *
 * Parametrisierte Middlewares (als Instanz übergeben):
 *   - new AuthMiddleware()                            // Hard-Require Login
 *   - new AuthMiddleware('ENOTF_REQUIRE_USER_AUTH')   // nur wenn Flag=true
 *   - new AuthMiddleware('KB_PUBLIC_ACCESS', true)    // Auth AUSSER Flag=true
 *   - new PermissionMiddleware('admin')               // Einzel-Permission
 *   - new PermissionMiddleware(['personnel.edit', 'personnel.admin'])
 *
 * Shortstring-Syntax (ohne Constructor-Args via Container):
 *   'App\\Http\\Middleware\\PermissionMiddleware:personnel.edit'
 *
 * ==============================================================================
 *
 * @var \App\Http\Router $router
 */

use App\Http\Controllers\AntragController;
use App\Http\Controllers\EinsatzController;
use App\Http\Controllers\EnotfController;
use App\Http\Controllers\FahrtenbuchController;
use App\Http\Controllers\ManvController;
use App\Http\Controllers\MitarbeiterController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\FiveMCspMiddleware;
use App\Http\Middleware\PinLockscreenMiddleware;
use App\Http\Middleware\PolicyMiddleware;

// Smoke-Test-Route — hilft beim Verifizieren, dass die Pipeline steht.
// Kein Auth erforderlich, damit sie auch ohne Login erreichbar ist.
$router->get('/_router/ping', function ($request) {
    return \App\Http\Response::json([
        'success' => true,
        'message' => 'pong',
        'time'    => date('c'),
    ]);
});

// ----------------------------------------------------------------------------
//  Top-Level public pages — Invite-Link, Login, Logout, Index.
//
//  invite.php existiert weiterhin als Datei und wird direkt von Apache
//  serviert, wenn jemand `/invite.php?code=...` aufruft (Legacy-Link aus
//  alten Mails). Die kanonische Form ist `/invite?code=...` — diese Route
//  inkludiert die existierende Datei, damit beide URLs identisch wirken.
// ----------------------------------------------------------------------------

$router->get('/invite', function () {
    require __DIR__ . '/../invite.php';
    return \App\Http\Response::empty();
});

$rootIndex = function () {
    require __DIR__ . '/../index.php';
    return \App\Http\Response::empty();
};
$router->get('/',      $rootIndex);
$router->get('/index', $rootIndex);

$loginPage = function () {
    require __DIR__ . '/../login.php';
    return \App\Http\Response::empty();
};
$router->match(['GET', 'POST'], '/login', $loginPage);

// ----------------------------------------------------------------------------
//  Benutzer-Modul — UserController + RoleController rufen intern
//  requireAuth() + ensure() auf, Routes brauchen nur AuthMiddleware.
// ----------------------------------------------------------------------------

$userAuth = [new AuthMiddleware()];

$router->get('/benutzer/list',     [UserController::class, 'index'], $userAuth);

// edit.php: GET → edit(), POST (mit ?new=1) → update() — Dispatcher-Closure
$benutzerEditDispatch = function (\App\Http\Request $request) {
    $controller = app(UserController::class);
    if ($request->method === 'POST' && (string) ($request->post['new'] ?? '') === '1') {
        $controller->update();
    } else {
        $controller->edit();
    }
    return \App\Http\Response::empty();
};
$router->match(['GET', 'POST'], '/benutzer/edit',     $benutzerEditDispatch, $userAuth);

$router->match(['GET', 'POST'], '/benutzer/delete',     [UserController::class, 'destroy'], $userAuth);

$router->get('/benutzer/auditlog',     [UserController::class, 'auditlog'], $userAuth);

$router->match(['GET', 'POST'], '/benutzer/registration-codes',     [UserController::class, 'registrationCodes'], $userAuth);

$router->match(['GET', 'POST'], '/benutzer/toggle-active',     [UserController::class, 'setActive'], $userAuth);

// Rollen-Verwaltung
$router->get('/benutzer/rollen',           [RoleController::class, 'index'], $userAuth);
$router->get('/benutzer/rollen/',          [RoleController::class, 'index'], $userAuth);
$router->get('/benutzer/rollen/index',     [RoleController::class, 'index'], $userAuth);

$router->post('/benutzer/rollen/create',     [RoleController::class, 'store'], $userAuth);

$router->post('/benutzer/rollen/update',     [RoleController::class, 'update'], $userAuth);

$router->post('/benutzer/rollen/delete',     [RoleController::class, 'destroy'], $userAuth);

// ----------------------------------------------------------------------------
//  Antrag-Modul
//
//  Das komplette Antragssystem (Urlaub, Beförderung, etc.) läuft über den
//  AntragController mit Eloquent-Models. Alle Permission-Checks sind über
//  Policies abgedeckt — die einzelne Antrags-Ansicht prüft Ownership
//  im Controller, weil dort der Antrag erst geladen wird.
//
//  Jede Route ist mit und ohne `.php`-Suffix registriert, damit die
//  ehemaligen File-Stubs (antrag/create.php, antrag/view.php, etc.)
//  transparent über den Router laufen.
// ----------------------------------------------------------------------------

$antragAuth       = [new AuthMiddleware()];
$antragCreateAuth = [new AuthMiddleware(), new PolicyMiddleware('antrag.create')];
$antragDecideAuth = [new AuthMiddleware(), new PolicyMiddleware('antrag.decide')];
$antragListAuth   = [new AuthMiddleware(), new PolicyMiddleware('antrag.viewAny')];

$router->get('/antrag/select',      [AntragController::class, 'selectType'], $antragAuth);

$router->get('/antrag/create',      [AntragController::class, 'create'], $antragCreateAuth);
$router->post('/antrag/create',     [AntragController::class, 'store'],  $antragCreateAuth);

// view() prüft intern Gate::denies('antrag.view', $antrag) mit dem geladenen
// Model — deshalb nur AuthMiddleware hier, keine PolicyMiddleware.
$router->get('/antrag/view',        [AntragController::class, 'view'], $antragAuth);

$router->get('/antrag/admin/list',      [AntragController::class, 'adminList'], $antragListAuth);

$router->get('/antrag/admin/view',      [AntragController::class, 'adminView'], $antragDecideAuth);
$router->post('/antrag/admin/view',     [AntragController::class, 'decide'],    $antragDecideAuth);

// ----------------------------------------------------------------------------
//  Benachrichtigungen-Modul
//
//  Eine einzige URL (`/benachrichtigungen/index.php`) dient als View-Listing
//  (GET) und als Action-Endpoint (POST mit `action`-Feld). Der Router
//  dispatcht nur auf Method + URL — die Unterscheidung der 3 Actions
//  passiert mit einem kleinen Dispatcher-Closure, damit PolicyMiddleware
//  pro Action den korrekten Permission-Check macht.
// ----------------------------------------------------------------------------

$notifIndexAuth  = [new AuthMiddleware(), new PolicyMiddleware('notification.viewAny')];
$notifMarkAuth   = [new AuthMiddleware(), new PolicyMiddleware('notification.markRead')];
$notifDeleteAuth = [new AuthMiddleware(), new PolicyMiddleware('notification.delete')];

// GET → Liste
$router->get('/benachrichtigungen',           [NotificationController::class, 'index'], $notifIndexAuth);
$router->get('/benachrichtigungen/',          [NotificationController::class, 'index'], $notifIndexAuth);
$router->get('/benachrichtigungen/index',     [NotificationController::class, 'index'], $notifIndexAuth);
$router->get('/benachrichtigungen/index.php', [NotificationController::class, 'index'], $notifIndexAuth);

// POST-Dispatcher anhand $_POST['action']. Gate::authorize() wirft bei
// fehlender Berechtigung eine AuthorizationException, die im globalen
// Exception-Handler (public/index.php) zu Flash+Redirect wird.
$notifPostDispatch = function (\App\Http\Request $request) {
    $controller = app(NotificationController::class);
    $action     = (string) ($request->post['action'] ?? '');

    switch ($action) {
        case 'mark_read':
            \App\Auth\Gate::authorize('notification.markRead');
            $controller->markAsRead();
            break;
        case 'mark_all_read':
            \App\Auth\Gate::authorize('notification.markRead');
            $controller->markAllAsRead();
            break;
        case 'delete':
            \App\Auth\Gate::authorize('notification.delete');
            $controller->delete();
            break;
        default:
            \App\Auth\Gate::authorize('notification.viewAny');
            $controller->index();
    }
    return \App\Http\Response::empty();
};

$router->post('/benachrichtigungen',           $notifPostDispatch, [new AuthMiddleware()]);
$router->post('/benachrichtigungen/',          $notifPostDispatch, [new AuthMiddleware()]);
$router->post('/benachrichtigungen/index',     $notifPostDispatch, [new AuthMiddleware()]);
$router->post('/benachrichtigungen/index.php', $notifPostDispatch, [new AuthMiddleware()]);

// ----------------------------------------------------------------------------
//  Fahrtenbuch-Modul
//
//  `index()` ist Admin-only mit Policy-Middleware. `store/update/destroy`
//  werden über /fahrtenbuch/actions.php angesprochen und sind multi-context
//  (Admin + eNOTF + FireTab) — der Controller checkt die verschiedenen
//  Auth-Szenarien selbst via `requireAnyContext()` / `Gate::denies`.
// ----------------------------------------------------------------------------

$fahrtListAuth   = [new AuthMiddleware(), new PolicyMiddleware('fahrt.viewList')];

$router->get('/fahrtenbuch',           [FahrtenbuchController::class, 'index'], $fahrtListAuth);
$router->get('/fahrtenbuch/',          [FahrtenbuchController::class, 'index'], $fahrtListAuth);
$router->get('/fahrtenbuch/index',     [FahrtenbuchController::class, 'index'], $fahrtListAuth);
$router->get('/fahrtenbuch/index.php', [FahrtenbuchController::class, 'index'], $fahrtListAuth);

// POST /fahrtenbuch/actions.php — Multi-Context-Dispatcher.
// Keine Router-Middleware, weil die drei Auth-Kontexte (userid/fahrername/
// einsatz_vehicle_id) im Controller via `requireAnyContext()` geprüft werden.
$fahrtPostDispatch = function (\App\Http\Request $request) {
    $controller = app(FahrtenbuchController::class);
    $action     = (string) ($request->post['action'] ?? '');

    match ($action) {
        'create' => $controller->store(),
        'update' => $controller->update(),
        'delete' => $controller->destroy(),
        default  => (function () {
            \App\Helpers\Flash::error('Unbekannte Aktion.');
            header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '/') . 'fahrtenbuch/index.php');
            exit;
        })(),
    };
    return \App\Http\Response::empty();
};

$router->post('/fahrtenbuch/actions',     $fahrtPostDispatch);

// ----------------------------------------------------------------------------
//  Mitarbeiter-Modul
//
//  Das Mitarbeiter-Modul hat 7 URL-Entry-Points. profile.php hat einen
//  POST-Dispatcher mit `$_POST['new']`-Feld (1/4/5/6), der je nach Action
//  eine andere Permission braucht — wir lösen das analog zu Notification
//  mit einem Router-Closure + inline Gate::authorize().
//
//  Inline-Edit / PFP-Upload / Quali-Modal laufen über `/api/personnel/*`
//  (nicht durch dieses Modul) und sind nicht Teil dieser Registrierung.
// ----------------------------------------------------------------------------

$mitarbeiterListAuth    = [new AuthMiddleware(), new PolicyMiddleware('mitarbeiter.viewList')];
$mitarbeiterViewAuth    = [new AuthMiddleware(), new PolicyMiddleware('mitarbeiter.view')];
$mitarbeiterCreateAuth  = [new AuthMiddleware(), new PolicyMiddleware('mitarbeiter.create')];
$mitarbeiterDeleteAuth  = [new AuthMiddleware(), new PolicyMiddleware('mitarbeiter.delete')];
$mitarbeiterDocsAuth    = [new AuthMiddleware(), new PolicyMiddleware('mitarbeiter.manageDocs')];
$mitarbeiterCommentAuth = [new AuthMiddleware(), new PolicyMiddleware('mitarbeiter.deleteComments')];

$router->get('/mitarbeiter/list',     [MitarbeiterController::class, 'index'], $mitarbeiterListAuth);

$router->get('/mitarbeiter/profile',     [MitarbeiterController::class, 'show'], $mitarbeiterViewAuth);

// Profile POST-Dispatcher anhand $_POST['new']
// 1=Update / 4=Fachdienste / 5=Notiz → mitarbeiter.update
// 6=Dokument erstellen → mitarbeiter.manageDocs
$mitarbeiterProfileDispatch = function (\App\Http\Request $request) {
    $controller = app(MitarbeiterController::class);
    $action     = (string) ($request->post['new'] ?? '');

    switch ($action) {
        case '1':
            \App\Auth\Gate::authorize('mitarbeiter.update');
            $controller->update();
            break;
        case '4':
            \App\Auth\Gate::authorize('mitarbeiter.update');
            $controller->updateFachdienste();
            break;
        case '5':
            \App\Auth\Gate::authorize('mitarbeiter.update');
            $controller->addNote();
            break;
        case '6':
            \App\Auth\Gate::authorize('mitarbeiter.manageDocs');
            $controller->createDocument();
            break;
        default:
            \App\Auth\Gate::authorize('mitarbeiter.view');
            $controller->show();
    }
    return \App\Http\Response::empty();
};

$router->post('/mitarbeiter/profile',     $mitarbeiterProfileDispatch, [new AuthMiddleware()]);

// store() ist ein AJAX-JSON-Endpoint (gibt JSON zurück, nicht Redirect)
$router->post('/mitarbeiter/create',     [MitarbeiterController::class, 'store'], $mitarbeiterCreateAuth);

// destroy() läuft per GET (Legacy — könnte später auf DELETE umgestellt werden,
// aber im aktuellen UI wird das via Link getriggert)
$router->get('/mitarbeiter/delete',     [MitarbeiterController::class, 'destroy'], $mitarbeiterDeleteAuth);

// Dokument-View (GET — zeigt Dokumenten-Details), dokument-delete (POST mit CSRF)
$router->get('/mitarbeiter/dokument-view',     [MitarbeiterController::class, 'showDocument'], [new AuthMiddleware()]);

$router->post('/mitarbeiter/dokument-delete',     [MitarbeiterController::class, 'deleteDocument'], $mitarbeiterDocsAuth);

// Comment-Delete — wird per Link in der Detail-Liste getriggert, daher GET.
$router->get('/mitarbeiter/comment-delete',     [MitarbeiterController::class, 'deleteComment'], $mitarbeiterCommentAuth);

// ----------------------------------------------------------------------------
//  MANV-Modul
//
//  MANV-Lagen (Massenanfall von Verletzten). Der ManvController ruft intern
//  `ensure('manv.<ability>', redirectTo: 'index.php')` auf — das liefert
//  benutzerfreundliche Redirects statt 403. Deshalb hier nur AuthMiddleware,
//  keine PolicyMiddleware (analog zu AntragController::view).
//
//  Die `.php`-Varianten bleiben registriert, weil sowohl Navbar als auch
//  alle Templates noch auf die Legacy-URLs mit .php-Suffix verlinken.
// ----------------------------------------------------------------------------

$manvAuth = [new AuthMiddleware()];

$router->get('/manv/',          [ManvController::class, 'index'], $manvAuth);
$router->get('/manv/index',     [ManvController::class, 'index'], $manvAuth);

$router->get('/manv/board',     [ManvController::class, 'board'], $manvAuth);

$router->get('/manv/create',      [ManvController::class, 'create'], $manvAuth);
$router->post('/manv/create',     [ManvController::class, 'store'],  $manvAuth);

$router->get('/manv/edit',      [ManvController::class, 'edit'],   $manvAuth);
$router->post('/manv/edit',     [ManvController::class, 'update'], $manvAuth);

$router->get('/manv/log',     [ManvController::class, 'log'], $manvAuth);

$router->get('/manv/patient-create',      [ManvController::class, 'patientCreate'], $manvAuth);
$router->post('/manv/patient-create',     [ManvController::class, 'patientStore'],  $manvAuth);

$router->get('/manv/patient-view',      [ManvController::class, 'patientView'],   $manvAuth);
$router->post('/manv/patient-view',     [ManvController::class, 'patientUpdate'], $manvAuth);

// Ressourcen: kombinierter Endpoint.
//   GET  ?delete_id=Y   → ressourceDelete() (Legacy-GET-Delete, via showConfirm)
//   GET                 → ressourcen()      (View)
//   POST action=create  → ressourceStore()
//   POST action=edit    → ressourceUpdate()
$manvRessourcenGet = function (\App\Http\Request $request) {
    $controller = app(ManvController::class);
    if (isset($request->query['delete_id'])) {
        $controller->ressourceDelete();
    } else {
        $controller->ressourcen();
    }
    return \App\Http\Response::empty();
};
$manvRessourcenPost = function (\App\Http\Request $request) {
    $controller = app(ManvController::class);
    $action     = (string) ($request->post['action'] ?? '');
    if ($action === 'create') {
        $controller->ressourceStore();
    } elseif ($action === 'edit') {
        $controller->ressourceUpdate();
    } else {
        $controller->ressourcen();
    }
    return \App\Http\Response::empty();
};
$router->get('/manv/ressourcen',      $manvRessourcenGet,  $manvAuth);
$router->post('/manv/ressourcen',     $manvRessourcenPost, $manvAuth);

// ----------------------------------------------------------------------------
//  Einsatz-Modul (FireTab)
//
//  Wird im FiveM-In-Game-Browser (CitizenFX CEF) angezeigt, also brauchen
//  die interaktiven Pages die FiveMCspMiddleware (CSP / X-Frame-Options
//  je nach User-Agent). Die Session-Cookie-Konfiguration für iframe
//  (SameSite=None + Secure) ist im SessionManager gekapselt — der
//  erkennt `/einsatz/` in REQUEST_URI automatisch.
//
//  Authentifizierung: Config-Flag FIRE_INCIDENT_REQUIRE_USER_AUTH entscheidet,
//  ob zusätzlich zum FireTab-Fahrzeug-Login ein System-User-Login nötig ist.
//  Der Controller selbst ruft intern `ensure('fireIncident.xxx')` für Policy-
//  Checks auf, deshalb hier nur AuthMiddleware als äußeres Gate.
//
//  Admin-Liste läuft auf einer eigenen Route mit hartem AuthMiddleware —
//  das ist kein FireTab-Browser, sondern das Back-Office.
//
//  API-Endpoints `lagekarte-api.php` und `status-api.php` werden mit 308
//  auf die kanonischen `/api/fire/...`-Routes umgeleitet, damit Legacy-JS
//  mit alten URLs weiter funktioniert.
// ----------------------------------------------------------------------------

$einsatzAuth    = [new AuthMiddleware('FIRE_INCIDENT_REQUIRE_USER_AUTH'), FiveMCspMiddleware::class];
$einsatzAdminAuth = [new AuthMiddleware()];

$router->get('/einsatz/',          [EinsatzController::class, 'index'], $einsatzAuth);
$router->get('/einsatz/index',     [EinsatzController::class, 'index'], $einsatzAuth);

$router->get('/einsatz/list',     [EinsatzController::class, 'list'], $einsatzAuth);

$router->get('/einsatz/view',     [EinsatzController::class, 'view'], $einsatzAuth);

$router->get('/einsatz/create',      [EinsatzController::class, 'createForm'], $einsatzAuth);
$router->post('/einsatz/create',     [EinsatzController::class, 'store'],     $einsatzAuth);

$router->get('/einsatz/login-fahrzeug',      [EinsatzController::class, 'loginForm'], $einsatzAuth);
$router->post('/einsatz/login-fahrzeug',     [EinsatzController::class, 'login'],     $einsatzAuth);

$router->post('/einsatz/actions',     [EinsatzController::class, 'dispatchAction'], $einsatzAuth);

$router->get('/einsatz/asu',     [EinsatzController::class, 'asuForm'], $einsatzAuth);

$router->get('/einsatz/fahrtenbuch',     [EinsatzController::class, 'fireTabFahrtenbuch'], $einsatzAuth);

$router->get('/einsatz/statusmeldungen',     [EinsatzController::class, 'statusmeldungen'], $einsatzAuth);

$router->get('/einsatz/admin/list',     [EinsatzController::class, 'adminList'], $einsatzAdminAuth);

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
$router->match(['GET', 'POST'], '/einsatz/lagekarte-api.php', $einsatzApiRedirect('/api/fire/lagekarte'));
$router->match(['GET', 'POST'], '/einsatz/status-api.php',    $einsatzApiRedirect('/api/fire/status'));

// ----------------------------------------------------------------------------
//  Settings-Modul
//
//  Alle Settings-Controller rufen intern `requireAuth()` + `ensureAdmin()`
//  auf — deshalb hier nur AuthMiddleware als äußeres Gate. Redirects auf
//  `/index.php` bei fehlender Permission liefert der Controller.
//
//  3 Legacy-API-Endpoints (defects-handler, departments-sort, regenerate-
//  api-key) sind 308-Redirects auf ihre `/api/...`-Router-Routes — die JS-
//  Callsites nutzen noch die Legacy-URLs, der 308 bewahrt Method + Body.
// ----------------------------------------------------------------------------

$settingsAuth = [new AuthMiddleware()];

// Antrag-Settings
$router->get('/settings/antrag/list',       [\App\Http\Controllers\Settings\AntragSettingsController::class, 'listAction'],  $settingsAuth);
$router->get('/settings/antrag/create',     [\App\Http\Controllers\Settings\AntragSettingsController::class, 'createForm'], $settingsAuth);
$router->get('/settings/antrag/edit',       [\App\Http\Controllers\Settings\AntragSettingsController::class, 'edit'],       $settingsAuth);
$router->post('/settings/antrag/edit',      [\App\Http\Controllers\Settings\AntragSettingsController::class, 'edit'],       $settingsAuth);

// Dashboard-Settings
$router->get('/settings/dashboard/index',      [\App\Http\Controllers\Settings\DashboardController::class, 'index'], $settingsAuth);
$router->post('/settings/dashboard/categories/create',     [\App\Http\Controllers\Settings\DashboardController::class, 'categoryStore'],   $settingsAuth);
$router->post('/settings/dashboard/categories/update',     [\App\Http\Controllers\Settings\DashboardController::class, 'categoryUpdate'],  $settingsAuth);
$router->post('/settings/dashboard/categories/delete',     [\App\Http\Controllers\Settings\DashboardController::class, 'categoryDestroy'], $settingsAuth);
$router->post('/settings/dashboard/tiles/create',     [\App\Http\Controllers\Settings\DashboardController::class, 'tileStore'],   $settingsAuth);
$router->post('/settings/dashboard/tiles/update',     [\App\Http\Controllers\Settings\DashboardController::class, 'tileUpdate'],  $settingsAuth);
$router->post('/settings/dashboard/tiles/delete',     [\App\Http\Controllers\Settings\DashboardController::class, 'tileDestroy'], $settingsAuth);

// Documents-Settings
$router->get('/settings/documents/categories',        [\App\Http\Controllers\Settings\DocumentController::class, 'categories'],   $settingsAuth);
$router->get('/settings/documents/templates',         [\App\Http\Controllers\Settings\DocumentController::class, 'templates'],    $settingsAuth);
$router->get('/settings/documents/visual-editor',     [\App\Http\Controllers\Settings\DocumentController::class, 'visualEditor'], $settingsAuth);

// eNOTF-Settings (Schnellzugriff + Kategorien)
$router->get('/settings/enotf/index',     [\App\Http\Controllers\Settings\EnotfController::class, 'index'],   $settingsAuth);
$router->post('/settings/enotf/create',     [\App\Http\Controllers\Settings\EnotfController::class, 'store'],   $settingsAuth);
$router->post('/settings/enotf/update',     [\App\Http\Controllers\Settings\EnotfController::class, 'update'],  $settingsAuth);
$router->post('/settings/enotf/delete',     [\App\Http\Controllers\Settings\EnotfController::class, 'destroy'], $settingsAuth);
$router->get('/settings/enotf/kategorien/index',     [\App\Http\Controllers\Settings\EnotfController::class, 'categoriesIndex'],  $settingsAuth);
$router->post('/settings/enotf/kategorien/create',     [\App\Http\Controllers\Settings\EnotfController::class, 'categoryStore'],   $settingsAuth);
$router->post('/settings/enotf/kategorien/update',     [\App\Http\Controllers\Settings\EnotfController::class, 'categoryUpdate'],  $settingsAuth);
$router->post('/settings/enotf/kategorien/delete',     [\App\Http\Controllers\Settings\EnotfController::class, 'categoryDestroy'], $settingsAuth);

// Fahrzeuge-Settings (Fahrzeuge + Beladelisten + Defekte)
$router->get('/settings/fahrzeuge/fahrzeuge/index',     [\App\Http\Controllers\Settings\FahrzeugeController::class, 'index'],   $settingsAuth);
$router->post('/settings/fahrzeuge/fahrzeuge/create',     [\App\Http\Controllers\Settings\FahrzeugeController::class, 'store'],   $settingsAuth);
$router->post('/settings/fahrzeuge/fahrzeuge/update',     [\App\Http\Controllers\Settings\FahrzeugeController::class, 'update'],  $settingsAuth);
$router->post('/settings/fahrzeuge/fahrzeuge/delete',     [\App\Http\Controllers\Settings\FahrzeugeController::class, 'destroy'], $settingsAuth);
$router->get('/settings/fahrzeuge/beladelisten/index',     [\App\Http\Controllers\Settings\FahrzeugeController::class, 'beladelistenIndex'], $settingsAuth);
$router->post('/settings/fahrzeuge/beladelisten/beladung_handler',     [\App\Http\Controllers\Settings\FahrzeugeController::class, 'beladungHandler'], $settingsAuth);
$router->get('/settings/fahrzeuge/defekte/index',     [\App\Http\Controllers\Settings\FahrzeugeController::class, 'defekteIndex'], $settingsAuth);

// Federation-Settings
$router->get('/settings/federation/index',      [\App\Http\Controllers\Settings\FederationController::class, 'index'], $settingsAuth);
$router->post('/settings/federation/index',     [\App\Http\Controllers\Settings\FederationController::class, 'index'], $settingsAuth);

// Medikamente-Settings
$router->get('/settings/medikamente/index',     [\App\Http\Controllers\Settings\MedikamenteController::class, 'index'],   $settingsAuth);
$router->post('/settings/medikamente/create',     [\App\Http\Controllers\Settings\MedikamenteController::class, 'store'],   $settingsAuth);
$router->post('/settings/medikamente/update',     [\App\Http\Controllers\Settings\MedikamenteController::class, 'update'],  $settingsAuth);
$router->post('/settings/medikamente/delete',     [\App\Http\Controllers\Settings\MedikamenteController::class, 'destroy'], $settingsAuth);

// Personal-Settings (Dienstgrade + 3x Qualifikationen)
$router->get('/settings/personal/dienstgrade/index',     [\App\Http\Controllers\Settings\PersonalController::class, 'dienstgradeIndex'], $settingsAuth);
$router->post('/settings/personal/dienstgrade/create',     [\App\Http\Controllers\Settings\PersonalController::class, 'dienstgradStore'],  $settingsAuth);
$router->post('/settings/personal/dienstgrade/update',     [\App\Http\Controllers\Settings\PersonalController::class, 'dienstgradUpdate'], $settingsAuth);
$router->post('/settings/personal/dienstgrade/delete',     [\App\Http\Controllers\Settings\PersonalController::class, 'dienstgradDelete'], $settingsAuth);

$router->get('/settings/personal/qualifw/index',     [\App\Http\Controllers\Settings\PersonalController::class, 'fwQualiIndex'], $settingsAuth);
$router->post('/settings/personal/qualifw/create',     [\App\Http\Controllers\Settings\PersonalController::class, 'fwQualiStore'],  $settingsAuth);
$router->post('/settings/personal/qualifw/update',     [\App\Http\Controllers\Settings\PersonalController::class, 'fwQualiUpdate'], $settingsAuth);
$router->post('/settings/personal/qualifw/delete',     [\App\Http\Controllers\Settings\PersonalController::class, 'fwQualiDelete'], $settingsAuth);

$router->get('/settings/personal/qualird/index',     [\App\Http\Controllers\Settings\PersonalController::class, 'rdQualiIndex'], $settingsAuth);
$router->post('/settings/personal/qualird/create',     [\App\Http\Controllers\Settings\PersonalController::class, 'rdQualiStore'],  $settingsAuth);
$router->post('/settings/personal/qualird/update',     [\App\Http\Controllers\Settings\PersonalController::class, 'rdQualiUpdate'], $settingsAuth);
$router->post('/settings/personal/qualird/delete',     [\App\Http\Controllers\Settings\PersonalController::class, 'rdQualiDelete'], $settingsAuth);

$router->get('/settings/personal/qualifd/index',     [\App\Http\Controllers\Settings\PersonalController::class, 'fdQualiIndex'], $settingsAuth);
$router->post('/settings/personal/qualifd/create',     [\App\Http\Controllers\Settings\PersonalController::class, 'fdQualiStore'],  $settingsAuth);
$router->post('/settings/personal/qualifd/update',     [\App\Http\Controllers\Settings\PersonalController::class, 'fdQualiUpdate'], $settingsAuth);
$router->post('/settings/personal/qualifd/delete',     [\App\Http\Controllers\Settings\PersonalController::class, 'fdQualiDelete'], $settingsAuth);

// POI-Settings
$router->get('/settings/pois/index',     [\App\Http\Controllers\Settings\PoiController::class, 'index'],   $settingsAuth);
$router->post('/settings/pois/create',     [\App\Http\Controllers\Settings\PoiController::class, 'store'],   $settingsAuth);
$router->post('/settings/pois/update',     [\App\Http\Controllers\Settings\PoiController::class, 'update'],  $settingsAuth);
$router->post('/settings/pois/delete',     [\App\Http\Controllers\Settings\PoiController::class, 'destroy'], $settingsAuth);
$router->get('/settings/pois/access-codes',     [\App\Http\Controllers\Settings\PoiController::class, 'accessCodes'], $settingsAuth);
$router->get('/settings/pois/departments',     [\App\Http\Controllers\Settings\PoiController::class, 'departmentsIndex'], $settingsAuth);
$router->post('/settings/pois/departments-create',     [\App\Http\Controllers\Settings\PoiController::class, 'departmentStore'],   $settingsAuth);
$router->post('/settings/pois/departments-update',     [\App\Http\Controllers\Settings\PoiController::class, 'departmentUpdate'],  $settingsAuth);
$router->post('/settings/pois/departments-delete',     [\App\Http\Controllers\Settings\PoiController::class, 'departmentDestroy'], $settingsAuth);
$router->post('/settings/pois/departments-reset-availability',     [\App\Http\Controllers\Settings\PoiController::class, 'departmentResetAvailability'], $settingsAuth);

// System-Settings
$router->get('/settings/system/index',        [\App\Http\Controllers\Settings\SystemController::class, 'index'],       $settingsAuth);
$router->post('/settings/system/index',       [\App\Http\Controllers\Settings\SystemController::class, 'index'],       $settingsAuth);
$router->get('/settings/system/config',       [\App\Http\Controllers\Settings\SystemController::class, 'config'],      $settingsAuth);
$router->post('/settings/system/config',      [\App\Http\Controllers\Settings\SystemController::class, 'config'],      $settingsAuth);
$router->get('/settings/system/performance', [\App\Http\Controllers\Settings\SystemController::class, 'performance'], $settingsAuth);
$router->get('/settings/system/telemetry',      [\App\Http\Controllers\Settings\SystemController::class, 'telemetry'],   $settingsAuth);
$router->post('/settings/system/telemetry',     [\App\Http\Controllers\Settings\SystemController::class, 'telemetry'],   $settingsAuth);
$router->get('/settings/system/logs',     [\App\Http\Controllers\Settings\LogsController::class, 'index'], $settingsAuth);

// Cron-Verwaltung
$router->get('/settings/system/cron',          [\App\Http\Controllers\Settings\CronController::class, 'index'],   $settingsAuth);
$router->get('/settings/system/cron/history',  [\App\Http\Controllers\Settings\CronController::class, 'history'], $settingsAuth);
$router->post('/settings/system/cron/toggle',  [\App\Http\Controllers\Settings\CronController::class, 'toggle'],  $settingsAuth);
$router->post('/settings/system/cron/run',     [\App\Http\Controllers\Settings\CronController::class, 'runNow'],  $settingsAuth);
$router->post('/settings/system/cron/delete',  [\App\Http\Controllers\Settings\CronController::class, 'delete'],  $settingsAuth);
$router->post('/settings/system/cron/create',  [\App\Http\Controllers\Settings\CronController::class, 'store'],   $settingsAuth);

// 308-Redirects für Legacy-API-URLs (JS-Callsites nutzen noch alte Pfade)
$settingsApiRedirect = function (string $target): \Closure {
    return function (\App\Http\Request $request) use ($target): \App\Http\Response {
        $qs   = $request->server['QUERY_STRING'] ?? '';
        $base = defined('BASE_PATH') ? (string) BASE_PATH : '/';
        $url  = rtrim($base, '/') . $target . ($qs !== '' ? '?' . $qs : '');
        return \App\Http\Response::redirect($url, 308);
    };
};
$router->match(['GET', 'POST'], '/settings/fahrzeuge/defekte/handler.php',       $settingsApiRedirect('/api/vehicles/defects-handler'));
$router->match(['GET', 'POST'], '/settings/pois/departments-update-sort.php',   $settingsApiRedirect('/api/pois/departments-sort'));
$router->match(['GET', 'POST'], '/settings/system/regenerate-api-key.php',      $settingsApiRedirect('/api/system/regenerate-api-key'));

// ----------------------------------------------------------------------------
//  eNOTF-Modul — Root-Pages + Login-Flow
//
//  Läuft im FiveM-CEF-Browser (iframe) → FiveMCspMiddleware an allen Routen.
//  User-Auth ist optional und wird über das Config-Flag
//  ENOTF_REQUIRE_USER_AUTH gesteuert.
//
//  Drei Middleware-Gruppen:
//    • Public          — keine Auth (nur CSP/iframe-Support)
//    • Entry / Login   — optionale User-Auth, KEIN PIN-Lockscreen (sonst
//                        Redirect-Loop auf lockscreen.php selbst)
//    • Crew-protected  — User-Auth + PIN-Lockscreen + CSP (volle Pipeline)
//
//  iframe-Cookie-Handling (SameSite=None, Secure) kommt vom SessionManager,
//  der `/enotf/` in REQUEST_URI automatisch erkennt.
// ----------------------------------------------------------------------------

$enotfPublic     = [FiveMCspMiddleware::class];
$enotfEntry      = [new AuthMiddleware('ENOTF_REQUIRE_USER_AUTH'), FiveMCspMiddleware::class];
$enotfCrew       = [new AuthMiddleware('ENOTF_REQUIRE_USER_AUTH'), PinLockscreenMiddleware::class, FiveMCspMiddleware::class];

$router->get('/enotf/',          [EnotfController::class, 'index'], $enotfCrew);
$router->get('/enotf/index',     [EnotfController::class, 'index'], $enotfCrew);

// Login-Flow: KEIN PIN-Middleware (wäre Loop)
$router->get('/enotf/login',      [EnotfController::class, 'loginForm'], $enotfEntry);
$router->post('/enotf/login',     [EnotfController::class, 'login'],     $enotfEntry);

// Logout (Legacy: DB-Write auf GET via `?mode=self|all`)
$router->get('/enotf/loggedout',     [EnotfController::class, 'logout'], $enotfEntry);

// Lockscreen selbst darf NICHT durch PinLockscreenMiddleware — Redirect-Loop
$router->match(['GET', 'POST'], '/enotf/lockscreen',     [EnotfController::class, 'lockscreen'], $enotfEntry);

// Crew-protected (brauchen aktive Crew-Session + PIN-Lockscreen)
$router->match(['GET', 'POST'], '/enotf/overview',     [EnotfController::class, 'overview'], $enotfCrew);

$router->get('/enotf/create',     [EnotfController::class, 'createForm'], $enotfCrew);

$router->get('/enotf/fahrzeuginfo',     [EnotfController::class, 'fahrzeuginfo'], $enotfCrew);

$router->get('/enotf/fahrtenbuch',     [EnotfController::class, 'fahrtenbuch'], $enotfCrew);

// hospital-availability ist public (kein Login, kein PIN)
$router->get('/enotf/hospital-availability',     [EnotfController::class, 'hospitalAvailability'], $enotfPublic);

// ----------------------------------------------------------------------------
//  eNOTF-Modul — Admin
//
//  EnotfAdminController prüft intern requireAuth() +
//  Permissions::check(['admin','edivi.view'|'edivi.edit']) → Routes
//  brauchen nur AuthMiddleware. Back-Office-UI, nicht im FiveM-CEF →
//  keine FiveMCspMiddleware nötig.
// ----------------------------------------------------------------------------

$enotfAdminAuth = [new AuthMiddleware()];

$router->get('/enotf/admin',          [\App\Http\Controllers\EnotfAdminController::class, 'listAction'], $enotfAdminAuth);
$router->get('/enotf/admin/',         [\App\Http\Controllers\EnotfAdminController::class, 'listAction'], $enotfAdminAuth);
$router->get('/enotf/admin/list',     [\App\Http\Controllers\EnotfAdminController::class, 'listAction'], $enotfAdminAuth);

$router->get('/enotf/admin/delete',     [\App\Http\Controllers\EnotfAdminController::class, 'destroy'], $enotfAdminAuth);

$router->get('/enotf/admin/qm-actions-modal',     [\App\Http\Controllers\EnotfAdminController::class, 'qmActionsModal'], $enotfAdminAuth);

$router->get('/enotf/admin/qm-log-modal',     [\App\Http\Controllers\EnotfAdminController::class, 'qmLogModal'], $enotfAdminAuth);

// bulk-delete-empty: 308-Redirect auf /api/enotf/bulk-delete-empty (Ziel-Route
// existiert bereits in routes/api.session.php)
$enotfApiRedirect = function (string $target): \Closure {
    return function (\App\Http\Request $request) use ($target): \App\Http\Response {
        $qs   = $request->server['QUERY_STRING'] ?? '';
        $base = defined('BASE_PATH') ? (string) BASE_PATH : '/';
        $url  = rtrim($base, '/') . $target . ($qs !== '' ? '?' . $qs : '');
        return \App\Http\Response::redirect($url, 308);
    };
};
$router->match(['GET', 'POST'], '/enotf/admin/bulk-delete-empty.php', $enotfApiRedirect('/api/enotf/bulk-delete-empty'));

// Zielverwaltung — auf POI-System konsolidiert. Legacy-URLs leiten
// dauerhaft auf `/settings/pois` um, bis externe Bookmarks aktualisiert
// sind. Controller + Template gibt's noch im Repo, sind aber nicht mehr
// erreichbar.
$zielverwaltungRedirect = static function (\App\Http\Request $request) {
    $base = defined('BASE_PATH') ? (string) BASE_PATH : '/';
    return \App\Http\Response::redirect($base . 'settings/pois', 301);
};
$router->match(['GET', 'POST'], '/enotf/admin/zielverwaltung',           $zielverwaltungRedirect);
$router->match(['GET', 'POST'], '/enotf/admin/zielverwaltung/',          $zielverwaltungRedirect);
$router->match(['GET', 'POST'], '/enotf/admin/zielverwaltung/create',    $zielverwaltungRedirect);
$router->match(['GET', 'POST'], '/enotf/admin/zielverwaltung/update',    $zielverwaltungRedirect);
$router->match(['GET', 'POST'], '/enotf/admin/zielverwaltung/delete',    $zielverwaltungRedirect);

// ----------------------------------------------------------------------------
//  eNOTF-Modul — Print + Schnittstelle
//
//  Print:         Crew-facing, voller Middleware-Stack inkl. PIN-Lockscreen.
//                 EnotfPrintController::show() macht zusätzlich einen eigenen
//                 PIN-Check — Belt-and-Suspenders, beide Policies sind
//                 deckungsgleich.
//  Schnittstelle: Public (Klinik-Access ohne User-Login möglich) → nur
//                 FiveMCspMiddleware. `voranmeldung` prüft PIN je nach
//                 Config selbst.
// ----------------------------------------------------------------------------

// Print — ENR kommt entweder als Query (/enotf/print/index.php?enr=…)
// oder als Clean-URL-Segment (/enotf/print/{enr}).
$router->get('/enotf/print',            [\App\Http\Controllers\EnotfPrintController::class, 'show'], $enotfCrew);
$router->get('/enotf/print/',           [\App\Http\Controllers\EnotfPrintController::class, 'show'], $enotfCrew);
$router->get('/enotf/print/index',      [\App\Http\Controllers\EnotfPrintController::class, 'show'], $enotfCrew);
// Clean-URL: Parameter über $_GET reichen, damit show() weiterhin ?enr= liest.
$router->get('/enotf/print/{enr:[\w._-]+}', function (\App\Http\Request $request, string $enr) {
    $_GET['enr'] = $enr;
    app(\App\Http\Controllers\EnotfPrintController::class)->show();
    return \App\Http\Response::empty();
}, $enotfCrew);

// Schnittstelle — public
$router->get('/enotf/schnittstelle',           [\App\Http\Controllers\EnotfSchnittstelleController::class, 'index'], $enotfPublic);
$router->get('/enotf/schnittstelle/',          [\App\Http\Controllers\EnotfSchnittstelleController::class, 'index'], $enotfPublic);
$router->get('/enotf/schnittstelle/index',     [\App\Http\Controllers\EnotfSchnittstelleController::class, 'index'], $enotfPublic);

$router->match(['GET', 'POST'], '/enotf/schnittstelle/klinikcode',     [\App\Http\Controllers\EnotfSchnittstelleController::class, 'klinikcode'], $enotfPublic);

$router->match(['GET', 'POST'], '/enotf/schnittstelle/voranmeldung',     [\App\Http\Controllers\EnotfSchnittstelleController::class, 'voranmeldung'], $enotfPublic);

$router->get('/enotf/schnittstelle/hospital-availability',     [\App\Http\Controllers\EnotfSchnittstelleController::class, 'hospitalAvailability'], $enotfPublic);

// api-prereg: 308 auf /api/enotf/prereg
$router->match(['GET', 'POST'], '/enotf/schnittstelle/api-prereg.php', $enotfApiRedirect('/api/enotf/prereg'));

// ----------------------------------------------------------------------------
//  eNOTF-Modul — Protokoll-Pages
//
//  EnotfProtokollController::serve(string $templatePath) rendert jede
//  Protokoll-Page. Der Template-Pfad spiegelt die URL-Struktur:
//  URL `/enotf/protokoll/abschluss/3_1.php` → Template
//  `enotf/protokoll/abschluss/3_1`.
//
//  Zwei URL-Formen werden unterstützt:
//    1. Direct-Path:  /enotf/protokoll/<section>/<page>.php?enr=X
//    2. Clean-URL:    /enotf/p/{enr}/<section>/<page>
//
//  Segment 3 ist mehrdeutig — für die Sections `erstbefund` und `massnahmen`
//  ist es ein Unter-Verzeichnis (→ index.php), für alle anderen ein
//  Leaf-Template (→ <page>.php). Der Resolver prüft das per FS-Check.
// ----------------------------------------------------------------------------

// Helper: resolviert Path-Segmente auf einen Template-Pfad.
$protokollResolveTemplate = static function (?string $section, ?string $subsection, ?string $page): string {
    $projectRoot = dirname(__DIR__);
    $base        = 'enotf/protokoll';

    if ($section === null) {
        return $base . '/index';
    }
    if ($subsection === null) {
        // `/enotf/protokoll/index.php` und `/enotf/protokoll/` (via Clean-URL
        // mit nur ENR) resolven beide auf das Protokoll-Root-Template —
        // "index" ist hier KEIN Sektions-Ordner-Name.
        if ($section === 'index') {
            return $base . '/index';
        }
        // Ambiguität: {section} kann Unter-Verzeichnis (mit index.php) ODER
        // direktes Leaf-Template sein (z.B. `protokollart.php`). Früher hat
        // Apache MultiViews das `/index.php` weggefallen lassen, wenn's keinen
        // passenden Ordner gab — der Router muss das jetzt selbst per FS-Check
        // erkennen.
        $candidateDirIndex = $projectRoot . '/templates/' . $base . '/' . $section . '/index.php';
        if (!is_file($candidateDirIndex)) {
            $candidateLeaf = $projectRoot . '/templates/' . $base . '/' . $section . '.php';
            if (is_file($candidateLeaf)) {
                return $base . '/' . $section;
            }
        }
        return $base . '/' . $section . '/index';
    }
    if ($page === null) {
        // Ambiguität: subsection kann Leaf-Page ODER Subdir-Name sein.
        // FS-Check: existiert `/section/subsection/index.php`?
        $candidateIndex = $projectRoot . '/templates/' . $base . '/' . $section . '/' . $subsection . '/index.php';
        if (is_file($candidateIndex)) {
            return $base . '/' . $section . '/' . $subsection . '/index';
        }
        return $base . '/' . $section . '/' . $subsection;
    }
    return $base . '/' . $section . '/' . $subsection . '/' . $page;
};

// Direct-Path-Handler: URL matched `/enotf/protokoll/<irgendwas>` — ohne `.php`
$protokollDirectHandler = function (\App\Http\Request $request) use ($protokollResolveTemplate): \App\Http\Response {
    $path   = $request->path;
    $suffix = '';
    if (preg_match('#^/enotf/protokoll/?(.*)$#', $path, $m)) {
        $suffix = rtrim($m[1], '/');
        $suffix = (string) preg_replace('/\.php$/', '', $suffix);
    }

    // Apache-MultiViews-Parität: `/foo/index.php` und `/foo/` zeigen auf
    // denselben Template-Pfad. Trailing `/index` oder `/index/…` strippen.
    $suffix = (string) preg_replace('#(?:^|/)index$#', '', $suffix);
    $suffix = trim($suffix, '/');

    if ($suffix === '') {
        $templatePath = 'enotf/protokoll/index';
    } else {
        $segments = explode('/', $suffix);
        $templatePath = $protokollResolveTemplate(
            $segments[0] ?? null,
            $segments[1] ?? null,
            $segments[2] ?? null
        );
        // 4-Segmente-Fall (selten, z.B. tiefste diagnose-Struktur)
        if (isset($segments[3])) {
            $templatePath .= '/' . $segments[3];
        }
    }

    app(\App\Http\Controllers\EnotfProtokollController::class)->serve($templatePath);
    return \App\Http\Response::empty();
};

$router->match(['GET', 'POST'], '/enotf/protokoll',                    $protokollDirectHandler, $enotfCrew);
$router->match(['GET', 'POST'], '/enotf/protokoll/',                   $protokollDirectHandler, $enotfCrew);
$router->match(['GET', 'POST'], '/enotf/protokoll/{path:[\w./_-]+}',   $protokollDirectHandler, $enotfCrew);

// Clean-URL-Routen `/enotf/p/{enr}/...` — replicieren die Root-htaccess-Rewrites
$router->match(['GET', 'POST'], '/enotf/p/{enr:[\w._-]+}', function (\App\Http\Request $request, string $enr) use ($protokollResolveTemplate): \App\Http\Response {
    $_GET['enr'] = $enr;
    app(\App\Http\Controllers\EnotfProtokollController::class)->serve($protokollResolveTemplate(null, null, null));
    return \App\Http\Response::empty();
}, $enotfCrew);

$router->match(['GET', 'POST'], '/enotf/p/{enr:[\w._-]+}/{section:[\w-]+}', function (\App\Http\Request $request, string $enr, string $section) use ($protokollResolveTemplate): \App\Http\Response {
    $_GET['enr'] = $enr;
    app(\App\Http\Controllers\EnotfProtokollController::class)->serve($protokollResolveTemplate($section, null, null));
    return \App\Http\Response::empty();
}, $enotfCrew);

$router->match(['GET', 'POST'], '/enotf/p/{enr:[\w._-]+}/{section:[\w-]+}/{subsection:[\w_-]+}', function (\App\Http\Request $request, string $enr, string $section, string $subsection) use ($protokollResolveTemplate): \App\Http\Response {
    $_GET['enr'] = $enr;
    app(\App\Http\Controllers\EnotfProtokollController::class)->serve($protokollResolveTemplate($section, $subsection, null));
    return \App\Http\Response::empty();
}, $enotfCrew);

$router->match(['GET', 'POST'], '/enotf/p/{enr:[\w._-]+}/{section:[\w-]+}/{subsection:[\w-]+}/{page:[\w_-]+}', function (\App\Http\Request $request, string $enr, string $section, string $subsection, string $page) use ($protokollResolveTemplate): \App\Http\Response {
    $_GET['enr'] = $enr;
    app(\App\Http\Controllers\EnotfProtokollController::class)->serve($protokollResolveTemplate($section, $subsection, $page));
    return \App\Http\Response::empty();
}, $enotfCrew);

/*
 * BEISPIEL — Benutzer-Modul mit Policy-basierter Autorisierung
 *
 * $router->group('/users', [new AuthMiddleware()], function ($r) {
 *     // Liste: klassen-level Ability, kein Ziel-Objekt
 *     $r->get('/',
 *         [\App\Http\Controllers\UserController::class, 'index'],
 *         [new PolicyMiddleware('user.viewList')]
 *     );
 *
 *     // Edit: mit Route-Parameter als Resource
 *     $r->post('/{id:\d+}',
 *         [\App\Http\Controllers\UserController::class, 'update'],
 *         [new PolicyMiddleware('user.update', resourceParam: 'id')]
 *     );
 * });
 *
 * BEISPIEL — eNOTF-Protokoll (config-gated Auth + PIN-Lockscreen + FiveM-CSP)
 *
 * $router->group('/enotf', [
 *     new AuthMiddleware('ENOTF_REQUIRE_USER_AUTH'),
 *     PinLockscreenMiddleware::class,
 *     FiveMCspMiddleware::class,
 * ], function ($r) {
 *     $r->get('/protokoll/{enr}', [\App\Http\Controllers\EnotfProtokollController::class, 'index']);
 * });
 *
 * BEISPIEL — Wissensdatenbank (public wenn KB_PUBLIC_ACCESS=true)
 *
 * $router->get('/wissensdb/{slug}',
 *     [\App\Http\Controllers\KnowledgebaseController::class, 'show'],
 *     [new AuthMiddleware('KB_PUBLIC_ACCESS', invert: true)]
 * );
 *
 * Für einfache Permission-Checks ohne Policy-Kontext reicht weiterhin
 * der schlankere PermissionMiddleware — z.B. Admin-only Endpoints ohne
 * Resource-Bezug. PolicyMiddleware ist der richtige Griff, sobald die
 * Entscheidung vom Ziel-Objekt abhängt (Priority-Vergleich, Ownership etc.).
 */
