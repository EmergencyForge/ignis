<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die Formulare des MANV-Boards lesen ihre Felder über die FormRequests in
 * plugins/manv-board/src/Requests: Lage, Patient und Ressource, jeweils
 * Anlegen und Ändern.
 */
final class MciFormsTest extends FeatureTestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user();
        $this->userId = $user->id;
        $this->actingAs($user->id, ['permissions' => ['mci.manage'], 'cirs_username' => $user->username]);
    }

    private function lage(): int
    {
        return (int) Capsule::table('intra_manv_lagen')->insertGetId([
            'einsatznummer' => 'MANV-' . uniqid(), 'einsatzort' => 'A1 km 42', 'status' => 'aktiv', 'erstellt_von' => $this->userId,
        ]);
    }

    private function fahrzeug(int $lageId, string $bezeichnung): int
    {
        return (int) Capsule::table('intra_manv_ressourcen')->insertGetId([
            'manv_lage_id' => $lageId, 'typ' => 'fahrzeug', 'bezeichnung' => $bezeichnung, 'fahrzeugtyp' => 'RTW', 'lokalisation' => 'VSP Nord',
        ]);
    }

    #[Test]
    public function lage_anlegen_und_bearbeiten(): void
    {
        $nummer = 'MANV-' . uniqid();

        $response = $this->post('/mci/create', [
            'einsatznummer'       => '  ' . $nummer . '  ',
            'einsatzort'          => 'Hauptbahnhof',
            'einsatzanlass'       => 'Zugunglück',
            'einsatzbeginn'       => '2026-10-09 08:15:00',
            'lna_name'            => 'Dr. Lang',
            'lna_mitarbeiter_id'  => '',
            'orgl_name'           => 'Otto Org',
            'orgl_mitarbeiter_id' => '',
            'notizen'             => 'Zwei Züge',
        ]);

        $lage = Capsule::table('intra_manv_lagen')->where('einsatznummer', $nummer)->first();
        $this->assertNotNull($lage);
        $this->assertRedirect($response, '/mci/board?id=' . $lage->id);
        $this->assertSame('Hauptbahnhof', $lage->einsatzort);
        $this->assertSame('Dr. Lang', $lage->lna_name);
        $this->assertNull($lage->lna_mitarbeiter_id);

        $response = $this->post('/mci/edit', [
            'einsatznummer' => $nummer,
            'einsatzort'    => 'Hauptbahnhof Gleis 3',
            'status'        => 'abgeschlossen',
        ], ['query' => ['id' => (string) $lage->id]]);

        $this->assertRedirect($response, '/mci/edit?id=' . $lage->id);
        $lage = Capsule::table('intra_manv_lagen')->where('id', $lage->id)->first();
        $this->assertSame('Hauptbahnhof Gleis 3', $lage->einsatzort);
        $this->assertSame('abgeschlossen', $lage->status);
    }

    #[Test]
    public function lage_ohne_pflichtfeld_oder_mit_fremdem_feld_wird_abgewiesen(): void
    {
        $vorher = Capsule::table('intra_manv_lagen')->count();

        $response = $this->post('/mci/create', ['einsatznummer' => '   ', 'einsatzort' => 'Hauptbahnhof']);
        $this->assertRedirect($response, '/mci/create');
        $this->assertSame('Einsatznummer und Einsatzort sind Pflichtfelder.', $_SESSION['flash']['text'] ?? null);

        $response = $this->post('/mci/create', ['einsatznummer' => 'MANV-X', 'einsatzort' => 'Hauptbahnhof', 'erstellt_von' => '1']);
        $this->assertRedirect($response, '/mci/create');
        $this->assertSame('Ungültige Eingabe.', $_SESSION['flash']['text'] ?? null);

        $this->assertSame($vorher, Capsule::table('intra_manv_lagen')->count());
    }

    #[Test]
    public function unbekannter_status_wird_zu_aktiv(): void
    {
        $lageId = $this->lage();

        $this->post('/mci/edit', ['einsatznummer' => 'MANV-1', 'einsatzort' => 'Ort', 'status' => 'geloescht'], ['query' => ['id' => (string) $lageId]]);

        $this->assertSame('aktiv', Capsule::table('intra_manv_lagen')->where('id', $lageId)->value('status'));
    }

    #[Test]
    public function patient_anlegen_und_sichtung_aendern(): void
    {
        $lageId = $this->lage();
        $rtw    = $this->fahrzeug($lageId, 'RTW 1/83-1');

        $response = $this->post('/mci/patient-create', [
            'name'               => 'Muster',
            'vorname'            => 'Max',
            'geburtsdatum'       => '',
            'geschlecht'         => 'm',
            'sichtungskategorie' => 'SK2',
            'transportmittel_id' => (string) $rtw,
            'transportziel'      => '',
            'verletzungen'       => 'Fraktur Unterarm',
            'notizen'            => '',
        ], ['query' => ['lage_id' => (string) $lageId]]);

        $patient = Capsule::table('intra_manv_patienten')->where('manv_lage_id', $lageId)->first();
        $this->assertNotNull($patient);
        $this->assertRedirect($response, '/mci/patient-view?id=' . $patient->id);
        $this->assertSame('Muster', $patient->name);
        $this->assertNull($patient->geburtsdatum);
        $this->assertSame('SK2', $patient->sichtungskategorie);
        $this->assertSame('RTW 1/83-1', $patient->transportmittel_rufname);
        $this->assertSame('VSP Nord', $patient->fahrzeug_lokalisation);

        $response = $this->post('/mci/patient-view', [
            'name'               => 'Muster',
            'vorname'            => 'Max',
            'sichtungskategorie' => 'SK1',
            'transportmittel_id' => '',
            'verletzungen'       => 'Fraktur Unterarm, Schock',
        ], ['query' => ['id' => (string) $patient->id]]);

        $this->assertRedirect($response, '/mci/patient-view?id=' . $patient->id);
        $patient = Capsule::table('intra_manv_patienten')->where('id', $patient->id)->first();
        $this->assertSame('SK1', $patient->sichtungskategorie);
        $this->assertSame('Fraktur Unterarm, Schock', $patient->verletzungen);
        $this->assertNull($patient->transportmittel_rufname);
        $this->assertTrue(Capsule::table('intra_manv_log')->where('manv_lage_id', $lageId)->where('aktion', 'sichtung_geaendert')->exists());
    }

    #[Test]
    public function patient_mit_feld_als_liste_wird_abgewiesen(): void
    {
        $lageId = $this->lage();

        $response = $this->post('/mci/patient-create', ['name' => ['Muster']], ['query' => ['lage_id' => (string) $lageId]]);

        $this->assertRedirect($response, '/mci/patient-create?lage_id=' . $lageId);
        $this->assertFalse(Capsule::table('intra_manv_patienten')->where('manv_lage_id', $lageId)->exists());
    }

    #[Test]
    public function ressource_anlegen_und_bearbeiten(): void
    {
        $lageId = $this->lage();

        $response = $this->post('/mci/resources', [
            'action'       => 'create',
            'typ'          => 'fahrzeug',
            'fahrzeug_id'  => '',
            'bezeichnung'  => ' RTW 2/83-1 ',
            'fahrzeugtyp'  => 'RTW',
            'lokalisation' => 'Haltepunkt Nord',
            'notizen'      => '',
        ], ['query' => ['lage_id' => (string) $lageId]]);

        $this->assertRedirect($response, '/mci/resources?lage_id=' . $lageId);
        $ressource = Capsule::table('intra_manv_ressourcen')->where('manv_lage_id', $lageId)->first();
        $this->assertNotNull($ressource);
        $this->assertSame('RTW 2/83-1', $ressource->bezeichnung);
        $this->assertSame('verfuegbar', $ressource->status);

        $this->post('/mci/resources', [
            'action'       => 'edit',
            'ressource_id' => (string) $ressource->id,
            'typ'          => 'fahrzeug',
            'bezeichnung'  => 'RTW 2/83-1',
            'fahrzeugtyp'  => 'RTW',
            'lokalisation' => 'Bereitstellungsraum',
            'notizen'      => 'Nachgerückt',
        ], ['query' => ['lage_id' => (string) $lageId]]);

        $ressource = Capsule::table('intra_manv_ressourcen')->where('id', $ressource->id)->first();
        $this->assertSame('Bereitstellungsraum', $ressource->lokalisation);
        $this->assertSame('Nachgerückt', $ressource->notizen);
    }

    #[Test]
    public function ressource_ohne_bezeichnung_wird_abgewiesen(): void
    {
        $lageId = $this->lage();

        $response = $this->post('/mci/resources', ['action' => 'create', 'bezeichnung' => '  '], ['query' => ['lage_id' => (string) $lageId]]);

        $this->assertRedirect($response, '/mci/resources?lage_id=' . $lageId);
        $this->assertSame('Bezeichnung ist Pflichtfeld.', $_SESSION['flash']['text'] ?? null);
        $this->assertFalse(Capsule::table('intra_manv_ressourcen')->where('manv_lage_id', $lageId)->exists());
    }
}
