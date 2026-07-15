<?php

declare(strict_types=1);

/**
 * eNOTF — API-Routen (session-basiert).
 *
 * Alle Protokoll-Endpoints laufen über den Api\EnotfController; dazu
 * kommen Hospitals (Verfügbarkeiten), Klinik-Codes, POI-Verwaltung und
 * die POI-Hover-Card.
 *
 * @var \App\Http\Router $router
 */

use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\JsonExceptionMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use Plugin\Enotf\Controllers\Api\EnotfController;
use Plugin\Enotf\Controllers\Api\HospitalAvailabilityController;
use Plugin\Enotf\Controllers\Api\KlinikCodeController;
use Plugin\Enotf\Controllers\Api\PoiCardController;
use Plugin\Enotf\Controllers\Api\PoiDepartmentsController;

$enotfApiAuth = [JsonExceptionMiddleware::class, new AuthMiddleware()];

$enotfHandler = fn (string $method) => [EnotfController::class, $method];

// ── Refactored Endpoints ──
$router->match(['GET', 'POST'], '/api/enotf/prereg',         $enotfHandler('prereg'),              $enotfApiAuth);

$router->match(['POST', 'DELETE'], '/api/enotf/delete-vehicle-session',     $enotfHandler('deleteVehicleSession'), $enotfApiAuth);

$router->match(['GET'], '/api/enotf/sync-status',     $enotfHandler('syncStatus'), $enotfApiAuth);

$router->match(['POST'], '/api/enotf/session-update',     $enotfHandler('sessionUpdate'), $enotfApiAuth);

$router->match(['GET'], '/api/enotf/check-vehicle-session',     $enotfHandler('checkVehicleSession'), $enotfApiAuth);

$router->match(['GET'], '/api/enotf/session-status',     $enotfHandler('sessionStatus'), $enotfApiAuth);

$router->match(['GET', 'POST'], '/api/enotf/poi/poi-search',     $enotfHandler('poiSearch'), $enotfApiAuth);

$router->match(['GET'], '/api/enotf/share/get-available-vehicles',     $enotfHandler('shareGetAvailableVehicles'), $enotfApiAuth);

$router->match(['POST'], '/api/enotf/check-conflict',     $enotfHandler('checkConflict'), $enotfApiAuth);

$router->match(['POST'], '/api/enotf/patient-sync',     $enotfHandler('patientSync'), $enotfApiAuth);

$router->match(['POST'], '/api/enotf/poi/save-field',     $enotfHandler('poiSaveField'), $enotfApiAuth);

$router->match(['POST', 'DELETE'], '/api/enotf/delete-protocol',     $enotfHandler('deleteProtocol'), $enotfApiAuth);

$router->match(['GET'],  '/api/enotf/share/check-requests',       $enotfHandler('shareCheckRequests'),    $enotfApiAuth);
$router->match(['GET'],  '/api/enotf/share/get-own-protocols',    $enotfHandler('shareGetOwnProtocols'),  $enotfApiAuth);
$router->match(['POST'], '/api/enotf/share/reject-request',       $enotfHandler('shareRejectRequest'),    $enotfApiAuth);
$router->match(['POST'], '/api/enotf/share/send-request',         $enotfHandler('shareSendRequest'),      $enotfApiAuth);

$router->match(['POST'],         '/api/enotf/billing',              $enotfHandler('billing'),           $enotfApiAuth);

$router->match(['GET', 'POST', 'DELETE'], '/api/enotf/bulk-delete-empty',     $enotfHandler('bulkDeleteEmpty'), $enotfApiAuth);

$router->match(['POST'],        '/api/enotf/save-fields',          $enotfHandler('saveFields'),         $enotfApiAuth);

$router->match(['POST'],        '/api/enotf/share/accept-request',     $enotfHandler('shareAcceptRequest'), $enotfApiAuth);

// Legacy-Aliase (alte Redirect-Stubs)
$router->post('/api/enotf-billing.php',         $enotfHandler('billing'),        $enotfApiAuth);
$router->post('/api/enotf-delete-protocol.php', $enotfHandler('deleteProtocol'), $enotfApiAuth);
$router->post('/api/enotf-patient-sync.php',    $enotfHandler('patientSync'),    $enotfApiAuth);
$router->get( '/api/enotf-sync-status.php',     $enotfHandler('syncStatus'),     $enotfApiAuth);

// ============================================================================
//  Hospitals — Verfügbarkeiten
// ============================================================================
$hospitalGet    = [HospitalAvailabilityController::class, 'get'];
$hospitalUpdate = [HospitalAvailabilityController::class, 'update'];
$router->get( '/api/hospitals/availability-get',         $hospitalGet,    $enotfApiAuth);
$router->post('/api/hospitals/availability-update',      $hospitalUpdate, $enotfApiAuth);
$router->get( '/api/hospital-availability-get.php',      $hospitalGet,    $enotfApiAuth);
$router->post('/api/hospital-availability-update.php',   $hospitalUpdate, $enotfApiAuth);

// ============================================================================
//  Klinik-Code
// ============================================================================
$klinikHandler = [KlinikCodeController::class, 'generate'];
$router->post('/api/klinik/generate-code',      $klinikHandler, $enotfApiAuth);
$router->post('/api/generate-klinikcode.php',   $klinikHandler, $enotfApiAuth);

// ============================================================================
//  POIs (Point-of-Interest Admin)
// ============================================================================
$poiAuth = [JsonExceptionMiddleware::class, new AuthMiddleware(), new PermissionMiddleware(['admin', 'pois.manage'])];
$router->post('/api/pois/departments-sort',     [PoiDepartmentsController::class, 'updateSort'], $poiAuth);

// POI-Hover-Card (HTML-Fragment)
$router->get('/api/pois/{id:\d+}/card',
    [PoiCardController::class, 'show'],
    [new AuthMiddleware()]
);
