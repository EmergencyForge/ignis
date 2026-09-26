<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * Leerzustände gibt es nur noch über templates/partials/empty.php. Die alten
 * Varianten sahen jede anders aus und hießen jedes Mal anders.
 */
final class EmptyStateUsageTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';
    private const OLD = ['twplus-empty', 'empty-state', 'logs-empty', 'ignis-table-empty'];

    public function testNoTemplateUsesAnOldEmptyStateClass(): void
    {
        $hits = [];
        foreach (['templates', 'assets/components', 'index.php', 'login.php'] as $path) {
            $files = is_file(self::ROOT . '/' . $path) ? [self::ROOT . '/' . $path] : $this->phpFiles(self::ROOT . '/' . $path);
            foreach ($files as $file) {
                if (str_contains($file, 'enotf')) {
                    continue;
                }
                $code = (string) file_get_contents($file);
                foreach (self::OLD as $class) {
                    if (preg_match('/[\s"\']' . preg_quote($class, '/') . '(?![\w-])/', $code)) {
                        $hits[] = str_replace(self::ROOT . '/', '', $file) . ': ' . $class;
                    }
                }
            }
        }
        self::assertSame([], $hits);
    }

    /** @return list<string> */
    private function phpFiles(string $dir): array
    {
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = str_replace('\\', '/', $file->getPathname());
            }
        }
        return $files;
    }
}
