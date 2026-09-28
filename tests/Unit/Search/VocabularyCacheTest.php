<?php

declare(strict_types=1);

namespace Tests\Unit\Search;

use App\Search\VocabularyCache;
use EmergencyForge\FuzzySearch\Vocabulary;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * $key kommt heute nur aus SearchSourceInterface::key() (feste Strings im
 * Code), aber der Dateiname darf trotzdem nie aus dem Cache-Verzeichnis
 * ausbrechen — siehe VocabularyCache::sanitizeKey().
 */
final class VocabularyCacheTest extends TestCase
{
    /** @var list<string> */
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

    private function tmpDir(): string
    {
        $dir = sys_get_temp_dir() . '/ignis-vocab-cache-test-' . bin2hex(random_bytes(6));
        $this->tmpDirs[] = $dir;
        return $dir;
    }

    #[Test]
    public function ein_schlechter_schluessel_bleibt_im_cache_verzeichnis(): void
    {
        $dir = $this->tmpDir();
        $cache = new VocabularyCache($dir);

        $cache->remember('../../evil', static fn (): Vocabulary => Vocabulary::fromWords(['Test']));

        $files = glob($dir . '/*') ?: [];
        $this->assertCount(1, $files, 'Es darf genau eine Cache-Datei entstehen.');
        $this->assertSame($dir, dirname($files[0]), 'Die Datei muss direkt im Cache-Verzeichnis liegen, nicht darüber oder daneben.');
    }

    #[Test]
    public function ein_gecachtes_vokabular_kommt_trotz_sanitisiertem_schluessel_unveraendert_zurueck(): void
    {
        $dir = $this->tmpDir();
        $cache = new VocabularyCache($dir);
        $built = 0;
        $build = static function () use (&$built): Vocabulary {
            $built++;
            return Vocabulary::fromWords(['Müller']);
        };

        $first = $cache->remember('../evil', $build);
        $second = $cache->remember('../evil', $build);

        $this->assertSame(1, $built, 'Der zweite Aufruf muss aus der Cache-Datei kommen.');
        $this->assertSame($first->toArray(), $second->toArray());
    }
}
