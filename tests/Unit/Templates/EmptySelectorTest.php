<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * querySelectorAll('.') wirft einen SyntaxError und bricht das ganze Skript
 * ab. So blieb das Sperr-Skript freigegebener eNOTF-Protokolle in 111
 * Templates wirkungslos, nachdem ein Suchen und Ersetzen die Klasse
 * form-check-input aus dem Selektor gelöscht hatte.
 */
final class EmptySelectorTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    public function testKeinSelektorBestehtNurAusPunktOderRaute(): void
    {
        $found = [];
        foreach (['templates', 'assets/components', 'assets/js', 'plugins'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (!preg_match('~\.(php|js|mjs)$~', $file->getFilename()) || str_contains($file->getPathname(), 'node_modules')) {
                    continue;
                }
                $source = (string) file_get_contents($file->getPathname());
                if (preg_match_all('~querySelector(?:All)?\(\s*([\'"])\s*[.#]\s*\1\s*\)~', $source, $m) > 0) {
                    $found[] = substr(str_replace('\\', '/', $file->getPathname()), strlen(self::ROOT) + 1);
                }
            }
        }

        $this->assertSame([], $found, 'Leerer Klassen- oder ID-Selektor gefunden.');
    }
}
