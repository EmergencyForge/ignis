<?php

declare(strict_types=1);

namespace Plugin\Enotf\Crew\Controllers\Api;

use App\Auth\Permissions;
use App\Logging\Logger;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use Plugin\Enotf\Crew\Models\Edivi;
use Plugin\Enotf\Crew\Policies\CrewPolicy;
use Plugin\Enotf\Crew\Support\ProtokollAccessGuard;

/**
 * Sync-API für die v2-Topbar.
 *
 *   GET  /api/enotf/sync-status?enr=...
 *     → { "success": true, "pat_synced": int|null, "last_emd_sync": "Y-m-d H:i:s"|null }
 *     pat_synced des Protokolls plus letzter EMD-Sync-Zeitpunkt aus
 *     storage/last_emd_sync.txt (schreibt der EmdSyncController bei
 *     jedem Leitstellen-Sync). Die Topbar pollt alle 10s und färbt
 *     das Leitstellen-Icon (rot ab 120s ohne Sync).
 *
 *   POST /api/enotf/patient-sync   JSON: { "enr": "..." }
 *     → markiert die Patientendaten zum Senden (pat_synced = 2), der
 *     nächste Vehicle-Sync nimmt sie mit. Wie v1 ohne Freigabe-Sperre,
 *     pat_synced ist Sync-Metadatum, kein Protokollinhalt.
 *
 * v2-Gegenstücke zu den v1-Endpoints in Api\EnotfController: gleiche
 * Antwortstruktur, aber hinter dem config-gated v2-Auth statt hartem
 * User-Auth, damit Crews ohne User-Login die Topbar-Anzeigen bekommen.
 */
final class SyncApiController
{
    /**
     * GET /api/enotf/sync-status: pat_synced + letzter EMD-Sync.
     */
    public function syncStatus(Request $request): Response
    {
        if (!CrewPolicy::hasCrewSession() && !CrewPolicy::viewModule()) {
            return Response::json(['success' => false, 'error' => 'Nicht angemeldet'], 401);
        }

        $enr = trim((string) ($request->query['enr'] ?? ''));

        $response = [
            'success'       => true,
            'pat_synced'    => null,
            'last_emd_sync' => null,
        ];

        if ($enr !== '') {
            $row = Edivi::where('enr', $enr)->first(['pat_synced', 'fzg_transp', 'fzg_na', 'enr']);
            if ($row !== null) {
                if (!ProtokollAccessGuard::canRead($row->getAttributes())) {
                    return Response::json(['success' => false, 'error' => 'Kein Zugriff auf dieses Protokoll'], 403);
                }
                $response['pat_synced'] = (int) $row->pat_synced;
            }
        }

        // Projekt-Root (…/plugins/enotf/src/Crew/Controllers/Api → 6 Ebenen hoch)
        $syncFile = dirname(__DIR__, 6) . '/storage/last_emd_sync.txt';
        if (is_file($syncFile)) {
            $response['last_emd_sync'] = trim((string) file_get_contents($syncFile));
        }

        return Response::json($response);
    }

    /**
     * POST /api/enotf/patient-sync: Patientendaten zum Senden markieren.
     */
    public function patientSync(Request $request): Response
    {
        if (!CrewPolicy::hasCrewSession() && !Permissions::check(['admin', 'edivi.edit'])) {
            return Response::json(['success' => false, 'error' => 'Nicht angemeldet'], 401);
        }

        $data = $request->json();
        $enr  = isset($data['enr']) && is_string($data['enr']) ? trim($data['enr']) : '';
        if ($enr === '') {
            return Response::json(['success' => false, 'error' => 'Ungültige Anfrage'], 400);
        }

        try {
            $row = Edivi::where('enr', $enr)
                ->first(['pat_vorname', 'pat_nachname', 'pat_synced', 'fzg_transp', 'fzg_na', 'enr']);

            if ($row === null) {
                return Response::json(['success' => false, 'error' => 'Protokoll nicht gefunden'], 404);
            }
            if (!ProtokollAccessGuard::canWrite($row->getAttributes())) {
                return Response::json(['success' => false, 'error' => 'Kein Zugriff auf dieses Protokoll'], 403);
            }
            if (empty($row['pat_vorname']) && empty($row['pat_nachname'])) {
                return Response::json(['success' => false, 'error' => 'Keine Patientendaten vorhanden'], 400);
            }

            Edivi::where('enr', $enr)->update(['pat_synced' => 2]);

            return Response::json([
                'success'    => true,
                'pat_synced' => 2,
                'message'    => 'Patientendaten zum Senden markiert',
            ]);
        } catch (\PDOException $e) {
            Logger::error('eNOTF: patient-sync Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'error' => 'Datenbankfehler'], 500);
        }
    }
}
