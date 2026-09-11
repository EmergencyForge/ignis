<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\VehicleDefect;
use App\Support\Activity;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die Fahrzeugseite (Settings\FahrzeugeController::show) nach dem
 * Detailmuster: Register Mängel (offene zuerst, Link je Mangel), Beladung
 * des Typs und taktisches Zeichen mit JSON, Seitenspalte mit Stammdaten
 * und der Aktivität aus dem Audit-Log, die nur Einträge mit der Kennung
 * dieses Fahrzeugs zeigt. Recht wie die Liste (vehicle.view).
 */
final class VehicleShowTest extends FeatureTestCase
{
    private int $userId = 0;
    private string $username = '';

    /**
     * @param list<string> $permissions
     */
    private function login(array $permissions): int
    {
        $user = FixtureFactory::user();
        $this->username = $user->username;
        $this->actingAs($user->id, ['permissions' => $permissions, 'cirs_username' => $user->username]);

        return $this->userId = $user->id;
    }

    private function pos(string $body, string $needle): int
    {
        $pos = strpos($body, $needle);
        $this->assertNotFalse($pos, "'$needle' fehlt in der Antwort.");

        return $pos;
    }

    private function audit(string $action, ?string $details): void
    {
        Capsule::table('intra_audit_log')->insert(['user' => $this->userId, 'module' => 'Fahrzeuge', 'action' => $action, 'details' => $details, 'global' => 1]);
    }

    #[Test]
    public function seite_mit_maengeln_beladung_und_zeichen(): void
    {
        $this->login(['vehicles.view']);
        $vehicle = FixtureFactory::fahrzeug(['name' => 'Florian Detail 1/83/3', 'veh_type' => 'RTW-D', 'rd_type' => 2]);
        Capsule::table('intra_fahrzeuge')->where('id', $vehicle['id'])->update([
            'kennzeichen' => 'LS-FW 833', 'grundzeichen' => 'fahrzeug', 'organisation' => 'feuerwehr', 'fachaufgabe' => 'rettungswesen',
            'text' => 'RTW 3', 'tz_name' => 'RD Standard', 'current_status' => '2', 'status_source' => 'EMD',
        ]);
        $ids = [];
        foreach ([['Alt und erledigt', 'resolved', 1, 4], ['Bremsen quietschen', 'open', 0, 1], ['Blaulicht hinten', 'in_progress', 1, 2]] as [$title, $status, $operable, $age]) {
            $ids[$title] = (int) VehicleDefect::query()->create([
                'vehicle_id' => $vehicle['id'], 'title' => $title, 'category' => 'bremsen', 'vehicle_operable' => $operable,
                'status' => $status, 'reported_by' => $this->userId, 'created_at' => date('Y-m-d H:i:s', strtotime("-$age hours")),
            ])->id;
        }
        $categoryId = (int) Capsule::table('intra_fahrzeuge_beladung_categories')->insertGetId(['title' => 'Fach 1', 'veh_type' => 'RTW-D', 'priority' => 1]);
        Capsule::table('intra_fahrzeuge_beladung_tiles')->insert([
            ['category' => $categoryId, 'title' => 'Verbandkasten', 'amount' => 2],
            ['category' => $categoryId, 'title' => 'Halskrause', 'amount' => 3],
        ]);

        $page = $this->get('/settings/vehicles/vehicles/' . $vehicle['id']);
        $body = $page->body;

        $this->assertOk($page);
        $this->assertBodyContains('<title>Fahrzeug: LS-FW 833', $page);
        $this->assertBodyContains('<span class="ignis-breadcrumb__item is-active">LS-FW 833</span>', $page);
        $this->assertBodyContains('<h1 class="ignis-detail__title">', $page);
        $this->assertBodyContains('Florian Detail 1/83/3', $page);
        $this->assertBodyContains('Einsatzbereit', $page);
        $this->assertBodyContains('RD - Ohne NA', $page);
        $this->assertBodyContains('Status 2', $page);
        $this->assertBodyContains('data-tab="maengel">Mängel (2)</button>', $page);
        $this->assertBodyContains('data-tab="beladung">Beladung</button>', $page);
        $this->assertBodyContains('data-tab="zeichen">Taktisches Zeichen</button>', $page);

        // Mängel: offene zuerst, der gelöste zuletzt, jeder mit Anker in der Mängelliste.
        $this->assertLessThan($this->pos($body, 'Blaulicht hinten'), $this->pos($body, 'Bremsen quietschen'));
        $this->assertLessThan($this->pos($body, 'Alt und erledigt'), $this->pos($body, 'Blaulicht hinten'));
        $this->assertBodyContains('href="/settings/vehicles/defects/index?vehicle=' . $vehicle['id'] . '#defect-' . $ids['Bremsen quietschen'] . '"', $page);
        $this->assertBodyContains('Nicht einsatzfähig', $page);
        $this->assertBodyContains('2 offene Mängel, 3 insgesamt', $page);

        // Beladung des Typs.
        $this->assertBodyContains('2 Positionen in 1 Kategorien, 5 Stück', $page);
        $this->assertBodyContains('Fach 1', $page);
        $this->assertBodyContains('<td>Verbandkasten</td>', $page);

        // Zeichen als JSON zum Zeichnen und zum Kopieren.
        $this->assertSame(1, preg_match('~data-ignis-tz="([^"]+)" data-ignis-tz-class="ignis-detail__tz-svg"~', $body, $m));
        $this->assertSame(
            ['grundzeichen' => 'fahrzeug', 'organisation' => 'feuerwehr', 'fachaufgabe' => 'rettungswesen', 'text' => 'RTW 3', 'name' => 'RD Standard'],
            json_decode(html_entity_decode($m[1], ENT_QUOTES), true),
        );
        $this->assertBodyContains('data-ignis-copy="#vehicle-tz-json"', $page);
        $this->assertBodyContains('&quot;grundzeichen&quot;: &quot;fahrzeug&quot;', $page);

        // Stammdaten in der Seitenspalte.
        $this->assertBodyContains('<dt>Kennzeichen</dt>', $page);
        $this->assertBodyContains('<dt>EMD-Status</dt>', $page);
        $this->assertBodyContains('<dt>Angelegt</dt>', $page);

        // vehicles.view darf melden, aber nicht bearbeiten.
        $this->assertBodyContains('href="/settings/vehicles/defects/create?vehicle=' . $vehicle['id'] . '" class="ignis-btn ignis-btn--primary" data-ignis-drawer', $page);
        $this->assertBodyNotContains('/edit"', $page);

        $this->login(['vehicles.view', 'vehicles.manage']);
        $page = $this->get('/settings/vehicles/vehicles/' . $vehicle['id']);
        $this->assertBodyContains('href="/settings/vehicles/vehicles/' . $vehicle['id'] . '/edit" class="ignis-btn ignis-btn--secondary" data-ignis-drawer', $page);
    }

    #[Test]
    public function leere_register_und_fehlende_tabelle_sind_kein_fehler(): void
    {
        $this->login(['vehicles.view']);
        $vehicle = FixtureFactory::fahrzeug(['name' => 'Leer 1', 'veh_type' => 'OHNE-LISTE']);

        $page = $this->get('/settings/vehicles/vehicles/' . $vehicle['id']);
        $this->assertOk($page);
        $this->assertBodyContains('data-tab="maengel">Mängel</button>', $page);
        $this->assertBodyContains('Keine Mängel gemeldet.', $page);
        $this->assertBodyContains('Keine Beladeliste für den Typ OHNE-LISTE.', $page);
        $this->assertBodyContains('Kein taktisches Zeichen hinterlegt.', $page);
        $this->assertBodyContains('Noch keine Aktivität.', $page);
        $this->assertBodyContains('<span class="ignis-breadcrumb__item is-active">Leer 1</span>', $page);
    }

    #[Test]
    public function ohne_recht_zurueck_zur_liste_und_unbekannte_kennung_auch(): void
    {
        $vehicle = FixtureFactory::fahrzeug(['name' => 'Geheim Detail 1']);

        $this->login(['personnel.view']);
        $denied = $this->get('/settings/vehicles/vehicles/' . $vehicle['id']);
        $this->assertRedirect($denied);
        $this->assertBodyNotContains('Geheim Detail 1', $denied);

        $this->login(['vehicles.view']);
        $this->assertRedirect($this->get('/settings/vehicles/vehicles/999999999'), '/settings/vehicles/vehicles/index');
    }

    #[Test]
    public function aktivitaet_zeigt_nur_eintraege_mit_der_eigenen_kennung(): void
    {
        $this->login(['vehicles.view']);
        $a = FixtureFactory::fahrzeug(['name' => 'Aktiv A']);
        $b = FixtureFactory::fahrzeug(['name' => 'Aktiv B']);
        $idA = (int) $a['id'];

        $this->audit('Fahrzeug erstellt [ID: ' . $idA . ']', 'Name: Aktiv A | Typ: RTW');
        $this->audit('Fahrzeug aktualisiert [ID: ' . $idA . ']', 'Status: inaktiv (Sammelaktion)');
        $this->audit('Fahrzeug aktualisiert [ID: ' . $idA . ']', null);
        $this->audit('Defekt gemeldet [ID: 7]', 'Fahrzeug-ID: ' . $idA . ' | Bremsen quietschen');
        $this->audit('Fahrzeug per EMD-Import überschrieben', 'Name: Aktiv A | ID: ' . $idA);
        $this->audit('Fahrzeug gelöscht [ID: ' . $b['id'] . ']', null);
        $this->audit('Fahrzeug aktualisiert [ID: ' . $idA . '1]', 'Fremde Kennung');
        $this->audit('Einsatz archiviert [ID: ' . $idA . ']', 'anderes Modul');
        Capsule::table('intra_audit_log')->where('user', $this->userId)->where('module', 'Fahrzeuge')->where('action', 'Einsatz archiviert [ID: ' . $idA . ']')->update(['module' => 'Feuerwehr']);

        $labels = array_column(Activity::vehicle($idA), 'label');
        sort($labels);
        $this->assertSame(
            ['Fahrzeug angelegt', 'Mangel gemeldet: Bremsen quietschen', 'Per EMD-Import überschrieben', 'Stammdaten geändert', 'Status auf inaktiv gesetzt'],
            $labels,
        );

        $page = $this->get('/settings/vehicles/vehicles/' . $idA);
        $this->assertOk($page);
        $this->assertBodyContains('Mangel gemeldet: Bremsen quietschen', $page);
        $this->assertBodyContains('Status auf inaktiv gesetzt', $page);
        $this->assertBodyContains('von ' . $this->username, $page);
        $this->assertBodyNotContains('Fahrzeug gelöscht', $page);
        $this->assertBodyNotContains('Fremde Kennung', $page);
        $this->assertBodyNotContains('Einsatz archiviert', $page);

        // Die andere Seite kennt nur ihren Eintrag.
        $pageB = $this->get('/settings/vehicles/vehicles/' . $b['id']);
        $this->assertBodyContains('Fahrzeug gelöscht', $pageB);
        $this->assertBodyNotContains('Bremsen quietschen', $pageB);
    }
}
