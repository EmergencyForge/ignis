<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AmbSkill;
use App\Models\FdSkill;
use App\Models\Personnel;
use App\Models\Rank;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die fireTab-App-Seiten behalten ihre eigene Hülle fürs Tablet und nehmen
 * nur die ignis-Bausteine: Fahrzeug-Login und Anlegen mit ignis-Selects,
 * die Register des Einsatzes mit ignis-Tabellen, die ASU-Trupps mit
 * Knopfgruppe und ignis-Fortschrittsbalken.
 */
final class FiretabAppTest extends FeatureTestCase
{
    /** @var array{id:int,name:string,identifier:string,rd_type:int,veh_type:string} */
    private array $vehicle;
    private Personnel $person;

    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user(['full_admin' => true]);
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);

        $this->vehicle = FixtureFactory::fahrzeug(['name' => 'Florian Test 1/44/1', 'rd_type' => 3]);

        $rank = new Rank();
        $rank->name = 'Rang_' . uniqid(); $rank->name_m = $rank->name; $rank->name_w = $rank->name; $rank->priority = 10; $rank->archive = false;
        $rank->save();
        $rd = new AmbSkill();
        $rd->name = 'Keine_' . uniqid(); $rd->name_m = $rd->name; $rd->name_w = $rd->name; $rd->priority = 0; $rd->none = true;
        $rd->save();
        $fw = new FdSkill();
        $fw->name = 'Keine_' . uniqid(); $fw->shortname = 'KE'; $fw->name_m = $fw->name; $fw->name_w = $fw->name; $fw->priority = 0; $fw->none = true;
        $fw->save();

        $this->person = new Personnel();
        $this->person->fullname = 'Fiona Feuer'; $this->person->dienstnr = 'FW-1'; $this->person->gebdatum = new \DateTime('1990-01-01'); $this->person->geschlecht = 1;
        $this->person->einstdatum = new \DateTime('2024-01-01'); $this->person->dienstgrad = $rank->id; $this->person->qualird = $rd->id; $this->person->qualifw2 = $fw->id;
        $this->person->save();
    }

    private function loginVehicle(): void
    {
        $_SESSION['einsatz_vehicle_id']     = $this->vehicle['id'];
        $_SESSION['einsatz_vehicle_name']   = $this->vehicle['name'];
        $_SESSION['einsatz_operator_id']    = $this->person->id;
        $_SESSION['einsatz_operator_name']  = $this->person->fullname;
    }

    private function incident(): int
    {
        return (int) Capsule::table('intra_fire_incidents')->insertGetId([
            'incident_number' => 'F-2026-001',
            'location'        => 'Musterstraße 1',
            'keyword'         => 'B2 Wohnungsbrand',
            'started_at'      => '2026-03-01 10:00:00',
            'leader_id'       => $this->person->id,
            'status'          => 0,
            'finalized'       => 0,
        ]);
    }

    #[Test]
    public function fahrzeug_login_und_einsatz_anlegen(): void
    {
        $login = $this->get('/firetab/login-vehicle');
        $this->assertOk($login);
        $this->assertBodyContains('<select name="vehicle_id" id="vehicleSelect" class="ignis-input" required data-custom-dropdown="true"', $login);
        $this->assertBodyContains('Florian Test 1/44/1', $login);
        $this->assertBodyNotContains('form-select', $login);

        $this->loginVehicle();
        $create = $this->get('/firetab/create');
        $this->assertOk($create);
        $this->assertBodyContains('<select name="leader_id" class="ignis-input" required data-custom-dropdown="true"', $create);
        $this->assertBodyNotContains('form-select', $create);
        $this->assertBodyNotContains('enotf-dropdown-container.form-select', $create);
    }

    #[Test]
    public function einsatz_register_mit_ignis_tabellen(): void
    {
        $this->loginVehicle();
        $id = $this->incident();

        $stammdaten = $this->get('/firetab/view', ['query' => ['id' => (string) $id, 'tab' => 'stammdaten']]);
        $this->assertOk($stammdaten);
        $this->assertBodyContains('<select class="ignis-input" name="edit_leader_id" data-custom-dropdown="true"', $stammdaten);

        $fahrzeuge = $this->get('/firetab/view', ['query' => ['id' => (string) $id, 'tab' => 'fahrzeuge']]);
        $this->assertOk($fahrzeuge);
        $this->assertBodyContains('Noch keine Fahrzeuge hinzugefügt', $fahrzeuge);
        $this->assertBodyContains('<select name="vehicle_id" class="ignis-input" data-custom-dropdown="true"', $fahrzeuge);

        Capsule::table('intra_fire_incident_log')->insert([
            ['incident_id' => $id, 'action_type' => 'created', 'action_description' => 'Einsatz angelegt', 'operator_id' => $this->person->id, 'vehicle_id' => $this->vehicle['id']],
            ['incident_id' => $id, 'action_type' => 'marker_deleted', 'action_description' => 'Marker entfernt', 'operator_id' => null, 'vehicle_id' => null],
        ]);
        $log = $this->get('/firetab/view', ['query' => ['id' => (string) $id, 'tab' => 'log']]);
        $this->assertOk($log);
        $this->assertBodyContains('<table class="ignis-table" id="table-incident-log">', $log);
        $this->assertBodyContains('<span class="ignis-chip ignis-chip--ok">', $log);
        $this->assertBodyContains('<span class="ignis-chip ignis-chip--danger">', $log);
        $this->assertBodyNotContains('system-badge', $log);
        $this->assertBodyNotContains('table-hover', $log);

        $karte = $this->get('/firetab/view', ['query' => ['id' => (string) $id, 'tab' => 'lagekarte']]);
        $this->assertOk($karte);
        $this->assertBodyContains('<table class="ignis-table" id="table-map-markers">', $karte);
        $this->assertBodyContains('<table class="ignis-table" id="table-map-zones">', $karte);
        $this->assertBodyNotContains('table-striped', $karte);

        $asu = $this->get('/firetab/asu', ['query' => ['id' => (string) $id]]);
        $this->assertOk($asu);
        $this->assertBodyContains('<div class="ignis-btn-group">', $asu);
        $this->assertBodyContains('<div class="ignis-progress asu-progress relative mt-2">', $asu);
        $this->assertBodyNotContains('btn-group-sm', $asu);
    }
}
