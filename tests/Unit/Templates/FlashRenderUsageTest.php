<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * Die Flash-Meldung wird an einer Stelle ausgegeben: oben im <main> von
 * templates/layouts/admin.php. Eine Ansicht, die durch die Hülle rendert
 * und `Flash::render()` trotzdem selbst aufruft, würde die Meldung
 * verbrauchen, bevor die Hülle sie ausgibt, oder sie doppelt zeigen.
 *
 * Erlaubt bleibt der Aufruf nur in Dateien mit eigener Hülle (eigenes
 * `<!DOCTYPE html>`), also den fireTab-App-Seiten und eNOTF, das nicht Teil
 * des Redesigns ist und hier gar nicht erst durchsucht wird.
 */
final class FlashRenderUsageTest extends TestCase
{
    /** @return list<string> Verzeichnisse relativ zur Repo-Wurzel */
    private function roots(): array
    {
        $roots = ['templates', 'assets/components'];
        foreach (glob(dirname(__DIR__, 3) . '/plugins/*/templates', GLOB_ONLYDIR) ?: [] as $dir) {
            $plugin = basename(dirname($dir));
            if (str_starts_with($plugin, 'enotf')) {
                continue;
            }
            $roots[] = 'plugins/' . $plugin . '/templates';
        }

        return $roots;
    }

    public function testOnlyTheLayoutAndOwnShellsRenderTheFlash(): void
    {
        $base = dirname(__DIR__, 3);
        $files = [$base . '/index.php', $base . '/dashboard.php'];
        foreach ($this->roots() as $root) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base . '/' . $root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        $offenders = [];
        foreach ($files as $path) {
            $src = (string) file_get_contents($path);
            if (!str_contains($src, 'Flash::render(')) {
                continue;
            }
            if (str_ends_with(str_replace('\\', '/', $path), 'templates/layouts/admin.php')) {
                continue;
            }
            if (str_contains($src, '<!DOCTYPE html>')) {
                continue;
            }
            $offenders[] = str_replace('\\', '/', substr($path, strlen($base) + 1));
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "Diese Ansichten rendern durch die Hülle und rufen Flash::render() trotzdem selbst:\n  "
                . implode("\n  ", $offenders),
        );
    }
}
