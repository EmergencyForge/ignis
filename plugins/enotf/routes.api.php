<?php

declare(strict_types=1);

/**
 * eNOTF: API-Routen (session-basiert).
 *
 * Alle Protokoll-Endpoints laufen über den Api\EnotfController; dazu
 * kommen Hospitals (Verfügbarkeiten), Klinik-Codes, POI-Verwaltung und
 * die POI-Hover-Card.
 *
 * @var \EmergencyForge\Http\Router $router
 */

use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use EmergencyForge\Http\Middleware\JsonExceptionMiddleware;
use Plugin\Enotf\Controllers\Api\EnotfController;
use Plugin\Enotf\Controllers\Api\HospitalAvailabilityController;
use Plugin\Enotf\Controllers\Api\KlinikCodeController;
use Plugin\Enotf\Controllers\Api\PoiCardController;
use Plugin\Enotf\Controllers\Api\PoiDepartmentsController;
use Plugin\Enotf\Http\CrewSessionMiddleware;

$enotfApiAuth = [JsonExceptionMiddleware::class, new AuthMiddleware()];

// Konto nur, wenn ENOTF_REQUIRE_USER_AUTH es verlangt, wie bei den Webseiten.
// Login-Seite: dort gibt es noch keine Crew-Sitzung.
$enotfLoginAuth = [JsonExceptionMiddleware::class, new AuthMiddleware('ENOTF_REQUIRE_USER_AUTH')];
$enotfCrewAuth  = [...$enotfLoginAuth, CrewSessionMiddleware::class];

$enotfHandler = fn (string $method) => [EnotfController::class, $method];

// ── Refactored Endpoints ──
// Pollt die öffentliche Ankunftstafel (/enotf/schnittstelle), ohne Anmeldung
// wie die Seite selbst. Liefert nichts, was die Seite nicht schon zeigt.
$router->match(['GET', 'POST'], '/api/enotf/prereg',         $enotfHandler('prereg'),              [JsonExceptionMiddleware::class]);

$router->match(['POST', 'DELETE'], '/api/enotf/delete-vehicle-session',     $enotfHandler('deleteVehicleSession'), $enotfLoginAuth);

$router->match(['GET'], '/api/enotf/sync-status',     $enotfHandler('syncStatus'), $enotfCrewAuth);

$router->match(['POST'], '/api/enotf/session-update',     $enotfHandler('sessionUpdate'), $enotfCrewAuth);

$router->match(['GET'], '/api/enotf/check-vehicle-session',     $enotfHandler('checkVehicleSession'), $enotfLoginAuth);

$router->match(['GET'], '/api/enotf/session-status',     $enotfHandler('sessionStatus'), $enotfCrewAuth);

$router->match(['GET', 'POST'], '/api/enotf/poi/poi-search',     $enotfHandler('poiSearch'), $enotfCrewAuth);

$router->match(['GET'], '/api/enotf/share/get-available-vehicles',     $enotfHandler('shareGetAvailableVehicles'), $enotfCrewAuth);

$router->match(['POST'], '/api/enotf/check-conflict',     $enotfHandler('checkConflict'), $enotfCrewAuth);

$router->match(['POST'], '/api/enotf/patient-sync',     $enotfHandler('patientSync'), $enotfCrewAuth);

$router->match(['POST'], '/api/enotf/poi/save-field',     $enotfHandler('poiSaveField'), $enotfCrewAuth);

$router->match(['POST', 'DELETE'], '/api/enotf/delete-protocol',     $enotfHandler('deleteProtocol'), $enotfCrewAuth);

$router->match(['GET'],  '/api/enotf/share/check-requests',       $enotfHandler('shareCheckRequests'),    $enotfCrewAuth);
$router->match(['GET'],  '/api/enotf/share/get-own-protocols',    $enotfHandler('shareGetOwnProtocols'),  $enotfCrewAuth);
$router->match(['POST'], '/api/enotf/share/reject-request',       $enotfHandler('shareRejectRequest'),    $enotfCrewAuth);
$router->match(['POST'], '/api/enotf/share/send-request',         $enotfHandler('shareSendRequest'),      $enotfCrewAuth);

$router->match(['GET', 'POST', 'DELETE'], '/api/enotf/bulk-delete-empty',     $enotfHandler('bulkDeleteEmpty'), $enotfApiAuth);

$router->match(['POST'],        '/api/enotf/save-fields',          $enotfHandler('saveFields'),         $enotfCrewAuth);

$router->match(['POST'],        '/api/enotf/share/accept-request',     $enotfHandler('shareAcceptRequest'), $enotfCrewAuth);

// Legacy-Aliase (alte Redirect-Stubs)
$router->post('/api/enotf-delete-protocol.php', $enotfHandler('deleteProtocol'), $enotfCrewAuth);
$router->post('/api/enotf-patient-sync.php',    $enotfHandler('patientSync'),    $enotfCrewAuth);
$router->get( '/api/enotf-sync-status.php',     $enotfHandler('syncStatus'),     $enotfCrewAuth);

// Abrechnung: ruft der FiveM-Server (ignisTab) ab, ohne Browser-Session.
// Deshalb API-Key statt Session und keine CSRF-Prüfung (CsrfMiddleware::EXEMPT).
$enotfApiKey = [JsonExceptionMiddleware::class, ApiKeyMiddleware::class];
$router->post('/api/enotf/billing',     $enotfHandler('billing'), $enotfApiKey);
$router->post('/api/enotf-billing.php', $enotfHandler('billing'), $enotfApiKey);

// ============================================================================
//  Hospitals: Verfügbarkeiten
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
$router->post('/api/klinik/generate-code',      $klinikHandler, $enotfCrewAuth);
$router->post('/api/generate-klinikcode.php',   $klinikHandler, $enotfCrewAuth);

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
