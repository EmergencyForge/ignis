<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die Formulare des FireTab lesen ihre Felder über die FormRequests in
 * plugins/firetab/src/Requests: Fahrzeug-Anmeldung, neuer Einsatz und
 * die Aktionen am Einsatz unter POST /firetab/actions.
 */
final class FiretabActionsTest extends FeatureTestCase
{
    /** @var array{id:int,name:string,identifier:string,rd_type:int,veh_type:string} */
    private array $vehicle;
    private int $personId;

    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user(['full_admin' => true]);
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);

        $this->vehicle  = FixtureFactory::fahrzeug(['rd_type' => 3]);
        $this->personId = FixtureFactory::personnel()->id;
    }

    private function loginVehicle(): void
    {
        $_SESSION['einsatz_vehicle_id']    = $this->vehicle['id'];
        $_SESSION['einsatz_vehicle_name']  = $this->vehicle['name'];
        $_SESSION['einsatz_operator_id']   = $this->personId;
        $_SESSION['einsatz_operator_name'] = 'Fiona Feuer';
    }

    private function incident(): int
    {
        return (int) Capsule::table('intra_fire_incidents')->insertGetId([
            'incident_number' => 'F-' . uniqid(),
            'location'        => 'Musterstraße 1',
            'keyword'         => 'B2 Wohnungsbrand',
            'started_at'      => '2026-03-01 10:00:00',
            'leader_id'       => $this->personId,
            'status'          => 0,
            'finalized'       => 0,
        ]);
    }

    /** @param array<string,mixed> $felder */
    private function action(int $id, string $action, array $felder = []): \EmergencyForge\Http\Response
    {
        return $this->post('/firetab/actions', ['action' => $action, 'incident_id' => (string) $id, 'return_tab' => 'stammdaten'] + $felder);
    }

    #[Test]
    public function fahrzeug_anmeldung(): void
    {
        $response = $this->post('/firetab/login-vehicle', ['vehicle_id' => '', 'operator_id' => (string) $this->personId]);
        $this->assertRedirect($response, '/firetab/login-vehicle');
        $this->assertSame('Bitte wählen Sie ein Fahrzeug aus.', $_SESSION['flash']['text'] ?? null);

        $response = $this->post('/firetab/login-vehicle', ['vehicle_id' => (string) $this->vehicle['id'], 'operator_id' => (string) $this->personId]);
        $this->assertRedirect($response, '/firetab/list');
        $this->assertSame($this->vehicle['id'], (int) $_SESSION['einsatz_vehicle_id']);
    }

    // Ein gültiger Einsatz lässt sich hier nicht anlegen: store() öffnet
    // seine Transaktion direkt am PDO, und der Test läuft schon in einer.

    #[Test]
    public function einsatz_mit_feld_als_liste_wird_abgewiesen(): void
    {
        $this->loginVehicle();
        $vorher = Capsule::table('intra_fire_incidents')->count();

        $response = $this->post('/firetab/create', ['incident_number' => ['F-1'], 'location' => 'Hauptstraße 5']);

        $this->assertOk($response);
        $this->assertBodyContains('Ungültige Eingabe.', $response);
        $this->assertSame($vorher, Capsule::table('intra_fire_incidents')->count());
    }

    #[Test]
    public function einsatz_ohne_pflichtfelder_zeigt_alle_fehler(): void
    {
        $this->loginVehicle();
        $vorher = Capsule::table('intra_fire_incidents')->count();

        $response = $this->post('/firetab/create', ['incident_number' => '', 'location' => 'Hauptstraße 5', 'keyword' => '']);

        $this->assertOk($response);
        $this->assertBodyContains('Einsatznummer ist erforderlich.', $response);
        $this->assertBodyContains('Einsatzstichwort ist erforderlich.', $response);
        $this->assertBodyContains('Einsatzleiter ist erforderlich.', $response);
        $this->assertSame($vorher, Capsule::table('intra_fire_incidents')->count());
    }

    #[Test]
    public function fahrzeug_und_lagemeldung_hinzufuegen_und_entfernen(): void
    {
        $this->loginVehicle();
        $id = $this->incident();

        $response = $this->action($id, 'add_vehicle', ['vehicle_id' => '', 'radio_name' => 'Florian Nachbar 1', 'vehicle_name' => 'HLF', 'vehicle_identifier' => '']);
        $this->assertRedirect($response, '/firetab/view?id=' . $id . '&tab=stammdaten');
        $row = Capsule::table('intra_fire_incident_vehicles')->where('incident_id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame('Florian Nachbar 1', $row->radio_name);
        $this->assertSame(1, (int) $row->from_other_org);

        $this->action($id, 'add_sitrep', ['rt_date' => '2026-10-09', 'rt_time' => '14:45', 'text' => ' Feuer aus ', 'sitrep_attached_vehicle_id' => (string) $row->id]);
        $sitrep = Capsule::table('intra_fire_incident_sitreps')->where('incident_id', $id)->first();
        $this->assertNotNull($sitrep);
        $this->assertSame('Feuer aus', $sitrep->text);
        $this->assertSame('Florian Nachbar 1', $sitrep->vehicle_radio_name);

        $this->action($id, 'remove_vehicle', ['vehicle_row_id' => (string) $row->id]);
        $this->assertFalse(Capsule::table('intra_fire_incident_vehicles')->where('id', $row->id)->exists());
    }

    #[Test]
    public function stammdaten_bericht_und_qm_status(): void
    {
        $this->loginVehicle();
        $id = $this->incident();

        $this->action($id, 'update_core', [
            'edit_location'        => 'Neue Straße 2',
            'edit_keyword'         => 'B4',
            'edit_incident_number' => 'F-NEU',
            'edit_date'            => '2026-10-09',
            'edit_time'            => '09:00',
            'edit_leader_id'       => (string) $this->personId,
            'edit_caller_name'     => 'Anrufer',
            'edit_caller_contact'  => '',
            'edit_owner_name'      => '',
            'edit_owner_contact'   => '',
        ]);
        $this->action($id, 'update_notes', ['notes' => ' Lage unter Kontrolle ']);
        $this->action($id, 'set_status', ['status' => '2']);

        $incident = Capsule::table('intra_fire_incidents')->where('id', $id)->first();
        $this->assertSame('Neue Straße 2', $incident->location);
        $this->assertSame('F-NEU', $incident->incident_number);
        $this->assertSame('Anrufer', $incident->caller_name);
        $this->assertNull($incident->caller_contact);
        $this->assertSame('Lage unter Kontrolle', $incident->notes);
        $this->assertSame(2, (int) $incident->status);
    }

    #[Test]
    public function stammdaten_ohne_pflichtfeld_aendern_nichts(): void
    {
        $this->loginVehicle();
        $id = $this->incident();

        $this->action($id, 'update_core', ['edit_location' => '', 'edit_keyword' => 'B4']);

        $this->assertSame('Bitte alle Pflichtfelder ausfüllen (Nummer, Ort, Stichwort, Beginn, Einsatzleiter).', $_SESSION['flash']['text'] ?? null);
        $this->assertSame('Musterstraße 1', Capsule::table('intra_fire_incidents')->where('id', $id)->value('location'));
    }

    #[Test]
    public function asu_protokoll_anlegen_aendern_loeschen(): void
    {
        $this->loginVehicle();
        $id   = $this->incident();
        $daten = ['supervisor' => 'Paul Prüfer', 'missionNumber' => 'F-1', 'missionLocation' => 'Musterstraße 1', 'missionDate' => '09.10.2026'];

        // asu.js schickt weder CSRF-Feld noch return_tab
        $this->post('/firetab/actions', ['action' => 'add_asu', 'incident_id' => (string) $id, 'asu_data' => json_encode($daten)]);
        $asu = Capsule::table('intra_fire_incident_asu')->where('incident_id', $id)->first();
        $this->assertNotNull($asu);
        $this->assertSame('2026-10-09', $asu->mission_date);

        $daten['missionLocation'] = 'Hinterhof';
        $this->post('/firetab/actions', ['action' => 'update_asu', 'incident_id' => (string) $id, 'asu_id' => (string) $asu->id, 'asu_data' => json_encode($daten)]);
        $this->assertSame('Hinterhof', Capsule::table('intra_fire_incident_asu')->where('id', $asu->id)->value('mission_location'));

        $this->post('/firetab/actions', ['action' => 'delete_asu', 'incident_id' => (string) $id, 'asu_id' => (string) $asu->id, 'return_tab' => 'asu']);
        $this->assertFalse(Capsule::table('intra_fire_incident_asu')->where('id', $asu->id)->exists());
    }

    #[Test]
    public function archivieren_und_wiederherstellen(): void
    {
        $id = $this->incident();

        $this->assertRedirect($this->post('/firetab/actions', ['action' => 'archive_incident', 'incident_id' => (string) $id]), '/firetab/admin/list');
        $this->assertSame(1, (int) Capsule::table('intra_fire_incidents')->where('id', $id)->value('archived'));

        $this->post('/firetab/actions', ['action' => 'unarchive_incident', 'incident_id' => (string) $id]);
        $this->assertSame(0, (int) Capsule::table('intra_fire_incidents')->where('id', $id)->value('archived'));
    }

    #[Test]
    public function aktion_mit_fremdem_feld_wird_abgewiesen(): void
    {
        $this->loginVehicle();
        $id = $this->incident();

        $response = $this->action($id, 'update_notes', ['notes' => 'neu', 'status' => '4', 'finalized' => '1']);

        $this->assertRedirect($response, '/index');
        $this->assertSame('Ungültige Eingabe.', $_SESSION['flash']['text'] ?? null);
        $incident = Capsule::table('intra_fire_incidents')->where('id', $id)->first();
        $this->assertNull($incident->notes);
        $this->assertSame(0, (int) $incident->finalized);
    }
}
