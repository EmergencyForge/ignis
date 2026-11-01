<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * In der Desktop-Hülle (efOS) laufen mehrere Seiten im selben Dokument.
 * Doppelte IDs bekommen dort einen Präfix je Fenster, ein Skript, das seine
 * Elemente über eine ID oder über `document` sucht, findet dann nur die im
 * ersten Fenster. Solche Seiten setzen `$windowSingle` (höchstens ein
 * Fenster) oder `$windowPage` (nie im Fenster, immer als ganze Seite).
 *
 * Geprüft wird jede Ansicht mit `$layout = 'admin'` im Kern und in den
 * Plugins: ihr eigenes Skript, die Partials, die sie einbindet, und die
 * Skripte unter assets/js, die sie lädt (ohne die Module des ui-Pakets).
 */
final class WindowSafetyTest extends TestCase
{
    private const LOOKUP = '~getElementById\(|document\.querySelector(All)?\(|document\.getElementsBy|\$\(\s*[\'"]#~';

    public function testPagesWithIdScriptsAreLimitedToOneWindow(): void
    {
        $root = dirname(__DIR__, 3);
        $offenders = [];
        foreach (self::views($root) as $path) {
            $src = (string) file_get_contents($path);
            if (preg_match('~\$layout\s*=\s*\'admin\'~', $src) !== 1 || preg_match('~\$window(Single|Page)\s*=\s*true~', $src) === 1) {
                continue;
            }
            foreach (self::sources($root, $path, $src) as $source) {
                if (preg_match(self::LOOKUP, $source) === 1) {
                    $offenders[] = substr($path, strlen($root) + 1);
                    break;
                }
            }
        }

        $this->assertSame([], $offenders, "Diese Ansichten suchen Elemente über IDs oder document und brauchen \$windowSingle = true:\n  " . implode("\n  ", $offenders));
    }

    /** @return list<string> */
    private static function views(string $root): array
    {
        $dirs = array_merge([$root . '/templates'], glob($root . '/plugins/*/templates', GLOB_ONLYDIR) ?: []);
        $views = [];
        foreach ($dirs as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                $path = $file->getPathname();
                if (str_ends_with($path, '.php') && !str_contains($path, '/layouts/')) {
                    $views[] = $path;
                }
            }
        }
        sort($views);

        return $views;
    }

    /** @return list<string> */
    private static function sources(string $root, string $path, string $src): array
    {
        $sources = [$src];
        if (preg_match_all('~(?:require|include)(?:_once)?\s*\(?\s*[^;]*\'/?([\w/.-]+\.php)\'~', $src, $m) > 0) {
            foreach ($m[1] as $partial) {
                foreach ([dirname($path) . '/' . $partial, $root . '/templates/' . ltrim($partial, '/'), $root . '/' . ltrim($partial, '/')] as $candidate) {
                    if (is_file($candidate)) {
                        $sources[] = (string) file_get_contents($candidate);
                        break;
                    }
                }
            }
        }
        if (preg_match_all('~assets/js/((?!ui/)[\w/.-]+\.js)~', $src, $m) > 0) {
            foreach ($m[1] as $script) {
                if (is_file($root . '/assets/js/' . $script)) {
                    $sources[] = (string) file_get_contents($root . '/assets/js/' . $script);
                }
            }
        }

        return $sources;
    }
}
