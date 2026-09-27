<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * Alle Shell-Regeln in assets/css/ui.scss stehen unter
 * body[data-ui-skin="core"] (seit 87f3175d). Jede Datei, die topbar.php
 * einbindet, muss das Attribut also selbst setzen — als PHP-Attribut wie
 * templates/layouts/admin.php, oder per JS wie der Legacy-Shim
 * assets/components/navbar.php, dessen Body erst zur Laufzeit entsteht.
 * Ohne das bleibt die Topbar unstyled (kaputte eNOTF-Adminseiten, siehe
 * navbar.php).
 *
 * Der Test findet die Einbindungen selbst, statt eine feste Liste zu
 * pflegen: eine neue Datei, die topbar.php einbindet, aber das Attribut
 * vergisst, fällt hier durch.
 */
final class TopbarSkinGuardTest extends TestCase
{
    public function testEveryTopbarIncluderProvidesTheCoreSkin(): void
    {
        $repo = dirname(__DIR__, 3);
        $includers = [];

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($repo, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.php')) {
                continue;
            }
            $path = $file->getPathname();
            if (str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)
                || str_contains($path, DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR)
                || str_contains($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR)
            ) {
                continue;
            }

            $src = (string) file_get_contents($path);
            // Nur die Shell-Topbar (assets/components/topbar.php), nicht die
            // eigene eNOTF-Protokoll-Topbar (assets/components/enotf/topbar.php).
            if (preg_match('~\b(?:require|include)(?:_once)?\s*\(?\s*(?:__DIR__|dirname\(__DIR__[^)]*\))\s*\.\s*[\'"](?:/topbar\.php|[^\'"]*/components/topbar\.php)[\'"]~', $src) !== 1) {
                continue;
            }

            $includers[] = substr($path, strlen($repo) + 1);

            $hasAttribute = str_contains($src, 'data-ui-skin="core"');
            $hasDatasetAssignment = preg_match('~dataset\.uiSkin\s*=\s*[\'"]core[\'"]~', $src) === 1;

            $this->assertTrue(
                $hasAttribute || $hasDatasetAssignment,
                str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($repo) + 1))
                    . ' bindet topbar.php ein, setzt aber weder data-ui-skin="core" noch dataset.uiSkin = \'core\' — die Shell-Styles unter body[data-ui-skin="core"] greifen dort nicht.',
            );
        }

        $this->assertNotEmpty($includers, 'Keine Einbindung von topbar.php gefunden — der Regex passt nicht mehr.');
    }
}
