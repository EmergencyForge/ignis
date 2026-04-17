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
use App\Http\Controllers\FahrtenbuchController;
use App\Http\Controllers\ManvController;
use App\Http\Controllers\MitarbeiterController;
use App\Http\Controllers\NotificationController;
use App\Http\Middleware\AuthMiddleware;
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
//  alle Templates noch auf `<?= BASE_PATH ?>manv/xxx.php` verlinken.
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
