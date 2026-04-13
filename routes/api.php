<?php

declare(strict_types=1);

/**
 * intraRP — JSON / API-Routes
 *
 * Wird vom Front-Controller nach web.php geladen. Routen sind hier unter
 * `/api/...` gruppiert, damit die AuthMiddleware sie automatisch als
 * API-Requests erkennt (→ 401 JSON statt Redirect).
 *
 * Konventionen:
 *   - Alle API-Routen antworten mit JSON, auch Fehler
 *   - CSRF-Middleware NUR für Session-authentifizierte, state-ändernde
 *     Requests. FiveM-API-Key-Routes brauchen kein CSRF (kommt von einem
 *     anderen Server, keine Browser-Session im Spiel).
 *   - FiveM-Server-Endpoints laufen unter `/api/fivem/...` und nutzen
 *     ApiKeyMiddleware statt AuthMiddleware.
 *
 * @var \App\Http\Router $router
 */

use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\CsrfMiddleware;
use App\Http\Middleware\PermissionMiddleware;

// ----------------------------------------------------------------------------
//  Smoke-Test-Routen
// ----------------------------------------------------------------------------

$router->get('/api/_router/ping', function ($request) {
    return \App\Http\Response::json([
        'success' => true,
        'message' => 'pong',
        'scope'   => 'api',
    ]);
});

$router->get('/api/_router/whoami', function ($request) {
    return \App\Http\Response::json([
        'success' => true,
        'user_id' => $_SESSION['userid'] ?? null,
        'perms'   => $_SESSION['permissions'] ?? [],
    ]);
}, [new AuthMiddleware()]);

/*
 * BEISPIEL — FiveM-Server-Endpoint (Maschine-zu-Maschine, API-Key)
 *
 * $router->post('/api/fivem/character/identify',
 *     [\App\Http\Controllers\Api\CharacterIdentifyController::class, 'handle'],
 *     [ApiKeyMiddleware::class]
 * );
 *
 * BEISPIEL — Admin-API-Endpoint (Session + Permission + CSRF)
 *
 * $router->post('/api/users/{id:\d+}',
 *     [\App\Http\Controllers\Api\UserApiController::class, 'update'],
 *     [new AuthMiddleware(), new PermissionMiddleware('personnel.edit'), CsrfMiddleware::class]
 * );
 */
