<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * POI-Verwaltung des eNOTF (PoiController): Liste, Fachrichtungen und
 * Zugangscodes stehen in der Hülle der Einstellungen, die Listen laufen
 * über ListQuery statt DataTables, die Formulare über FormRequests.
 */
final class EnotfPoiSettingsTest extends FeatureTestCase
{
    private const LIST = '/settings/pois/index';

    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
        $this->prefix = 'PoiTest ' . uniqid() . ' ';
    }

    /** @param array<string,mixed> $fields */
    private function poi(string $name, array $fields = []): int
    {
        return (int) Capsule::table('intra_edivi_pois')->insertGetId($fields + [
            'name' => $this->prefix . $name, 'ort' => 'Teststadt', 'active' => 1,
        ]);
    }

    /** @return array<string,mixed>|null */
    private function row(string $name): ?array
    {
        $row = Capsule::table('intra_edivi_pois')->where('name', $this->prefix . $name)->first();

        return $row === null ? null : (array) $row;
    }

    private function pos(string $body, string $needle): int
    {
        $pos = strpos($body, $needle);
        $this->assertNotFalse($pos, "'$needle' fehlt in der Antwort.");

        return $pos;
    }

    #[Test]
    public function liste_sucht_sortiert_und_filtert(): void
    {
        $klinik = $this->poi('Zentrum', ['ort' => 'Bstadt', 'typ' => 'Krankenhaus']);
        $wache  = $this->poi('Wache', ['ort' => 'Astadt', 'typ' => 'Feuerwache', 'active' => 0]);

        $default = $this->get(self::LIST, ['query' => ['q' => $this->prefix]]);
        $this->assertOk($default);
        $this->assertBodyNotContains('DataTable(', $default);
        $this->assertBodyContains('class="ignis-app"', $default);
        $this->assertBodyContains('<h1>POI-Verwaltung</h1>', $default);
        $this->assertLessThan($this->pos($default->body, $this->prefix . 'Zentrum'), $this->pos($default->body, $this->prefix . 'Wache'));
        $this->assertBodyContains('settings/pois/departments?poi_id=' . $klinik . '"', $default);
        $this->assertBodyNotContains('settings/pois/departments?poi_id=' . $wache . '"', $default);
        $this->assertBodyContains('1 bis 2 von 2 POIs', $default);

        $byOrt = $this->get(self::LIST, ['query' => ['q' => $this->prefix, 'sort' => 'ort', 'dir' => 'desc']]);
        $this->assertLessThan($this->pos($byOrt->body, $this->prefix . 'Wache'), $this->pos($byOrt->body, $this->prefix . 'Zentrum'));

        $byTyp = $this->get(self::LIST, ['query' => ['q' => $this->prefix, 'typ' => 'Krankenhaus']]);
        $this->assertBodyContains($this->prefix . 'Zentrum', $byTyp);
        $this->assertBodyNotContains($this->prefix . 'Wache', $byTyp);
        $this->assertBodyContains('<option value="Krankenhaus" selected>', $byTyp);

        $inactive = $this->get(self::LIST, ['query' => ['q' => $this->prefix, 'active' => '0']]);
        $this->assertBodyContains($this->prefix . 'Wache', $inactive);
        $this->assertBodyNotContains($this->prefix . 'Zentrum', $inactive);
        $this->assertBodyContains('Aktiv <span class="ignis-segmented__count">1</span>', $inactive);

        $byOrtSearch = $this->get(self::LIST, ['query' => ['q' => 'Astadt']]);
        $this->assertBodyContains($this->prefix . 'Wache', $byOrtSearch);
        $this->assertBodyNotContains($this->prefix . 'Zentrum', $byOrtSearch);
    }

    #[Test]
    public function seite_zwei(): void
    {
        for ($i = 1; $i <= 26; $i++) {
            $this->poi(sprintf('Seite %02d', $i));
        }

        $first = $this->get(self::LIST, ['query' => ['q' => $this->prefix]]);
        $this->assertBodyContains($this->prefix . 'Seite 25', $first);
        $this->assertBodyNotContains($this->prefix . 'Seite 26', $first);
        $this->assertBodyContains('1 bis 25 von 26 POIs', $first);

        $second = $this->get(self::LIST, ['query' => ['q' => $this->prefix, 'page' => '2']]);
        $this->assertBodyContains($this->prefix . 'Seite 26', $second);
        $this->assertBodyNotContains($this->prefix . 'Seite 25', $second);
    }

    #[Test]
    public function anlegen_aendern_loeschen(): void
    {
        $created = $this->post('/settings/pois/create', [
            'name' => $this->prefix . 'Neu', 'strasse' => '', 'hnr' => '12', 'ort' => 'Ort',
            'ortsteil' => 'Mitte', 'typ' => 'Schule', 'active' => 'on',
        ]);
        $this->assertRedirect($created, self::LIST);
        $row = $this->row('Neu');
        $this->assertNotNull($row);
        $this->assertSame('', $row['strasse'], 'Ein leeres Feld bleibt ein leerer Text.');
        $this->assertSame('Schule', $row['typ']);
        $this->assertSame(1, (int) $row['active']);

        $this->post('/settings/pois/update', [
            'id' => (string) $row['id'], 'name' => $this->prefix . 'Neu', 'ort' => 'Anderswo', 'typ' => '',
        ]);
        $row = $this->row('Neu');
        $this->assertNotNull($row);
        $this->assertSame('Anderswo', $row['ort']);
        $this->assertNull($row['strasse'], 'Ein fehlendes Feld wird NULL.');
        $this->assertSame(0, (int) $row['active']);

        $this->assertRedirect($this->post('/settings/pois/delete', ['id' => (string) $row['id']]), self::LIST);
        $this->assertNull($this->row('Neu'));
    }

    #[Test]
    public function ungueltige_poi_eingaben_werden_abgewiesen(): void
    {
        $this->assertRedirect($this->post('/settings/pois/create', ['name' => $this->prefix . 'Ohne Ort', 'ort' => ' ']), self::LIST);
        $this->assertSame('Der Ort ist Pflicht und darf höchstens 255 Zeichen lang sein.', $_SESSION['flash']['text'] ?? null);
        $this->assertNull($this->row('Ohne Ort'));

        $this->post('/settings/pois/create', ['name' => $this->prefix . 'Lang', 'ort' => 'Ort', 'hnr' => str_repeat('1', 51)]);
        $this->assertSame('Die Hausnummer darf höchstens 50 Zeichen lang sein.', $_SESSION['flash']['text'] ?? null);
        $this->assertNull($this->row('Lang'));

        $this->post('/settings/pois/create', ['name' => $this->prefix . 'Fremd', 'ort' => 'Ort', 'fremd' => '1']);
        $this->assertSame('Das Formular enthält unbekannte Felder.', $_SESSION['flash']['text'] ?? null);
        $this->assertNull($this->row('Fremd'));

        $this->post('/settings/pois/delete', ['id' => 'abc']);
        $this->assertSame('Ungültige ID.', $_SESSION['flash']['text'] ?? null);
    }

    #[Test]
    public function fachrichtungen_anlegen_sortieren_aendern_loeschen_zuruecksetzen(): void
    {
        $poiId = $this->poi('Klinikum', ['typ' => 'Krankenhaus']);
        $path  = '/settings/pois/departments?poi_id=' . $poiId;

        $this->assertRedirect($this->post('/settings/pois/departments-create', ['poi_id' => (string) $poiId, 'name' => 'ZNA', 'sort_order' => '2']), $path);
        $this->post('/settings/pois/departments-create', ['poi_id' => (string) $poiId, 'name' => 'Schockraum', 'sort_order' => '1']);
        $depts = Capsule::table('intra_edivi_hospital_departments')->where('poi_id', $poiId)->pluck('id', 'name')->all();
        $this->assertCount(2, $depts);
        $this->assertSame(2, Capsule::table('intra_edivi_hospital_availability')->whereIn('department_id', array_values($depts))->where('status', 'not_staffed')->count());

        $list = $this->get('/settings/pois/departments', ['query' => ['poi_id' => (string) $poiId]]);
        $this->assertOk($list);
        $this->assertBodyNotContains('DataTable(', $list);
        $this->assertBodyContains('class="ignis-app"', $list);
        $this->assertBodyContains('data-poi-card="' . $poiId . '"', $list);
        $this->assertLessThan($this->pos($list->body, '>ZNA<'), $this->pos($list->body, '>Schockraum<'));
        $this->assertBodyContains('href="/settings/pois/departments?sort=name&amp;dir=asc&amp;poi_id=' . $poiId . '"', $list);

        $byName = $this->get('/settings/pois/departments', ['query' => ['poi_id' => (string) $poiId, 'sort' => 'name', 'dir' => 'desc']]);
        $this->assertLessThan($this->pos($byName->body, '>Schockraum<'), $this->pos($byName->body, '>ZNA<'));

        $this->post('/settings/pois/departments-update', ['id' => (string) $depts['ZNA'], 'poi_id' => (string) $poiId, 'name' => 'ZNA/INA', 'sort_order' => '0']);
        $this->assertSame(0, (int) Capsule::table('intra_edivi_hospital_departments')->where('id', $depts['ZNA'])->value('sort_order'));
        $this->assertSame('ZNA/INA', Capsule::table('intra_edivi_hospital_departments')->where('id', $depts['ZNA'])->value('name'));

        Capsule::table('intra_edivi_hospital_availability')->where('department_id', $depts['ZNA'])->update(['status' => 'available']);
        $this->assertRedirect($this->post('/settings/pois/departments-reset-availability', ['poi_id' => (string) $poiId]), $path);
        $this->assertSame('not_staffed', Capsule::table('intra_edivi_hospital_availability')->where('department_id', $depts['ZNA'])->value('status'));

        $this->assertRedirect($this->post('/settings/pois/departments-delete', ['id' => (string) $depts['Schockraum'], 'poi_id' => (string) $poiId]), $path);
        $this->assertFalse(Capsule::table('intra_edivi_hospital_departments')->where('id', $depts['Schockraum'])->exists());

        // Ungültige Eingaben führen zurück zu den Fachrichtungen desselben POIs.
        $this->assertRedirect($this->post('/settings/pois/departments-create', ['poi_id' => (string) $poiId, 'name' => '  ']), $path);
        $this->assertSame('Der Name der Fachrichtung ist Pflicht und darf höchstens 255 Zeichen lang sein.', $_SESSION['flash']['text'] ?? null);
        $this->post('/settings/pois/departments-create', ['poi_id' => (string) $poiId, 'name' => 'Labor', 'sort_order' => 'oben']);
        $this->assertSame('Die Sortierung muss eine Zahl sein.', $_SESSION['flash']['text'] ?? null);
        $this->assertSame(1, Capsule::table('intra_edivi_hospital_departments')->where('poi_id', $poiId)->count());
    }

    #[Test]
    public function zugangscodes_liste_und_generieren(): void
    {
        $klinik = $this->poi('Klinikum', ['typ' => 'Krankenhaus']);
        $this->poi('Wache', ['typ' => 'Feuerwache']);
        $path = '/settings/pois/access-codes';

        $list = $this->get($path, ['query' => ['q' => $this->prefix]]);
        $this->assertOk($list);
        $this->assertBodyNotContains('DataTable(', $list);
        $this->assertBodyContains('class="ignis-app"', $list);
        $this->assertBodyContains($this->prefix . 'Klinikum', $list);
        $this->assertBodyNotContains($this->prefix . 'Wache', $list);
        $this->assertBodyContains('Nicht konfiguriert', $list);
        $this->assertBodyContains('1 bis 1 von 1 Krankenhäusern', $list);
        $this->assertBodyContains('enotf/schnittstelle/hospital-availability', $list);

        $this->assertRedirect($this->post($path, ['poi_id' => (string) $klinik, 'new_code' => 'Code1234abcd']), $path);
        $this->assertSame('Code1234abcd', Capsule::table('intra_edivi_hospital_access_codes')->where('poi_id', $klinik)->value('code'));

        $this->post($path, ['poi_id' => (string) $klinik, 'new_code' => 'Neu5678efgh']);
        $this->assertSame(['Neu5678efgh'], Capsule::table('intra_edivi_hospital_access_codes')->where('poi_id', $klinik)->pluck('code')->all());
        $this->assertBodyContains('Neu5678efgh', $this->get($path, ['query' => ['q' => $this->prefix]]));

        $this->assertRedirect($this->post($path, ['poi_id' => '0', 'new_code' => '']), $path);
        $this->assertSame('POI ID oder Code fehlt.', $_SESSION['flash']['text'] ?? null);
    }
}
