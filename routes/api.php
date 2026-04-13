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

// ----------------------------------------------------------------------------
//  FiveM-Server-Endpoints (Maschine-zu-Maschine, API-Key)
//
//  Diese Routen werden vom FiveM-Game-Server gerufen. Backward-Compat:
//  Beide Pfade (mit und ohne `.php`-Suffix) sind registriert, weil bestehende
//  FiveM-Clients den alten URLs folgen — die `.php`-Files sind durch den
//  Cutover gelöscht, der Router hängt sich via .htaccess-Fallback rein.
// ----------------------------------------------------------------------------

use App\Http\Controllers\Api\CharacterController;
use App\Http\Controllers\Api\EmdSyncController;
use App\Http\Controllers\Api\FireStatusPollController;

// Browser-side: aktuelle Session-ID abfragen — kein Auth, läuft im
// User-Browser, wird dann an FiveM weitergereicht.
$router->match(['GET', 'POST'], '/api/character/get-session-id',     [CharacterController::class, 'sessionId']);
$router->match(['GET', 'POST'], '/api/character/get-session-id.php', [CharacterController::class, 'sessionId']);

// FiveM-Server: Charakter-Daten in Spieler-Session injizieren
$router->post('/api/character/identify',
    [CharacterController::class, 'identify'],
    [ApiKeyMiddleware::class]
);
$router->post('/api/character/identify.php',
    [CharacterController::class, 'identify'],
    [ApiKeyMiddleware::class]
);

// FiveM-Server: Fire-Status-Queue pollen
$router->post('/api/emd/status-poll',
    [FireStatusPollController::class, 'poll'],
    [ApiKeyMiddleware::class]
);
$router->post('/api/emd/status-poll.php',
    [FireStatusPollController::class, 'poll'],
    [ApiKeyMiddleware::class]
);

// FiveM-Server: EMD-Sync (Haupt-Endpoint für Einsatz-/Status-/Fahrzeug-Sync)
// Migriert aus api/emd/sync.php — Business-Logik 1:1 übernommen, inkl. FW-Fix.
$router->post('/api/emd/sync',
    [EmdSyncController::class, 'sync'],
    [ApiKeyMiddleware::class]
);
$router->post('/api/emd/sync.php',
    [EmdSyncController::class, 'sync'],
    [ApiKeyMiddleware::class]
);
// Legacy-Alias: vor langem umgezogener Redirect-Stub (api/emd-sync.php →
// api/emd/sync.php) — wir honorieren den alten Pfad für den Fall dass noch
// irgendwo ein FiveM-Script darauf zeigt.
$router->post('/api/emd-sync.php',
    [EmdSyncController::class, 'sync'],
    [ApiKeyMiddleware::class]
);

/*
 * BEISPIEL — Admin-API-Endpoint (Session + Permission + CSRF)
 *
 * $router->post('/api/users/{id:\d+}',
 *     [\App\Http\Controllers\Api\UserApiController::class, 'update'],
 *     [new AuthMiddleware(), new PermissionMiddleware('personnel.edit'), CsrfMiddleware::class]
 * );
 */
