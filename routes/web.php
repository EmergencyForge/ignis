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
//  Benutzer-Modul (Pilot — Controller + Templates seit Welle 1 migriert, hier
//  nur noch Routes). UserController + RoleController machen requireAuth +
//  ensure() intern, Routes brauchen nur AuthMiddleware.
// ----------------------------------------------------------------------------

$userAuth = [new AuthMiddleware()];

$router->get('/benutzer/list',     [UserController::class, 'index'], $userAuth);
$router->get('/benutzer/list.php', [UserController::class, 'index'], $userAuth);

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
$router->match(['GET', 'POST'], '/benutzer/edit.php', $benutzerEditDispatch, $userAuth);

$router->match(['GET', 'POST'], '/benutzer/delete',     [UserController::class, 'destroy'], $userAuth);
$router->match(['GET', 'POST'], '/benutzer/delete.php', [UserController::class, 'destroy'], $userAuth);

$router->get('/benutzer/auditlog',     [UserController::class, 'auditlog'], $userAuth);
$router->get('/benutzer/auditlog.php', [UserController::class, 'auditlog'], $userAuth);

$router->match(['GET', 'POST'], '/benutzer/registration-codes',     [UserController::class, 'registrationCodes'], $userAuth);
$router->match(['GET', 'POST'], '/benutzer/registration-codes.php', [UserController::class, 'registrationCodes'], $userAuth);

$router->match(['GET', 'POST'], '/benutzer/toggle-active',     [UserController::class, 'setActive'], $userAuth);
$router->match(['GET', 'POST'], '/benutzer/toggle-active.php', [UserController::class, 'setActive'], $userAuth);

// Rollen-Verwaltung
$router->get('/benutzer/rollen',           [RoleController::class, 'index'], $userAuth);
$router->get('/benutzer/rollen/',          [RoleController::class, 'index'], $userAuth);
$router->get('/benutzer/rollen/index',     [RoleController::class, 'index'], $userAuth);
$router->get('/benutzer/rollen/index.php', [RoleController::class, 'index'], $userAuth);

$router->post('/benutzer/rollen/create',     [RoleController::class, 'store'], $userAuth);
$router->post('/benutzer/rollen/create.php', [RoleController::class, 'store'], $userAuth);

$router->post('/benutzer/rollen/update',     [RoleController::class, 'update'], $userAuth);
$router->post('/benutzer/rollen/update.php', [RoleController::class, 'update'], $userAuth);

$router->post('/benutzer/rollen/delete',     [RoleController::class, 'destroy'], $userAuth);
$router->post('/benutzer/rollen/delete.php', [RoleController::class, 'destroy'], $userAuth);

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
$router->get('/antrag/select.php',  [AntragController::class, 'selectType'], $antragAuth);

$router->get('/antrag/create',      [AntragController::class, 'create'], $antragCreateAuth);
$router->get('/antrag/create.php',  [AntragController::class, 'create'], $antragCreateAuth);
$router->post('/antrag/create',     [AntragController::class, 'store'],  $antragCreateAuth);
$router->post('/antrag/create.php', [AntragController::class, 'store'],  $antragCreateAuth);

// view() prüft intern Gate::denies('antrag.view', $antrag) mit dem geladenen
// Model — deshalb nur AuthMiddleware hier, keine PolicyMiddleware.
$router->get('/antrag/view',        [AntragController::class, 'view'], $antragAuth);
$router->get('/antrag/view.php',    [AntragController::class, 'view'], $antragAuth);

$router->get('/antrag/admin/list',      [AntragController::class, 'adminList'], $antragListAuth);
$router->get('/antrag/admin/list.php',  [AntragController::class, 'adminList'], $antragListAuth);

$router->get('/antrag/admin/view',      [AntragController::class, 'adminView'], $antragDecideAuth);
$router->get('/antrag/admin/view.php',  [AntragController::class, 'adminView'], $antragDecideAuth);
$router->post('/antrag/admin/view',     [AntragController::class, 'decide'],    $antragDecideAuth);
$router->post('/antrag/admin/view.php', [AntragController::class, 'decide'],    $antragDecideAuth);

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
$router->post('/fahrtenbuch/actions.php', $fahrtPostDispatch);

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
$router->get('/mitarbeiter/list.php', [MitarbeiterController::class, 'index'], $mitarbeiterListAuth);

$router->get('/mitarbeiter/profile',     [MitarbeiterController::class, 'show'], $mitarbeiterViewAuth);
$router->get('/mitarbeiter/profile.php', [MitarbeiterController::class, 'show'], $mitarbeiterViewAuth);

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
$router->post('/mitarbeiter/profile.php', $mitarbeiterProfileDispatch, [new AuthMiddleware()]);

// store() ist ein AJAX-JSON-Endpoint (gibt JSON zurück, nicht Redirect)
$router->post('/mitarbeiter/create',     [MitarbeiterController::class, 'store'], $mitarbeiterCreateAuth);
$router->post('/mitarbeiter/create.php', [MitarbeiterController::class, 'store'], $mitarbeiterCreateAuth);

// destroy() läuft per GET (Legacy — könnte später auf DELETE umgestellt werden,
// aber im aktuellen UI wird das via Link getriggert)
$router->get('/mitarbeiter/delete',     [MitarbeiterController::class, 'destroy'], $mitarbeiterDeleteAuth);
$router->get('/mitarbeiter/delete.php', [MitarbeiterController::class, 'destroy'], $mitarbeiterDeleteAuth);

// Dokument-View (GET — zeigt Dokumenten-Details), dokument-delete (POST mit CSRF)
$router->get('/mitarbeiter/dokument-view',     [MitarbeiterController::class, 'showDocument'], [new AuthMiddleware()]);
$router->get('/mitarbeiter/dokument-view.php', [MitarbeiterController::class, 'showDocument'], [new AuthMiddleware()]);

$router->post('/mitarbeiter/dokument-delete',     [MitarbeiterController::class, 'deleteDocument'], $mitarbeiterDocsAuth);
$router->post('/mitarbeiter/dokument-delete.php', [MitarbeiterController::class, 'deleteDocument'], $mitarbeiterDocsAuth);

// Comment-Delete (GET, Legacy-Pfad — ein Link in der Detail-Liste)
$router->get('/mitarbeiter/comment-delete',     [MitarbeiterController::class, 'deleteComment'], $mitarbeiterCommentAuth);
$router->get('/mitarbeiter/comment-delete.php', [MitarbeiterController::class, 'deleteComment'], $mitarbeiterCommentAuth);

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
$router->get('/manv/index.php', [ManvController::class, 'index'], $manvAuth);

$router->get('/manv/board',     [ManvController::class, 'board'], $manvAuth);
$router->get('/manv/board.php', [ManvController::class, 'board'], $manvAuth);

$router->get('/manv/create',      [ManvController::class, 'create'], $manvAuth);
$router->get('/manv/create.php',  [ManvController::class, 'create'], $manvAuth);
$router->post('/manv/create',     [ManvController::class, 'store'],  $manvAuth);
$router->post('/manv/create.php', [ManvController::class, 'store'],  $manvAuth);

$router->get('/manv/edit',      [ManvController::class, 'edit'],   $manvAuth);
$router->get('/manv/edit.php',  [ManvController::class, 'edit'],   $manvAuth);
$router->post('/manv/edit',     [ManvController::class, 'update'], $manvAuth);
$router->post('/manv/edit.php', [ManvController::class, 'update'], $manvAuth);

$router->get('/manv/log',     [ManvController::class, 'log'], $manvAuth);
$router->get('/manv/log.php', [ManvController::class, 'log'], $manvAuth);

$router->get('/manv/patient-create',      [ManvController::class, 'patientCreate'], $manvAuth);
$router->get('/manv/patient-create.php',  [ManvController::class, 'patientCreate'], $manvAuth);
$router->post('/manv/patient-create',     [ManvController::class, 'patientStore'],  $manvAuth);
$router->post('/manv/patient-create.php', [ManvController::class, 'patientStore'],  $manvAuth);

$router->get('/manv/patient-view',      [ManvController::class, 'patientView'],   $manvAuth);
$router->get('/manv/patient-view.php',  [ManvController::class, 'patientView'],   $manvAuth);
$router->post('/manv/patient-view',     [ManvController::class, 'patientUpdate'], $manvAuth);
$router->post('/manv/patient-view.php', [ManvController::class, 'patientUpdate'], $manvAuth);

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
$router->get('/manv/ressourcen.php',  $manvRessourcenGet,  $manvAuth);
$router->post('/manv/ressourcen',     $manvRessourcenPost, $manvAuth);
$router->post('/manv/ressourcen.php', $manvRessourcenPost, $manvAuth);

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
$router->get('/einsatz/index.php', [EinsatzController::class, 'index'], $einsatzAuth);

$router->get('/einsatz/list',     [EinsatzController::class, 'list'], $einsatzAuth);
$router->get('/einsatz/list.php', [EinsatzController::class, 'list'], $einsatzAuth);

$router->get('/einsatz/view',     [EinsatzController::class, 'view'], $einsatzAuth);
$router->get('/einsatz/view.php', [EinsatzController::class, 'view'], $einsatzAuth);

$router->get('/einsatz/create',      [EinsatzController::class, 'createForm'], $einsatzAuth);
$router->get('/einsatz/create.php',  [EinsatzController::class, 'createForm'], $einsatzAuth);
$router->post('/einsatz/create',     [EinsatzController::class, 'store'],     $einsatzAuth);
$router->post('/einsatz/create.php', [EinsatzController::class, 'store'],     $einsatzAuth);

$router->get('/einsatz/login-fahrzeug',      [EinsatzController::class, 'loginForm'], $einsatzAuth);
$router->get('/einsatz/login-fahrzeug.php',  [EinsatzController::class, 'loginForm'], $einsatzAuth);
$router->post('/einsatz/login-fahrzeug',     [EinsatzController::class, 'login'],     $einsatzAuth);
$router->post('/einsatz/login-fahrzeug.php', [EinsatzController::class, 'login'],     $einsatzAuth);

$router->post('/einsatz/actions',     [EinsatzController::class, 'dispatchAction'], $einsatzAuth);
$router->post('/einsatz/actions.php', [EinsatzController::class, 'dispatchAction'], $einsatzAuth);

$router->get('/einsatz/asu',     [EinsatzController::class, 'asuForm'], $einsatzAuth);
$router->get('/einsatz/asu.php', [EinsatzController::class, 'asuForm'], $einsatzAuth);

$router->get('/einsatz/fahrtenbuch',     [EinsatzController::class, 'fireTabFahrtenbuch'], $einsatzAuth);
$router->get('/einsatz/fahrtenbuch.php', [EinsatzController::class, 'fireTabFahrtenbuch'], $einsatzAuth);

$router->get('/einsatz/statusmeldungen',     [EinsatzController::class, 'statusmeldungen'], $einsatzAuth);
$router->get('/einsatz/statusmeldungen.php', [EinsatzController::class, 'statusmeldungen'], $einsatzAuth);

$router->get('/einsatz/admin/list',     [EinsatzController::class, 'adminList'], $einsatzAdminAuth);
$router->get('/einsatz/admin/list.php', [EinsatzController::class, 'adminList'], $einsatzAdminAuth);

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
$router->get('/settings/antrag/list.php',   [\App\Http\Controllers\Settings\AntragSettingsController::class, 'listAction'],  $settingsAuth);
$router->get('/settings/antrag/create',     [\App\Http\Controllers\Settings\AntragSettingsController::class, 'createForm'], $settingsAuth);
$router->get('/settings/antrag/create.php', [\App\Http\Controllers\Settings\AntragSettingsController::class, 'createForm'], $settingsAuth);
$router->post('/settings/antrag/edit',      [\App\Http\Controllers\Settings\AntragSettingsController::class, 'edit'],       $settingsAuth);
$router->post('/settings/antrag/edit.php',  [\App\Http\Controllers\Settings\AntragSettingsController::class, 'edit'],       $settingsAuth);

// Dashboard-Settings
$router->get('/settings/dashboard/index',      [\App\Http\Controllers\Settings\DashboardController::class, 'index'], $settingsAuth);
$router->get('/settings/dashboard/index.php',  [\App\Http\Controllers\Settings\DashboardController::class, 'index'], $settingsAuth);
$router->post('/settings/dashboard/categories/create',     [\App\Http\Controllers\Settings\DashboardController::class, 'categoryStore'],   $settingsAuth);
$router->post('/settings/dashboard/categories/create.php', [\App\Http\Controllers\Settings\DashboardController::class, 'categoryStore'],   $settingsAuth);
$router->post('/settings/dashboard/categories/update',     [\App\Http\Controllers\Settings\DashboardController::class, 'categoryUpdate'],  $settingsAuth);
$router->post('/settings/dashboard/categories/update.php', [\App\Http\Controllers\Settings\DashboardController::class, 'categoryUpdate'],  $settingsAuth);
$router->post('/settings/dashboard/categories/delete',     [\App\Http\Controllers\Settings\DashboardController::class, 'categoryDestroy'], $settingsAuth);
$router->post('/settings/dashboard/categories/delete.php', [\App\Http\Controllers\Settings\DashboardController::class, 'categoryDestroy'], $settingsAuth);
$router->post('/settings/dashboard/tiles/create',     [\App\Http\Controllers\Settings\DashboardController::class, 'tileStore'],   $settingsAuth);
$router->post('/settings/dashboard/tiles/create.php', [\App\Http\Controllers\Settings\DashboardController::class, 'tileStore'],   $settingsAuth);
$router->post('/settings/dashboard/tiles/update',     [\App\Http\Controllers\Settings\DashboardController::class, 'tileUpdate'],  $settingsAuth);
$router->post('/settings/dashboard/tiles/update.php', [\App\Http\Controllers\Settings\DashboardController::class, 'tileUpdate'],  $settingsAuth);
$router->post('/settings/dashboard/tiles/delete',     [\App\Http\Controllers\Settings\DashboardController::class, 'tileDestroy'], $settingsAuth);
$router->post('/settings/dashboard/tiles/delete.php', [\App\Http\Controllers\Settings\DashboardController::class, 'tileDestroy'], $settingsAuth);

// Documents-Settings
$router->get('/settings/documents/categories',        [\App\Http\Controllers\Settings\DocumentController::class, 'categories'],   $settingsAuth);
$router->get('/settings/documents/categories.php',    [\App\Http\Controllers\Settings\DocumentController::class, 'categories'],   $settingsAuth);
$router->get('/settings/documents/templates',         [\App\Http\Controllers\Settings\DocumentController::class, 'templates'],    $settingsAuth);
$router->get('/settings/documents/templates.php',     [\App\Http\Controllers\Settings\DocumentController::class, 'templates'],    $settingsAuth);
$router->get('/settings/documents/visual-editor',     [\App\Http\Controllers\Settings\DocumentController::class, 'visualEditor'], $settingsAuth);
$router->get('/settings/documents/visual-editor.php', [\App\Http\Controllers\Settings\DocumentController::class, 'visualEditor'], $settingsAuth);

// eNOTF-Settings (Schnellzugriff + Kategorien)
$router->get('/settings/enotf/index',     [\App\Http\Controllers\Settings\EnotfController::class, 'index'],   $settingsAuth);
$router->get('/settings/enotf/index.php', [\App\Http\Controllers\Settings\EnotfController::class, 'index'],   $settingsAuth);
$router->post('/settings/enotf/create',     [\App\Http\Controllers\Settings\EnotfController::class, 'store'],   $settingsAuth);
$router->post('/settings/enotf/create.php', [\App\Http\Controllers\Settings\EnotfController::class, 'store'],   $settingsAuth);
$router->post('/settings/enotf/update',     [\App\Http\Controllers\Settings\EnotfController::class, 'update'],  $settingsAuth);
$router->post('/settings/enotf/update.php', [\App\Http\Controllers\Settings\EnotfController::class, 'update'],  $settingsAuth);
$router->post('/settings/enotf/delete',     [\App\Http\Controllers\Settings\EnotfController::class, 'destroy'], $settingsAuth);
$router->post('/settings/enotf/delete.php', [\App\Http\Controllers\Settings\EnotfController::class, 'destroy'], $settingsAuth);
$router->get('/settings/enotf/kategorien/index',     [\App\Http\Controllers\Settings\EnotfController::class, 'categoriesIndex'],  $settingsAuth);
$router->get('/settings/enotf/kategorien/index.php', [\App\Http\Controllers\Settings\EnotfController::class, 'categoriesIndex'],  $settingsAuth);
$router->post('/settings/enotf/kategorien/create',     [\App\Http\Controllers\Settings\EnotfController::class, 'categoryStore'],   $settingsAuth);
$router->post('/settings/enotf/kategorien/create.php', [\App\Http\Controllers\Settings\EnotfController::class, 'categoryStore'],   $settingsAuth);
$router->post('/settings/enotf/kategorien/update',     [\App\Http\Controllers\Settings\EnotfController::class, 'categoryUpdate'],  $settingsAuth);
$router->post('/settings/enotf/kategorien/update.php', [\App\Http\Controllers\Settings\EnotfController::class, 'categoryUpdate'],  $settingsAuth);
$router->post('/settings/enotf/kategorien/delete',     [\App\Http\Controllers\Settings\EnotfController::class, 'categoryDestroy'], $settingsAuth);
$router->post('/settings/enotf/kategorien/delete.php', [\App\Http\Controllers\Settings\EnotfController::class, 'categoryDestroy'], $settingsAuth);

// Fahrzeuge-Settings (Fahrzeuge + Beladelisten + Defekte)
$router->get('/settings/fahrzeuge/fahrzeuge/index',     [\App\Http\Controllers\Settings\FahrzeugeController::class, 'index'],   $settingsAuth);
$router->get('/settings/fahrzeuge/fahrzeuge/index.php', [\App\Http\Controllers\Settings\FahrzeugeController::class, 'index'],   $settingsAuth);
$router->post('/settings/fahrzeuge/fahrzeuge/create',     [\App\Http\Controllers\Settings\FahrzeugeController::class, 'store'],   $settingsAuth);
$router->post('/settings/fahrzeuge/fahrzeuge/create.php', [\App\Http\Controllers\Settings\FahrzeugeController::class, 'store'],   $settingsAuth);
$router->post('/settings/fahrzeuge/fahrzeuge/update',     [\App\Http\Controllers\Settings\FahrzeugeController::class, 'update'],  $settingsAuth);
$router->post('/settings/fahrzeuge/fahrzeuge/update.php', [\App\Http\Controllers\Settings\FahrzeugeController::class, 'update'],  $settingsAuth);
$router->post('/settings/fahrzeuge/fahrzeuge/delete',     [\App\Http\Controllers\Settings\FahrzeugeController::class, 'destroy'], $settingsAuth);
$router->post('/settings/fahrzeuge/fahrzeuge/delete.php', [\App\Http\Controllers\Settings\FahrzeugeController::class, 'destroy'], $settingsAuth);
$router->get('/settings/fahrzeuge/beladelisten/index',     [\App\Http\Controllers\Settings\FahrzeugeController::class, 'beladelistenIndex'], $settingsAuth);
$router->get('/settings/fahrzeuge/beladelisten/index.php', [\App\Http\Controllers\Settings\FahrzeugeController::class, 'beladelistenIndex'], $settingsAuth);
$router->post('/settings/fahrzeuge/beladelisten/beladung_handler',     [\App\Http\Controllers\Settings\FahrzeugeController::class, 'beladungHandler'], $settingsAuth);
$router->post('/settings/fahrzeuge/beladelisten/beladung_handler.php', [\App\Http\Controllers\Settings\FahrzeugeController::class, 'beladungHandler'], $settingsAuth);
$router->get('/settings/fahrzeuge/defekte/index',     [\App\Http\Controllers\Settings\FahrzeugeController::class, 'defekteIndex'], $settingsAuth);
$router->get('/settings/fahrzeuge/defekte/index.php', [\App\Http\Controllers\Settings\FahrzeugeController::class, 'defekteIndex'], $settingsAuth);

// Federation-Settings
$router->get('/settings/federation/index',     [\App\Http\Controllers\Settings\FederationController::class, 'index'], $settingsAuth);
$router->get('/settings/federation/index.php', [\App\Http\Controllers\Settings\FederationController::class, 'index'], $settingsAuth);

// Medikamente-Settings
$router->get('/settings/medikamente/index',     [\App\Http\Controllers\Settings\MedikamenteController::class, 'index'],   $settingsAuth);
$router->get('/settings/medikamente/index.php', [\App\Http\Controllers\Settings\MedikamenteController::class, 'index'],   $settingsAuth);
$router->post('/settings/medikamente/create',     [\App\Http\Controllers\Settings\MedikamenteController::class, 'store'],   $settingsAuth);
$router->post('/settings/medikamente/create.php', [\App\Http\Controllers\Settings\MedikamenteController::class, 'store'],   $settingsAuth);
$router->post('/settings/medikamente/update',     [\App\Http\Controllers\Settings\MedikamenteController::class, 'update'],  $settingsAuth);
$router->post('/settings/medikamente/update.php', [\App\Http\Controllers\Settings\MedikamenteController::class, 'update'],  $settingsAuth);
$router->post('/settings/medikamente/delete',     [\App\Http\Controllers\Settings\MedikamenteController::class, 'destroy'], $settingsAuth);
$router->post('/settings/medikamente/delete.php', [\App\Http\Controllers\Settings\MedikamenteController::class, 'destroy'], $settingsAuth);

// Personal-Settings (Dienstgrade + 3x Qualifikationen)
$router->get('/settings/personal/dienstgrade/index',     [\App\Http\Controllers\Settings\PersonalController::class, 'dienstgradeIndex'], $settingsAuth);
$router->get('/settings/personal/dienstgrade/index.php', [\App\Http\Controllers\Settings\PersonalController::class, 'dienstgradeIndex'], $settingsAuth);
$router->post('/settings/personal/dienstgrade/create',     [\App\Http\Controllers\Settings\PersonalController::class, 'dienstgradStore'],  $settingsAuth);
$router->post('/settings/personal/dienstgrade/create.php', [\App\Http\Controllers\Settings\PersonalController::class, 'dienstgradStore'],  $settingsAuth);
$router->post('/settings/personal/dienstgrade/update',     [\App\Http\Controllers\Settings\PersonalController::class, 'dienstgradUpdate'], $settingsAuth);
$router->post('/settings/personal/dienstgrade/update.php', [\App\Http\Controllers\Settings\PersonalController::class, 'dienstgradUpdate'], $settingsAuth);
$router->post('/settings/personal/dienstgrade/delete',     [\App\Http\Controllers\Settings\PersonalController::class, 'dienstgradDelete'], $settingsAuth);
$router->post('/settings/personal/dienstgrade/delete.php', [\App\Http\Controllers\Settings\PersonalController::class, 'dienstgradDelete'], $settingsAuth);

$router->get('/settings/personal/qualifw/index',     [\App\Http\Controllers\Settings\PersonalController::class, 'fwQualiIndex'], $settingsAuth);
$router->get('/settings/personal/qualifw/index.php', [\App\Http\Controllers\Settings\PersonalController::class, 'fwQualiIndex'], $settingsAuth);
$router->post('/settings/personal/qualifw/create',     [\App\Http\Controllers\Settings\PersonalController::class, 'fwQualiStore'],  $settingsAuth);
$router->post('/settings/personal/qualifw/create.php', [\App\Http\Controllers\Settings\PersonalController::class, 'fwQualiStore'],  $settingsAuth);
$router->post('/settings/personal/qualifw/update',     [\App\Http\Controllers\Settings\PersonalController::class, 'fwQualiUpdate'], $settingsAuth);
$router->post('/settings/personal/qualifw/update.php', [\App\Http\Controllers\Settings\PersonalController::class, 'fwQualiUpdate'], $settingsAuth);
$router->post('/settings/personal/qualifw/delete',     [\App\Http\Controllers\Settings\PersonalController::class, 'fwQualiDelete'], $settingsAuth);
$router->post('/settings/personal/qualifw/delete.php', [\App\Http\Controllers\Settings\PersonalController::class, 'fwQualiDelete'], $settingsAuth);

$router->get('/settings/personal/qualird/index',     [\App\Http\Controllers\Settings\PersonalController::class, 'rdQualiIndex'], $settingsAuth);
$router->get('/settings/personal/qualird/index.php', [\App\Http\Controllers\Settings\PersonalController::class, 'rdQualiIndex'], $settingsAuth);
$router->post('/settings/personal/qualird/create',     [\App\Http\Controllers\Settings\PersonalController::class, 'rdQualiStore'],  $settingsAuth);
$router->post('/settings/personal/qualird/create.php', [\App\Http\Controllers\Settings\PersonalController::class, 'rdQualiStore'],  $settingsAuth);
$router->post('/settings/personal/qualird/update',     [\App\Http\Controllers\Settings\PersonalController::class, 'rdQualiUpdate'], $settingsAuth);
$router->post('/settings/personal/qualird/update.php', [\App\Http\Controllers\Settings\PersonalController::class, 'rdQualiUpdate'], $settingsAuth);
$router->post('/settings/personal/qualird/delete',     [\App\Http\Controllers\Settings\PersonalController::class, 'rdQualiDelete'], $settingsAuth);
$router->post('/settings/personal/qualird/delete.php', [\App\Http\Controllers\Settings\PersonalController::class, 'rdQualiDelete'], $settingsAuth);

$router->get('/settings/personal/qualifd/index',     [\App\Http\Controllers\Settings\PersonalController::class, 'fdQualiIndex'], $settingsAuth);
$router->get('/settings/personal/qualifd/index.php', [\App\Http\Controllers\Settings\PersonalController::class, 'fdQualiIndex'], $settingsAuth);
$router->post('/settings/personal/qualifd/create',     [\App\Http\Controllers\Settings\PersonalController::class, 'fdQualiStore'],  $settingsAuth);
$router->post('/settings/personal/qualifd/create.php', [\App\Http\Controllers\Settings\PersonalController::class, 'fdQualiStore'],  $settingsAuth);
$router->post('/settings/personal/qualifd/update',     [\App\Http\Controllers\Settings\PersonalController::class, 'fdQualiUpdate'], $settingsAuth);
$router->post('/settings/personal/qualifd/update.php', [\App\Http\Controllers\Settings\PersonalController::class, 'fdQualiUpdate'], $settingsAuth);
$router->post('/settings/personal/qualifd/delete',     [\App\Http\Controllers\Settings\PersonalController::class, 'fdQualiDelete'], $settingsAuth);
$router->post('/settings/personal/qualifd/delete.php', [\App\Http\Controllers\Settings\PersonalController::class, 'fdQualiDelete'], $settingsAuth);

// POI-Settings
$router->get('/settings/pois/index',     [\App\Http\Controllers\Settings\PoiController::class, 'index'],   $settingsAuth);
$router->get('/settings/pois/index.php', [\App\Http\Controllers\Settings\PoiController::class, 'index'],   $settingsAuth);
$router->post('/settings/pois/create',     [\App\Http\Controllers\Settings\PoiController::class, 'store'],   $settingsAuth);
$router->post('/settings/pois/create.php', [\App\Http\Controllers\Settings\PoiController::class, 'store'],   $settingsAuth);
$router->post('/settings/pois/update',     [\App\Http\Controllers\Settings\PoiController::class, 'update'],  $settingsAuth);
$router->post('/settings/pois/update.php', [\App\Http\Controllers\Settings\PoiController::class, 'update'],  $settingsAuth);
$router->post('/settings/pois/delete',     [\App\Http\Controllers\Settings\PoiController::class, 'destroy'], $settingsAuth);
$router->post('/settings/pois/delete.php', [\App\Http\Controllers\Settings\PoiController::class, 'destroy'], $settingsAuth);
$router->get('/settings/pois/access-codes',     [\App\Http\Controllers\Settings\PoiController::class, 'accessCodes'], $settingsAuth);
$router->get('/settings/pois/access-codes.php', [\App\Http\Controllers\Settings\PoiController::class, 'accessCodes'], $settingsAuth);
$router->get('/settings/pois/departments',     [\App\Http\Controllers\Settings\PoiController::class, 'departmentsIndex'], $settingsAuth);
$router->get('/settings/pois/departments.php', [\App\Http\Controllers\Settings\PoiController::class, 'departmentsIndex'], $settingsAuth);
$router->post('/settings/pois/departments-create',     [\App\Http\Controllers\Settings\PoiController::class, 'departmentStore'],   $settingsAuth);
$router->post('/settings/pois/departments-create.php', [\App\Http\Controllers\Settings\PoiController::class, 'departmentStore'],   $settingsAuth);
$router->post('/settings/pois/departments-update',     [\App\Http\Controllers\Settings\PoiController::class, 'departmentUpdate'],  $settingsAuth);
$router->post('/settings/pois/departments-update.php', [\App\Http\Controllers\Settings\PoiController::class, 'departmentUpdate'],  $settingsAuth);
$router->post('/settings/pois/departments-delete',     [\App\Http\Controllers\Settings\PoiController::class, 'departmentDestroy'], $settingsAuth);
$router->post('/settings/pois/departments-delete.php', [\App\Http\Controllers\Settings\PoiController::class, 'departmentDestroy'], $settingsAuth);
$router->post('/settings/pois/departments-reset-availability',     [\App\Http\Controllers\Settings\PoiController::class, 'departmentResetAvailability'], $settingsAuth);
$router->post('/settings/pois/departments-reset-availability.php', [\App\Http\Controllers\Settings\PoiController::class, 'departmentResetAvailability'], $settingsAuth);

// System-Settings
$router->get('/settings/system/index',       [\App\Http\Controllers\Settings\SystemController::class, 'index'],       $settingsAuth);
$router->get('/settings/system/index.php',   [\App\Http\Controllers\Settings\SystemController::class, 'index'],       $settingsAuth);
$router->get('/settings/system/config',      [\App\Http\Controllers\Settings\SystemController::class, 'config'],      $settingsAuth);
$router->get('/settings/system/config.php',  [\App\Http\Controllers\Settings\SystemController::class, 'config'],      $settingsAuth);
$router->get('/settings/system/performance', [\App\Http\Controllers\Settings\SystemController::class, 'performance'], $settingsAuth);
$router->get('/settings/system/performance.php', [\App\Http\Controllers\Settings\SystemController::class, 'performance'], $settingsAuth);
$router->get('/settings/system/telemetry',     [\App\Http\Controllers\Settings\SystemController::class, 'telemetry'],   $settingsAuth);
$router->get('/settings/system/telemetry.php', [\App\Http\Controllers\Settings\SystemController::class, 'telemetry'],   $settingsAuth);
$router->get('/settings/system/logs',     [\App\Http\Controllers\Settings\LogsController::class, 'index'], $settingsAuth);
$router->get('/settings/system/logs.php', [\App\Http\Controllers\Settings\LogsController::class, 'index'], $settingsAuth);

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
//  eNOTF-Modul — Sub-Welle 9a: Root-Pages + Login-Flow
//
//  eNOTF läuft im FiveM-CEF-Browser (iframe) genauso wie einsatz/ — deshalb
//  FiveMCspMiddleware an allen Routen. AuthMiddleware wird über das Config-
//  Flag ENOTF_REQUIRE_USER_AUTH gesteuert (Controller-Doku).
//
//  Drei Middleware-Gruppen:
//    • Public          — keine Auth (nur CSP/iframe-Support)
//    • Entry / Login   — optionale User-Auth, KEIN PIN-Lockscreen (sonst
//                        Loop auf lockscreen.php selbst)
//    • Crew-protected  — User-Auth + PIN-Lockscreen + CSP (volle Pipeline)
//
//  Der Controller ruft intern FiveMSupport::prepareCookiesAndHeaders() —
//  iframe-Cookie-Handling bleibt unabhängig davon durch den SessionManager
//  abgedeckt (erkennt `/enotf/` in REQUEST_URI).
// ----------------------------------------------------------------------------

$enotfPublic     = [FiveMCspMiddleware::class];
$enotfEntry      = [new AuthMiddleware('ENOTF_REQUIRE_USER_AUTH'), FiveMCspMiddleware::class];
$enotfCrew       = [new AuthMiddleware('ENOTF_REQUIRE_USER_AUTH'), PinLockscreenMiddleware::class, FiveMCspMiddleware::class];

$router->get('/enotf/',          [EnotfController::class, 'index'], $enotfCrew);
$router->get('/enotf/index',     [EnotfController::class, 'index'], $enotfCrew);
$router->get('/enotf/index.php', [EnotfController::class, 'index'], $enotfCrew);

// Login-Flow: KEIN PIN-Middleware (wäre Loop)
$router->get('/enotf/login',      [EnotfController::class, 'loginForm'], $enotfEntry);
$router->get('/enotf/login.php',  [EnotfController::class, 'loginForm'], $enotfEntry);
$router->post('/enotf/login',     [EnotfController::class, 'login'],     $enotfEntry);
$router->post('/enotf/login.php', [EnotfController::class, 'login'],     $enotfEntry);

// Logout (Legacy: DB-Write auf GET via `?mode=self|all`)
$router->get('/enotf/loggedout',     [EnotfController::class, 'logout'], $enotfEntry);
$router->get('/enotf/loggedout.php', [EnotfController::class, 'logout'], $enotfEntry);

// Lockscreen selbst darf NICHT durch PinLockscreenMiddleware — Redirect-Loop
$router->match(['GET', 'POST'], '/enotf/lockscreen',     [EnotfController::class, 'lockscreen'], $enotfEntry);
$router->match(['GET', 'POST'], '/enotf/lockscreen.php', [EnotfController::class, 'lockscreen'], $enotfEntry);

// Crew-protected (brauchen aktive Crew-Session + PIN-Lockscreen)
$router->match(['GET', 'POST'], '/enotf/overview',     [EnotfController::class, 'overview'], $enotfCrew);
$router->match(['GET', 'POST'], '/enotf/overview.php', [EnotfController::class, 'overview'], $enotfCrew);

$router->get('/enotf/create',     [EnotfController::class, 'createForm'], $enotfCrew);
$router->get('/enotf/create.php', [EnotfController::class, 'createForm'], $enotfCrew);

$router->get('/enotf/fahrzeuginfo',     [EnotfController::class, 'fahrzeuginfo'], $enotfCrew);
$router->get('/enotf/fahrzeuginfo.php', [EnotfController::class, 'fahrzeuginfo'], $enotfCrew);

$router->get('/enotf/fahrtenbuch',     [EnotfController::class, 'fahrtenbuch'], $enotfCrew);
$router->get('/enotf/fahrtenbuch.php', [EnotfController::class, 'fahrtenbuch'], $enotfCrew);

// hospital-availability ist public (kein Login, kein PIN)
$router->get('/enotf/hospital-availability',     [EnotfController::class, 'hospitalAvailability'], $enotfPublic);
$router->get('/enotf/hospital-availability.php', [EnotfController::class, 'hospitalAvailability'], $enotfPublic);

// ----------------------------------------------------------------------------
//  eNOTF-Modul — Sub-Welle 9b: Admin
//
//  EnotfAdminController macht Auth + Permissions intern (requireAuth +
//  Permissions::check(['admin','edivi.view'|'edivi.edit'])) — Routes
//  brauchen nur AuthMiddleware. Admin-Seiten laufen ausschließlich im
//  Back-Office, nicht im FiveM-CEF → keine FiveMCspMiddleware nötig.
//
//  admin/zielverwaltung/ ist noch NICHT migriert (die 4 Files enthalten
//  reale Logik, kein Controller vorhanden). Separate Sub-Welle.
// ----------------------------------------------------------------------------

$enotfAdminAuth = [new AuthMiddleware()];

$router->get('/enotf/admin',          [\App\Http\Controllers\EnotfAdminController::class, 'listAction'], $enotfAdminAuth);
$router->get('/enotf/admin/',         [\App\Http\Controllers\EnotfAdminController::class, 'listAction'], $enotfAdminAuth);
$router->get('/enotf/admin/list',     [\App\Http\Controllers\EnotfAdminController::class, 'listAction'], $enotfAdminAuth);
$router->get('/enotf/admin/list.php', [\App\Http\Controllers\EnotfAdminController::class, 'listAction'], $enotfAdminAuth);

$router->get('/enotf/admin/delete',     [\App\Http\Controllers\EnotfAdminController::class, 'destroy'], $enotfAdminAuth);
$router->get('/enotf/admin/delete.php', [\App\Http\Controllers\EnotfAdminController::class, 'destroy'], $enotfAdminAuth);

$router->get('/enotf/admin/qm-actions-modal',     [\App\Http\Controllers\EnotfAdminController::class, 'qmActionsModal'], $enotfAdminAuth);
$router->get('/enotf/admin/qm-actions-modal.php', [\App\Http\Controllers\EnotfAdminController::class, 'qmActionsModal'], $enotfAdminAuth);

$router->get('/enotf/admin/qm-log-modal',     [\App\Http\Controllers\EnotfAdminController::class, 'qmLogModal'], $enotfAdminAuth);
$router->get('/enotf/admin/qm-log-modal.php', [\App\Http\Controllers\EnotfAdminController::class, 'qmLogModal'], $enotfAdminAuth);

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

// Zielverwaltung (Krankenhaus-Ziele) — Controller + Template neu gebaut,
// Thin-Stubs ersetzen die alten Standalone-PHP-Files aus `enotf/admin/
// zielverwaltung/`.
$router->get('/enotf/admin/zielverwaltung',           [\App\Http\Controllers\EnotfZielverwaltungController::class, 'index'], $enotfAdminAuth);
$router->get('/enotf/admin/zielverwaltung/',          [\App\Http\Controllers\EnotfZielverwaltungController::class, 'index'], $enotfAdminAuth);
$router->get('/enotf/admin/zielverwaltung/index.php', [\App\Http\Controllers\EnotfZielverwaltungController::class, 'index'], $enotfAdminAuth);

$router->post('/enotf/admin/zielverwaltung/create',     [\App\Http\Controllers\EnotfZielverwaltungController::class, 'store'], $enotfAdminAuth);
$router->post('/enotf/admin/zielverwaltung/create.php', [\App\Http\Controllers\EnotfZielverwaltungController::class, 'store'], $enotfAdminAuth);

$router->post('/enotf/admin/zielverwaltung/update',     [\App\Http\Controllers\EnotfZielverwaltungController::class, 'update'], $enotfAdminAuth);
$router->post('/enotf/admin/zielverwaltung/update.php', [\App\Http\Controllers\EnotfZielverwaltungController::class, 'update'], $enotfAdminAuth);

$router->post('/enotf/admin/zielverwaltung/delete',     [\App\Http\Controllers\EnotfZielverwaltungController::class, 'destroy'], $enotfAdminAuth);
$router->post('/enotf/admin/zielverwaltung/delete.php', [\App\Http\Controllers\EnotfZielverwaltungController::class, 'destroy'], $enotfAdminAuth);

// ----------------------------------------------------------------------------
//  eNOTF-Modul — Sub-Welle 9d: Print + Schnittstelle
//
//  Print:        crew-facing, Controller enforced PIN intern — Routes mit
//                FiveMCspMiddleware + PinLockscreenMiddleware (belt-and-
//                suspenders, die Middleware-Policy ist identisch zum
//                internen Controller-Check).
//  Schnittstelle: public (Klinik-Access ohne User-Login möglich). Routes
//                ohne Auth, nur FiveMCspMiddleware. Voranmeldung prüft PIN
//                intern je nach Config.
// ----------------------------------------------------------------------------

// Print (Legacy `.htaccess`-Rewrite: /enotf/print/{enr} → index.php?enr=$1)
$router->get('/enotf/print',            [\App\Http\Controllers\EnotfPrintController::class, 'show'], $enotfCrew);
$router->get('/enotf/print/',           [\App\Http\Controllers\EnotfPrintController::class, 'show'], $enotfCrew);
$router->get('/enotf/print/index',      [\App\Http\Controllers\EnotfPrintController::class, 'show'], $enotfCrew);
$router->get('/enotf/print/index.php',  [\App\Http\Controllers\EnotfPrintController::class, 'show'], $enotfCrew);
// Clean-URL: /enotf/print/{enr} → show() liest ?enr= aus Query; Router
// muss den Parameter als Query-String durchreichen
$router->get('/enotf/print/{enr:[\w._-]+}', function (\App\Http\Request $request, string $enr) {
    $_GET['enr'] = $enr;
    app(\App\Http\Controllers\EnotfPrintController::class)->show();
    return \App\Http\Response::empty();
}, $enotfCrew);

// Schnittstelle — public
$router->get('/enotf/schnittstelle',           [\App\Http\Controllers\EnotfSchnittstelleController::class, 'index'], $enotfPublic);
$router->get('/enotf/schnittstelle/',          [\App\Http\Controllers\EnotfSchnittstelleController::class, 'index'], $enotfPublic);
$router->get('/enotf/schnittstelle/index',     [\App\Http\Controllers\EnotfSchnittstelleController::class, 'index'], $enotfPublic);
$router->get('/enotf/schnittstelle/index.php', [\App\Http\Controllers\EnotfSchnittstelleController::class, 'index'], $enotfPublic);

$router->match(['GET', 'POST'], '/enotf/schnittstelle/klinikcode',     [\App\Http\Controllers\EnotfSchnittstelleController::class, 'klinikcode'], $enotfPublic);
$router->match(['GET', 'POST'], '/enotf/schnittstelle/klinikcode.php', [\App\Http\Controllers\EnotfSchnittstelleController::class, 'klinikcode'], $enotfPublic);

$router->match(['GET', 'POST'], '/enotf/schnittstelle/voranmeldung',     [\App\Http\Controllers\EnotfSchnittstelleController::class, 'voranmeldung'], $enotfPublic);
$router->match(['GET', 'POST'], '/enotf/schnittstelle/voranmeldung.php', [\App\Http\Controllers\EnotfSchnittstelleController::class, 'voranmeldung'], $enotfPublic);

$router->get('/enotf/schnittstelle/hospital-availability',     [\App\Http\Controllers\EnotfSchnittstelleController::class, 'hospitalAvailability'], $enotfPublic);
$router->get('/enotf/schnittstelle/hospital-availability.php', [\App\Http\Controllers\EnotfSchnittstelleController::class, 'hospitalAvailability'], $enotfPublic);

// api-prereg: 308 auf /api/enotf/prereg
$router->match(['GET', 'POST'], '/enotf/schnittstelle/api-prereg.php', $enotfApiRedirect('/api/enotf/prereg'));

// ----------------------------------------------------------------------------
//  eNOTF-Modul — Sub-Welle 9c: Protokoll-Pages (121 Files)
//
//  EnotfProtokollController::serve(string $templatePath) rendert jede
//  Protokoll-Page. Der Template-Pfad entspricht der URL-Struktur 1:1
//  (z.B. URL `/enotf/protokoll/abschluss/3_1.php` → Template
//  `enotf/protokoll/abschluss/3_1`).
//
//  Zwei URL-Formen werden unterstützt:
//    1. Direct-Path (Legacy):  /enotf/protokoll/<section>/<page>.php?enr=X
//    2. Clean-URL (.htaccess):  /enotf/p/{enr}/<section>/<page>
//
//  Der Clean-URL-Handler bildet die .htaccess-Rewrites des Root-
//  htaccess nach: je nach Section sind 3 Segmente entweder
//  "{section}/{page}" (diagnose, anamnese, rettdaten, verlauf,
//  abschluss) oder "{section}/{subsection}/index" (erstbefund,
//  massnahmen). Fallback ist dateisystem-basiert — wenn die
//  index-Variante nicht existiert, wird als Leaf-Page aufgelöst.
// ----------------------------------------------------------------------------

// Helper: löst Path-Segmente auf den Template-Pfad auf (mit FS-Fallback
// für die 3-Segment-Ambiguität)
$protokollResolveTemplate = static function (?string $section, ?string $subsection, ?string $page): string {
    $projectRoot = dirname(__DIR__);
    $base        = 'enotf/protokoll';

    if ($section === null) {
        return $base . '/index';
    }
    if ($subsection === null) {
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
