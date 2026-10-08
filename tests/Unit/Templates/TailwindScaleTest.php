<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * Farben und Ecken der Seiten im Skin kommen aus den Rollen des Looks.
 * tailwind.config.js führt Text 2 und 3, die Tonschriften und einige
 * Flächen unter ihrem Token-Namen (text-tertiary-text, bg-well), die
 * Radien auf --radius-1 bis --radius-3. Eine Klasse mit beliebigem Wert
 * (text-[#d46b6b], bg-[rgba(0,0,0,.3)]) oder ein Grau aus der Palette von
 * Tailwind läuft an dieser Skala vorbei und hat im hellen Modus oft zu
 * wenig Kontrast. StyleLiteralsTest sieht nur SCSS, deshalb steht die
 * Prüfung für Templates und die Skripte, die Markup bauen, hier.
 *
 * Seiten ohne Skin bleiben außen vor: die eNOTF-Crewseiten,
 * die fireTab-App auf dem Tablet und die Fehlerseiten.
 */
final class TailwindScaleTest extends TestCase
{
    private const ROOTS = ['templates', 'assets/components', 'assets/js/modules', 'assets/js/pages', 'plugins'];

    private const WITHOUT_SKIN = '~^(plugins/enotf/templates/(?!settings/)|plugins/enotf/assets/|plugins/[^/]+/(src|tests|migrations)/|assets/components/enotf/|assets/components/error-page\.php|assets/components/firetab-sidebar\.php|plugins/firetab/templates/firetab/(asu|create|list|logbook|login-vehicle|status-reports|view)\.php|plugins/firetab/templates/firetab/tabs/|plugins/firetab/assets/)~';

    private const OFF_SCALE = '~\b(?:text|bg|border)-\[(?:#|var\(|rgba?\(|hsla?\()|\b(?:text|bg|border)-(?:gray|slate|zinc|neutral|stone)-\d+|\brounded-(?:\[|2xl|3xl)~';

    public function testSkinnedTemplatesStayOnTheScale(): void
    {
        $base  = dirname(__DIR__, 3);
        $files = glob($base . '/*.php') ?: [];
        foreach (self::ROOTS as $root) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base . '/' . $root, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file instanceof \SplFileInfo && in_array($file->getExtension(), ['php', 'js'], true)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        $found = [];
        foreach ($files as $file) {
            $relative = ltrim(str_replace('\\', '/', substr($file, strlen($base))), '/');
            if (preg_match(self::WITHOUT_SKIN, $relative) === 1) {
                continue;
            }
            foreach (file($file) ?: [] as $index => $line) {
                if (preg_match_all(self::OFF_SCALE, $line, $matches) > 0) {
                    $found[] = $relative . ':' . ($index + 1) . ' ' . implode(', ', $matches[0]);
                }
            }
        }

        $this->assertSame([], $found, "Klassen neben der Skala:\n  " . implode("\n  ", $found));
    }
}
