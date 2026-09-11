<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Pipeline;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Der Route-Cache muss verfallen, sobald sich der Satz der Quelldateien
 * ändert. Vorher verglich er nur die mtime der Kern-Dateien unter routes/,
 * und damit blieb ein deaktiviertes Plugin über seine Routen erreichbar.
 */
final class RouterCacheInvalidationTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/ignis_routecache_' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/cache', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach ((glob($this->dir . '/cache/*') ?: []) as $f) {
            @unlink($f);
        }
        foreach ((glob($this->dir . '/*.php') ?: []) as $f) {
            @unlink($f);
        }
        @rmdir($this->dir . '/cache');
        @rmdir($this->dir);

        parent::tearDown();
    }

    #[Test]
    public function gleiche_quellen_lassen_den_cache_stehen(): void
    {
        $a = $this->sourceFile('a');

        $first = $this->buildAndStampCache([$a]);
        $this->assertFileExists($this->cachePath());

        $this->buildAndStampCache([$a]);

        $this->assertSame(
            $first,
            (int) filemtime($this->cachePath()),
            'Unveränderte Quellen dürfen den Cache nicht neu bauen'
        );
    }

    /**
     * Der Fall, der im Panel passiert: das Plugin wird abgeschaltet, auf der
     * Platte ändert sich nichts. Ohne diesen Test bliebe seine Route bedienbar.
     */
    #[Test]
    public function eine_weggefallene_quelle_verwirft_den_cache(): void
    {
        $a = $this->sourceFile('a');
        $b = $this->sourceFile('b');

        $this->buildAndStampCache([$a, $b]);
        $stampVorher = file_get_contents($this->stampPath());

        $this->buildAndStampCache([$a]);

        $this->assertNotSame($stampVorher, file_get_contents($this->stampPath()));
    }

    #[Test]
    public function eine_neue_quelle_verwirft_den_cache(): void
    {
        $a = $this->sourceFile('a');
        $this->buildAndStampCache([$a]);
        $stampVorher = file_get_contents($this->stampPath());

        $b = $this->sourceFile('b');
        $this->buildAndStampCache([$a, $b]);

        $this->assertNotSame($stampVorher, file_get_contents($this->stampPath()));
    }

    #[Test]
    public function eine_geaenderte_plugin_quelle_verwirft_den_cache(): void
    {
        $a = $this->sourceFile('a');
        $this->buildAndStampCache([$a]);
        $stampVorher = file_get_contents($this->stampPath());

        touch($a, time() + 60);
        clearstatcache(true, $a);
        $this->buildAndStampCache([$a]);

        $this->assertNotSame($stampVorher, file_get_contents($this->stampPath()));
    }

    /**
     * Welches Plugin zuerst geladen wird, sagt nichts über den Route-Satz.
     * Ohne Sortierung würfe eine andere Reihenfolge den Cache grundlos weg.
     */
    #[Test]
    public function die_reihenfolge_der_quellen_zaehlt_nicht(): void
    {
        $a = $this->sourceFile('a');
        $b = $this->sourceFile('b');

        $this->buildAndStampCache([$a, $b]);
        $stampVorher = file_get_contents($this->stampPath());

        $this->buildAndStampCache([$b, $a]);

        $this->assertSame($stampVorher, file_get_contents($this->stampPath()));
    }

    private function cachePath(): string
    {
        return $this->dir . '/cache/routes.php';
    }

    private function stampPath(): string
    {
        return $this->cachePath() . '.sources';
    }

    private function sourceFile(string $name): string
    {
        $path = $this->dir . '/' . $name . '.php';
        file_put_contents($path, '<?php // Routenfragment ' . $name);

        return $path;
    }

    /**
     * Baut einen Router mit Cache über die angegebenen Quellen und stösst
     * den Dispatcher an, damit die Invalidierung läuft.
     *
     * @param  list<string>  $sources
     * @return int mtime der Cache-Datei danach
     */
    private function buildAndStampCache(array $sources): int
    {
        $router = new Router(
            $this->container,
            new Pipeline($this->container),
            enableCache: true,
            cacheFile: $this->cachePath(),
        );
        foreach ($sources as $s) {
            $router->registerRouteSource($s);
        }
        $router->get('/ping', fn () => Response::json(['ok' => true]));
        $router->dispatch(new Request('GET', '/ping'));

        clearstatcache(true, $this->cachePath());

        return (int) filemtime($this->cachePath());
    }
}
