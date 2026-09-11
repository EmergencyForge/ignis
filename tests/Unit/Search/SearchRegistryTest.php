<?php

declare(strict_types=1);

namespace Tests\Unit\Search;

use App\Plugins\Plugin;
use App\Plugins\PluginLoader;
use App\Plugins\PluginManifest;
use App\Search\SearchRegistry;
use App\Search\SearchSourceInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Die Registry fragt Kern- und Plugin-Quellen gleich ab: Quellen ohne
 * Recht fehlen, leere Gruppen fehlen, mehr als das Limit gibt es nicht,
 * unter zwei Zeichen kommt nichts, und eine Quelle, die wirft, nimmt die
 * anderen nicht mit. Die Kern-Quellen brauchen eine Datenbank und stehen
 * in tests/Feature/GlobalSearchTest; hier zählt die Mechanik.
 */
final class SearchRegistryTest extends TestCase
{
    /**
     * @param list<Plugin> $plugins
     */
    private function loaderWith(array $plugins): PluginLoader
    {
        return new class($plugins) extends PluginLoader {
            /** @param list<Plugin> $stubbed */
            public function __construct(private readonly array $stubbed)
            {
            }

            public function active(): array
            {
                return $this->stubbed;
            }
        };
    }

    /**
     * Eigene Kern-Quellen statt der echten (die eine DB brauchen), dazu die
     * Plugin-Quellen des Loaders.
     *
     * @param list<SearchSourceInterface> $core
     */
    private function registry(PluginLoader $loader, array $core = []): SearchRegistry
    {
        return new SearchRegistry($loader, $core);
    }

    private function goodPlugin(): Plugin
    {
        $dir = dirname(__DIR__) . '/Plugins/fixtures/plugins/good';
        require_once $dir . '/src/Search/WidgetSource.php';
        $manifest = PluginManifest::fromArray(require $dir . '/manifest.php');
        return new Plugin($manifest, $dir);
    }

    /**
     * @param list<array{label: string, sub: string, href: string}> $items
     */
    private function source(string $key, bool $allowed, array $items, ?\Throwable $throws = null): SearchSourceInterface
    {
        return new class($key, $allowed, $items, $throws) implements SearchSourceInterface {
            /**
             * @param list<array{label: string, sub: string, href: string}> $items
             */
            public function __construct(private string $key, private bool $allowed, private array $items, private ?\Throwable $throws)
            {
            }

            public function key(): string
            {
                return $this->key;
            }

            public function label(): string
            {
                return ucfirst($this->key);
            }

            public function allowed(): bool
            {
                return $this->allowed;
            }

            public function search(string $q, int $limit): array
            {
                if ($this->throws !== null) {
                    throw $this->throws;
                }
                return $this->items;
            }
        };
    }

    #[Test]
    public function plugin_quellen_kommen_aus_dem_manifest(): void
    {
        $registry = $this->registry($this->loaderWith([$this->goodPlugin()]));

        $groups = $registry->run('widget');

        $this->assertSame(['widgets'], array_column($groups, 'key'));
        $this->assertSame('Widgets', $groups[0]['label']);
        $this->assertSame('Widget Alpha', $groups[0]['items'][0]['label']);
        $this->assertSame('/good/widgets/1', $groups[0]['items'][0]['href']);
    }

    #[Test]
    public function quellen_ohne_recht_und_ohne_treffer_fehlen(): void
    {
        $hit = ['label' => 'Treffer', 'sub' => '', 'href' => '/x'];
        $registry = $this->registry($this->loaderWith([]), [
            $this->source('secret', false, [$hit]),
            $this->source('empty', true, []),
            $this->source('open', true, [$hit]),
        ]);

        $this->assertSame(['open'], array_column($registry->run('tref'), 'key'));
    }

    #[Test]
    public function das_limit_gilt_je_quelle_und_kurze_suchworte_liefern_nichts(): void
    {
        $items = array_map(static fn (int $n): array => ['label' => 'Nr ' . $n, 'sub' => '', 'href' => '/' . $n], range(1, 8));
        $registry = $this->registry($this->loaderWith([]), [$this->source('many', true, $items)]);

        $this->assertCount(5, $registry->run('nr')[0]['items']);
        $this->assertCount(2, $registry->run('nr', 2)[0]['items']);
        $this->assertSame([], $registry->run('n'));
        $this->assertSame([], $registry->run('  '));
    }

    #[Test]
    public function eine_werfende_quelle_nimmt_die_anderen_nicht_mit(): void
    {
        $hit = ['label' => 'Treffer', 'sub' => '', 'href' => '/x'];
        $registry = $this->registry($this->loaderWith([]), [
            $this->source('broken', true, [], new \RuntimeException('Tabelle fehlt')),
            $this->source('fine', true, [$hit]),
        ]);

        $this->assertSame(['fine'], array_column($registry->run('tref'), 'key'));
    }

    #[Test]
    public function eine_unbekannte_klasse_im_manifest_wird_uebersprungen(): void
    {
        $manifest = PluginManifest::fromArray([
            'id' => 'broken', 'name' => 'Broken', 'version' => '1.0.0',
            'search' => ['Nope\\Missing', 'GoodPluginFixture\\Policies\\GoodresPolicy'],
        ]);
        require_once dirname(__DIR__) . '/Plugins/fixtures/plugins/good/src/Policies/GoodresPolicy.php';
        $loader = $this->loaderWith([new Plugin($manifest, '/virtual/broken')]);

        $this->assertSame([], $loader->searchSources());
    }
}
