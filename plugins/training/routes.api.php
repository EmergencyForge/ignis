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
use Plugin\Training\Controllers\AttemptController;
use Plugin\Training\Controllers\CurriculumController;

$router->post('/api/training/units/{unit:\d+}/pages', [ApiController::class, 'pages'], [JsonExceptionMiddleware::class, new AuthMiddleware(), new PolicyMiddleware('training.view')]);
$router->post('/api/training/modules/{id:\d+}/images', [CurriculumController::class, 'uploadImage'], [JsonExceptionMiddleware::class, new AuthMiddleware(), new PermissionMiddleware(['admin', 'training.teach', 'training.manage'])]);
$router->post('/api/training/attempts/{id:\d+}/answers', [AttemptController::class, 'answers'], [JsonExceptionMiddleware::class, new AuthMiddleware(), new PolicyMiddleware('training.view')]);
$router->post('/api/training/attempts/{id:\d+}/focus', [AttemptController::class, 'focus'], [JsonExceptionMiddleware::class, new AuthMiddleware(), new PolicyMiddleware('training.view')]);
