<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Session\SessionManager;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die eNOTF-Fahrzeuginfo meldet Mängel auch ohne ignis-Konto. Mehr als
 * melden darf eine Crew ohne Konto nicht.
 */
final class EnotfDefectReportTest extends FeatureTestCase
{
    private int $vehicleId;

    protected function setUp(): void
    {
        parent::setUp();

        $vehicle = FixtureFactory::fahrzeug();
        $this->vehicleId = $vehicle['id'];
        SessionManager::loginEnotfCrew('fahrer', 'tok', ['fahrer' => ['name' => 'Erika Muster', 'quali' => 'NotSan']], $vehicle['identifier']);
    }

    #[Test]
    public function crew_without_account_reports_a_defect(): void
    {
        $response = $this->post('/api/vehicles/defects-handler', [
            'action'           => 'create',
            'vehicle_id'       => (string) $this->vehicleId,
            'title'            => 'Blaulicht defekt',
            'category'         => 'beleuchtung',
            'vehicle_operable' => '1',
        ]);

        $this->assertStatus(200, $response);
        $this->assertTrue($this->assertJsonResponse($response)['success']);
        $this->assertSame(1, Capsule::table('intra_fahrzeuge_defects')->where('vehicle_id', $this->vehicleId)->count());
        $this->assertArrayNotHasKey('userid', $_SESSION);
    }

    #[Test]
    public function crew_without_account_cannot_list_defects(): void
    {
        $response = $this->get('/api/vehicles/defects-handler', ['query' => ['action' => 'list']]);

        $this->assertStatus(401, $response);
    }
}
