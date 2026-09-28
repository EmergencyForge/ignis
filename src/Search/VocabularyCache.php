<?php

declare(strict_types=1);

namespace App\Search;

use EmergencyForge\FuzzySearch\Vocabulary;

/**
 * Datei-Cache für das Vokabular einer Suchquelle: storage/cache/search-<key>.php,
 * 10 Minuten gültig. Eine ereignisgesteuerte Invalidierung ist in ignis
 * nicht verlässlich, weil über 30 Stellen direkt in die Tabellen
 * schreiben (siehe Spec) — eine Zeit-TTL reicht.
 *
 * Der Inhalt ist ein reines var_export-Array (keine Objekte, kein Code
 * aus Nutzereingaben). Geschrieben wird über eine temporäre Datei plus
 * rename, damit ein gleichzeitiger Leser nie eine halb geschriebene
 * Datei sieht. Schlägt Schreiben oder Verzeichnis fehl, läuft die
 * Anfrage einfach mit dem im Speicher gebauten Vokabular weiter.
 */
final class VocabularyCache
{
    private const TTL = 600;

    public function __construct(private readonly string $dir)
    {
    }

    /**
     * @param callable(): Vocabulary $build
     */
    public function remember(string $key, callable $build): Vocabulary
    {
        $file = $this->dir . '/search-' . $key . '.php';

        $cached = $this->read($file);
        if ($cached !== null) {
            return $cached;
        }

        $vocabulary = $build();
        $this->write($file, $vocabulary);

        return $vocabulary;
    }

    private function read(string $file): ?Vocabulary
    {
        // PHP hält den stat()-Aufruf sonst für die Prozesslaufzeit im Cache;
        // ohne clearstatcache() sähe ein zweiter Lauf im selben Prozess (z.B.
        // Queue-Worker, Tests) noch die alte mtime einer gerade erst
        // geschriebenen oder künstlich zurückdatierten Datei.
        clearstatcache(true, $file);
        if (!is_file($file) || (time() - (int) @filemtime($file)) >= self::TTL) {
            return null;
        }

        $data = @include $file;
        if (!is_array($data) || !isset($data['root'], $data['count'])) {
            return null;
        }

        /** @var array{root: array<string, mixed>, count: int} $data */
        return Vocabulary::fromArray($data);
    }

    private function write(string $file, Vocabulary $vocabulary): void
    {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0775, true) && !is_dir($this->dir)) {
            return;
        }

        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $code = '<?php return ' . var_export($vocabulary->toArray(), true) . ';' . "\n";
        if (@file_put_contents($tmp, $code) === false) {
            return;
        }
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
        }
    }
}
