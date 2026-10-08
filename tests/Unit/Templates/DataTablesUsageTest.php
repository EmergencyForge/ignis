<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * Listen sortieren, filtern und blättern auf dem Server (App\Support\ListQuery,
 * templates/partials/pagination.php). Der Test findet jede Ansicht und jedes
 * Skript, das DataTables noch initialisiert, und verlangt, dass es keine gibt.
 *
 * eNOTF gehört dazu: die Einstellungsseiten unter plugins/enotf/templates/
 * settings/ (Medikamente, POIs, Fachrichtungen, Zugangscodes) laufen über
 * ListQuery, die Crew-Seiten haben DataTables nie benutzt.
 */
final class DataTablesUsageTest extends TestCase
{
    /** @return list<string> Verzeichnisse relativ zur Repo-Wurzel */
    private function roots(): array
    {
        $roots = ['templates', 'assets/components', 'assets/js/modules', 'assets/js/pages'];
        foreach (glob(dirname(__DIR__, 3) . '/plugins/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $roots[] = 'plugins/' . basename($dir);
        }

        return $roots;
    }

    public function testNoViewInitialisesDataTables(): void
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
                if (preg_match('~\.DataTable\(~', (string) file_get_contents($file->getPathname())) === 1) {
                    $found[] = $rel;
                }
            }
        }

        sort($found);

        $this->assertSame(
            [],
            $found,
            "DataTables-Aufrufe. Listen laufen über ListQuery (Sortier-Links, Suche als GET, Pagination-Partial):\n  " . implode("\n  ", $found),
        );
    }
}
