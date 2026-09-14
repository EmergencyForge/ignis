<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die vier Stammdaten-Kataloge des Personals: Dienstgrade, FW- und
 * RD-Qualifikationen, Fachdienste.
 *
 * Sie hatten keine Tests, und der Controller las seine Felder roh aus
 * `$_POST`. Beim Umbau auf FormRequests kam heraus, dass die drei
 * Qualifikationskataloge ihre Meldungen unter dem Schlüssel `quali`
 * ablegten, den die Tabelle in {@see \App\Helpers\Flash} nicht kennt —
 * `Flash::set()` gibt bei einem unbekannten stillschweigend auf, also hat
 * dort nie jemand eine Rückmeldung gesehen, weder bei Erfolg noch bei
 * Fehler.
 */
final class PersonnelCatalogueTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user(['full_admin' => true]);
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
    }

    /**
     * Pfad, Tabelle und ein gültiger Satz Felder je Katalog.
     *
     * @return array<string,array{0:string,1:string,2:array<string,string>,3:string}>
     */
    public static function kataloge(): array
    {
        return [
            'Dienstgrade' => ['/settings/personnel/ranks', 'intra_mitarbeiter_dienstgrade', [
                'name' => 'Brandmeister', 'name_m' => 'Brandmeister', 'name_w' => 'Brandmeisterin', 'priority' => '5',
            ], 'name'],
            'FW-Qualifikationen' => ['/settings/personnel/fdskills', 'intra_mitarbeiter_fwquali', [
                'shortname' => 'TM', 'name' => 'Truppmann', 'name_m' => 'Truppmann', 'name_w' => 'Truppfrau', 'priority' => '1',
            ], 'name'],
            'RD-Qualifikationen' => ['/settings/personnel/ambskills', 'intra_mitarbeiter_rdquali', [
                'name' => 'Notfallsanitäter', 'name_m' => 'Notfallsanitäter', 'name_w' => 'Notfallsanitäterin', 'abkuerzung' => 'NFS',
            ], 'name'],
            'Fachdienste' => ['/settings/personnel/specialties', 'intra_mitarbeiter_fdquali', [
                'sgnr' => '7', 'sgname' => 'Höhenrettung',
            ], 'sgname'],
        ];
    }

    /** @param array<string,string> $felder */
    #[Test]
    #[DataProvider('kataloge')]
    public function anlegen_aendern_loeschen(string $pfad, string $tabelle, array $felder, string $namensspalte): void
    {
        $vorher = Capsule::table($tabelle)->count();

        $this->assertRedirect($this->post($pfad . '/create', $felder), $pfad);
        $this->assertSame($vorher + 1, Capsule::table($tabelle)->count());

        $id = (int) Capsule::table($tabelle)->where($namensspalte, $felder[$namensspalte])->value('id');
        $this->assertGreaterThan(0, $id);

        $geaendert = $felder;
        $geaendert[$namensspalte] = 'Geändert';
        $this->assertRedirect($this->post($pfad . '/update', $geaendert + ['id' => (string) $id]), $pfad);
        $this->assertSame('Geändert', Capsule::table($tabelle)->where('id', $id)->value($namensspalte));

        $this->assertRedirect($this->post($pfad . '/delete', ['id' => (string) $id]), $pfad);
        $this->assertSame($vorher, Capsule::table($tabelle)->count());
    }

    /** @param array<string,string> $felder */
    #[Test]
    #[DataProvider('kataloge')]
    public function jeder_katalog_meldet_was_er_getan_hat(string $pfad, string $tabelle, array $felder, string $namensspalte): void
    {
        // Der Kern des Fundes: unter dem falschen Schluessel blieb $_SESSION
        // ['flash'] leer, und die Seite zeigte nach dem Anlegen nichts an.
        unset($_SESSION['flash']);

        $this->post($pfad . '/create', $felder);

        $this->assertArrayHasKey('flash', $_SESSION, 'Nach dem Anlegen muss eine Meldung anstehen.');
        $this->assertSame('success', $_SESSION['flash']['type']);
    }

    /** @param array<string,string> $felder */
    #[Test]
    #[DataProvider('kataloge')]
    public function ein_zu_langer_name_legt_nichts_an(string $pfad, string $tabelle, array $felder, string $namensspalte): void
    {
        // Vorher lief das in eine PDOException, die als „exception" im
        // Hinweis landete — oder die Datenbank schnitt still ab.
        $vorher = Capsule::table($tabelle)->count();
        $felder[$namensspalte] = str_repeat('a', 300);

        $this->assertRedirect($this->post($pfad . '/create', $felder), $pfad);
        $this->assertSame($vorher, Capsule::table($tabelle)->count());
    }

    /** @param array<string,string> $felder */
    #[Test]
    #[DataProvider('kataloge')]
    public function loeschen_ohne_treffer_meldet_statt_stillzuschweigen(string $pfad, string $tabelle, array $felder, string $namensspalte): void
    {
        // Nur der Dienstgrad prüfte das vorher; die drei anderen löschten
        // nichts und meldeten Erfolg.
        unset($_SESSION['flash']);

        $this->assertRedirect($this->post($pfad . '/delete', ['id' => '999999']), $pfad);

        $this->assertArrayHasKey('flash', $_SESSION);
        $this->assertSame('danger', $_SESSION['flash']['type']);
    }

    #[Test]
    public function eine_nummer_die_keine_ist_wird_abgewiesen(): void
    {
        $vorher = Capsule::table('intra_mitarbeiter_fdquali')->count();

        // (int) machte aus „abc" die Null und legte den Fachdienst an.
        $this->post('/settings/personnel/specialties/create', ['sgnr' => 'abc', 'sgname' => 'Krumm']);

        $this->assertSame($vorher, Capsule::table('intra_mitarbeiter_fdquali')->count());
    }
}
