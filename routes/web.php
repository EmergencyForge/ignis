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

use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\FiveMCspMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\PinLockscreenMiddleware;

// ----------------------------------------------------------------------------
//  Beispiel-Routen — werden in den Folge-Wellen durch echte Modul-Routen
//  ersetzt. Bleiben hier als lebende Referenz für das Routen-Format.
// ----------------------------------------------------------------------------

// Smoke-Test-Route — hilft beim Verifizieren, dass die Pipeline steht.
// Kein Auth erforderlich, damit sie auch ohne Login erreichbar ist.
$router->get('/_router/ping', function ($request) {
    return \App\Http\Response::json([
        'success' => true,
        'message' => 'pong',
        'time'    => date('c'),
    ]);
});

/*
 * BEISPIEL — Benutzer-Modul (Pilot, wird in Welle 3.1a aktiviert)
 *
 * $router->group('/users', [new AuthMiddleware(), 'App\\Http\\Middleware\\PermissionMiddleware:personnel.view'], function ($r) {
 *     $r->get('/',          [\App\Http\Controllers\UserController::class, 'index']);
 *     $r->get('/{id:\d+}',  [\App\Http\Controllers\UserController::class, 'show']);
 *     $r->post('/{id:\d+}', [\App\Http\Controllers\UserController::class, 'update'],
 *         [new PermissionMiddleware('personnel.edit')]);
 * });
 *
 * BEISPIEL — eNOTF-Protokoll (config-gated Auth + PIN-Lockscreen + FiveM-CSP)
 *
 * $router->group('/enotf', [
 *     new AuthMiddleware('ENOTF_REQUIRE_USER_AUTH'),   // nur wenn Flag=true
 *     PinLockscreenMiddleware::class,                  // eigener PIN-Gate
 *     FiveMCspMiddleware::class,                       // CSP für CitizenFX
 * ], function ($r) {
 *     $r->get('/protokoll/{enr}', [\App\Http\Controllers\EnotfProtokollController::class, 'index']);
 * });
 *
 * BEISPIEL — Wissensdatenbank (public, wenn KB_PUBLIC_ACCESS=true)
 *
 * $router->get('/wissensdb/{slug}',
 *     [\App\Http\Controllers\KnowledgebaseController::class, 'show'],
 *     [new AuthMiddleware('KB_PUBLIC_ACCESS', invert: true)]
 * );
 */
