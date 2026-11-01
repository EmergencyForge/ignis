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
 * Protokoll, final_ids für Status, die nie eins finden werden.
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
        // Legt das Protokoll für den RTW an
        $this->sync(['dispatch_data' => ['vehicles' => [
            ['dispatch' => (int) $this->dispatchId, 'value' => $this->rtw['name']],
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
        ]]]);

        // 5 bringt denselben Wert wie 1 und ändert keine Zeile, steht aber im Protokoll
        $this->assertSame([1, 5], $body['status_ack']['successful_ids']);
        // 4 wartet auf sein Protokoll und bleibt in beiden Listen draußen
        $this->assertSame([2, 3], $body['status_ack']['final_ids']);

        $s3 = Capsule::table('intra_edivi')
            ->where('enr', 'LIKE', $this->dispatchId . '%')
            ->where('fzg_transp', $this->rtw['identifier'])
            ->value('s3');
        $this->assertSame('2026-11-01 10:05:00', $s3);
    }
}
