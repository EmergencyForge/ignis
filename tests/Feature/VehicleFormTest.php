<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Fahrzeug anlegen und ändern samt taktischem Zeichen, und die
 * Beladelisten-Aktionen (beladung_handler), die als JSON antworten.
 */
final class VehicleFormTest extends FeatureTestCase
{
    private const HANDLER = '/settings/vehicles/vehload/beladung_handler';

    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user(['full_admin' => true]);
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
    }

    /** @return array<string,string> */
    private function felder(string $name): array
    {
        return [
            'name'                 => $name,
            'kennzeichen'          => 'LS-FW 1',
            'identifier'           => strtolower(str_replace(' ', '_', $name)),
            'veh_type'             => 'RTW',
            'priority'             => '5',
            'rd_type'              => '2',
            'stationierung_poi_id' => '',
            'active'               => 'on',
            'allowed_jobs'         => '',
            'grundzeichen'         => 'fahrzeug',
            'organisation'         => 'rettungsdienst',
            'fachaufgabe'          => '',
            'einheit'              => '',
            'symbol'               => '',
            'typ'                  => '',
            'text'                 => 'RTW',
            'tz_name'              => '',
        ];
    }

    /**
     * @param array<string,string> $body
     * @return array<string,mixed>
     */
    private function handler(array $body): array
    {
        $response = $this->post(self::HANDLER, $body);
        $data = json_decode($response->body, true);
        $this->assertIsArray($data, 'Keine JSON-Antwort: ' . substr($response->body, 0, 200));

        return $data;
    }

    #[Test]
    public function anlegen_und_aendern(): void
    {
        $name = 'Florian Form ' . uniqid();

        $this->assertRedirect($this->post('/settings/vehicles/vehicles/create', $this->felder($name)), '/settings/vehicles/vehicles/index');

        $row = Capsule::table('intra_fahrzeuge')->where('name', $name)->first();
        $this->assertNotNull($row);
        $this->assertSame(5, (int) $row->priority);
        $this->assertSame(1, (int) $row->active);
        $this->assertSame('fahrzeug', $row->grundzeichen);
        $this->assertNull($row->allowed_jobs);
        $this->assertNull($row->stationierung_poi_id);

        $geaendert = ['id' => (string) $row->id, 'kennzeichen' => 'LS-FW 2', 'allowed_jobs' => 'BF'] + $this->felder($name);
        unset($geaendert['active']);
        $this->assertRedirect($this->post('/settings/vehicles/vehicles/update', $geaendert), '/settings/vehicles/vehicles/index');

        $row = Capsule::table('intra_fahrzeuge')->where('id', $row->id)->first();
        $this->assertSame('LS-FW 2', $row->kennzeichen);
        $this->assertSame('BF', $row->allowed_jobs);
        $this->assertSame(0, (int) $row->active);
    }

    #[Test]
    public function prioritaet_als_text_legt_nichts_an(): void
    {
        $name = 'Florian Krumm ' . uniqid();

        $response = $this->post('/settings/vehicles/vehicles/create', ['priority' => 'hoch'] + $this->felder($name));

        $this->assertRedirect($response, '/settings/vehicles/vehicles/create');
        $this->assertSame('Die Priorität muss eine Zahl sein.', $_SESSION['flash']['text'] ?? null);
        $this->assertFalse(Capsule::table('intra_fahrzeuge')->where('name', $name)->exists());
    }

    #[Test]
    public function beladung_kategorie_und_gegenstand(): void
    {
        $title = 'Fach ' . uniqid();

        $this->assertTrue($this->handler(['action' => 'add_category', 'title' => $title, 'type' => '0', 'veh_type' => 'RTW', 'priority' => '2'])['success']);
        $category = Capsule::table('intra_fahrzeuge_beladung_categories')->where('title', $title)->first();
        $this->assertNotNull($category);
        $this->assertSame('RTW', $category->veh_type);

        $this->assertTrue($this->handler(['action' => 'edit_category', 'id' => (string) $category->id, 'title' => $title, 'type' => '1', 'veh_type' => '', 'priority' => '3'])['success']);
        $category = Capsule::table('intra_fahrzeuge_beladung_categories')->where('id', $category->id)->first();
        $this->assertNull($category->veh_type);
        $this->assertSame(3, (int) $category->priority);

        $this->assertTrue($this->handler(['action' => 'add_tile', 'category' => (string) $category->id, 'title' => 'Absaugpumpe', 'amount' => '1'])['success']);
        $tile = Capsule::table('intra_fahrzeuge_beladung_tiles')->where('category', $category->id)->first();
        $this->assertNotNull($tile);

        $this->assertSame(0, $this->handler(['action' => 'update_amount', 'id' => (string) $tile->id, 'amount' => '-3'])['amount']);
        $this->assertSame(1, $this->handler(['action' => 'reorder_tiles', 'category' => (string) $category->id, 'order' => (string) $tile->id])['count']);

        $this->assertTrue($this->handler(['action' => 'delete_tile', 'id' => (string) $tile->id])['success']);
        $this->assertTrue($this->handler(['action' => 'delete_category', 'id' => (string) $category->id])['success']);
        $this->assertFalse(Capsule::table('intra_fahrzeuge_beladung_categories')->where('id', $category->id)->exists());
    }

    #[Test]
    public function beladung_mit_kaputten_feldern(): void
    {
        $title = 'Fach ' . uniqid();

        $antwort = $this->handler(['action' => 'add_category', 'title' => $title, 'type' => 'gross']);
        $this->assertFalse($antwort['success']);
        $this->assertSame('Ungültiger Typ', $antwort['message']);
        $this->assertFalse(Capsule::table('intra_fahrzeuge_beladung_categories')->where('title', $title)->exists());

        $this->assertSame('Unbekannte Aktion', $this->handler(['action' => 'gibt_es_nicht'])['message']);
    }
}
