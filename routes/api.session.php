<?php

declare(strict_types=1);

/**
 * intraRP — API-Routen (Router v2)
 *
 * Alle `/api/*`-Endpoints sind über echte Controller-Methoden angebunden,
 * aufgeteilt in `App\Http\Controllers\Api\*`. Sowohl die saubere URL als
 * auch das alte `.php`-Suffix werden registriert, damit Client-Code
 * während des Umstiegs nicht reißt.
 *
 * Diese Datei wird von `public/index.php` nach `routes/api.php` geladen.
 *
 * @var \App\Http\Router $router
 */

use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AsuSyncController;
use App\Http\Controllers\Api\DocumentsController;
use App\Http\Controllers\Api\FederationController;
use App\Http\Controllers\Api\PersonnelController;
use App\Http\Controllers\Api\PersonnelProfileController;
use App\Http\Controllers\Api\SystemController as SystemApiController;
use App\Http\Controllers\Api\TelemetryApiController;
use App\Http\Controllers\Api\VehicleTzTemplatesController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\VersionController;
use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\JsonExceptionMiddleware;
use App\Http\Middleware\PermissionMiddleware;

// JsonExceptionMiddleware läuft als äußerste Schicht und wandelt
// FormRequest-Validation- und Gate-Authorization-Exceptions zentral in
// 422- bzw. 403-JSON-Responses um. Controller können die Exceptions
// einfach fliegen lassen.
$auth   = [JsonExceptionMiddleware::class, new AuthMiddleware()];
$apiKey = [JsonExceptionMiddleware::class, ApiKeyMiddleware::class];
$public = [JsonExceptionMiddleware::class];

// ============================================================================
//  Announcements — refactored zum echten Controller
// ============================================================================
$announcementHandler = [AnnouncementController::class, 'dismiss'];
$router->post('/api/announcements/dismiss',     $announcementHandler, $auth);
$router->post('/api/dismiss-announcement.php',  $announcementHandler, $auth);

// ============================================================================
//  ASU-Sync (FiveM-Server, API-Key) — refactored
// ============================================================================
$router->post('/api/asu/sync',     [AsuSyncController::class, 'sync'], $apiKey);
$router->post('/api/asu-sync.php', [AsuSyncController::class, 'sync'], $apiKey);

// ============================================================================
//  Documents — refactored zum echten Controller (DocumentsController).
//  Permission-Prüfung läuft im Controller selbst (meist
//  `admin` + `personnel.documents.manage`).
// ============================================================================
$docHandler = fn (string $method) => [DocumentsController::class, $method];

// Templates
$router->match(['GET'],         '/api/documents/list',            $docHandler('listTemplates'),        $auth);
$router->match(['GET'],         '/api/documents/get',             $docHandler('getTemplate'),          $auth);
$router->match(['POST'],        '/api/documents/save',            $docHandler('saveTemplate'),         $auth);
$router->match(['GET', 'POST', 'DELETE'], '/api/documents/delete',     $docHandler('deleteTemplate'), $auth);
$router->match(['POST'],        '/api/documents/duplicate',       $docHandler('duplicateTemplate'),    $auth);
$router->match(['POST'],        '/api/documents/regenerate',      $docHandler('regenerateTemplateFile'), $auth);
$router->match(['POST'],        '/api/documents/create-custom',       $docHandler('createCustom'),     $auth);
$router->match(['GET'],         '/api/documents/get-document',        $docHandler('getDocument'),      $auth);
$router->match(['POST'],        '/api/documents/archive',             $docHandler('archiveDocument'),  $auth);

// Assets
$router->match(['GET'],             '/api/documents/asset-list',       $docHandler('assetList'),   $auth);
$router->match(['POST'],            '/api/documents/asset-upload',     $docHandler('assetUpload'), $auth);
$router->match(['POST', 'DELETE'],  '/api/documents/asset-delete',     $docHandler('assetDelete'), $auth);

// Layouts
$router->match(['GET'],         '/api/documents/layout-get',         $docHandler('layoutGet'),      $auth);
$router->match(['POST'],        '/api/documents/layout-save',        $docHandler('layoutSave'),     $auth);
$router->match(['GET', 'POST'], '/api/documents/layout-versions',     $docHandler('layoutVersions'), $auth);
$router->match(['POST'],        '/api/documents/layout-preview',     $docHandler('layoutPreview'),  $auth);

// Twig / Convert
$router->match(['GET'],  '/api/documents/twig-preview',     $docHandler('twigPreview'),  $auth);
$router->match(['POST'], '/api/documents/convert-twig',     $docHandler('convertTwig'),  $auth);

// Kategorien (GET|POST|DELETE in einer Methode, interne Method-Weiche)
$router->match(['GET', 'POST', 'DELETE'], '/api/documents/categories',     $docHandler('categories'), $auth);

// ============================================================================
//  Federation (Server-to-Server) — refactored.
//  Auth läuft intern via FederationMiddleware::authenticate() — kein
//  Router-Middleware-Stack, weil Federation einen eigenen DB-gespeicherten
//  Per-Instanz-Key-Mechanismus nutzt (X-Federation-Key-Header).
// ============================================================================
$router->match(['GET'],         '/api/federation/handshake',          [FederationController::class, 'handshake'],     $public);
$router->match(['POST'],        '/api/federation/pair',               [FederationController::class, 'pair'],          $public);
$router->match(['GET'],         '/api/federation/personnel',          [FederationController::class, 'personnel'],     $public);
$router->match(['GET'],         '/api/federation/enotf',              [FederationController::class, 'enotf'],         $public);
$router->match(['GET'],         '/api/federation/fire-incidents',     [FederationController::class, 'fireIncidents'], $public);



// ============================================================================
//  Personnel (Mitarbeiter-Admin-UI) — vollständig refactored
// ============================================================================
$personnelEditAuth = [JsonExceptionMiddleware::class, new AuthMiddleware(), new PermissionMiddleware(['admin', 'personnel.edit'])];
$personnelViewAuth = [JsonExceptionMiddleware::class, new AuthMiddleware(), new PermissionMiddleware(['admin', 'personnel.view'])];
$usersCreateAuth   = [JsonExceptionMiddleware::class, new AuthMiddleware(), new PermissionMiddleware(['admin', 'users.create'])];

// Dienstnummer-Check (JSON + Legacy-Plain-Text-Variante)
$router->post('/api/personnel/check-dienstnr',            [PersonnelController::class, 'checkDienstnr'],        $personnelEditAuth);
$router->post('/api/personnel/check-dienstnr-legacy',     [PersonnelController::class, 'checkDienstnrLegacy'],  $personnelEditAuth);

// Invite-Code-Generierung
$router->post('/api/personnel/generate-invite',     [PersonnelController::class, 'generateInvite'], $usersCreateAuth);

// Profile Comments/Logs — HTML-Fragments
$router->match(['GET'], '/api/personnel/profile-comments',     [PersonnelProfileController::class, 'comments'], $personnelViewAuth);
$router->match(['GET'], '/api/personnel/profile-logs',         [PersonnelProfileController::class, 'logs'],     $personnelViewAuth);

// Profile-Update + PFP-Upload
$router->post('/api/personnel/update-profile',     [PersonnelController::class, 'updateProfile'], $personnelEditAuth);
$router->post('/api/personnel/upload-pfp',         [PersonnelController::class, 'uploadPfp'],     $personnelEditAuth);


// ============================================================================
//  System-Admin-API — vollständig refactored
// ============================================================================
$adminAuth = [JsonExceptionMiddleware::class, new AuthMiddleware(), new PermissionMiddleware('admin')];

// Composer-Status
$router->match(['GET', 'POST'], '/api/system/composer-status',     [SystemApiController::class, 'composerStatus'], $adminAuth);
$router->match(['GET', 'POST'], '/api/composer-status.php',        [SystemApiController::class, 'composerStatus'], $adminAuth);

// Performance-Metrics
$router->get('/api/system/performance',     [SystemApiController::class, 'performance'], $adminAuth);

// API-Key-Regeneration
$router->post('/api/system/regenerate-api-key',     [SystemApiController::class, 'regenerateApiKey'], $adminAuth);

// Theme (user-specific, nur Session-Auth reicht)
$router->get( '/api/system/theme',     [SystemApiController::class, 'getTheme'], $auth);
$router->post('/api/system/theme',     [SystemApiController::class, 'setTheme'], $auth);

// Globale Suche
$router->get('/api/system/global-search',     [SystemApiController::class, 'globalSearch'], $auth);

// ============================================================================
//  Telemetry — refactored zum echten Controller
//   - heartbeat: X-API-Key (wird vom Hub-Server gerufen, Machine-to-Machine)
//   - background: Session-Auth (Admin-UI triggert Heartbeat manuell)
// ============================================================================
$router->match(['GET', 'POST'], '/api/telemetry/heartbeat',     [TelemetryApiController::class, 'heartbeat'], $apiKey);
$router->match(['GET', 'POST'], '/api/telemetry-heartbeat.php', [TelemetryApiController::class, 'heartbeat'], $apiKey);

$router->match(['GET', 'POST'], '/api/telemetry/background',     [TelemetryApiController::class, 'background'], $auth);
$router->match(['GET', 'POST'], '/api/telemetry-background.php', [TelemetryApiController::class, 'background'], $auth);

// ============================================================================
//  Vehicles — alle Endpoints refactored.
// ============================================================================
$router->match(['GET', 'POST'], '/api/vehicles/defects-handler',     [\App\Http\Controllers\Api\VehicleDefectsController::class, 'handle'], $auth);
$router->match(['GET', 'POST'], '/api/vehicles/import-handler',      [\App\Http\Controllers\Api\VehicleImportController::class,  'handle'], $auth);

$router->match(['GET', 'POST'], '/api/vehicles/tz-templates',     [VehicleTzTemplatesController::class, 'handle'], $auth);

// ============================================================================
//  Version (Public — refactored zum echten Controller)
// ============================================================================
$router->get('/api/version',     [VersionController::class, 'index']);

// ============================================================================
//  Health-Check (Public — für externe Monitoring-Tools)
// ============================================================================
$router->get('/healthz',        [HealthController::class, 'index']);
$router->get('/api/health',     [HealthController::class, 'index']);
