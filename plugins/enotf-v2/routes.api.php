<?php

declare(strict_types=1);

/**
 * eNOTF v2 — API-Routen.
 *
 * Alles JSON (JsonExceptionMiddleware). Auth ist wie bei den Web-Routen
 * config-gated (`ENOTF_REQUIRE_USER_AUTH`) statt hart wie in v1 — die
 * Crew-Session-Prüfung (Schicht 3) macht der Controller selbst, damit
 * Crews ohne User-Login den Autosave nutzen können, wenn das User-Gate
 * deaktiviert ist.
 *
 * save-fields ist Mehrfeld-fähig:
 *   POST { "enr": "...", "fields": { "spalte": wert, ... } }
 *   →    { "ok": bool, "updated": [...], "errors": {...} }
 *
 * @var \App\Http\Router $router
 */

use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\JsonExceptionMiddleware;
use Plugin\EnotfV2\Controllers\Api\PlausibilityApiController;
use Plugin\EnotfV2\Controllers\Api\ProtokollApiController;

$enotfV2ApiAuth = [JsonExceptionMiddleware::class, new AuthMiddleware('ENOTF_REQUIRE_USER_AUTH')];

$router->post('/api/enotf-v2/save-fields', [ProtokollApiController::class, 'saveFields'], $enotfV2ApiAuth);

$router->get('/api/enotf-v2/protokoll/{enr:[\w._-]+}', [ProtokollApiController::class, 'show'], $enotfV2ApiAuth);

// --- POI-Routen (Adressen, Suche) ---
$router->get('/api/enotf-v2/poi/search', [\Plugin\EnotfV2\Controllers\Api\PoiApiController::class, 'poiSearch'], $enotfV2ApiAuth);
$router->post('/api/enotf-v2/poi/save-address', [\Plugin\EnotfV2\Controllers\Api\PoiApiController::class, 'saveAddress'], $enotfV2ApiAuth);

// --- Vitalwerte- und Medikations-Routen ---

// Vitalwerte (intra_edivi_vitalparameter_einzelwerte): list gruppiert
// nach Zeitpunkt, add = mehrere Parameter je Zeitpunkt, delete = Soft-Delete
$router->get('/api/enotf-v2/vitals/{enr:[\w._-]+}', [\Plugin\EnotfV2\Controllers\Api\VitalsApiController::class, 'index'], $enotfV2ApiAuth);
$router->post('/api/enotf-v2/vitals', [\Plugin\EnotfV2\Controllers\Api\VitalsApiController::class, 'add'], $enotfV2ApiAuth);
$router->post('/api/enotf-v2/vitals/delete', [\Plugin\EnotfV2\Controllers\Api\VitalsApiController::class, 'delete'], $enotfV2ApiAuth);

// Medikation (JSON-Feld intra_edivi.medis, v1-Format inkl. timestamp);
// add/delete prüfen anders als v1 die Freigabe-Sperre
$router->get('/api/enotf-v2/medis/{enr:[\w._-]+}', [\Plugin\EnotfV2\Controllers\Api\MedisApiController::class, 'index'], $enotfV2ApiAuth);
$router->post('/api/enotf-v2/medis', [\Plugin\EnotfV2\Controllers\Api\MedisApiController::class, 'add'], $enotfV2ApiAuth);
$router->post('/api/enotf-v2/medis/delete', [\Plugin\EnotfV2\Controllers\Api\MedisApiController::class, 'delete'], $enotfV2ApiAuth);

// --- Plausibilitäts-Routen ---

$router->get('/api/enotf-v2/plausibility/{enr:[\w._-]+}', [PlausibilityApiController::class, 'show'], $enotfV2ApiAuth);

// --- Crew-Session-Routen ---

// check-vehicle-session: v2-Gegenstück zum v1-Endpoint (der hängt hinter
// hartem User-Auth) — die Login-Seite pollt hierüber beim Fahrzeugwechsel,
// ob schon eine Crew angemeldet ist (Join-Panel).
$router->get('/api/enotf-v2/check-vehicle-session', [\Plugin\EnotfV2\Controllers\Api\SessionApiController::class, 'checkVehicleSession'], $enotfV2ApiAuth);

// check-conflict: Konfliktprüfung vor dem Anlegen (v1-Pendant hart
// auth-gated) — genutzt vom Create-Formular.
$router->post('/api/enotf-v2/check-conflict', [\Plugin\EnotfV2\Controllers\Api\SessionApiController::class, 'checkConflict'], $enotfV2ApiAuth);

// delete-protocol: Soft-Delete durch die Crew (Overview-Swipe) —
// Semantik wie v1 (403 bei Leitstellen-Protokollen), Crew-Session-Pflicht
// prüft der Controller.
$router->post('/api/enotf-v2/delete-protocol', [ProtokollApiController::class, 'deleteProtocol'], $enotfV2ApiAuth);

// delete-vehicle-session: aktive Crew-Session eines Fahrzeugs beenden
// („Session löschen" auf der Login-Seite, dort noch ohne eigene Session).
$router->post('/api/enotf-v2/delete-vehicle-session', [\Plugin\EnotfV2\Controllers\Api\SessionApiController::class, 'deleteVehicleSession'], $enotfV2ApiAuth);

// session-status/session-update: 10s-Live-Sync der Crew-Session
// (session-sync.js auf Protokollseiten + Overview) — Redirect bei
// deaktivierter Session, Crew-Änderungen in die PHP-Session ziehen.
$router->get('/api/enotf-v2/session-status', [\Plugin\EnotfV2\Controllers\Api\SessionApiController::class, 'sessionStatus'], $enotfV2ApiAuth);
$router->post('/api/enotf-v2/session-update', [\Plugin\EnotfV2\Controllers\Api\SessionApiController::class, 'sessionUpdate'], $enotfV2ApiAuth);

// --- Topbar-Sync-Routen ---

// v2-Gegenstücke zu v1 sync-status/patient-sync (dort hart auth-gated):
// die Topbar pollt sync-status alle 10s (Leitstellen-Icon + pat_synced),
// patient-sync markiert die Patientendaten zum Senden (pat_synced = 2).
$router->get('/api/enotf-v2/sync-status', [\Plugin\EnotfV2\Controllers\Api\SyncApiController::class, 'syncStatus'], $enotfV2ApiAuth);
$router->post('/api/enotf-v2/patient-sync', [\Plugin\EnotfV2\Controllers\Api\SyncApiController::class, 'patientSync'], $enotfV2ApiAuth);

// --- Share-Routen ---

// Protokoll-Übergabe zwischen Fahrzeugen (v2-Gegenstücke zu den
// v1-Endpoints unter /api/enotf/share/*): Senden legt eine pending
// Anfrage an, das Zielfahrzeug pollt check-requests und übernimmt die
// Daten per accept-request als Merge in ein eigenes offenes Protokoll
// oder als neues Protokoll. Crew-Session-Pflicht prüft der Controller.
$router->get('/api/enotf-v2/share/get-available-vehicles', [\Plugin\EnotfV2\Controllers\Api\ShareApiController::class, 'getAvailableVehicles'], $enotfV2ApiAuth);
$router->get('/api/enotf-v2/share/check-requests',         [\Plugin\EnotfV2\Controllers\Api\ShareApiController::class, 'checkRequests'],        $enotfV2ApiAuth);
$router->get('/api/enotf-v2/share/get-own-protocols',      [\Plugin\EnotfV2\Controllers\Api\ShareApiController::class, 'getOwnProtocols'],      $enotfV2ApiAuth);
$router->post('/api/enotf-v2/share/send-request',          [\Plugin\EnotfV2\Controllers\Api\ShareApiController::class, 'sendRequest'],          $enotfV2ApiAuth);
$router->post('/api/enotf-v2/share/accept-request',        [\Plugin\EnotfV2\Controllers\Api\ShareApiController::class, 'acceptRequest'],        $enotfV2ApiAuth);
$router->post('/api/enotf-v2/share/reject-request',        [\Plugin\EnotfV2\Controllers\Api\ShareApiController::class, 'rejectRequest'],        $enotfV2ApiAuth);
