<?php

declare(strict_types=1);

namespace Tests\Integration\Http\Controllers\Api;

use App\Http\Controllers\Api\EmdSyncController;
use App\Http\Request;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FixtureFactory;
use Tests\IntegrationTestCase;

/**
 * Regression-Test für den FW-Fahrzeug-Bug im EmdSyncController.
 *
 * Bug (aus User-Report):
 *   "FW Fahrzeug-Einsätze werden auch im eNOTF erstellt"
 *   "Es ist das 1/44/1 alarmiert nach Fahrzeugtyp feuerwehr und es wird ein
 *    eNOTF Protokoll erstellt + eins im firetab"
 *
 * Ursache: Fahrzeuge mit rd_type=3 (Feuerwehr) wurden ins $validVehicles-
 * Array aufgenommen und landeten über den else-Branch im eNOTF-Insert-Pfad
 * als Transport-Fahrzeug.
 *
 * Fix: Fahrzeuge werden nach rd_type sauber in $validVehicles (RD, 1+2)
 * und $fireVehicles (FW, 3) getrennt. eNOTF-Loop iteriert nur über
 * $validVehicles.
 *
 * Dieser Test simuliert einen gemischten Dispatch (RTW + NEF + LHF) und
 * verifiziert den DB-State nach dem Sync:
 *   - `intra_edivi` enthält EXAKT einen Eintrag für den NEF (fzg_na)
 *     und EXAKT einen für den RTW (fzg_transp)
 *   - `intra_edivi` enthält KEINEN Eintrag für den LHF
 *   - `intra_fire_incidents` enthält EXAKT einen Eintrag für den Dispatch
 *   - `intra_fire_incident_vehicles` enthält den LHF
 */
class EmdSyncFwVehicleRegressionTest extends IntegrationTestCase
{
    // PDO erlaubt keine nested Transactions. Der EmdSyncController ruft
    // intern $this->pdo->beginTransaction(), was mit unserer Test-Base-
    // Transaction kollidiert. Deshalb Isolation aus und manuelles Cleanup
    // über die eindeutig zufällige Dispatch-ID.
    protected bool $useTransactions = false;

    private array $rtw;
    private array $nef;
    private array $lhf;
    private string $dispatchId;

    protected function setUp(): void
    {
        parent::setUp();

        // Eindeutige Dispatch-ID für diesen Test — verhindert Kollisionen
        // mit echten Daten falls Transactions doch nicht rollen.
        $this->dispatchId = (string) random_int(9000000000, 9999999999);

        // Drei Fahrzeuge: NEF (rd_type=1), RTW (rd_type=2), LHF (rd_type=3)
        $this->nef = FixtureFactory::fahrzeug([
            'name'    => 'NEF Test-' . $this->dispatchId,
            'rd_type' => 1,
            'veh_type'=> 'NEF',
        ]);
        $this->rtw = FixtureFactory::fahrzeug([
            'name'    => 'RTW Test-' . $this->dispatchId,
            'rd_type' => 2,
            'veh_type'=> 'RTW',
        ]);
        $this->lhf = FixtureFactory::fahrzeug([
            'name'    => 'LHF Test-' . $this->dispatchId,
            'rd_type' => 3,
            'veh_type'=> 'LHF',
        ]);
    }

    protected function tearDown(): void
    {
        // Manuelles Cleanup (useTransactions=false). Reihenfolge respektiert
        // Foreign-Key-Constraints: abhängige Tabellen zuerst.
        $pdo = \Illuminate\Database\Capsule\Manager::connection()->getPdo();

        try {
            $dispatchId = $this->dispatchId ?? null;
            if ($dispatchId !== null) {
                $enrPattern = $dispatchId . '_%';

                // Fire-Incident-bezogene Tabellen
                $pdo->prepare("DELETE FROM intra_fire_incident_vehicles WHERE incident_id IN (SELECT id FROM intra_fire_incidents WHERE incident_number = :inc)")
                    ->execute([':inc' => $dispatchId]);
                $pdo->prepare("DELETE FROM intra_fire_incident_sitreps WHERE incident_id IN (SELECT id FROM intra_fire_incidents WHERE incident_number = :inc)")
                    ->execute([':inc' => $dispatchId]);
                $pdo->prepare("DELETE FROM intra_fire_incident_log WHERE incident_id IN (SELECT id FROM intra_fire_incidents WHERE incident_number = :inc)")
                    ->execute([':inc' => $dispatchId]);
                $pdo->prepare("DELETE FROM intra_fire_incidents WHERE incident_number = :inc")
                    ->execute([':inc' => $dispatchId]);

                // eNOTF
                $pdo->prepare("DELETE FROM intra_edivi WHERE enr = :enr OR enr LIKE :pat")
                    ->execute([':enr' => $dispatchId, ':pat' => $enrPattern]);
            }

            // Die drei Test-Fahrzeuge
            foreach ([$this->nef['id'] ?? 0, $this->rtw['id'] ?? 0, $this->lhf['id'] ?? 0] as $id) {
                if ($id > 0) {
                    $pdo->prepare("DELETE FROM intra_fahrzeuge WHERE id = :id")->execute([':id' => $id]);
                }
            }
        } catch (\Throwable $e) {
            // Cleanup-Fehler loggen, aber Test nicht fail-en
            fwrite(STDERR, "Cleanup error: " . $e->getMessage() . "\n");
        }

        parent::tearDown();
    }

    #[Test]
    public function mixed_dispatch_creates_edivi_only_for_rd_vehicles(): void
    {
        $controller = $this->resolve(EmdSyncController::class);

        $payload = [
            'protocol_version' => 2,
            'dispatch_data'    => [
                'vehicles' => [
                    ['dispatch' => (int) $this->dispatchId, 'value' => $this->nef['name']],
                    ['dispatch' => (int) $this->dispatchId, 'value' => $this->rtw['name']],
                    ['dispatch' => (int) $this->dispatchId, 'value' => $this->lhf['name']],
                ],
            ],
        ];

        $request = new Request(
            method:  'POST',
            path:    '/api/emd/sync',
            rawBody: json_encode($payload),
        );

        $response = $controller->sync($request);

        $this->assertSame(200, $response->status, 'Sync sollte erfolgreich sein. Body: ' . $response->body);
        $this->assertStringContainsString('"success":true', $response->body);

        $pdo = Capsule::connection()->getPdo();

        // ── intra_edivi: exakt 2 Einträge (NEF + RTW), KEINER für LHF ──
        $ediviStmt = $pdo->prepare("
            SELECT enr, fzg_na, fzg_transp
            FROM intra_edivi
            WHERE enr = :enr OR enr LIKE :pattern
            ORDER BY enr
        ");
        $ediviStmt->execute([
            ':enr'     => $this->dispatchId,
            ':pattern' => $this->dispatchId . '_%',
        ]);
        $ediviRows = $ediviStmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertCount(
            2,
            $ediviRows,
            'intra_edivi sollte exakt 2 Einträge enthalten (NEF + RTW), hat aber: ' . count($ediviRows)
        );

        $fzgNaValues     = array_filter(array_column($ediviRows, 'fzg_na'));
        $fzgTranspValues = array_filter(array_column($ediviRows, 'fzg_transp'));

        $this->assertContains(
            $this->nef['identifier'],
            $fzgNaValues,
            'NEF-Identifier sollte in fzg_na stehen'
        );
        $this->assertContains(
            $this->rtw['identifier'],
            $fzgTranspValues,
            'RTW-Identifier sollte in fzg_transp stehen'
        );

        // KRITISCH: LHF darf NIRGENDWO in intra_edivi auftauchen
        $allIdentifiers = array_merge($fzgNaValues, $fzgTranspValues);
        $this->assertNotContains(
            $this->lhf['identifier'],
            $allIdentifiers,
            'FW-Fahrzeug (LHF) DARF KEIN eNOTF-Protokoll bekommen — das war der Bug'
        );

        // ── intra_fire_incidents: ein Eintrag für den Dispatch ──
        $fireStmt = $pdo->prepare("
            SELECT id, incident_number
            FROM intra_fire_incidents
            WHERE incident_number = :incident
        ");
        $fireStmt->execute([':incident' => $this->dispatchId]);
        $fireIncidents = $fireStmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertCount(
            1,
            $fireIncidents,
            'intra_fire_incidents sollte exakt 1 Eintrag für den Dispatch haben'
        );

        // ── intra_fire_incident_vehicles: LHF ist dem Incident zugeordnet ──
        $fireIncidentId = (int) $fireIncidents[0]['id'];
        $vehStmt = $pdo->prepare("
            SELECT fiv.vehicle_id, f.name
            FROM intra_fire_incident_vehicles fiv
            JOIN intra_fahrzeuge f ON f.id = fiv.vehicle_id
            WHERE fiv.incident_id = :incident_id
        ");
        $vehStmt->execute([':incident_id' => $fireIncidentId]);
        $fireVehicles = $vehStmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertCount(
            1,
            $fireVehicles,
            'intra_fire_incident_vehicles sollte den LHF enthalten'
        );
        $this->assertSame(
            $this->lhf['id'],
            (int) $fireVehicles[0]['vehicle_id'],
            'Das zugeordnete Fahrzeug sollte der LHF sein'
        );
    }

    #[Test]
    public function pure_fire_dispatch_creates_only_fire_incident_no_edivi(): void
    {
        // Edge-Case: Dispatch mit AUSSCHLIESSLICH FW-Fahrzeugen darf kein
        // eNOTF-Protokoll erzeugen und muss trotzdem einen Fire-Incident
        // anlegen (wurde früher als "keine gültigen Fahrzeuge" geskippt).
        $controller = $this->resolve(EmdSyncController::class);

        $payload = [
            'protocol_version' => 2,
            'dispatch_data'    => [
                'vehicles' => [
                    ['dispatch' => (int) $this->dispatchId, 'value' => $this->lhf['name']],
                ],
            ],
        ];

        $request = new Request(
            method:  'POST',
            path:    '/api/emd/sync',
            rawBody: json_encode($payload),
        );

        $response = $controller->sync($request);
        $this->assertSame(200, $response->status);

        $pdo = Capsule::connection()->getPdo();

        // intra_edivi MUSS komplett leer für diesen Dispatch sein
        $ediviCount = (int) $pdo->query(
            "SELECT COUNT(*) FROM intra_edivi WHERE enr = {$this->dispatchId} OR enr LIKE '{$this->dispatchId}_%'"
        )->fetchColumn();
        $this->assertSame(0, $ediviCount, 'Pure FW-Dispatch darf KEIN eNOTF-Protokoll erzeugen');

        // Fire-Incident wurde trotzdem angelegt
        $fireCount = (int) $pdo->query(
            "SELECT COUNT(*) FROM intra_fire_incidents WHERE incident_number = '{$this->dispatchId}'"
        )->fetchColumn();
        $this->assertSame(1, $fireCount, 'Pure FW-Dispatch muss Fire-Incident erzeugen');
    }
}
