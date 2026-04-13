<?php

declare(strict_types=1);

/**
 * intraRP — Legacy-API-Routen
 *
 * Alle Routen, die noch nicht zu echten Controller-Methoden portiert sind,
 * sondern ihre Business-Logik aus `src/LegacyApi/` laden (via LegacyDispatcher).
 *
 * Diese Datei wird von `public/index.php` nach `routes/api.php` geladen.
 * Nach und nach sollen einzelne Blocks hier rausfliegen, sobald die
 * Legacy-Files in richtige Controller + FormRequest refactored sind.
 *
 * Konvention: jede Route ist mit ihrem Middleware-Stack annotiert und
 * matcht sowohl den alten Pfad (inkl. `.php`-Suffix für Backwards-Compat)
 * als auch die neue saubere URL.
 *
 * @var \App\Http\Router $router
 */

use App\Http\Controllers\Api\LegacyDispatcher;
use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Response;

// Helper-Closure für den häufigen Fall "Route → LegacyDispatcher::run"
$legacy = fn (string $path) => function (\App\Http\Request $req) use ($path): Response {
    return app(LegacyDispatcher::class)->run($req, $path);
};

// Registriert eine Route sowohl unter `/clean/path` als auch `/clean/path.php`.
// $methods: "GET", "POST", "GET|POST", ["GET", "POST"], ...
$legacyRoute = function (string $methods, string $cleanPath, string $legacyFile, array $middleware) use ($router, $legacy): void {
    $methodList = is_array($methods) ? $methods : explode('|', $methods);
    $handler    = $legacy($legacyFile);

    $router->match($methodList, $cleanPath,           $handler, $middleware);
    $router->match($methodList, $cleanPath . '.php',  $handler, $middleware);
};

$auth   = [new AuthMiddleware()];
$apiKey = [ApiKeyMiddleware::class];
$public = [];

// ============================================================================
//  Announcements
// ============================================================================
$legacyRoute('POST', '/api/announcements/dismiss', 'announcements/dismiss.php', $auth);
// Legacy-Alias (alter Redirect-Stub)
$router->post('/api/dismiss-announcement.php', $legacy('announcements/dismiss.php'), $auth);

// ============================================================================
//  ASU-Sync (FiveM-Server, API-Key)
// ============================================================================
$legacyRoute('POST', '/api/asu/sync', 'asu/sync.php', $apiKey);
$router->post('/api/asu-sync.php', $legacy('asu/sync.php'), $apiKey);

// ============================================================================
//  Documents (Admin-UI, Session-Auth)
// ============================================================================
$legacyRoute('GET|POST',    '/api/documents/archive',         'documents/archive.php',         $auth);
$legacyRoute('POST|DELETE', '/api/documents/asset-delete',    'documents/asset-delete.php',    $auth);
$legacyRoute('GET',         '/api/documents/asset-list',      'documents/asset-list.php',      $auth);
$legacyRoute('POST',        '/api/documents/asset-upload',    'documents/asset-upload.php',    $auth);
$legacyRoute('GET|POST',    '/api/documents/categories',      'documents/categories.php',      $auth);
$legacyRoute('POST',        '/api/documents/convert-twig',    'documents/convert-twig.php',    $auth);
$legacyRoute('POST',        '/api/documents/create-custom',   'documents/create-custom.php',   $auth);
$legacyRoute('POST|DELETE', '/api/documents/delete',          'documents/delete.php',          $auth);
$legacyRoute('POST',        '/api/documents/duplicate',       'documents/duplicate.php',       $auth);
$legacyRoute('GET',         '/api/documents/get-document',    'documents/get-document.php',    $auth);
$legacyRoute('GET',         '/api/documents/get',             'documents/get.php',             $auth);
$legacyRoute('GET',         '/api/documents/layout-get',      'documents/layout-get.php',      $auth);
$legacyRoute('POST',        '/api/documents/layout-preview',  'documents/layout-preview.php',  $auth);
$legacyRoute('POST',        '/api/documents/layout-save',     'documents/layout-save.php',     $auth);
$legacyRoute('GET',         '/api/documents/layout-versions', 'documents/layout-versions.php', $auth);
$legacyRoute('GET',         '/api/documents/list',            'documents/list.php',            $auth);
$legacyRoute('POST',        '/api/documents/regenerate',      'documents/regenerate.php',      $auth);
$legacyRoute('POST',        '/api/documents/save',            'documents/save.php',            $auth);
$legacyRoute('POST',        '/api/documents/twig-preview',    'documents/twig-preview.php',    $auth);

// ============================================================================
//  eNOTF-Admin & -Protokoll-API (Admin-UI, Session-Auth)
// ============================================================================
$legacyRoute('GET|POST',    '/api/enotf/billing',                'enotf/billing.php',                $auth);
$legacyRoute('POST|DELETE', '/api/enotf/bulk-delete-empty',      'enotf/bulk-delete-empty.php',      $auth);
$legacyRoute('GET|POST',    '/api/enotf/check-conflict',         'enotf/check-conflict.php',         $auth);
$legacyRoute('GET|POST',    '/api/enotf/check-vehicle-session',  'enotf/check-vehicle-session.php',  $auth);
$legacyRoute('POST|DELETE', '/api/enotf/delete-protocol',        'enotf/delete-protocol.php',        $auth);
$legacyRoute('POST|DELETE', '/api/enotf/delete-vehicle-session', 'enotf/delete-vehicle-session.php', $auth);
$legacyRoute('POST',        '/api/enotf/patient-sync',           'enotf/patient-sync.php',           $auth);
$legacyRoute('GET|POST',    '/api/enotf/prereg',                 'enotf/prereg.php',                 $auth);
$legacyRoute('POST',        '/api/enotf/save-fields',            'enotf/save-fields.php',            $auth);
$legacyRoute('GET|POST',    '/api/enotf/session-status',         'enotf/session-status.php',         $auth);
$legacyRoute('POST',        '/api/enotf/session-update',         'enotf/session-update.php',         $auth);
$legacyRoute('GET|POST',    '/api/enotf/sync-status',            'enotf/sync-status.php',            $auth);
// eNOTF-POI-Endpoints
$legacyRoute('GET|POST',    '/api/enotf/poi/poi-search',         'enotf/poi/poi-search.php',         $auth);
$legacyRoute('POST',        '/api/enotf/poi/save-field',         'enotf/poi/save-field.php',         $auth);
// eNOTF-Share-Endpoints (Protokoll-Übergabe zwischen Fahrzeugen)
$legacyRoute('POST',        '/api/enotf/share/accept-request',      'enotf/share/accept-request.php',      $auth);
$legacyRoute('GET',         '/api/enotf/share/check-requests',      'enotf/share/check-requests.php',      $auth);
$legacyRoute('GET',         '/api/enotf/share/get-available-vehicles', 'enotf/share/get-available-vehicles.php', $auth);
$legacyRoute('GET',         '/api/enotf/share/get-own-protocols',   'enotf/share/get-own-protocols.php',   $auth);
$legacyRoute('POST',        '/api/enotf/share/reject-request',      'enotf/share/reject-request.php',      $auth);
$legacyRoute('POST',        '/api/enotf/share/send-request',        'enotf/share/send-request.php',        $auth);
// Legacy-Aliase (alte Redirect-Stubs)
$router->post('/api/enotf-billing.php',         $legacy('enotf/billing.php'),         $auth);
$router->post('/api/enotf-delete-protocol.php', $legacy('enotf/delete-protocol.php'), $auth);
$router->post('/api/enotf-patient-sync.php',    $legacy('enotf/patient-sync.php'),    $auth);
$router->get( '/api/enotf-sync-status.php',     $legacy('enotf/sync-status.php'),     $auth);

// ============================================================================
//  Federation (Server-to-Server, eigener Auth-Mechanismus im Legacy-Code —
//  kein Router-Middleware, die Files prüfen selbst via FederationMiddleware)
// ============================================================================
$legacyRoute('GET|POST', '/api/federation/enotf',          'federation/enotf.php',          $public);
$legacyRoute('GET|POST', '/api/federation/fire-incidents', 'federation/fire-incidents.php', $public);
$legacyRoute('GET|POST', '/api/federation/handshake',      'federation/handshake.php',      $public);
$legacyRoute('GET|POST', '/api/federation/pair',           'federation/pair.php',           $public);
$legacyRoute('GET|POST', '/api/federation/personnel',      'federation/personnel.php',      $public);

// ============================================================================
//  Fire-Incident-API (Admin-UI, Session-Auth)
// ============================================================================
$legacyRoute('POST|DELETE', '/api/fire/bulk-delete-empty', 'fire/bulk-delete-empty.php', $auth);
$legacyRoute('GET|POST',    '/api/fire/lagekarte',         'fire/lagekarte.php',         $auth);
$legacyRoute('GET|POST',    '/api/fire/status',            'fire/status.php',            $auth);

// ============================================================================
//  Hospitals
// ============================================================================
$legacyRoute('GET',  '/api/hospitals/availability-get',    'hospitals/availability-get.php',    $auth);
$legacyRoute('POST', '/api/hospitals/availability-update', 'hospitals/availability-update.php', $auth);
// Legacy-Aliase
$router->get( '/api/hospital-availability-get.php',    $legacy('hospitals/availability-get.php'),    $auth);
$router->post('/api/hospital-availability-update.php', $legacy('hospitals/availability-update.php'), $auth);

// ============================================================================
//  Klinik-Code
// ============================================================================
$legacyRoute('POST', '/api/klinik/generate-code', 'klinik/generate-code.php', $auth);
$router->post('/api/generate-klinikcode.php', $legacy('klinik/generate-code.php'), $auth);

// ============================================================================
//  Knowledgebase (config-gated: KB_PUBLIC_ACCESS — Auth außer Flag=true)
// ============================================================================
$kbAuth = [new AuthMiddleware('KB_PUBLIC_ACCESS', invert: true)];
$legacyRoute('GET', '/api/knowledgebase/categories', 'knowledgebase/categories.php', $kbAuth);
$legacyRoute('GET', '/api/knowledgebase/search',     'knowledgebase/search.php',     $kbAuth);
$legacyRoute('GET', '/api/knowledgebase/tags',       'knowledgebase/tags.php',       $kbAuth);

// ============================================================================
//  MANV (Massenanfall)
// ============================================================================
$legacyRoute('GET|POST', '/api/manv/api', 'manv/api.php', $auth);
$router->match(['GET', 'POST'], '/api/manv-api.php', $legacy('manv/api.php'), $auth);

// ============================================================================
//  Personnel (Mitarbeiter-Admin-UI)
// ============================================================================
$legacyRoute('GET',  '/api/personnel/check-dienstnr-legacy', 'personnel/check-dienstnr-legacy.php', $auth);
$legacyRoute('GET',  '/api/personnel/check-dienstnr',        'personnel/check-dienstnr.php',        $auth);
$legacyRoute('POST', '/api/personnel/generate-invite',       'personnel/generate-invite.php',       $auth);
$legacyRoute('GET|POST', '/api/personnel/profile-comments', 'personnel/profile-comments.php',       $auth);
$legacyRoute('GET|POST', '/api/personnel/profile-logs',     'personnel/profile-logs.php',           $auth);
$legacyRoute('POST', '/api/personnel/update-profile',        'personnel/update-profile.php',        $auth);
$legacyRoute('POST', '/api/personnel/upload-pfp',            'personnel/upload-pfp.php',            $auth);

// ============================================================================
//  POIs (Point-of-Interest Admin)
// ============================================================================
$legacyRoute('POST', '/api/pois/departments-sort', 'pois/departments-sort.php', $auth);

// ============================================================================
//  System-Admin-API
// ============================================================================
$legacyRoute('GET',  '/api/system/composer-status',    'system/composer-status.php',    $auth);
$legacyRoute('GET',  '/api/system/global-search',      'system/global-search.php',      $auth);
$legacyRoute('GET',  '/api/system/performance',        'system/performance.php',        $auth);
$legacyRoute('POST', '/api/system/regenerate-api-key', 'system/regenerate-api-key.php', $auth);
$legacyRoute('POST', '/api/system/theme',              'system/theme.php',              $auth);
// Legacy-Alias
$router->get('/api/composer-status.php', $legacy('system/composer-status.php'), $auth);

// ============================================================================
//  Telemetry
//   - heartbeat: X-API-Key (wird vom Hub-Server gerufen, Machine-to-Machine)
//   - background: Session-Auth (Admin-UI triggert Heartbeat manuell)
// ============================================================================
$legacyRoute('POST', '/api/telemetry/heartbeat',  'telemetry/heartbeat.php',  $apiKey);
$legacyRoute('POST', '/api/telemetry/background', 'telemetry/background.php', $auth);
// Legacy-Aliase
$router->post('/api/telemetry-heartbeat.php',  $legacy('telemetry/heartbeat.php'),  $apiKey);
$router->post('/api/telemetry-background.php', $legacy('telemetry/background.php'), $auth);

// ============================================================================
//  Vehicles
// ============================================================================
$legacyRoute('GET|POST',    '/api/vehicles/defects-handler', 'vehicles/defects-handler.php', $auth);
$legacyRoute('GET|POST',    '/api/vehicles/import-handler',  'vehicles/import-handler.php',  $auth);
$legacyRoute('GET|POST',    '/api/vehicles/tz-templates',    'vehicles/tz-templates.php',    $auth);

// ============================================================================
//  Version (Public — zeigt nur die Release-Version an)
// ============================================================================
$legacyRoute('GET', '/api/version', 'version.php', $public);
