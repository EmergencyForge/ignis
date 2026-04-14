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
use App\Http\Middleware\PolicyMiddleware;

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
 * BEISPIEL — Benutzer-Modul mit Policy-basierter Autorisierung
 *
 * $router->group('/users', [new AuthMiddleware()], function ($r) {
 *     // Liste: klassen-level Ability, kein Ziel-Objekt
 *     $r->get('/',
 *         [\App\Http\Controllers\UserController::class, 'index'],
 *         [new PolicyMiddleware('user.viewList')]
 *     );
 *
 *     // Edit: mit Route-Parameter als Resource
 *     $r->post('/{id:\d+}',
 *         [\App\Http\Controllers\UserController::class, 'update'],
 *         [new PolicyMiddleware('user.update', resourceParam: 'id')]
 *     );
 * });
 *
 * BEISPIEL — eNOTF-Protokoll (config-gated Auth + PIN-Lockscreen + FiveM-CSP)
 *
 * $router->group('/enotf', [
 *     new AuthMiddleware('ENOTF_REQUIRE_USER_AUTH'),
 *     PinLockscreenMiddleware::class,
 *     FiveMCspMiddleware::class,
 * ], function ($r) {
 *     $r->get('/protokoll/{enr}', [\App\Http\Controllers\EnotfProtokollController::class, 'index']);
 * });
 *
 * BEISPIEL — Wissensdatenbank (public wenn KB_PUBLIC_ACCESS=true)
 *
 * $router->get('/wissensdb/{slug}',
 *     [\App\Http\Controllers\KnowledgebaseController::class, 'show'],
 *     [new AuthMiddleware('KB_PUBLIC_ACCESS', invert: true)]
 * );
 *
 * Für einfache Permission-Checks ohne Policy-Kontext reicht weiterhin
 * der schlankere PermissionMiddleware — z.B. Admin-only Endpoints ohne
 * Resource-Bezug. PolicyMiddleware ist der richtige Griff, sobald die
 * Entscheidung vom Ziel-Objekt abhängt (Priority-Vergleich, Ownership etc.).
 */
