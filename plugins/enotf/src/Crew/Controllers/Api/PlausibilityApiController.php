<?php

declare(strict_types=1);

namespace Plugin\Enotf\Crew\Controllers\Api;

use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use Plugin\Enotf\Crew\Policies\CrewPolicy;
use Plugin\Enotf\Crew\Support\ConditionsService;
use Plugin\Enotf\Crew\Support\ProtokollAccessGuard;
use Plugin\Enotf\Crew\Support\ProtokollService;

/**
 * Plausibilitäts-API für den eNOTF-v2-Stepper.
 *
 *   GET /api/enotf/plausibility/{enr}
 *     Antwort: {
 *       "ok":         true,
 *       "releasable": bool,                     // keine offene Pflichtregel
 *       "openCount":  int,
 *       "open":       { "rettdaten": [{ "key": "...", "message": "..." }, ...], ... },
 *       "sections":   { "rettdaten": { "status": "partfilled", "filled": 3, "total": 9 }, ... }
 *     }
 *
 * Der Autosave-Client (assets/autosave.js) ruft den Endpoint nach jedem
 * erfolgreichen save-fields-Batch und aktualisiert damit die Füllstands-
 * Badges der Section-Navigation; abschluss.php nutzt dieselbe Antwort
 * für die offene-Punkte-Liste im Freigabe-Dialog.
 *
 * Regelquelle: ConditionsService (1:1-Port von conditions.php).
 * Auth: wie ProtokollApiController::show, Crew-Session ODER eingeloggter
 * User mit Modul-Sicht (lesender Endpoint).
 */
final class PlausibilityApiController
{
    /**
     * GET /api/enotf/plausibility/{enr}: evaluate + sectionStatus als JSON.
     */
    public function show(Request $request, string $enr): Response
    {
        if (!CrewPolicy::hasCrewSession() && !CrewPolicy::viewModule()) {
            return Response::json(['ok' => false, 'error' => 'Nicht angemeldet'], 401);
        }

        $protokoll = app(ProtokollService::class)->findByEnr($enr);
        if ($protokoll === null) {
            return Response::json(['ok' => false, 'error' => 'Protokoll nicht gefunden'], 404);
        }
        if (!ProtokollAccessGuard::canRead($protokoll)) {
            return Response::json(['ok' => false, 'error' => 'Kein Zugriff auf dieses Protokoll'], 403);
        }

        $conditions = app(ConditionsService::class);
        $open       = $conditions->evaluate($protokoll);

        $openCount = 0;
        foreach ($open as $rules) {
            $openCount += count($rules);
        }

        return Response::json([
            'ok'         => true,
            'releasable' => $openCount === 0,
            'openCount'  => $openCount,
            'open'       => (object) $open,
            'sections'   => (object) $conditions->sectionStatus($protokoll),
        ]);
    }
}
