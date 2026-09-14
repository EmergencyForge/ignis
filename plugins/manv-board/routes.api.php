<?php

declare(strict_types=1);

/**
 * MANV-Board — API-Routen (Session-basiert).
 *
 * @var \EmergencyForge\Http\Router $router
 */

use App\Http\Middleware\AuthMiddleware;
use EmergencyForge\Http\Middleware\JsonExceptionMiddleware;
use Plugin\ManvBoard\Controllers\Api\MciController;

$mciAuth = [JsonExceptionMiddleware::class, new AuthMiddleware()];

$router->match(['GET', 'POST'], '/api/mci/api',     [MciController::class, 'handle'], $mciAuth);
$router->match(['GET', 'POST'], '/api/mci-api.php', [MciController::class, 'handle'], $mciAuth);
