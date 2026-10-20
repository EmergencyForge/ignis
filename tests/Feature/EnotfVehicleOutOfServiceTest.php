<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Session\SessionManager;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Ein Defekt, der das Fahrzeug nicht einsatzklar lässt, stellt es außer
 * Dienst (active = 0). Die Fahrzeuginfo zeigte danach „Fahrzeug nicht
 * gefunden“; jetzt endet die Anmeldung auf dem Fahrzeug mit einem Hinweis.
 */
final class EnotfVehicleOutOfServiceTest extends FeatureTestCase
{
    /** @var array{id:int,name:string,identifier:string,rd_type:int,veh_type:string} */
    private array $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        SessionManager::setPinVerified(true);

        $this->vehicle = FixtureFactory::fahrzeug(['rd_type' => 2]);
        FixtureFactory::crewLogin($this->vehicle['identifier'], ['fahrer' => ['name' => 'Erika Muster', 'quali' => 'NotSan']]);
    }

    #[Test]
    public function ein_aktives_fahrzeug_zeigt_seine_fahrzeuginfo(): void
    {
        $page = $this->get('/enotf/fahrzeuginfo');

        $this->assertOk($page);
        $this->assertBodyContains('Defekt melden', $page);
    }

    #[Test]
    public function ein_ausser_dienst_gestelltes_fahrzeug_beendet_die_anmeldung(): void
    {
        Capsule::table('intra_fahrzeuge')->where('id', $this->vehicle['id'])->update(['active' => 0]);

        $this->assertRedirect($this->get('/enotf/fahrzeuginfo'), '/enotf/loggedout?grund=ausser-dienst');
        $this->assertFalse(SessionManager::isEnotfActive());
        $this->assertFalse(Capsule::table('intra_enotf_sessions')
            ->where('vehicle_identifier', $this->vehicle['identifier'])->where('active', 1)->exists());

        $hinweis = $this->get('/enotf/loggedout', ['query' => ['grund' => 'ausser-dienst']]);
        $this->assertOk($hinweis);
        $this->assertBodyContains('Das Fahrzeug ist außer Dienst', $hinweis);
        $this->assertBodyNotContains('Sie sind nicht angemeldet!', $hinweis);
    }
}
