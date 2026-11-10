<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Wächter für den Live-Vortrag der Ausbildung. ThemeTokensTest und
 * NativeTitleTooltipTest lassen plugins/ aus, darum prüft dieser Test die
 * Dateien des Vortrags selbst. Das Skript muss klassisch bleiben: der Desk
 * führt ein Modul nur einmal je Dokument aus, ein zweites Vortragsfenster
 * bliebe dann tot.
 */
final class TrainingPresentationGuardTest extends TestCase
{
    private const PLUGIN = __DIR__ . '/../../../plugins/training';

    #[Test]
    public function presentation_css_has_no_color_literals(): void
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', self::read('assets/css/presentation.css'));

        self::assertSame(0, preg_match_all('/#[0-9a-fA-F]{3,8}\b|\brgba?\(|\bhsla?\(/', $css, $hits), 'Farben nur aus Tokens: ' . implode(', ', $hits[0]));
    }

    #[Test]
    public function presentation_templates_use_ignis_tooltips(): void
    {
        $files = [...self::templates(), self::PLUGIN . '/assets/js/presentation.js'];
        $hits  = [];
        foreach ($files as $file) {
            $src = (string) file_get_contents($file);
            if (preg_match('/\stitle\s*=|\.title\s*=|setAttribute\(\s*[\'"]title[\'"]/', $src) === 1) {
                $hits[] = basename($file);
            }
        }

        self::assertSame([], $hits, 'data-ignis-tooltip statt title.');
    }

    #[Test]
    public function presentation_script_is_not_a_module(): void
    {
        $tags = 0;
        foreach (self::templates() as $file) {
            preg_match_all('~<script\b[^>]*presentation\.js[^>]*>~', (string) file_get_contents($file), $m);
            foreach ($m[0] as $tag) {
                $tags++;
                self::assertStringNotContainsString('type="module"', $tag, basename($file));
            }
        }
        self::assertGreaterThan(0, $tags, 'Die Vortragsansichten laden presentation.js.');

        $js = self::read('assets/js/presentation.js');
        self::assertSame(0, preg_match('/^\s*(import|export)\s/m', $js), 'Kein import oder export auf oberster Ebene.');
        self::assertStringContainsString('document.currentScript', $js);
        self::assertStringContainsString('[data-tr-live]:not([data-tr-live-ready])', $js);
    }

    private static function read(string $path): string
    {
        $file = self::PLUGIN . '/' . $path;
        self::assertFileExists($file);

        return (string) file_get_contents($file);
    }

    /** @return list<string> */
    private static function templates(): array
    {
        return glob(self::PLUGIN . '/templates/presentation/*.php') ?: [];
    }
}
