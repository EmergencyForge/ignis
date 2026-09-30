<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Die Templates binden Klassen per `use` ein. Zeigt ein Import ins Leere,
 * fällt das erst beim Rendern auf, und dann als fataler Fehler: die
 * Lexikon-Ansichten holten KBHelper noch aus App\KnowledgeBase, als er
 * längst im Plugin lag.
 */
final class TemplateImportsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    #[Test]
    public function jeder_import_in_templates_zeigt_auf_eine_klasse(): void
    {
        $files = array_merge(
            $this->phpFiles(self::ROOT . '/templates'),
            $this->phpFiles(self::ROOT . '/assets/components'),
            ...array_map(fn (string $dir): array => $this->phpFiles($dir), glob(self::ROOT . '/plugins/*/templates') ?: []),
        );
        $this->assertNotSame([], $files);

        $missing = [];
        foreach ($files as $file) {
            preg_match_all('~^use\s+([A-Za-z_\\\\]+)(?:\s+as\s+\w+)?\s*;~m', (string) file_get_contents($file), $matches);
            foreach ($matches[1] as $class) {
                if (!class_exists($class) && !interface_exists($class) && !trait_exists($class) && !enum_exists($class)) {
                    $missing[] = substr($file, strlen(self::ROOT) + 1) . ': ' . $class;
                }
            }
        }

        $this->assertSame([], $missing);
    }

    /** @return list<string> */
    private function phpFiles(string $dir): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
