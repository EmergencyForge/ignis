<?php

declare(strict_types=1);

/**
 * Wissensdatenbank — API-Routen (Session-basiert).
 *
 * GET-Routen sind config-gated (KB_PUBLIC_ACCESS). Write-Operationen
 * (POST/DELETE categories, POST/DELETE tags) erfordern Session + kb.edit
 * und werden intern im Controller geprüft.
 *
 * @var \App\Http\Router $router
 */

use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\JsonExceptionMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use Plugin\KnowledgeBase\Controllers\Api\KnowledgebaseController;

$kbReadAuth  = [JsonExceptionMiddleware::class, new AuthMiddleware('KB_PUBLIC_ACCESS', invert: true)];
$kbWriteAuth = [JsonExceptionMiddleware::class, new AuthMiddleware(), new PermissionMiddleware(['admin', 'kb.edit'])];

// Categories
$router->get(   '/api/knowledgebase/categories', [KnowledgebaseController::class, 'listCategories'], $kbReadAuth);
$router->post(  '/api/knowledgebase/categories', [KnowledgebaseController::class, 'saveCategory'],   $kbWriteAuth);
$router->delete('/api/knowledgebase/categories', [KnowledgebaseController::class, 'deleteCategory'], $kbWriteAuth);

// Tags
$router->get(   '/api/knowledgebase/tags', [KnowledgebaseController::class, 'listTags'],  $kbReadAuth);
$router->post(  '/api/knowledgebase/tags', [KnowledgebaseController::class, 'saveTag'],   $kbWriteAuth);
$router->delete('/api/knowledgebase/tags', [KnowledgebaseController::class, 'deleteTag'], $kbWriteAuth);

// Search
$router->get('/api/knowledgebase/search', [KnowledgebaseController::class, 'search'], $kbReadAuth);
