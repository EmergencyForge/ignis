<?php

declare(strict_types=1);

/**
 * MANV-Board — Web-Routen.
 *
 * MANV-Lagen (Massenanfall von Verletzten). Der MciController ruft intern
 * `ensure('mci.<ability>', redirectTo: 'index.php')` auf — das liefert
 * benutzerfreundliche Redirects statt 403. Deshalb hier nur AuthMiddleware,
 * keine PolicyMiddleware (analog zu FormsController::view).
 *
 * @var \App\Http\Router $router
 */

use App\Http\Middleware\AuthMiddleware;
use Plugin\ManvBoard\Controllers\MciController;

$manvAuth = [new AuthMiddleware()];

$router->get('/mci/',          [MciController::class, 'index'], $manvAuth);
$router->get('/mci/index',     [MciController::class, 'index'], $manvAuth);

$router->get('/mci/board',     [MciController::class, 'board'], $manvAuth);

$router->get('/mci/create',    [MciController::class, 'create'], $manvAuth);
$router->post('/mci/create',   [MciController::class, 'store'],  $manvAuth);

$router->get('/mci/edit',      [MciController::class, 'edit'],   $manvAuth);
$router->post('/mci/edit',     [MciController::class, 'update'], $manvAuth);

$router->get('/mci/log',       [MciController::class, 'log'], $manvAuth);

$router->get('/mci/patient-create',  [MciController::class, 'patientCreate'], $manvAuth);
$router->post('/mci/patient-create', [MciController::class, 'patientStore'],  $manvAuth);

$router->get('/mci/patient-view',    [MciController::class, 'patientView'],   $manvAuth);
$router->post('/mci/patient-view',   [MciController::class, 'patientUpdate'], $manvAuth);

// Ressourcen: kombinierter Endpoint.
//   GET  ?delete_id=Y   → ressourceDelete() (Legacy-GET-Delete, via showConfirm)
//   GET                 → ressourcen()      (View)
//   POST action=create  → ressourceStore()
//   POST action=edit    → ressourceUpdate()
$manvRessourcenGet = function (\App\Http\Request $request) {
    $controller = app(MciController::class);
    if (isset($request->query['delete_id'])) {
        $controller->ressourceDelete();
    } else {
        $controller->ressourcen();
    }
    return \App\Http\Response::empty();
};
$manvRessourcenPost = function (\App\Http\Request $request) {
    $controller = app(MciController::class);
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
$router->get('/mci/resources',  $manvRessourcenGet,  $manvAuth);
$router->post('/mci/resources', $manvRessourcenPost, $manvAuth);
