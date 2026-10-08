<?php

declare(strict_types=1);

/**
 * eNOTF: API-Routen (session-basiert).
 *
 * Protokoll, Crew-Sitzung und Teilen laufen über die Crew-Controller. Sie
 * prüfen die Crew-Sitzung selbst, damit eine Crew ohne ignis-Konto
 * arbeiten kann, solange ENOTF_REQUIRE_USER_AUTH aus ist. Dazu kommen
 * Ankunftstafel, Abrechnung, Hospitals, Klinik-Codes, POI-Verwaltung und
 * die POI-Hover-Card.
 *
 * save-fields nimmt mehrere Felder auf einmal:
 *   POST { "enr": "...", "fields": { "spalte": wert, ... } }
 *   →    { "ok": bool, "updated": [...], "errors": {...} }
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
use Plugin\Enotf\Crew\Controllers\Api\MedisApiController;
use Plugin\Enotf\Crew\Controllers\Api\PlausibilityApiController;
use Plugin\Enotf\Crew\Controllers\Api\PoiApiController;
use Plugin\Enotf\Crew\Controllers\Api\ProtokollApiController;
use Plugin\Enotf\Crew\Controllers\Api\SessionApiController;
use Plugin\Enotf\Crew\Controllers\Api\ShareApiController;
use Plugin\Enotf\Crew\Controllers\Api\SyncApiController;
use Plugin\Enotf\Crew\Controllers\Api\VitalsApiController;
use Plugin\Enotf\Http\CrewSessionMiddleware;

$enotfApiAuth = [JsonExceptionMiddleware::class, new AuthMiddleware()];

// Konto nur, wenn ENOTF_REQUIRE_USER_AUTH es verlangt, wie bei den Webseiten.
$enotfLoginAuth = [JsonExceptionMiddleware::class, new AuthMiddleware('ENOTF_REQUIRE_USER_AUTH')];
$enotfCrewAuth  = [...$enotfLoginAuth, CrewSessionMiddleware::class];

// ── Protokoll ──
$router->post('/api/enotf/save-fields', [ProtokollApiController::class, 'saveFields'], $enotfLoginAuth);
$router->get('/api/enotf/protokoll/{enr:[\w._-]+}', [ProtokollApiController::class, 'show'], $enotfLoginAuth);
// Soft-Delete durch die Crew (403 bei Leitstellen-Protokollen)
$router->post('/api/enotf/delete-protocol', [ProtokollApiController::class, 'deleteProtocol'], $enotfLoginAuth);

$router->get('/api/enotf/poi/search', [PoiApiController::class, 'poiSearch'], $enotfLoginAuth);
$router->post('/api/enotf/poi/save-address', [PoiApiController::class, 'saveAddress'], $enotfLoginAuth);

// Vitalwerte: mehrere Parameter je Zeitpunkt, delete ist ein Soft-Delete
$router->get('/api/enotf/vitals/{enr:[\w._-]+}', [VitalsApiController::class, 'index'], $enotfLoginAuth);
$router->post('/api/enotf/vitals', [VitalsApiController::class, 'add'], $enotfLoginAuth);
$router->post('/api/enotf/vitals/delete', [VitalsApiController::class, 'delete'], $enotfLoginAuth);

// Medikation im JSON-Feld intra_edivi.medis, gesperrt nach der Freigabe
$router->get('/api/enotf/medis/{enr:[\w._-]+}', [MedisApiController::class, 'index'], $enotfLoginAuth);
$router->post('/api/enotf/medis', [MedisApiController::class, 'add'], $enotfLoginAuth);
$router->post('/api/enotf/medis/delete', [MedisApiController::class, 'delete'], $enotfLoginAuth);

$router->get('/api/enotf/plausibility/{enr:[\w._-]+}', [PlausibilityApiController::class, 'show'], $enotfLoginAuth);

// ── Crew-Sitzung ──
// Die Login-Seite fragt beim Fahrzeugwechsel, ob schon eine Crew angemeldet ist.
$router->get('/api/enotf/check-vehicle-session', [SessionApiController::class, 'checkVehicleSession'], $enotfLoginAuth);
$router->post('/api/enotf/check-conflict', [SessionApiController::class, 'checkConflict'], $enotfLoginAuth);
$router->post('/api/enotf/delete-vehicle-session', [SessionApiController::class, 'deleteVehicleSession'], $enotfLoginAuth);
// 10-Sekunden-Abgleich der Crew (session-sync.js)
$router->get('/api/enotf/session-status', [SessionApiController::class, 'sessionStatus'], $enotfLoginAuth);
$router->post('/api/enotf/session-update', [SessionApiController::class, 'sessionUpdate'], $enotfLoginAuth);

// Topbar: Leitstellen-Icon und Patientendaten zum Senden markieren
$router->get('/api/enotf/sync-status', [SyncApiController::class, 'syncStatus'], $enotfLoginAuth);
$router->post('/api/enotf/patient-sync', [SyncApiController::class, 'patientSync'], $enotfLoginAuth);

// ── Teilen zwischen Fahrzeugen ──
$router->get('/api/enotf/share/get-available-vehicles', [ShareApiController::class, 'getAvailableVehicles'], $enotfLoginAuth);
$router->get('/api/enotf/share/check-requests',         [ShareApiController::class, 'checkRequests'],        $enotfLoginAuth);
$router->get('/api/enotf/share/get-own-protocols',      [ShareApiController::class, 'getOwnProtocols'],      $enotfLoginAuth);
$router->post('/api/enotf/share/send-request',          [ShareApiController::class, 'sendRequest'],          $enotfLoginAuth);
$router->post('/api/enotf/share/accept-request',        [ShareApiController::class, 'acceptRequest'],        $enotfLoginAuth);
$router->post('/api/enotf/share/reject-request',        [ShareApiController::class, 'rejectRequest'],        $enotfLoginAuth);

// ── Ankunftstafel, Prüfliste, Abrechnung ──
// Pollt die öffentliche Ankunftstafel (/enotf/schnittstelle), ohne Anmeldung
// wie die Seite selbst. Liefert nichts, was die Seite nicht schon zeigt.
$router->match(['GET', 'POST'], '/api/enotf/prereg', [EnotfController::class, 'prereg'], [JsonExceptionMiddleware::class]);

$router->match(['GET', 'POST', 'DELETE'], '/api/enotf/bulk-delete-empty', [EnotfController::class, 'bulkDeleteEmpty'], $enotfApiAuth);

// Abrechnung: ruft der FiveM-Server (ef_bridge) ab, ohne Browser-Session.
// Deshalb API-Key statt Session und keine CSRF-Prüfung (CsrfMiddleware::EXEMPT).
$enotfApiKey = [JsonExceptionMiddleware::class, ApiKeyMiddleware::class];
$router->post('/api/enotf/billing', [EnotfController::class, 'billing'], $enotfApiKey);
$router->post('/api/enotf-billing', [EnotfController::class, 'billing'], $enotfApiKey);

// ============================================================================
//  Hospitals: Verfügbarkeiten
// ============================================================================
$hospitalGet    = [HospitalAvailabilityController::class, 'get'];
$hospitalUpdate = [HospitalAvailabilityController::class, 'update'];
$router->get( '/api/hospitals/availability-get',         $hospitalGet,    $enotfApiAuth);
$router->post('/api/hospitals/availability-update',      $hospitalUpdate, $enotfApiAuth);
$router->get( '/api/hospital-availability-get',      $hospitalGet,    $enotfApiAuth);
$router->post('/api/hospital-availability-update',   $hospitalUpdate, $enotfApiAuth);

// ============================================================================
//  Klinik-Code
// ============================================================================
$klinikHandler = [KlinikCodeController::class, 'generate'];
$router->post('/api/klinik/generate-code',      $klinikHandler, $enotfCrewAuth);
$router->post('/api/generate-klinikcode',   $klinikHandler, $enotfCrewAuth);

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
