<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use App\MANV\MANVLage;
use App\MANV\MANVLog;
use App\MANV\MANVPatient;
use PDO;

/**
 * MANV (Massenanfall von Verletzten) — Admin-API.
 *
 * Unterstützt die sechs Aktionen: get_stats, get_patients, search_patients,
 * update_sichtung, get_lage, transport_abfahrt. Wird vom MANV-Admin-UI
 * via AJAX aufgerufen.
 */
final class MciController
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * GET|POST /api/manv/api?action=...
     */
    public function handle(Request $request): Response
    {
        $action = (string) ($request->query['action'] ?? $request->post['action'] ?? '');
        $lageIdParam = $request->query['lage_id'] ?? $request->post['lage_id'] ?? null;
        $lageId = $lageIdParam !== null ? (int) $lageIdParam : null;

        $manvLage    = new MANVLage($this->pdo);
        $manvPatient = new MANVPatient($this->pdo);
        $manvLog     = new MANVLog($this->pdo);

        try {
            return match ($action) {
                'get_stats'         => $this->getStats($manvLage, $lageId),
                'get_patients'      => $this->getPatients($manvPatient, $lageId, (string) ($request->query['kategorie'] ?? '')),
                'search_patients'   => $this->searchPatients($manvPatient, $lageId, (string) ($request->query['search'] ?? '')),
                'update_sichtung'   => $this->updateSichtung($request, $manvPatient),
                'get_lage'          => $this->getLage($manvLage, $lageId),
                'transport_abfahrt' => $this->transportAbfahrt($request, $manvLog),
                default             => Response::json(['success' => false, 'error' => 'Ungültige Aktion'], 400),
            };
        } catch (\Throwable $e) {
            Logger::error('MANV-API: Fehler', ['error' => $e->getMessage(), 'action' => $action]);
            return Response::json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    private function getStats(MANVLage $manvLage, ?int $lageId): Response
    {
        if ($lageId === null) {
            return Response::json(['success' => false, 'error' => 'Lage-ID fehlt'], 400);
        }
        return Response::json(['success' => true, 'data' => $manvLage->getStatistics($lageId)]);
    }

    private function getPatients(MANVPatient $manvPatient, ?int $lageId, string $kategorie): Response
    {
        if ($lageId === null) {
            return Response::json(['success' => false, 'error' => 'Lage-ID fehlt'], 400);
        }
        $patienten = $manvPatient->getByLage($lageId, $kategorie !== '' ? $kategorie : null);
        return Response::json(['success' => true, 'data' => $patienten]);
    }

    private function searchPatients(MANVPatient $manvPatient, ?int $lageId, string $searchTerm): Response
    {
        if ($lageId === null) {
            return Response::json(['success' => false, 'error' => 'Lage-ID fehlt'], 400);
        }
        return Response::json([
            'success' => true,
            'data'    => $manvPatient->search($lageId, $searchTerm),
        ]);
    }

    private function updateSichtung(Request $request, MANVPatient $manvPatient): Response
    {
        if (strtoupper($request->method) !== 'POST') {
            return Response::json(['success' => false, 'error' => 'Ungültige Anfrage'], 400);
        }

        $postData  = $request->json() ?? [];
        $patientId = $postData['patient_id'] ?? null;
        $kategorie = $postData['kategorie']  ?? null;

        if (!$patientId || !$kategorie) {
            return Response::json(['success' => false, 'error' => 'Fehlende Parameter'], 400);
        }

        $manvPatient->updateSichtung(
            (int) $patientId,
            (string) $kategorie,
            $_SESSION['user_id'] ?? null
        );

        return Response::json([
            'success' => true,
            'message' => 'Sichtungskategorie aktualisiert',
        ]);
    }

    private function getLage(MANVLage $manvLage, ?int $lageId): Response
    {
        if ($lageId === null) {
            return Response::json(['success' => false, 'error' => 'Lage-ID fehlt'], 400);
        }
        $lage = $manvLage->getById($lageId);
        if (!$lage) {
            return Response::json(['success' => false, 'error' => 'Lage nicht gefunden'], 404);
        }
        return Response::json(['success' => true, 'data' => $lage]);
    }

    private function transportAbfahrt(Request $request, MANVLog $manvLog): Response
    {
        if (strtoupper($request->method) !== 'POST') {
            return Response::json(['success' => false, 'error' => 'Ungültige Anfrage'], 400);
        }

        $patientId = (int) ($request->post['patient_id'] ?? 0);
        if ($patientId <= 0) {
            return Response::json(['success' => false, 'error' => 'Patient-ID fehlt'], 400);
        }

        $stmt = $this->pdo->prepare(
            "SELECT manv_lage_id, patienten_nummer, transportmittel_rufname FROM intra_manv_patienten WHERE id = ?"
        );
        $stmt->execute([$patientId]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$patient) {
            return Response::json(['success' => false, 'error' => 'Patient nicht gefunden'], 404);
        }

        $this->pdo->prepare("
            UPDATE intra_manv_patienten
            SET transport_abfahrt = NOW(),
                geaendert_von = ?,
                geaendert_am = NOW()
            WHERE id = ?
        ")->execute([
            $_SESSION['user_id'] ?? null,
            $patientId,
        ]);

        // Fahrzeug aus Ressourcen entfernen, falls vorhanden
        if ($patient['transportmittel_rufname']) {
            $deleteStmt = $this->pdo->prepare("
                DELETE FROM intra_manv_ressourcen
                WHERE manv_lage_id = ? AND bezeichnung = ?
                LIMIT 1
            ");
            $deleteStmt->execute([
                (int) $patient['manv_lage_id'],
                $patient['transportmittel_rufname'],
            ]);

            if ($deleteStmt->rowCount() > 0) {
                $manvLog->log(
                    (int) $patient['manv_lage_id'],
                    'ressource_abgefahren',
                    'Fahrzeug ' . $patient['transportmittel_rufname'] . ' mit Patient abgefahren',
                    $_SESSION['user_id']  ?? null,
                    $_SESSION['username'] ?? null,
                    'ressource',
                    null
                );
            }
        }

        $manvLog->log(
            (int) $patient['manv_lage_id'],
            'patient_abfahrt',
            'Patient ' . $patient['patienten_nummer'] . ' ist abgefahren',
            $_SESSION['user_id']  ?? null,
            $_SESSION['username'] ?? null,
            'patient',
            $patientId
        );

        return Response::json([
            'success' => true,
            'message' => 'Patient als abgefahren markiert',
        ]);
    }
}
