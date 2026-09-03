<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * Listen sortieren, filtern und blättern auf dem Server (App\Support\ListQuery,
 * templates/partials/pagination.php); DataTables bleibt nur für eNOTF, das
 * nicht Teil des Redesigns ist. Der Test findet jede Ansicht außerhalb von
 * eNOTF, die DataTables noch initialisiert, und verlangt, dass sie auf der
 * Liste unten steht.
 *
 * Die Liste ist der Rest, nicht die Regel: die beiden Plugin-Listen nutzen
 * Mehrfachauswahl über DataTables-Zeilen (fireTab) und sortieren nach zwei
 * Spalten (MANV); sie kommen dran, wenn ListQuery Mehrfachauswahl kann.
 */
final class DataTablesUsageTest extends TestCase
{
    private const STILL_DATATABLES = [
        'plugins/firetab/templates/firetab/admin-list.php',
        'plugins/manv-board/templates/mci/board.php',
    ];

    /** @return list<string> Verzeichnisse relativ zur Repo-Wurzel */
    private function roots(): array
    {
        $roots = ['templates', 'assets/components', 'assets/js/modules', 'assets/js/pages'];
        foreach (glob(dirname(__DIR__, 3) . '/plugins/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (str_starts_with(basename($dir), 'enotf')) {
                continue;
            }
            $roots[] = 'plugins/' . basename($dir);
        }

        return $roots;
    }

    public function testOnlyTheListedViewsStillInitialiseDataTables(): void
    {
        $base  = dirname(__DIR__, 3);
        $found = [];

        foreach ($this->roots() as $root) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base . '/' . $root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if (!$file instanceof \SplFileInfo || !in_array($file->getExtension(), ['php', 'js'], true)) {
                    continue;
                }
                $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
                if (str_contains($rel, '/enotf') || str_contains($rel, 'datatables-config.js')) {
                    continue;
                }
                if (preg_match('~\.DataTable\(~', (string) file_get_contents($file->getPathname())) === 1) {
                    $found[] = $rel;
                }
            }
        }

        sort($found);

        $this->assertSame(
            self::STILL_DATATABLES,
            $found,
            "DataTables-Aufrufe außerhalb der bekannten Reste. Neue Listen laufen über ListQuery; eine umgestellte Liste muss von der Liste im Test gestrichen werden.",
        );
    }

    public function testListedViewsExist(): void
    {
        foreach (self::STILL_DATATABLES as $rel) {
            $this->assertFileExists(dirname(__DIR__, 3) . '/' . $rel);
        }
    }
}
