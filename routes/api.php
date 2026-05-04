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
use App\Http\Controllers\Api\NotificationController;

// Browser-side: aktuelle Session-ID abfragen — kein Auth, läuft im
// User-Browser, wird dann an FiveM weitergereicht.
$router->match(['GET', 'POST'], '/api/character/get-session-id',     [CharacterController::class, 'sessionId']);

// FiveM-Server: Charakter-Daten in Spieler-Session injizieren
$router->post('/api/character/identify',
    [CharacterController::class, 'identify'],
    [ApiKeyMiddleware::class]
);

// FiveM-Server: Fire-Status-Queue pollen
$router->post('/api/emd/status-poll',
    [FireStatusPollController::class, 'poll'],
    [ApiKeyMiddleware::class]
);

// FiveM-Server: EMD-Sync (Haupt-Endpoint für Einsatz-/Status-/Fahrzeug-Sync)
// Business-Logik 1:1 übernommen, inkl. FW-Fix.
$router->post('/api/emd/sync',
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

// ----------------------------------------------------------------------------
//  Hover-Card-Fragments (Browser-Session, intern für Tooltips/Popovers)
// ----------------------------------------------------------------------------

$router->get('/api/mitarbeiter/{id:\d+}/card',
    [\App\Http\Controllers\MitarbeiterController::class, 'card'],
    [new AuthMiddleware()]
);

// Dienstnummer-Lookup-Variante: erlaubt Hover-Card auf Dienstnr-Strings,
// ohne dass das Template die Mitarbeiter-ID kennt. Regex erlaubt Zahlen,
// Buchstaben, Bindestrich, Unterstrich (typische DNr-Schemata).
$router->get('/api/mitarbeiter/by-dienstnr/{nr:[A-Za-z0-9_\-]+}/card',
    [\App\Http\Controllers\MitarbeiterController::class, 'cardByDienstnr'],
    [new AuthMiddleware()]
);

$router->get('/api/users/{id:\d+}/card',
    [\App\Http\Controllers\UserController::class, 'card'],
    [new AuthMiddleware()]
);

$router->get('/api/pois/{id:\d+}/card',
    [\App\Http\Controllers\Api\PoiCardController::class, 'show'],
    [new AuthMiddleware()]
);

$router->get('/api/vehicles/{id:\d+}/card',
    [\App\Http\Controllers\Api\VehicleCardController::class, 'show'],
    [new AuthMiddleware()]
);

// ----------------------------------------------------------------------------
//  Notifications (Browser-Session, Admin-UI)
//
//  Beide Endpoints laufen hinter Session-Auth. CSRF ist bewusst NICHT
//  eingehängt — der bestehende Frontend-Code schickt keinen Token mit,
//  ein CSRF-Upgrade braucht koordinierte Frontend-Anpassung.
// ----------------------------------------------------------------------------

// Polling für neue Notifications (Navbar-Badge)
$router->get('/api/notifications/poll',
    [NotificationController::class, 'poll'],
    [new AuthMiddleware()]
);

// Einzelne Notification als gelesen markieren
$router->post('/api/notifications/mark-read',
    [NotificationController::class, 'markRead'],
    [new AuthMiddleware()]
);
// Legacy-Stub-Alias: benachrichtigungen/mark-read.php → NotificationController
$router->post('/benachrichtigungen/mark-read.php',
    [NotificationController::class, 'markRead'],
    [new AuthMiddleware()]
);

// Alle Notifications als gelesen markieren (Topbar-Flyout)
$router->post('/api/notifications/mark-all-read',
    [NotificationController::class, 'markAllRead'],
    [new AuthMiddleware()]
);

// ----------------------------------------------------------------------------
//  Kalender — FullCalendar-EventSource
//
//  Liefert ein Array von FullCalendar-EventInput-Objekten fuer den Range
//  [from, to]. Recurring-Events werden serverseitig durch RecurrenceExpander
//  in Einzelvorkommen aufgeloest, sodass das Frontend keine Recurrence-
//  Logik braucht.
// ----------------------------------------------------------------------------

$router->get('/api/kalender/events',
    [\App\Http\Controllers\CalendarController::class, 'eventsJson'],
    [new AuthMiddleware()]
);

// Detail eines einzelnen Events fuer Edit-Prefill
$router->get('/api/kalender/event',
    [\App\Http\Controllers\CalendarController::class, 'eventJson'],
    [new AuthMiddleware()]
);

// iCal-Subscribe — generiert Token bei Bedarf, gibt absolute URL zurueck.
$router->get('/api/kalender/subscribe-info',
    [\App\Http\Controllers\CalendarController::class, 'subscribeInfo'],
    [new AuthMiddleware()]
);
$router->post('/api/kalender/subscribe-regenerate',
    [\App\Http\Controllers\CalendarController::class, 'subscribeRegenerate'],
    [new AuthMiddleware()]
);

// iCal-Feed — Token in der URL ist die Auth (Cookie-Auth funktioniert
// fuer externe Kalender-Apps nicht). Bewusst KEINE AuthMiddleware.
$router->get('/api/kalender/ical/{token:[a-f0-9]{20,64}}',
    [\App\Http\Controllers\CalendarController::class, 'icalFeed'],
);

/*
 * BEISPIEL — Admin-API-Endpoint (Session + Permission + CSRF)
 *
 * $router->post('/api/users/{id:\d+}',
 *     [\App\Http\Controllers\Api\UserApiController::class, 'update'],
 *     [new AuthMiddleware(), new PermissionMiddleware('personnel.edit'), CsrfMiddleware::class]
 * );
 */
