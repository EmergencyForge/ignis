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
use App\Http\Controllers\Api\EnotfController;
use App\Http\Controllers\Api\FederationController;
use App\Http\Controllers\Api\FireController;
use App\Http\Controllers\Api\HospitalAvailabilityController;
use App\Http\Controllers\Api\KlinikCodeController;
use App\Http\Controllers\Api\KnowledgebaseController;
use App\Http\Controllers\Api\ManvController;
use App\Http\Controllers\Api\PersonnelController;
use App\Http\Controllers\Api\PersonnelProfileController;
use App\Http\Controllers\Api\PoiDepartmentsController;
use App\Http\Controllers\Api\SystemController as SystemApiController;
use App\Http\Controllers\Api\TelemetryApiController;
use App\Http\Controllers\Api\VehicleTzTemplatesController;
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
$router->post('/api/announcements/dismiss.php', $announcementHandler, $auth);
$router->post('/api/dismiss-announcement.php',  $announcementHandler, $auth);

// ============================================================================
//  ASU-Sync (FiveM-Server, API-Key) — refactored
// ============================================================================
$router->post('/api/asu/sync',     [AsuSyncController::class, 'sync'], $apiKey);
$router->post('/api/asu/sync.php', [AsuSyncController::class, 'sync'], $apiKey);
$router->post('/api/asu-sync.php', [AsuSyncController::class, 'sync'], $apiKey);

// ============================================================================
//  Documents — refactored zum echten Controller (DocumentsController).
//  Permission-Prüfung läuft im Controller selbst (meist
//  `admin` + `personnel.documents.manage`).
// ============================================================================
$docHandler = fn (string $method) => [DocumentsController::class, $method];

// Templates
$router->match(['GET'],         '/api/documents/list',            $docHandler('listTemplates'),        $auth);
$router->match(['GET'],         '/api/documents/list.php',        $docHandler('listTemplates'),        $auth);
$router->match(['GET'],         '/api/documents/get',             $docHandler('getTemplate'),          $auth);
$router->match(['GET'],         '/api/documents/get.php',         $docHandler('getTemplate'),          $auth);
$router->match(['POST'],        '/api/documents/save',            $docHandler('saveTemplate'),         $auth);
$router->match(['POST'],        '/api/documents/save.php',        $docHandler('saveTemplate'),         $auth);
$router->match(['GET', 'POST', 'DELETE'], '/api/documents/delete',     $docHandler('deleteTemplate'), $auth);
$router->match(['GET', 'POST', 'DELETE'], '/api/documents/delete.php', $docHandler('deleteTemplate'), $auth);
$router->match(['POST'],        '/api/documents/duplicate',       $docHandler('duplicateTemplate'),    $auth);
$router->match(['POST'],        '/api/documents/duplicate.php',   $docHandler('duplicateTemplate'),    $auth);
$router->match(['POST'],        '/api/documents/regenerate',      $docHandler('regenerateTemplateFile'), $auth);
$router->match(['POST'],        '/api/documents/regenerate.php',  $docHandler('regenerateTemplateFile'), $auth);
$router->match(['POST'],        '/api/documents/create-custom',       $docHandler('createCustom'),     $auth);
$router->match(['POST'],        '/api/documents/create-custom.php',   $docHandler('createCustom'),     $auth);
$router->match(['GET'],         '/api/documents/get-document',        $docHandler('getDocument'),      $auth);
$router->match(['GET'],         '/api/documents/get-document.php',    $docHandler('getDocument'),      $auth);
$router->match(['POST'],        '/api/documents/archive',             $docHandler('archiveDocument'),  $auth);
$router->match(['POST'],        '/api/documents/archive.php',         $docHandler('archiveDocument'),  $auth);

// Assets
$router->match(['GET'],             '/api/documents/asset-list',       $docHandler('assetList'),   $auth);
$router->match(['GET'],             '/api/documents/asset-list.php',   $docHandler('assetList'),   $auth);
$router->match(['POST'],            '/api/documents/asset-upload',     $docHandler('assetUpload'), $auth);
$router->match(['POST'],            '/api/documents/asset-upload.php', $docHandler('assetUpload'), $auth);
$router->match(['POST', 'DELETE'],  '/api/documents/asset-delete',     $docHandler('assetDelete'), $auth);
$router->match(['POST', 'DELETE'],  '/api/documents/asset-delete.php', $docHandler('assetDelete'), $auth);

// Layouts
$router->match(['GET'],         '/api/documents/layout-get',         $docHandler('layoutGet'),      $auth);
$router->match(['GET'],         '/api/documents/layout-get.php',     $docHandler('layoutGet'),      $auth);
$router->match(['POST'],        '/api/documents/layout-save',        $docHandler('layoutSave'),     $auth);
$router->match(['POST'],        '/api/documents/layout-save.php',    $docHandler('layoutSave'),     $auth);
$router->match(['GET', 'POST'], '/api/documents/layout-versions',     $docHandler('layoutVersions'), $auth);
$router->match(['GET', 'POST'], '/api/documents/layout-versions.php', $docHandler('layoutVersions'), $auth);
$router->match(['POST'],        '/api/documents/layout-preview',     $docHandler('layoutPreview'),  $auth);
$router->match(['POST'],        '/api/documents/layout-preview.php', $docHandler('layoutPreview'),  $auth);

// Twig / Convert
$router->match(['GET'],  '/api/documents/twig-preview',     $docHandler('twigPreview'),  $auth);
$router->match(['GET'],  '/api/documents/twig-preview.php', $docHandler('twigPreview'),  $auth);
$router->match(['POST'], '/api/documents/convert-twig',     $docHandler('convertTwig'),  $auth);
$router->match(['POST'], '/api/documents/convert-twig.php', $docHandler('convertTwig'),  $auth);

// Kategorien (GET|POST|DELETE in einer Methode, interne Method-Weiche)
$router->match(['GET', 'POST', 'DELETE'], '/api/documents/categories',     $docHandler('categories'), $auth);
$router->match(['GET', 'POST', 'DELETE'], '/api/documents/categories.php', $docHandler('categories'), $auth);

// ============================================================================
//  eNOTF-API — vollständig refactored.
//  Alle Endpoints laufen über EnotfController.
// ============================================================================
$enotfHandler = fn (string $method) => [EnotfController::class, $method];

// ── Refactored Endpoints ──
$router->match(['GET', 'POST'], '/api/enotf/prereg',         $enotfHandler('prereg'),              $auth);
$router->match(['GET', 'POST'], '/api/enotf/prereg.php',     $enotfHandler('prereg'),              $auth);

$router->match(['POST', 'DELETE'], '/api/enotf/delete-vehicle-session',     $enotfHandler('deleteVehicleSession'), $auth);
$router->match(['POST', 'DELETE'], '/api/enotf/delete-vehicle-session.php', $enotfHandler('deleteVehicleSession'), $auth);

$router->match(['GET'], '/api/enotf/sync-status',     $enotfHandler('syncStatus'), $auth);
$router->match(['GET'], '/api/enotf/sync-status.php', $enotfHandler('syncStatus'), $auth);

$router->match(['POST'], '/api/enotf/session-update',     $enotfHandler('sessionUpdate'), $auth);
$router->match(['POST'], '/api/enotf/session-update.php', $enotfHandler('sessionUpdate'), $auth);

$router->match(['GET'], '/api/enotf/check-vehicle-session',     $enotfHandler('checkVehicleSession'), $auth);
$router->match(['GET'], '/api/enotf/check-vehicle-session.php', $enotfHandler('checkVehicleSession'), $auth);

$router->match(['GET'], '/api/enotf/session-status',     $enotfHandler('sessionStatus'), $auth);
$router->match(['GET'], '/api/enotf/session-status.php', $enotfHandler('sessionStatus'), $auth);

$router->match(['GET', 'POST'], '/api/enotf/poi/poi-search',     $enotfHandler('poiSearch'), $auth);
$router->match(['GET', 'POST'], '/api/enotf/poi/poi-search.php', $enotfHandler('poiSearch'), $auth);

$router->match(['GET'], '/api/enotf/share/get-available-vehicles',     $enotfHandler('shareGetAvailableVehicles'), $auth);
$router->match(['GET'], '/api/enotf/share/get-available-vehicles.php', $enotfHandler('shareGetAvailableVehicles'), $auth);

$router->match(['POST'], '/api/enotf/check-conflict',     $enotfHandler('checkConflict'), $auth);
$router->match(['POST'], '/api/enotf/check-conflict.php', $enotfHandler('checkConflict'), $auth);

$router->match(['POST'], '/api/enotf/patient-sync',     $enotfHandler('patientSync'), $auth);
$router->match(['POST'], '/api/enotf/patient-sync.php', $enotfHandler('patientSync'), $auth);

$router->match(['POST'], '/api/enotf/poi/save-field',     $enotfHandler('poiSaveField'), $auth);
$router->match(['POST'], '/api/enotf/poi/save-field.php', $enotfHandler('poiSaveField'), $auth);

$router->match(['POST', 'DELETE'], '/api/enotf/delete-protocol',     $enotfHandler('deleteProtocol'), $auth);
$router->match(['POST', 'DELETE'], '/api/enotf/delete-protocol.php', $enotfHandler('deleteProtocol'), $auth);

$router->match(['GET'],  '/api/enotf/share/check-requests',       $enotfHandler('shareCheckRequests'),    $auth);
$router->match(['GET'],  '/api/enotf/share/check-requests.php',   $enotfHandler('shareCheckRequests'),    $auth);
$router->match(['GET'],  '/api/enotf/share/get-own-protocols',    $enotfHandler('shareGetOwnProtocols'),  $auth);
$router->match(['GET'],  '/api/enotf/share/get-own-protocols.php',$enotfHandler('shareGetOwnProtocols'),  $auth);
$router->match(['POST'], '/api/enotf/share/reject-request',       $enotfHandler('shareRejectRequest'),    $auth);
$router->match(['POST'], '/api/enotf/share/reject-request.php',   $enotfHandler('shareRejectRequest'),    $auth);
$router->match(['POST'], '/api/enotf/share/send-request',         $enotfHandler('shareSendRequest'),      $auth);
$router->match(['POST'], '/api/enotf/share/send-request.php',     $enotfHandler('shareSendRequest'),      $auth);

$router->match(['POST'],         '/api/enotf/billing',              $enotfHandler('billing'),           $auth);
$router->match(['POST'],         '/api/enotf/billing.php',          $enotfHandler('billing'),           $auth);

$router->match(['GET', 'POST', 'DELETE'], '/api/enotf/bulk-delete-empty',     $enotfHandler('bulkDeleteEmpty'), $auth);
$router->match(['GET', 'POST', 'DELETE'], '/api/enotf/bulk-delete-empty.php', $enotfHandler('bulkDeleteEmpty'), $auth);

$router->match(['POST'],        '/api/enotf/save-fields',          $enotfHandler('saveFields'),         $auth);
$router->match(['POST'],        '/api/enotf/save-fields.php',      $enotfHandler('saveFields'),         $auth);

$router->match(['POST'],        '/api/enotf/share/accept-request',     $enotfHandler('shareAcceptRequest'), $auth);
$router->match(['POST'],        '/api/enotf/share/accept-request.php', $enotfHandler('shareAcceptRequest'), $auth);

// Legacy-Aliase (alte Redirect-Stubs)
$router->post('/api/enotf-billing.php',         $enotfHandler('billing'),        $auth);
$router->post('/api/enotf-delete-protocol.php', $enotfHandler('deleteProtocol'), $auth);
$router->post('/api/enotf-patient-sync.php',    $enotfHandler('patientSync'),    $auth);
$router->get( '/api/enotf-sync-status.php',     $enotfHandler('syncStatus'),     $auth);

// ============================================================================
//  Federation (Server-to-Server) — refactored.
//  Auth läuft intern via FederationMiddleware::authenticate() — kein
//  Router-Middleware-Stack, weil Federation einen eigenen DB-gespeicherten
//  Per-Instanz-Key-Mechanismus nutzt (X-Federation-Key-Header).
// ============================================================================
$router->match(['GET'],         '/api/federation/handshake',          [FederationController::class, 'handshake'],     $public);
$router->match(['GET'],         '/api/federation/handshake.php',      [FederationController::class, 'handshake'],     $public);
$router->match(['POST'],        '/api/federation/pair',               [FederationController::class, 'pair'],          $public);
$router->match(['POST'],        '/api/federation/pair.php',           [FederationController::class, 'pair'],          $public);
$router->match(['GET'],         '/api/federation/personnel',          [FederationController::class, 'personnel'],     $public);
$router->match(['GET'],         '/api/federation/personnel.php',      [FederationController::class, 'personnel'],     $public);
$router->match(['GET'],         '/api/federation/enotf',              [FederationController::class, 'enotf'],         $public);
$router->match(['GET'],         '/api/federation/enotf.php',          [FederationController::class, 'enotf'],         $public);
$router->match(['GET'],         '/api/federation/fire-incidents',     [FederationController::class, 'fireIncidents'], $public);
$router->match(['GET'],         '/api/federation/fire-incidents.php', [FederationController::class, 'fireIncidents'], $public);

// ============================================================================
//  Fire-Incident-API — vollständig refactored.
// ============================================================================
$fireQmAuth = [new AuthMiddleware(), new PermissionMiddleware(['admin', 'fire.incident.qm'])];

$router->match(['GET', 'POST'], '/api/fire/status',     [FireController::class, 'status'], $auth);
$router->match(['GET', 'POST'], '/api/fire/status.php', [FireController::class, 'status'], $auth);

$router->match(['GET', 'POST', 'DELETE'], '/api/fire/bulk-delete-empty',     [FireController::class, 'bulkDeleteEmpty'], $fireQmAuth);
$router->match(['GET', 'POST', 'DELETE'], '/api/fire/bulk-delete-empty.php', [FireController::class, 'bulkDeleteEmpty'], $fireQmAuth);

$router->match(['GET', 'POST'], '/api/fire/lagekarte',     [\App\Http\Controllers\Api\FireLagekarteController::class, 'handle'], $auth);
$router->match(['GET', 'POST'], '/api/fire/lagekarte.php', [\App\Http\Controllers\Api\FireLagekarteController::class, 'handle'], $auth);

// ============================================================================
//  Hospitals — refactored zum echten Controller
// ============================================================================
$hospitalGet    = [HospitalAvailabilityController::class, 'get'];
$hospitalUpdate = [HospitalAvailabilityController::class, 'update'];
$router->get( '/api/hospitals/availability-get',         $hospitalGet,    $auth);
$router->get( '/api/hospitals/availability-get.php',     $hospitalGet,    $auth);
$router->post('/api/hospitals/availability-update',      $hospitalUpdate, $auth);
$router->post('/api/hospitals/availability-update.php',  $hospitalUpdate, $auth);
$router->get( '/api/hospital-availability-get.php',      $hospitalGet,    $auth);
$router->post('/api/hospital-availability-update.php',   $hospitalUpdate, $auth);

// ============================================================================
//  Klinik-Code — refactored zum echten Controller
// ============================================================================
$klinikHandler = [KlinikCodeController::class, 'generate'];
$router->post('/api/klinik/generate-code',      $klinikHandler, $auth);
$router->post('/api/klinik/generate-code.php',  $klinikHandler, $auth);
$router->post('/api/generate-klinikcode.php',   $klinikHandler, $auth);

// ============================================================================
//  Knowledgebase — refactored zum echten Controller.
//
//  GET-Routen sind config-gated (KB_PUBLIC_ACCESS). Write-Operationen
//  (POST/DELETE categories, POST/DELETE tags) erfordern Session + kb.edit
//  und werden intern im Controller geprüft.
// ============================================================================
$kbReadAuth  = [new AuthMiddleware('KB_PUBLIC_ACCESS', invert: true)];
$kbWriteAuth = [new AuthMiddleware(), new PermissionMiddleware(['admin', 'kb.edit'])];

// Categories
$router->get(   '/api/knowledgebase/categories',     [KnowledgebaseController::class, 'listCategories'],  $kbReadAuth);
$router->get(   '/api/knowledgebase/categories.php', [KnowledgebaseController::class, 'listCategories'],  $kbReadAuth);
$router->post(  '/api/knowledgebase/categories',     [KnowledgebaseController::class, 'saveCategory'],    $kbWriteAuth);
$router->post(  '/api/knowledgebase/categories.php', [KnowledgebaseController::class, 'saveCategory'],    $kbWriteAuth);
$router->delete('/api/knowledgebase/categories',     [KnowledgebaseController::class, 'deleteCategory'], $kbWriteAuth);
$router->delete('/api/knowledgebase/categories.php', [KnowledgebaseController::class, 'deleteCategory'], $kbWriteAuth);

// Tags
$router->get(   '/api/knowledgebase/tags',     [KnowledgebaseController::class, 'listTags'],   $kbReadAuth);
$router->get(   '/api/knowledgebase/tags.php', [KnowledgebaseController::class, 'listTags'],   $kbReadAuth);
$router->post(  '/api/knowledgebase/tags',     [KnowledgebaseController::class, 'saveTag'],    $kbWriteAuth);
$router->post(  '/api/knowledgebase/tags.php', [KnowledgebaseController::class, 'saveTag'],    $kbWriteAuth);
$router->delete('/api/knowledgebase/tags',     [KnowledgebaseController::class, 'deleteTag'],  $kbWriteAuth);
$router->delete('/api/knowledgebase/tags.php', [KnowledgebaseController::class, 'deleteTag'],  $kbWriteAuth);

// Search
$router->get(   '/api/knowledgebase/search',     [KnowledgebaseController::class, 'search'], $kbReadAuth);
$router->get(   '/api/knowledgebase/search.php', [KnowledgebaseController::class, 'search'], $kbReadAuth);

// ============================================================================
//  MANV (Massenanfall) — refactored
// ============================================================================
$router->match(['GET', 'POST'], '/api/manv/api',     [ManvController::class, 'handle'], $auth);
$router->match(['GET', 'POST'], '/api/manv/api.php', [ManvController::class, 'handle'], $auth);
$router->match(['GET', 'POST'], '/api/manv-api.php', [ManvController::class, 'handle'], $auth);

// ============================================================================
//  Personnel (Mitarbeiter-Admin-UI) — vollständig refactored
// ============================================================================
$personnelEditAuth = [new AuthMiddleware(), new PermissionMiddleware(['admin', 'personnel.edit'])];
$personnelViewAuth = [new AuthMiddleware(), new PermissionMiddleware(['admin', 'personnel.view'])];
$usersCreateAuth   = [new AuthMiddleware(), new PermissionMiddleware(['admin', 'users.create'])];

// Dienstnummer-Check (JSON + Legacy-Plain-Text-Variante)
$router->post('/api/personnel/check-dienstnr',            [PersonnelController::class, 'checkDienstnr'],        $personnelEditAuth);
$router->post('/api/personnel/check-dienstnr.php',        [PersonnelController::class, 'checkDienstnr'],        $personnelEditAuth);
$router->post('/api/personnel/check-dienstnr-legacy',     [PersonnelController::class, 'checkDienstnrLegacy'],  $personnelEditAuth);
$router->post('/api/personnel/check-dienstnr-legacy.php', [PersonnelController::class, 'checkDienstnrLegacy'],  $personnelEditAuth);

// Invite-Code-Generierung
$router->post('/api/personnel/generate-invite',     [PersonnelController::class, 'generateInvite'], $usersCreateAuth);
$router->post('/api/personnel/generate-invite.php', [PersonnelController::class, 'generateInvite'], $usersCreateAuth);

// Profile Comments/Logs — HTML-Fragments
$router->match(['GET'], '/api/personnel/profile-comments',     [PersonnelProfileController::class, 'comments'], $personnelViewAuth);
$router->match(['GET'], '/api/personnel/profile-comments.php', [PersonnelProfileController::class, 'comments'], $personnelViewAuth);
$router->match(['GET'], '/api/personnel/profile-logs',         [PersonnelProfileController::class, 'logs'],     $personnelViewAuth);
$router->match(['GET'], '/api/personnel/profile-logs.php',     [PersonnelProfileController::class, 'logs'],     $personnelViewAuth);

// Profile-Update + PFP-Upload
$router->post('/api/personnel/update-profile',     [PersonnelController::class, 'updateProfile'], $personnelEditAuth);
$router->post('/api/personnel/update-profile.php', [PersonnelController::class, 'updateProfile'], $personnelEditAuth);
$router->post('/api/personnel/upload-pfp',         [PersonnelController::class, 'uploadPfp'],     $personnelEditAuth);
$router->post('/api/personnel/upload-pfp.php',     [PersonnelController::class, 'uploadPfp'],     $personnelEditAuth);

// ============================================================================
//  POIs (Point-of-Interest Admin) — refactored zum echten Controller
// ============================================================================
$poiAuth = [new AuthMiddleware(), new PermissionMiddleware(['admin', 'pois.manage'])];
$router->post('/api/pois/departments-sort',     [PoiDepartmentsController::class, 'updateSort'], $poiAuth);
$router->post('/api/pois/departments-sort.php', [PoiDepartmentsController::class, 'updateSort'], $poiAuth);

// ============================================================================
//  System-Admin-API — vollständig refactored
// ============================================================================
$adminAuth = [new AuthMiddleware(), new PermissionMiddleware('admin')];

// Composer-Status
$router->match(['GET', 'POST'], '/api/system/composer-status',     [SystemApiController::class, 'composerStatus'], $adminAuth);
$router->match(['GET', 'POST'], '/api/system/composer-status.php', [SystemApiController::class, 'composerStatus'], $adminAuth);
$router->match(['GET', 'POST'], '/api/composer-status.php',        [SystemApiController::class, 'composerStatus'], $adminAuth);

// Performance-Metrics
$router->get('/api/system/performance',     [SystemApiController::class, 'performance'], $adminAuth);
$router->get('/api/system/performance.php', [SystemApiController::class, 'performance'], $adminAuth);

// API-Key-Regeneration
$router->post('/api/system/regenerate-api-key',     [SystemApiController::class, 'regenerateApiKey'], $adminAuth);
$router->post('/api/system/regenerate-api-key.php', [SystemApiController::class, 'regenerateApiKey'], $adminAuth);

// Theme (user-specific, nur Session-Auth reicht)
$router->get( '/api/system/theme',     [SystemApiController::class, 'getTheme'], $auth);
$router->get( '/api/system/theme.php', [SystemApiController::class, 'getTheme'], $auth);
$router->post('/api/system/theme',     [SystemApiController::class, 'setTheme'], $auth);
$router->post('/api/system/theme.php', [SystemApiController::class, 'setTheme'], $auth);

// Globale Suche
$router->get('/api/system/global-search',     [SystemApiController::class, 'globalSearch'], $auth);
$router->get('/api/system/global-search.php', [SystemApiController::class, 'globalSearch'], $auth);

// ============================================================================
//  Telemetry — refactored zum echten Controller
//   - heartbeat: X-API-Key (wird vom Hub-Server gerufen, Machine-to-Machine)
//   - background: Session-Auth (Admin-UI triggert Heartbeat manuell)
// ============================================================================
$router->match(['GET', 'POST'], '/api/telemetry/heartbeat',     [TelemetryApiController::class, 'heartbeat'], $apiKey);
$router->match(['GET', 'POST'], '/api/telemetry/heartbeat.php', [TelemetryApiController::class, 'heartbeat'], $apiKey);
$router->match(['GET', 'POST'], '/api/telemetry-heartbeat.php', [TelemetryApiController::class, 'heartbeat'], $apiKey);

$router->match(['GET', 'POST'], '/api/telemetry/background',     [TelemetryApiController::class, 'background'], $auth);
$router->match(['GET', 'POST'], '/api/telemetry/background.php', [TelemetryApiController::class, 'background'], $auth);
$router->match(['GET', 'POST'], '/api/telemetry-background.php', [TelemetryApiController::class, 'background'], $auth);

// ============================================================================
//  Vehicles — alle Endpoints refactored.
// ============================================================================
$router->match(['GET', 'POST'], '/api/vehicles/defects-handler',     [\App\Http\Controllers\Api\VehicleDefectsController::class, 'handle'], $auth);
$router->match(['GET', 'POST'], '/api/vehicles/defects-handler.php', [\App\Http\Controllers\Api\VehicleDefectsController::class, 'handle'], $auth);
$router->match(['GET', 'POST'], '/api/vehicles/import-handler',      [\App\Http\Controllers\Api\VehicleImportController::class,  'handle'], $auth);
$router->match(['GET', 'POST'], '/api/vehicles/import-handler.php',  [\App\Http\Controllers\Api\VehicleImportController::class,  'handle'], $auth);

$router->match(['GET', 'POST'], '/api/vehicles/tz-templates',     [VehicleTzTemplatesController::class, 'handle'], $auth);
$router->match(['GET', 'POST'], '/api/vehicles/tz-templates.php', [VehicleTzTemplatesController::class, 'handle'], $auth);

// ============================================================================
//  Version (Public — refactored zum echten Controller)
// ============================================================================
$router->get('/api/version',     [VersionController::class, 'index']);
$router->get('/api/version.php', [VersionController::class, 'index']);
