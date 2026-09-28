<?php

declare(strict_types=1);

namespace Tests\Unit\Search;

use App\Plugins\PluginLoader;
use App\Search\FuzzySearchSource;
use App\Search\SearchRegistry;
use App\Search\SearchSourceInterface;
use App\Search\VocabularyCache;
use EmergencyForge\Plugins\Plugin;
use EmergencyForge\Plugins\PluginManifest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Die Registry fragt Kern- und Plugin-Quellen gleich ab: Quellen ohne
 * Recht fehlen, leere Gruppen fehlen, mehr als das Limit gibt es nicht,
 * unter zwei Zeichen kommt nichts, und eine Quelle, die wirft, nimmt die
 * anderen nicht mit. Die Kern-Quellen brauchen eine Datenbank und stehen
 * in tests/Feature/GlobalSearchTest; hier zählt die Mechanik. Das
 * unscharfe Nachlegen bei FuzzySearchSource-Quellen steht weiter unten.
 */
final class SearchRegistryTest extends TestCase
{
    /** @var list<string> Temp-Verzeichnisse aus Cache-Tests, am Ende aufräumen. */
    private array $tmpDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpDirs as $dir) {
            foreach (glob($dir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
        $this->tmpDirs = [];
        parent::tearDown();
    }

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
    private function registry(PluginLoader $loader, array $core = [], ?VocabularyCache $cache = null): SearchRegistry
    {
        return new SearchRegistry($loader, $core, $cache);
    }

    private function tmpCache(): VocabularyCache
    {
        $dir = sys_get_temp_dir() . '/ignis-fuzzy-test-' . bin2hex(random_bytes(6));
        $this->tmpDirs[] = $dir;
        return new VocabularyCache($dir);
    }

    /**
     * Rückgabetyp als Objekt-Shape statt nur FuzzySearchSource: die
     * aufrufenden Tests lesen die öffentlichen Zähler ($searchCalls,
     * $vocabularyCalls) der anonymen Klasse.
     *
     * @param array<string, list<array{label: string, sub: string, href: string}>> $itemsByQuery Antwort von search() je genauem Suchwort.
     * @param list<string> $vocabulary
     * @return FuzzySearchSource&object{searchCalls: list<string>, vocabularyCalls: int}
     */
    private function fuzzySource(string $key, bool $allowed, array $itemsByQuery, array $vocabulary, ?\Throwable $vocabularyThrows = null): FuzzySearchSource
    {
        return new class($key, $allowed, $itemsByQuery, $vocabulary, $vocabularyThrows) implements FuzzySearchSource {
            /** @var list<string> */
            public array $searchCalls = [];
            public int $vocabularyCalls = 0;

            /**
             * @param array<string, list<array{label: string, sub: string, href: string}>> $itemsByQuery
             * @param list<string> $vocabulary
             */
            public function __construct(
                private string $key,
                private bool $allowed,
                private array $itemsByQuery,
                private array $vocabulary,
                private ?\Throwable $vocabularyThrows,
            ) {
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
                $this->searchCalls[] = $q;
                return array_slice($this->itemsByQuery[$q] ?? [], 0, $limit);
            }

            public function vocabulary(): iterable
            {
                $this->vocabularyCalls++;
                if ($this->vocabularyThrows !== null) {
                    throw $this->vocabularyThrows;
                }
                return $this->vocabulary;
            }
        };
    }

    private function goodPlugin(): Plugin
    {
        $dir = dirname(__DIR__) . '/Plugins/fixtures/plugins/good';
        require_once $dir . '/src/Search/WidgetSource.php';
        $manifest = PluginManifest::fromArray(require $dir . '/manifest.php');
        return new Plugin($manifest, $dir);
    }

    #[Test]
    public function scopes_respect_permissions_and_filter_before_searching(): void
    {
        $hit = ['label' => 'Treffer', 'sub' => '', 'href' => '/x'];
        $selected = $this->createMock(SearchSourceInterface::class);
        $selected->method('key')->willReturn('selected');
        $selected->method('label')->willReturn('Selected');
        $selected->method('allowed')->willReturn(true);
        $selected->expects($this->once())->method('search')->with('term', 5)->willReturn([$hit]);
        $other = $this->createMock(SearchSourceInterface::class);
        $other->method('key')->willReturn('other');
        $other->method('allowed')->willReturn(true);
        $other->expects($this->never())->method('search');
        $registry = $this->registry($this->loaderWith([]), [$selected, $other, $this->source('secret', false, [$hit])]);
        $this->assertSame(['selected', 'other'], array_column($registry->scopes(), 'key'));
        $this->assertSame(['selected'], array_column($registry->run('term', scope: 'selected'), 'key'));
        $this->assertSame([], $registry->run('term', scope: 'secret'));
        $this->assertSame([], $registry->run('term', scope: 'unknown'));
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

    // ── Unscharfes Nachlegen (FuzzySearchSource) ────────────────────────

    #[Test]
    public function fuzzy_quellen_legen_erst_nach_wenn_die_suche_unter_dem_limit_bleibt(): void
    {
        $exact = ['label' => 'Hans Meier', 'sub' => '', 'href' => '/1'];
        $approx = ['label' => 'Hans Müller', 'sub' => '', 'href' => '/2'];
        $source = $this->fuzzySource('personnel', true, [
            'mueller' => [$exact],
            'Müller'  => [$approx],
        ], ['Hans Müller']);

        $groups = $this->registry($this->loaderWith([]), [$source], $this->tmpCache())->run('mueller');

        $this->assertSame([$exact, $approx + ['approx' => true]], $groups[0]['items']);
        $this->assertSame(1, $source->vocabularyCalls);
        $this->assertSame(['mueller', 'Müller'], $source->searchCalls);
    }

    #[Test]
    public function fuzzy_quellen_ohne_luecke_legen_nicht_nach(): void
    {
        $items = array_map(static fn (int $n): array => ['label' => 'Nr ' . $n, 'sub' => '', 'href' => '/' . $n], range(1, 5));
        $source = $this->fuzzySource('many', true, ['nr' => $items], ['Irrelevantes Wort']);

        $groups = $this->registry($this->loaderWith([]), [$source], $this->tmpCache())->run('nr');

        $this->assertCount(5, $groups[0]['items']);
        $this->assertSame(0, $source->vocabularyCalls, 'Genug Treffer: kein Vokabular nötig.');
        $this->assertSame(['nr'], $source->searchCalls);
    }

    #[Test]
    public function quellen_ohne_fuzzysearchsource_bekommen_kein_nachlegen(): void
    {
        $hit = ['label' => 'Treffer', 'sub' => '', 'href' => '/x'];
        $registry = $this->registry($this->loaderWith([]), [$this->source('plain', true, [$hit])]);

        // Der Fake aus source() implementiert nur SearchSourceInterface: bleibt
        // die Anfrage unter dem Limit, gibt es trotzdem kein Nachlegen, weil die
        // Registry FuzzySearchSource per instanceof prüft.
        $this->assertSame([$hit], $registry->run('tref')[0]['items']);
    }

    #[Test]
    public function ohne_recht_wird_auch_nicht_unscharf_gesucht(): void
    {
        $source = $this->fuzzySource('secret', false, ['mueller' => []], ['Hans Müller']);

        $groups = $this->registry($this->loaderWith([]), [$source], $this->tmpCache())->run('mueller');

        $this->assertSame([], $groups);
        $this->assertSame(0, $source->vocabularyCalls);
        $this->assertSame([], $source->searchCalls);
    }

    #[Test]
    public function unscharfe_treffer_kommen_hinten_und_ohne_duplikate(): void
    {
        $exact = ['label' => 'Hans Meier', 'sub' => '', 'href' => '/1'];
        // Käme aus der unscharfen Suche mit derselben href zurück wie ein
        // schon vorhandener Treffer: darf kein zweites Mal auftauchen.
        $dup = ['label' => 'Hans Meier', 'sub' => '', 'href' => '/1'];
        $new = ['label' => 'Hans Müller', 'sub' => '', 'href' => '/2'];
        $source = $this->fuzzySource('personnel', true, [
            'mueller' => [$exact],
            'Müller'  => [$dup, $new],
        ], ['Hans Müller']);

        $items = $this->registry($this->loaderWith([]), [$source], $this->tmpCache())->run('mueller')[0]['items'];

        $this->assertSame([$exact, $new + ['approx' => true]], $items);
    }

    #[Test]
    public function unscharfe_anfragen_sind_je_quelle_auf_drei_gedeckelt(): void
    {
        // Zwei Tokens mit je zwei Vokabular-Treffern (exakt + unscharf)
        // ergäben ohne Deckel vier Kombinationen; die Registry bricht bei drei ab.
        $source = $this->fuzzySource('personnel', true, [
            'hans mueller' => [],
            'Hans Mueller' => [['label' => 'a', 'sub' => '', 'href' => '/a']],
            'Hans Moeller' => [['label' => 'b', 'sub' => '', 'href' => '/b']],
            'Hanz Mueller' => [['label' => 'c', 'sub' => '', 'href' => '/c']],
            'Hanz Moeller' => [['label' => 'd', 'sub' => '', 'href' => '/d']],
        ], ['Hans Mueller', 'Hanz Mueller', 'Hans Moeller']);

        $this->registry($this->loaderWith([]), [$source], $this->tmpCache())->run('hans mueller');

        $this->assertSame(
            ['hans mueller', 'Hans Mueller', 'Hans Moeller', 'Hanz Mueller'],
            $source->searchCalls,
        );
    }

    #[Test]
    public function das_vokabular_wird_zehn_minuten_gecacht_und_danach_neu_gebaut(): void
    {
        $dir = sys_get_temp_dir() . '/ignis-fuzzy-test-' . bin2hex(random_bytes(6));
        $this->tmpDirs[] = $dir;
        $cache = new VocabularyCache($dir);
        $source = $this->fuzzySource('personnel', true, [
            'mueller' => [],
            'Müller'  => [['label' => 'Hans Müller', 'sub' => '', 'href' => '/2']],
        ], ['Hans Müller']);

        $this->registry($this->loaderWith([]), [$source], $cache)->run('mueller');
        $this->assertSame(1, $source->vocabularyCalls);

        $file = $dir . '/search-personnel.php';
        $this->assertFileExists($file, 'Cache-Datei fehlt.');

        // Innerhalb der TTL: derselbe Cache, kein erneuter Aufbau.
        $this->registry($this->loaderWith([]), [$source], $cache)->run('mueller');
        $this->assertSame(1, $source->vocabularyCalls);

        // TTL abgelaufen: Vokabular wird neu gebaut.
        touch($file, time() - 601);
        $this->registry($this->loaderWith([]), [$source], $cache)->run('mueller');
        $this->assertSame(2, $source->vocabularyCalls);
    }

    #[Test]
    public function ein_scheiterndes_nachlegen_reisst_die_bereits_gefundenen_treffer_nicht_mit(): void
    {
        $exact = ['label' => 'Hans Meier', 'sub' => '', 'href' => '/1'];
        $source = $this->fuzzySource(
            'personnel',
            true,
            ['mueller' => [$exact]],
            [],
            new \RuntimeException('Vokabular-Aufbau kaputt'),
        );

        $groups = $this->registry($this->loaderWith([]), [$source], $this->tmpCache())->run('mueller');

        $this->assertSame([$exact], $groups[0]['items']);
    }
}
