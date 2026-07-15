<?php

declare(strict_types=1);

/**
 * fireTab — API-Routen.
 *
 * `/api/emd/status-poll` ist der FiveM-Server-Endpoint (ApiKey-Auth, kein
 * Session-Kontext) zum Abholen der Fire-Status-Queue. Die `/api/fire/...`-
 * Routen sind session-basiert und bedienen das fireTab-Frontend.
 *
 * @var \App\Http\Router $router
 */

use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\JsonExceptionMiddleware;
use App\Http\Middleware\PermissionMiddleware;
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
