<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die Dashboard-Konfiguration: Kategorien und die Verlinkungen darin.
 *
 * Das Ziel einer Verlinkung landet auf dem Dashboard in einem `href`
 * (`dashboard.php`). Der Controller nahm es, wie es kam — `javascript:…`
 * eingeschlossen, und damit ein Skript für jeden, der die Kachel anklickt.
 */
final class DashboardSettingsTest extends FeatureTestCase
{
    private const SEITE = '/settings/dashboard';

    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user(['full_admin' => true]);
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
    }

    private function kategorie(): int
    {
        return (int) Capsule::table('intra_dashboard_categories')
            ->insertGetId(['title' => 'Einsatz', 'priority' => 1]);
    }

    /** @return array<string,array{0:string}> */
    public static function boeseZiele(): array
    {
        return [
            'javascript'            => ['javascript:alert(1)'],
            'javascript in Grossbuchstaben' => ['JavaScript:alert(1)'],
            'mit fuehrendem Leerzeichen'    => ['  javascript:alert(1)'],
            'data-URL'              => ['data:text/html,<script>alert(1)</script>'],
            'vbscript'              => ['vbscript:msgbox(1)'],
        ];
    }

    #[Test]
    #[DataProvider('boeseZiele')]
    public function ein_ziel_mit_skript_wird_nicht_angelegt(string $ziel): void
    {
        $vorher = Capsule::table('intra_dashboard_tiles')->count();

        $this->post(self::SEITE . '/tiles/create', [
            'category' => (string) $this->kategorie(),
            'title'    => 'Harmlos',
            'url'      => $ziel,
        ]);

        $this->assertSame($vorher, Capsule::table('intra_dashboard_tiles')->count(), 'Ziel: ' . $ziel);
    }

    /** @return array<string,array{0:string}> */
    public static function guteZiele(): array
    {
        return [
            'Pfad dieser Installation' => ['/settings/vehicles/vehicles/index'],
            'relativer Pfad'           => ['dashboard'],
            'Pfad mit Doppelpunkt'     => ['/akte/a:b'],
            'Anker'                    => ['#abschnitt'],
            'https'                    => ['https://example.org/handbuch'],
            'mailto'                   => ['mailto:leitstelle@example.org'],
        ];
    }

    #[Test]
    #[DataProvider('guteZiele')]
    public function ein_gewoehnliches_ziel_geht_durch(string $ziel): void
    {
        $vorher = Capsule::table('intra_dashboard_tiles')->count();

        $this->post(self::SEITE . '/tiles/create', [
            'category' => (string) $this->kategorie(),
            'title'    => 'Harmlos',
            'url'      => $ziel,
        ]);

        $this->assertSame($vorher + 1, Capsule::table('intra_dashboard_tiles')->count(), 'Ziel: ' . $ziel);
    }

    #[Test]
    public function eine_verlinkung_ohne_kategorie_wird_abgewiesen(): void
    {
        $vorher = Capsule::table('intra_dashboard_tiles')->count();

        $this->post(self::SEITE . '/tiles/create', ['category' => '0', 'title' => 'Ohne', 'url' => '/x']);

        $this->assertSame($vorher, Capsule::table('intra_dashboard_tiles')->count());
    }

    #[Test]
    public function kategorie_anlegen_aendern_loeschen(): void
    {
        $vorher = Capsule::table('intra_dashboard_categories')->count();

        $this->assertRedirect($this->post(self::SEITE . '/categories/create', ['title' => 'Technik', 'priority' => '3']));
        $id = (int) Capsule::table('intra_dashboard_categories')->where('title', 'Technik')->value('id');
        $this->assertGreaterThan(0, $id);

        $this->post(self::SEITE . '/categories/update', ['id' => (string) $id, 'title' => 'Technik & Sicherheit']);
        $this->assertSame('Technik & Sicherheit', Capsule::table('intra_dashboard_categories')->where('id', $id)->value('title'));

        $this->post(self::SEITE . '/categories/delete', ['id' => (string) $id]);
        $this->assertSame($vorher, Capsule::table('intra_dashboard_categories')->count());
    }

    #[Test]
    public function ein_leerer_titel_legt_nichts_an(): void
    {
        $vorher = Capsule::table('intra_dashboard_categories')->count();

        $this->post(self::SEITE . '/categories/create', ['title' => '   ']);

        $this->assertSame($vorher, Capsule::table('intra_dashboard_categories')->count());
    }
}
