<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Api\SystemController;
use App\Plugins\Plugin;
use App\Plugins\PluginLoader;
use App\Plugins\PluginManifest;
use App\Search\SearchRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * GET /api/system/global-search für die Palette: nur angemeldet, eine
 * Gruppe je Quelle mit Beschriftung, Nebenzeile und Ziel, Quellen ohne
 * Recht fehlen, Plugin-Quellen aus dem Manifest erscheinen neben den
 * Kern-Quellen, unter zwei Zeichen nichts. Die Topbar gibt der Palette
 * die Aktionen mit, die der Betrachter darf.
 */
final class GlobalSearchTest extends FeatureTestCase
{
    private const PATH = '/api/system/global-search';

    /**
     * @param list<string> $permissions
     */
    private function login(array $permissions = ['full_admin']): void
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => $permissions, 'cirs_username' => $user->username]);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function search(string $q): array
    {
        return $this->assertJsonResponse($this->get(self::PATH, ['query' => ['q' => $q]]));
    }

    #[Test]
    public function ohne_anmeldung_401(): void
    {
        $this->assertUnauthorized($this->get(self::PATH, ['query' => ['q' => 'mu']]));
    }

    #[Test]
    public function gruppen_mit_beschriftung_nebenzeile_und_ziel(): void
    {
        $this->login();
        $vehicle = FixtureFactory::fahrzeug(['name' => 'Florian Suchtest 1/83/1', 'identifier' => 'suchtest_rtw']);
        $this->pdo->exec("INSERT INTO intra_mitarbeiter (fullname, dienstnr, gebdatum, einstdatum, geschlecht, discordtag, charakterid, dienstgrad, qualifw2, qualird) VALUES ('Sucht Estmann', 'ST-01', '1990-01-01', '2024-01-01', 0, '123456789012345678', 'ABC00001', (SELECT MIN(id) FROM intra_mitarbeiter_dienstgrade), (SELECT MIN(id) FROM intra_mitarbeiter_fwquali), (SELECT MIN(id) FROM intra_mitarbeiter_rdquali))");

        $body = $this->search('suchtest');

        $this->assertSame('suchtest', $body['q']);
        $keys = array_column($body['results'], 'key');
        $this->assertContains('vehicles', $keys);
        $this->assertNotContains('personnel', $keys, 'Der Mitarbeiter heißt anders.');

        $vehicles = $body['results'][array_search('vehicles', $keys, true)];
        $this->assertSame('Fahrzeuge', $vehicles['label']);
        $this->assertSame('Florian Suchtest 1/83/1', $vehicles['items'][0]['label']);
        $this->assertSame('suchtest_rtw', $vehicles['items'][0]['sub']);
        $this->assertMatchesRegularExpression('~^/settings/vehicles/vehicles/\d+$~', $vehicles['items'][0]['href']);

        $people = $this->search('estmann');
        $this->assertSame(['personnel'], array_column($people['results'], 'key'));
        $this->assertSame('Sucht Estmann', $people['results'][0]['items'][0]['label']);
        $this->assertSame('Dienstnr. ST-01', $people['results'][0]['items'][0]['sub']);
        $this->assertMatchesRegularExpression('~^/personnel/profile\?id=\d+$~', $people['results'][0]['items'][0]['href']);
        $this->assertSame(1, (int) $this->pdo->query("DELETE FROM intra_mitarbeiter WHERE dienstnr = 'ST-01'")->rowCount());
        unset($vehicle);
    }

    #[Test]
    public function quellen_ohne_recht_fehlen(): void
    {
        $this->login(['vehicles.view']);
        FixtureFactory::fahrzeug(['name' => 'Florian Rechtetest 1']);
        $this->pdo->exec("INSERT INTO intra_mitarbeiter (fullname, dienstnr, gebdatum, einstdatum, geschlecht, discordtag, charakterid, dienstgrad, qualifw2, qualird) VALUES ('Rechte Testperson', 'RT-01', '1990-01-01', '2024-01-01', 0, '123456789012345679', 'ABC00002', (SELECT MIN(id) FROM intra_mitarbeiter_dienstgrade), (SELECT MIN(id) FROM intra_mitarbeiter_fwquali), (SELECT MIN(id) FROM intra_mitarbeiter_rdquali))");

        try {
            $this->assertSame(['vehicles'], array_column($this->search('rechtetest')['results'], 'key'));
            $this->assertSame([], $this->search('testperson')['results'], 'Ohne personnel.view keine Mitarbeiter.');
        } finally {
            $this->pdo->exec("DELETE FROM intra_mitarbeiter WHERE dienstnr = 'RT-01'");
        }
    }

    #[Test]
    public function kurze_suchworte_liefern_nichts(): void
    {
        $this->login();
        FixtureFactory::fahrzeug(['name' => 'Florian K 1']);

        $this->assertSame([], $this->search('K')['results']);
        $this->assertSame([], $this->search('')['results']);
    }

    #[Test]
    public function plugin_quelle_aus_dem_manifest_erscheint(): void
    {
        $this->login();

        // Ein Fixture-Plugin mit Manifest-Feld `search` als aktives Plugin.
        $dir = dirname(__DIR__) . '/Unit/Plugins/fixtures/plugins/good';
        require_once $dir . '/src/Search/WidgetSource.php';
        $plugin = new Plugin(PluginManifest::fromArray(require $dir . '/manifest.php'), $dir);
        $loader = new class([$plugin]) extends PluginLoader {
            /** @param list<Plugin> $stubbed */
            public function __construct(private readonly array $stubbed)
            {
            }

            public function active(): array
            {
                return $this->stubbed;
            }
        };
        // Der Container hält Loader, Registry und Controller als Singletons;
        // alle drei für diesen Test austauschen und danach zurücksetzen.
        $container = $this->container;
        $this->assertInstanceOf(\DI\Container::class, $container);
        $previous = [
            PluginLoader::class     => $container->get(PluginLoader::class),
            SearchRegistry::class   => $container->get(SearchRegistry::class),
            SystemController::class => $container->get(SystemController::class),
        ];
        $registry = new SearchRegistry($loader);
        $container->set(PluginLoader::class, $loader);
        $container->set(SearchRegistry::class, $registry);
        $container->set(SystemController::class, new SystemController($registry));

        try {
            $body = $this->search('widget');
        } finally {
            foreach ($previous as $id => $instance) {
                $container->set($id, $instance);
            }
        }

        $this->assertSame(['widgets'], array_column($body['results'], 'key'));
        $this->assertSame('Widgets', $body['results'][0]['label']);
        $this->assertSame([['label' => 'Widget Alpha', 'sub' => 'aus dem Fixture-Plugin', 'href' => '/good/widgets/1']], $body['results'][0]['items']);
    }

    #[Test]
    public function topbar_gibt_der_palette_nur_erlaubte_aktionen_mit(): void
    {
        $this->login(['calendar.view', 'calendar.create']);

        $page = $this->get('/index')->body;
        $this->assertMatchesRegularExpression('~<div class="ignis-topbar__search" role="search"\s+data-endpoint="/api/system/global-search"\s+data-ignis-actions="~', $page);
        $this->assertSame(1, preg_match('~data-ignis-actions="([^"]*)"~', $page, $m));
        $actions = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
        $this->assertIsArray($actions);

        $labels = array_column($actions, 'label');
        $this->assertContains('Neuen Termin erstellen', $labels);
        $this->assertContains('Kalender', $labels);
        $this->assertNotContains('Neuen Mitarbeiter anlegen', $labels);
        $this->assertNotContains('Benutzer', $labels);

        $termin = $actions[array_search('Neuen Termin erstellen', $labels, true)];
        $this->assertSame(['label' => 'Neuen Termin erstellen', 'sub' => 'Neu', 'href' => '/calendar/create', 'drawer' => true, 'keywords' => 'neu anlegen erstellen'], $termin);
        $kalender = $actions[array_search('Kalender', $labels, true)];
        $this->assertSame('/calendar', $kalender['href']);
        $this->assertSame('Gehe zu', $kalender['sub']);
        $this->assertFalse($kalender['drawer']);

        $this->assertStringNotContainsString('globalSearchOverlay', $page);
        $this->assertStringContainsString('assets/js/ui/palette.js', $page);
    }
}
