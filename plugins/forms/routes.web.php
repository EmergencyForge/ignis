<?php

declare(strict_types=1);

/**
 * Anträge: Web-Routen.
 *
 * Das Antragssystem (Urlaub, Beförderung, etc.) läuft über den
 * FormsController. Die einzelne Antrags-Ansicht prüft Ownership im
 * Controller, weil dort der Antrag erst geladen wird. Die Antragstypen
 * verwaltet der AntragSettingsController unter /settings/forms.
 *
 * @var \EmergencyForge\Http\Router $router
 */

use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\PolicyMiddleware;
use Plugin\Forms\Controllers\AntragSettingsController;
use Plugin\Forms\Controllers\FormsController;

$antragAuth       = [new AuthMiddleware()];
$antragCreateAuth = [new AuthMiddleware(), new PolicyMiddleware('forms.create')];
$antragDecideAuth = [new AuthMiddleware(), new PolicyMiddleware('forms.decide')];
$antragListAuth   = [new AuthMiddleware(), new PolicyMiddleware('forms.viewAny')];

$router->get('/forms/select',      [FormsController::class, 'selectType'], $antragAuth);

$router->get('/forms/create',      [FormsController::class, 'create'], $antragCreateAuth);
$router->post('/forms/create',     [FormsController::class, 'store'],  $antragCreateAuth);

// view() prüft intern Gate::denies('forms.view', $antrag) mit dem geladenen
// Model, deshalb nur AuthMiddleware hier, keine PolicyMiddleware.
$router->get('/forms/view',        [FormsController::class, 'view'], $antragAuth);

$router->get('/forms/admin/list',  [FormsController::class, 'adminList'], $antragListAuth);

$router->get('/forms/admin/view',  [FormsController::class, 'adminView'], $antragDecideAuth);
$router->post('/forms/admin/view', [FormsController::class, 'decide'],    $antragDecideAuth);

// Antragstypen. Der Controller prüft system.admin selbst.
$settingsAuth = [new AuthMiddleware()];

$router->get('/settings/forms/list',    [AntragSettingsController::class, 'listAction'], $settingsAuth);
$router->get('/settings/forms/create',  [AntragSettingsController::class, 'createForm'], $settingsAuth);
$router->post('/settings/forms/create', [AntragSettingsController::class, 'store'],      $settingsAuth);
$router->get('/settings/forms/edit',    [AntragSettingsController::class, 'edit'],       $settingsAuth);
$router->post('/settings/forms/edit',   [AntragSettingsController::class, 'edit'],       $settingsAuth);

// Umschalten, Löschen und die Reihenfolge sind POST: eine Zustandsänderung
// an einer GET-Adresse löst jeder Bildaufruf und jedes Vorabladen aus.
$router->post('/settings/forms/toggle',        [AntragSettingsController::class, 'toggle'],       $settingsAuth);
$router->post('/settings/forms/delete',        [AntragSettingsController::class, 'destroy'],      $settingsAuth);
$router->post('/settings/forms/sort',          [AntragSettingsController::class, 'sort'],         $settingsAuth);
$router->post('/settings/forms/fields/delete', [AntragSettingsController::class, 'destroyField'], $settingsAuth);
