<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * Jedes <input type="file"> gehört seit der 0.4.2-Dropzone in einen
 * Wrapper mit data-ignis-file, sonst bindet file.js nichts und das Feld
 * bleibt eine unformatierte native Datei-Auswahl ohne Drag-and-Drop und
 * ohne Client-Validierung.
 *
 * eNOTF (plugins/enotf*) ist wie bei ComponentClassTest nicht Teil des
 * Redesigns und bleibt außen vor.
 */
final class FileInputDropzoneTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';
    private const DIRS = ['templates', 'assets/components', 'plugins'];

    /** Elemente ohne schließendes Tag zählen nie als Wrapper. */
    private const VOID_TAGS = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
        'link', 'meta', 'param', 'source', 'track', 'wbr',
    ];

    public function testEveryFileInputSitsInAnIgnisFileWrapper(): void
    {
        $hits = [];
        foreach (self::DIRS as $dir) {
            foreach ($this->sourceFiles(self::ROOT . '/' . $dir) as $file) {
                foreach (self::violations((string) file_get_contents($file)) as $line) {
                    $hits[] = str_replace(self::ROOT . '/', '', $file) . ':' . $line;
                }
            }
        }

        self::assertSame(
            [],
            $hits,
            "<input type=\"file\"> braucht einen Wrapper mit data-ignis-file (siehe packages/ui-Dropzone-Vertrag):\n  " . implode("\n  ", $hits),
        );
    }

    public function testTheScannerFlagsWhatItShould(): void
    {
        $src = implode("\n", [
            '<div class="ignis-file" data-ignis-file>',                 // 1
            '  <input type="file" class="ignis-file__input">',          // 2
            '</div>',                                                   // 3
            '<div class="wrap">',                                       // 4
            '  <input type="file">',                                    // 5 - violation
            '</div>',                                                   // 6
            '<input type="text">',                                      // 7
        ]);

        self::assertSame([5], self::violations($src));
    }

    /** @return list<int> Zeilennummern mit ungewrappten Datei-Inputs */
    private static function violations(string $src): array
    {
        $lines = [];
        $stack = []; // je Eintrag: hasIgnisFile (bool)

        preg_match_all('/<(\/)?([a-zA-Z][\w-]*)([^>]*)>/s', $src, $tags, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        foreach ($tags as $tag) {
            [$whole] = $tag[0];
            $isClosing  = $tag[1][0] === '/';
            $tagName    = strtolower($tag[2][0]);
            $attrs      = $tag[3][0];
            $offset     = $tag[0][1];
            $selfClosed = str_ends_with(rtrim($whole, '>'), '/');

            if ($tagName === 'input' && preg_match('/type\s*=\s*["\']file["\']/i', $attrs) === 1) {
                $wrapped = false;
                foreach ($stack as $entry) {
                    if ($entry) {
                        $wrapped = true;
                        break;
                    }
                }
                if (!$wrapped) {
                    $lines[] = substr_count($src, "\n", 0, $offset) + 1;
                }
                continue;
            }

            if (in_array($tagName, self::VOID_TAGS, true) || $selfClosed) {
                continue;
            }

            if ($isClosing) {
                array_pop($stack);
                continue;
            }

            $stack[] = str_contains($attrs, 'data-ignis-file');
        }

        sort($lines);
        return array_values(array_unique($lines));
    }

    /** @return list<string> */
    private function sourceFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if ($file->getExtension() !== 'php') {
                continue;
            }
            if (preg_match('~/(plugins/enotf|enotf)[^/]*/~', $path) === 1) {
                continue;
            }
            $files[] = $path;
        }
        return $files;
    }
}
