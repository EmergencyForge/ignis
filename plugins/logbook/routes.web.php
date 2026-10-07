<?php

declare(strict_types=1);

/**
 * Fahrtenbuch: Web-Routen.
 *
 * `index()` ist Admin-only mit Policy-Middleware. `store/update/destroy`
 * werden über /logbook/actions angesprochen und sind multi-context
 * (Admin + eNOTF + FireTab). Der Controller prüft die Auth-Kontexte selbst
 * via `requireAnyContext()` / `Gate::denies`.
 *
 * @var \EmergencyForge\Http\Router $router
 */

use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\PolicyMiddleware;
use Plugin\Logbook\Controllers\LogbookController;

$fahrtListAuth = [new AuthMiddleware(), new PolicyMiddleware('logbook.viewList')];

$router->get('/logbook',           [LogbookController::class, 'index'], $fahrtListAuth);
$router->get('/logbook/',          [LogbookController::class, 'index'], $fahrtListAuth);
$router->get('/logbook/index',     [LogbookController::class, 'index'], $fahrtListAuth);
$router->get('/logbook/index.php', [LogbookController::class, 'index'], $fahrtListAuth);

// POST /logbook/actions: Multi-Context-Dispatcher.
// Keine Router-Middleware, weil die drei Auth-Kontexte (userid/fahrername/
// einsatz_vehicle_id) im Controller via `requireAnyContext()` geprüft werden.
$router->post('/logbook/actions', function (\EmergencyForge\Http\Request $request) {
    $controller = app(LogbookController::class);
    $action     = (string) ($request->post['action'] ?? '');

    match ($action) {
        'create' => $controller->store(),
        'update' => $controller->update(),
        'delete' => $controller->destroy(),
        default  => (function () {
            \App\Helpers\Flash::error('Unbekannte Aktion.');
            header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '/') . 'logbook/index');
            exit;
        })(),
    };
    return \EmergencyForge\Http\Response::empty();
});
