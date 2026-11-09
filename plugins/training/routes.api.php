<?php

declare(strict_types=1);

/**
 * Ausbildung: API-Routen (Session).
 *
 * @var \EmergencyForge\Http\Router $router
 */

use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\PolicyMiddleware;
use EmergencyForge\Http\Middleware\JsonExceptionMiddleware;
use Plugin\Training\Controllers\ApiController;
use Plugin\Training\Controllers\CurriculumController;

$router->post('/api/training/units/{unit:\d+}/pages', [ApiController::class, 'pages'], [JsonExceptionMiddleware::class, new AuthMiddleware(), new PolicyMiddleware('training.view')]);
$router->post('/api/training/images', [CurriculumController::class, 'uploadImage'], [JsonExceptionMiddleware::class, new AuthMiddleware(), new PermissionMiddleware(['admin', 'training.teach', 'training.manage'])]);
