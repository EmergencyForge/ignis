<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die Antragstypen und ihre Felder.
 *
 * Der Bereich hatte keine Tests, und beim Umbau auf FormRequests kam
 * zweierlei heraus:
 *
 * Das Anlegen eines Antragstyps und beide Sortier-Knöpfe posteten auf eine
 * Adresse, für die nur GET registriert war — 405, also tote Knöpfe.
 *
 * Und Umschalten, Löschen eines Typs und Löschen eines Feldes liefen über
 * `?toggle=`, `?delete=` und `?delete_feld=`: Zustandsänderungen an einer
 * GET-Adresse, für die CsrfMiddleware nicht zuständig ist.
 */
final class FormTypeSettingsTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user(['full_admin' => true]);
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
    }

    /** @param array<string,mixed> $overrides */
    private function typ(array $overrides = []): int
    {
        return (int) Capsule::table('intra_antrag_typen')->insertGetId($overrides + [
            'name'       => 'Urlaub',
            'aktiv'      => 1,
            'sortierung' => 1,
        ]);
    }

    #[Test]
    public function ein_antragstyp_laesst_sich_anlegen(): void
    {
        // Das Formular postete auf die GET-Route und bekam 405.
        $vorher = Capsule::table('intra_antrag_typen')->count();

        $response = $this->post('/settings/forms/create', [
            'name'         => 'Beförderung',
            'beschreibung' => 'Antrag auf Beförderung',
            'aktiv'        => '1',
            'sortierung'   => '2',
            'submit'       => '1',
        ]);

        $this->assertRedirect($response, '/settings/forms/edit');
        $this->assertSame($vorher + 1, Capsule::table('intra_antrag_typen')->count());
    }

    #[Test]
    public function ohne_namen_entsteht_kein_antragstyp(): void
    {
        $vorher = Capsule::table('intra_antrag_typen')->count();

        $this->post('/settings/forms/create', ['name' => '  ', 'submit' => '1']);

        $this->assertSame($vorher, Capsule::table('intra_antrag_typen')->count());
    }

    #[Test]
    public function umschalten_geht_nur_noch_per_post(): void
    {
        $id = $this->typ(['aktiv' => 1]);

        // Der alte Weg: ein Bildaufruf haette gereicht.
        $this->get('/settings/forms/list', ['query' => ['toggle' => (string) $id]]);
        $this->assertSame(1, (int) Capsule::table('intra_antrag_typen')->where('id', $id)->value('aktiv'));

        $this->post('/settings/forms/toggle', ['id' => (string) $id]);
        $this->assertSame(0, (int) Capsule::table('intra_antrag_typen')->where('id', $id)->value('aktiv'));
    }

    #[Test]
    public function loeschen_geht_nur_noch_per_post(): void
    {
        $id = $this->typ();

        $this->get('/settings/forms/list', ['query' => ['delete' => (string) $id]]);
        $this->assertTrue(Capsule::table('intra_antrag_typen')->where('id', $id)->exists());

        $this->post('/settings/forms/delete', ['id' => (string) $id]);
        $this->assertFalse(Capsule::table('intra_antrag_typen')->where('id', $id)->exists());
    }

    #[Test]
    public function ein_typ_mit_antraegen_bleibt_stehen(): void
    {
        $id = $this->typ();
        Capsule::table('intra_antraege')->insert([
            'uniqueid'      => substr(uniqid(), -10),
            'antragstyp_id' => $id,
            'name_dn'       => 'Testperson',
        ]);

        $this->post('/settings/forms/delete', ['id' => (string) $id]);

        $this->assertTrue(Capsule::table('intra_antrag_typen')->where('id', $id)->exists());
    }

    #[Test]
    public function die_sortierung_laesst_sich_speichern(): void
    {
        $id = $this->typ(['sortierung' => 1]);

        $this->post('/settings/forms/sort', ['sortierung' => [(string) $id => '9']]);

        $this->assertSame(9, (int) Capsule::table('intra_antrag_typen')->where('id', $id)->value('sortierung'));
    }

    #[Test]
    public function ein_krummer_schluessel_in_der_sortierung_aendert_nichts(): void
    {
        $id = $this->typ(['sortierung' => 1]);

        // 'abc' waere als (int) zur 0 geworden und haette WHERE id = 0
        // abgesetzt — harmlos, aber niemand hatte es geprueft.
        $this->post('/settings/forms/sort', ['sortierung' => ['abc' => '9']]);

        $this->assertSame(1, (int) Capsule::table('intra_antrag_typen')->where('id', $id)->value('sortierung'));
    }

    #[Test]
    public function ein_feld_laesst_sich_anlegen_und_loeschen(): void
    {
        $id = $this->typ();

        $this->post('/settings/forms/edit', [
            'add_feld' => '1',
            'feldname' => 'von_datum',
            'label'    => 'Von',
            'feldtyp'  => 'date',
            'breite'   => 'half',
        ], ['query' => ['id' => (string) $id]]);

        $feldId = (int) Capsule::table('intra_antrag_felder')->where('antragstyp_id', $id)->value('id');
        $this->assertGreaterThan(0, $feldId);

        // Der alte Weg war ein Link.
        $this->get('/settings/forms/edit', ['query' => ['id' => (string) $id, 'delete_feld' => (string) $feldId]]);
        $this->assertTrue(Capsule::table('intra_antrag_felder')->where('id', $feldId)->exists());

        $this->post('/settings/forms/fields/delete', ['antragstyp_id' => (string) $id, 'id' => (string) $feldId]);
        $this->assertFalse(Capsule::table('intra_antrag_felder')->where('id', $feldId)->exists());
    }

    #[Test]
    public function ein_unbekannter_feldtyp_wird_abgewiesen(): void
    {
        $id = $this->typ();

        $this->post('/settings/forms/edit', [
            'add_feld' => '1',
            'feldname' => 'irgendwas',
            'label'    => 'Irgendwas',
            'feldtyp'  => 'raketenstart',
        ], ['query' => ['id' => (string) $id]]);

        $this->assertFalse(Capsule::table('intra_antrag_felder')->where('antragstyp_id', $id)->exists());
    }

    #[Test]
    public function ein_feldname_der_kein_bezeichner_ist_wird_abgewiesen(): void
    {
        $id = $this->typ();

        // Der Feldname wird zum Schluessel in den Antragsdaten.
        $this->post('/settings/forms/edit', [
            'add_feld' => '1',
            'feldname' => 'von datum; drop',
            'label'    => 'Von',
            'feldtyp'  => 'date',
        ], ['query' => ['id' => (string) $id]]);

        $this->assertFalse(Capsule::table('intra_antrag_felder')->where('antragstyp_id', $id)->exists());
    }
}
