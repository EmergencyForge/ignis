<?php

declare(strict_types=1);

/**
 * fireTab: API-Routen.
 *
 * `/api/emd/status-poll` ist der FiveM-Server-Endpoint (ApiKey-Auth, kein
 * Session-Kontext) zum Abholen der Fire-Status-Queue. Die `/api/fire/...`-
 * Routen sind session-basiert und bedienen das fireTab-Frontend.
 *
 * @var \EmergencyForge\Http\Router $router
 */

use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use EmergencyForge\Http\Middleware\JsonExceptionMiddleware;
use Plugin\Firetab\Controllers\Api\FireAsuController;
use Plugin\Firetab\Controllers\Api\FireController;
use Plugin\Firetab\Controllers\Api\FireLagekarteController;
use Plugin\Firetab\Controllers\Api\FireStatusPollController;

// FiveM-Server: Fire-Status-Queue pollen
$router->post('/api/emd/status-poll',
    [FireStatusPollController::class, 'poll'],
    [ApiKeyMiddleware::class]
);

$fireAuth   = [JsonExceptionMiddleware::class, new AuthMiddleware()];
$fireQmAuth = [JsonExceptionMiddleware::class, new AuthMiddleware(), new PermissionMiddleware(['admin', 'fire.incident.qm'])];

$router->match(['GET', 'POST'], '/api/fire/status',     [FireController::class, 'status'], $fireAuth);

$router->match(['GET', 'POST', 'DELETE'], '/api/fire/bulk-delete-empty',     [FireController::class, 'bulkDeleteEmpty'], $fireQmAuth);

$router->match(['GET', 'POST'], '/api/fire/lagekarte',     [FireLagekarteController::class, 'handle'], $fireAuth);

// Atemschutzüberwachung im Einsatz: Tablets ohne ignis-Konto sind erlaubt,
// solange FIRE_INCIDENT_REQUIRE_USER_AUTH aus ist; Fahrzeugzuordnung prüft der Controller.
$fireTabAuth = [JsonExceptionMiddleware::class, new AuthMiddleware('FIRE_INCIDENT_REQUIRE_USER_AUTH')];
$router->get('/api/fire/asu/state',          [FireAsuController::class, 'state'],       $fireTabAuth);
$router->post('/api/fire/asu/trupp',         [FireAsuController::class, 'saveTrupp'],   $fireTabAuth);
$router->post('/api/fire/asu/trupp/delete',  [FireAsuController::class, 'deleteTrupp'], $fireTabAuth);
$router->post('/api/fire/asu/event',         [FireAsuController::class, 'event'],       $fireTabAuth);
$router->post('/api/fire/asu/event/void',    [FireAsuController::class, 'voidEvent'],   $fireTabAuth);
