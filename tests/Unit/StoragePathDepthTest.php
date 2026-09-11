<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Wer den Weg zur Projektwurzel von Hand zählt, verzählt sich irgendwann.
 * Genau so entstand der leere Sync-Zeitstempel in eNOTF v1: der Leser stand
 * auf dirname(__DIR__, 4) und landete damit in plugins/ statt in der Wurzel,
 * während Schreiber und v2-Leser dieselbe Datei korrekt fanden.
 *
 * Der Fehler ist still — es gibt keine Exception, nur ein leeres Feld in der
 * Oberfläche. Deshalb hier ein Wächter über alle Vorkommen statt eines Tests
 * für die eine reparierte Zeile.
 */
final class StoragePathDepthTest extends TestCase
{
    #[Test]
    public function jeder_handgezaehlte_storage_pfad_trifft_die_projektwurzel(): void
    {
        $root  = str_replace('\\', '/', dirname(__DIR__, 2));
        $fehler = [];

        foreach ($this->phpFiles([$root . '/src', $root . '/plugins']) as $file) {
            $inhalt = (string) file_get_contents($file);
            if (!preg_match_all('~dirname\(__DIR__,\s*(\d+)\)\s*\.\s*\'/storage~', $inhalt, $treffer)) {
                continue;
            }

            foreach ($treffer[1] as $tiefe) {
                $ziel = str_replace('\\', '/', dirname($file));
                for ($i = 0; $i < (int) $tiefe; $i++) {
                    $ziel = dirname($ziel);
                }

                if ($ziel !== $root) {
                    $fehler[] = sprintf(
                        '%s: dirname(__DIR__, %s) landet in %s statt in der Wurzel',
                        ltrim(str_replace($root, '', str_replace('\\', '/', $file)), '/'),
                        $tiefe,
                        $ziel
                    );
                }
            }
        }

        $this->assertSame([], $fehler, "Falsch gezählte storage-Pfade:\n" . implode("\n", $fehler));
    }

    /**
     * @param  list<string>  $dirs
     * @return list<string>
     */
    private function phpFiles(array $dirs): array
    {
        $files = [];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $entry) {
                if ($entry instanceof \SplFileInfo && $entry->getExtension() === 'php') {
                    $files[] = $entry->getPathname();
                }
            }
        }

        return $files;
    }
}
