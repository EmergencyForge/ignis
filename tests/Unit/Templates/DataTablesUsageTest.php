<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * Listen sortieren, filtern und blättern auf dem Server (App\Support\ListQuery,
 * templates/partials/pagination.php); DataTables bleibt nur für eNOTF, das
 * nicht Teil des Redesigns ist. Der Test findet jede Ansicht außerhalb von
 * eNOTF, die DataTables noch initialisiert, und verlangt, dass es keine gibt.
 *
 * Die letzten beiden Reste, die fireTab-Einsatzliste (Mehrfachauswahl über
 * den Arbeitsbereich, assets/js/ui/workbench.js) und das MANV-Board
 * (Sortierung über ListQuery), sind umgestellt; das Vendor-Bundle
 * (assets/js/vendor.js) trägt DataTables nur noch für eNOTF.
 */
final class DataTablesUsageTest extends TestCase
{
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

    public function testNoViewOutsideEnotfInitialisesDataTables(): void
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
            [],
            $found,
            "DataTables-Aufrufe außerhalb von eNOTF. Listen laufen über ListQuery (Sortier-Links, Suche als GET, Pagination-Partial):\n  " . implode("\n  ", $found),
        );
    }
}
