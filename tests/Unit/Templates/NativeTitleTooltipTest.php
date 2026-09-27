<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * Keine nativen Browser-Tooltips: Hinweise laufen über data-ignis-tooltip
 * (optional data-placement) aus dem ui-Paket. Icon-Knöpfe behalten dafür ein
 * aria-label, sonst hätten sie keinen Namen mehr.
 *
 * Erlaubt bleibt title an <iframe> (Pflicht für den zugänglichen Namen),
 * <abbr> (Auflösung der Abkürzung) und <svg>. Geprüft werden Attribute in
 * PHP-Markup und in per JavaScript gebautem Markup sowie JavaScript, das title
 * am Element setzt. plugins/ (eNOTF, fireTab …) liegt außerhalb; assets/js/dist
 * sind gebaute Kopien.
 */
final class NativeTitleTooltipTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';
    private const DIRS = ['templates', 'assets/components', 'assets/js'];
    private const ALLOWED_TAGS = ['iframe', 'abbr', 'svg'];

    public function testNoNativeTitleTooltips(): void
    {
        $hits = [];
        foreach (self::DIRS as $dir) {
            foreach ($this->sourceFiles(self::ROOT . '/' . $dir) as $file) {
                foreach (self::violations((string) file_get_contents($file)) as $line) {
                    $hits[] = str_replace(self::ROOT . '/', '', $file) . ':' . $line;
                }
            }
        }

        self::assertSame([], $hits, 'title="…" durch data-ignis-tooltip="…" ersetzen (Icon-Knöpfe mit aria-label).');
    }

    public function testTheScannerFlagsWhatItShould(): void
    {
        $src = implode("\n", [
            '<iframe src="<?= $url ?>" title="PDF"></iframe>',
            '<abbr title="Rettungswagen">RTW</abbr>',
            '<span data-title="x" data-ignis-tooltip="Hilfe">?</span>',
            'document.title = "(1) Posteingang";',
            'showConfirm("Sicher?", { title: "Löschen" });',
            '<button title="Löschen"><i class="fa-solid fa-trash"></i></button>',
            "html += '<a class=\"btn\" title=\"' + label + '\">';",
            "\"<button type='button' title='Bearbeiten'>\"",
            'el.title = "Session aktiv";',
            "el.setAttribute('title', 'x');",
        ]);

        self::assertSame([6, 7, 8, 9, 10], self::violations($src));
    }

    /** @return list<int> Zeilennummern mit nativem title */
    private static function violations(string $src): array
    {
        $lines = [];
        preg_match_all('/(?<![\w$-])title=\\\\?["\']/', $src, $attributes, PREG_OFFSET_CAPTURE);
        foreach ($attributes[0] as [, $offset]) {
            // Das Tag, in dem das Attribut steht: das letzte geöffnete vor der Fundstelle (<?= zählt nicht).
            preg_match_all('/<([a-zA-Z][\w-]*)/', substr($src, 0, $offset), $tags);
            $tag = strtolower((string) end($tags[1]));
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                $lines[] = substr_count($src, "\n", 0, $offset) + 1;
            }
        }

        preg_match_all('/(?<!document)\.title\s*=(?!=)|setAttribute\(\s*[\'"]title[\'"]/', $src, $scripts, PREG_OFFSET_CAPTURE);
        foreach ($scripts[0] as [, $offset]) {
            $lines[] = substr_count($src, "\n", 0, $offset) + 1;
        }

        sort($lines);
        return array_values(array_unique($lines));
    }

    /** @return list<string> */
    private function sourceFiles(string $dir): array
    {
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if (in_array($file->getExtension(), ['php', 'js', 'mjs'], true) && !str_contains($path, '/assets/js/dist/')) {
                $files[] = $path;
            }
        }
        return $files;
    }
}
