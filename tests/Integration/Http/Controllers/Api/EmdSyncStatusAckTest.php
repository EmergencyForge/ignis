<?php

declare(strict_types=1);

namespace Tests\Integration\Http\Controllers\Api;

use App\Http\Controllers\Api\EmdSyncController;
use EmergencyForge\Http\Request;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FixtureFactory;
use Tests\IntegrationTestCase;

/**
 * Welche Status ignis der Bridge bestätigt: successful_ids für Status im
 * Protokoll oder fireTab-Einsatz, final_ids für Status, die nie dort landen.
 */
class EmdSyncStatusAckTest extends IntegrationTestCase
{
    // Der Controller öffnet selbst eine Transaktion, siehe EmdSyncFwVehicleRegressionTest.
    protected bool $useTransactions = false;

    /** @var array<string, mixed> */
    private array $rtw;
    /** @var array<string, mixed> */
    private array $lhf;
    private string $dispatchId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatchId = (string) random_int(9000000000, 9999999999);
        $this->rtw = FixtureFactory::fahrzeug([
            'name'     => 'RTW Ack-' . $this->dispatchId,
            'rd_type'  => 2,
            'veh_type' => 'RTW',
        ]);
        $this->lhf = FixtureFactory::fahrzeug([
            'name'     => 'LHF Ack-' . $this->dispatchId,
            'rd_type'  => 3,
            'veh_type' => 'LHF',
        ]);
    }

    protected function tearDown(): void
    {
        $pdo = Capsule::connection()->getPdo();
        try {
            foreach (['intra_fire_incident_vehicles', 'intra_fire_incident_sitreps', 'intra_fire_incident_log'] as $table) {
                $pdo->prepare("DELETE FROM $table WHERE incident_id IN (SELECT id FROM intra_fire_incidents WHERE incident_number = :inc)")
                    ->execute([':inc' => $this->dispatchId]);
            }
            $pdo->prepare('DELETE FROM intra_fire_incidents WHERE incident_number = :inc')
                ->execute([':inc' => $this->dispatchId]);
            $pdo->prepare('DELETE FROM intra_fire_status_queue WHERE incident_number = :inc')
                ->execute([':inc' => $this->dispatchId]);
            $pdo->prepare('DELETE FROM intra_edivi WHERE enr = :enr OR enr LIKE :pat')
                ->execute([':enr' => $this->dispatchId, ':pat' => $this->dispatchId . '_%']);
            foreach ([$this->rtw['id'] ?? 0, $this->lhf['id'] ?? 0] as $id) {
                if ($id > 0) {
                    $pdo->prepare('DELETE FROM intra_fahrzeuge WHERE id = :id')->execute([':id' => $id]);
                }
            }
        } catch (\Throwable $e) {
            fwrite(STDERR, 'Cleanup error: ' . $e->getMessage() . "\n");
        }

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sync(array $payload): array
    {
        $response = $this->resolve(EmdSyncController::class)->sync(new Request(
            method:  'POST',
            path:    '/api/emd/sync',
            rawBody: json_encode(['protocol_version' => 2] + $payload),
        ));
        $this->assertSame(200, $response->status, $response->body);

        return json_decode($response->body, true);
    }

    #[Test]
    public function status_ack_separates_landed_waiting_and_hopeless_statuses(): void
    {
        // Legt das Protokoll für den RTW und den fireTab-Einsatz mit dem LHF an
        $this->sync(['dispatch_data' => ['vehicles' => [
            ['dispatch' => (int) $this->dispatchId, 'value' => $this->rtw['name']],
            ['dispatch' => (int) $this->dispatchId, 'value' => $this->lhf['name']],
        ]]]);

        $status = fn (int $id, string $sender, string $mission) => [
            'id'             => $id,
            'mission_number' => $mission,
            'sender'         => $sender,
            'status'         => '3',
            'timestamp'      => '01.11.2026 10:05',
        ];
        $otherMission = (string) ((int) $this->dispatchId - 1);

        $body = $this->sync(['status_updates' => ['statuses' => [
            $status(1, $this->rtw['name'], $this->dispatchId),
            $status(2, $this->lhf['name'], $this->dispatchId),
            $status(3, 'Unbekannt ' . $this->dispatchId, $this->dispatchId),
            $status(4, $this->rtw['name'], $otherMission),
            $status(5, $this->rtw['name'], $this->dispatchId),
            $status(6, $this->lhf['name'], $otherMission),
        ]]]);

        // 5 bringt denselben Wert wie 1 und ändert keine Zeile, steht aber im Protokoll
        $this->assertSame([1, 2, 5], $body['status_ack']['successful_ids']);
        // 4 und 6 warten auf Protokoll oder Einsatz und bleiben in beiden Listen draußen
        $this->assertSame([3], $body['status_ack']['final_ids']);

        $s3 = Capsule::table('intra_edivi')
            ->where('enr', 'LIKE', $this->dispatchId . '%')
            ->where('fzg_transp', $this->rtw['identifier'])
            ->value('s3');
        $this->assertSame('2026-11-01 10:05:00', $s3);

        $fireStatus = Capsule::table('intra_fire_incident_vehicles as fiv')
            ->join('intra_fire_incidents as fi', 'fiv.incident_id', '=', 'fi.id')
            ->where('fi.incident_number', $this->dispatchId)
            ->where('fiv.vehicle_id', $this->lhf['id'])
            ->value('fiv.current_status');
        $this->assertSame('3', $fireStatus);

        $vehicle = Capsule::table('intra_fahrzeuge')->where('id', $this->lhf['id'])->first(['current_status', 'status_source']);
        $this->assertSame('3', $vehicle->current_status);
        $this->assertSame('incident', $vehicle->status_source);
    }

    #[Test]
    public function fire_status_from_emd_is_logged_once_and_tablet_echoes_are_swallowed(): void
    {
        $this->sync(['dispatch_data' => ['vehicles' => [
            ['dispatch' => (int) $this->dispatchId, 'value' => $this->lhf['name']],
        ]]]);
        $status = fn (int $id, string $value) => [
            'id'             => $id,
            'mission_number' => $this->dispatchId,
            'sender'         => $this->lhf['name'],
            'status'         => $value,
            'timestamp'      => '01.11.2026 10:05',
        ];

        // In EMD gedrückt, dann von der Bridge wiederholt
        $this->sync(['status_updates' => ['statuses' => [$status(1, '3')]]]);
        $this->sync(['status_updates' => ['statuses' => [$status(1, '3')]]]);
        $this->assertSame(['Status auf 3 (Einsatz übernommen) über die Leitstelle gesetzt'], $this->statusLog());

        // Am Tablet schnell 4 und 2, die Bridge hat beide abgeholt
        foreach (['4', '2'] as $value) {
            Capsule::table('intra_fire_status_queue')->insert([
                'vehicle_id'      => $this->lhf['id'],
                'vehicle_name'    => $this->lhf['name'],
                'incident_number' => $this->dispatchId,
                'new_status'      => $value,
                'delivered'       => 1,
            ]);
        }
        Capsule::table('intra_fire_incident_vehicles')->where('vehicle_id', $this->lhf['id'])->update(['current_status' => '2']);

        $body = $this->sync(['status_updates' => ['statuses' => [$status(2, '4'), $status(3, '2')]]]);
        $this->assertSame([2, 3], $body['status_ack']['successful_ids']);
        $this->assertSame('2', $this->fireStatus(), 'das Echo der 4 dreht die 2 nicht zurück');
        $this->assertCount(1, $this->statusLog());

        // Jede Tablet-Meldung schluckt nur ein Echo
        $this->sync(['status_updates' => ['statuses' => [$status(4, '4')]]]);
        $this->assertSame('4', $this->fireStatus());
        $this->assertCount(2, $this->statusLog());
    }

    private function fireStatus(): ?string
    {
        return Capsule::table('intra_fire_incident_vehicles')->where('vehicle_id', $this->lhf['id'])->value('current_status');
    }

    /** @return list<string> */
    private function statusLog(): array
    {
        return Capsule::table('intra_fire_incident_log')
            ->where('vehicle_id', $this->lhf['id'])
            ->where('action_type', 'status_changed')
            ->orderBy('id')
            ->pluck('action_description')
            ->all();
    }
}
