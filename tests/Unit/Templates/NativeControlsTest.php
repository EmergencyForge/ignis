<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * Auswahl, Datum, Uhrzeit und Farbe zeichnet nicht der Browser, sondern das
 * UI-Paket: Dropdown, DatePicker, DatetimePicker, TimePicker, ColorPicker.
 * Die Browser-Fenster sehen auf jedem System anders aus, lassen sich nicht
 * gestalten und öffnen sich in einem iframe außerhalb des Rahmens.
 *
 * Geprüft wird der Quelltext von templates/, assets/components/ und den
 * Plugin-Templates, wie in Lex' CustomComponentsTest. Außen vor bleiben die
 * Tablet-Apps: die eNOTF-Crewseiten (plugins/enotf/templates/ ohne enotf/admin/
 * und settings/), die eine eigene Auswahl (Ev2Select) haben, und die
 * fireTab-App (plugins/firetab/templates/firetab/ ohne admin-list.php).
 */
final class NativeControlsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    private const EXCLUDED = '~^(plugins/enotf/templates/enotf/(?!admin/)|plugins/firetab/templates/firetab/(?!admin-list\.php$)|plugins/enotf/templates/(?!enotf/|settings/)|plugins/enotf/assets/)~';

    /** Feldtyp => Attribut der Paket-Komponente; null heißt: den Typ gar nicht verwenden. */
    private const INPUTS = [
        'date'           => 'data-ignis-datepicker',
        'time'           => 'data-ignis-timepicker',
        'datetime-local' => 'data-ignis-datetimepicker',
        'color'          => null,
    ];

    public function testNoNativeControls(): void
    {
        $found = [];
        foreach ($this->files() as $relative => $path) {
            foreach (self::offenders((string) file_get_contents($path)) as $tag) {
                $found[] = $relative . ' → ' . $tag;
            }
        }

        $this->assertSame(
            [],
            $found,
            "Native Felder ohne Paket-Komponente. <select> bekommt data-custom-dropdown=\"true\", "
                . "date/time/datetime-local data-ignis-datepicker/-timepicker/-datetimepicker, "
                . "eine Farbe ist <input type=\"text\" data-ignis-colorpicker>:\n  " . implode("\n  ", $found),
        );
    }

    public function testTheScannerFlagsWhatItShould(): void
    {
        $src = implode("\n", [
            '<select name="a" data-custom-dropdown="true">',
            '<select name="b" id="<?= $id ?>" data-custom-dropdown="true">',
            '<select name="c">',
            '<select name="d" data-custom-dropdown="false">',
            '<input type="date" data-ignis-datepicker>',
            '<input type="date" value="<?= $d ?>">',
            '<input data-type="x" type="date">',
            "<input type='time' data-ignis-timepicker>",
            '<input type="time">',
            '<input type="datetime-local">',
            '<input type="text" data-ignis-colorpicker>',
            '<input type="color" data-ignis-colorpicker>',
            '<input type="<?= $feld->feldtyp ?>">',
            '<!-- <select name="kommentar"> -->',
        ]);

        $this->assertSame([
            '<select name="c">',
            '<select name="d" data-custom-dropdown="false">',
            '<input type="date" value="X">',
            '<input data-type="x" type="date">',
            '<input type="time">',
            '<input type="datetime-local">',
            '<input type="color" data-ignis-colorpicker>',
        ], self::offenders($src));
    }

    /** @return list<string> die beanstandeten Tags */
    private static function offenders(string $source): array
    {
        /* Kommentare raus, PHP-Blöcke maskieren: ein `>` in einem `<?= … ?>` beendet sonst das Tag zu früh. */
        $source = (string) preg_replace('~<!--.*?-->~s', '', $source);
        $source = (string) preg_replace('~<\?(?:php|=).*?\?>~s', 'X', $source);

        $found = [];
        preg_match_all('~<(select|input)\b[^>]*>~i', $source, $tags);
        foreach ($tags[0] as $i => $tag) {
            $tag = trim((string) preg_replace('~\s+~', ' ', $tag));
            if (strtolower($tags[1][$i]) === 'select') {
                if (preg_match('~data-custom-dropdown=["\']true["\']~', $tag) !== 1) {
                    $found[] = $tag;
                }
                continue;
            }
            if (preg_match('~\stype=["\']?([\w-]+)~i', $tag, $type) !== 1 || !array_key_exists(strtolower($type[1]), self::INPUTS)) {
                continue;
            }
            $attribute = self::INPUTS[strtolower($type[1])];
            if ($attribute === null || preg_match('~\s' . preg_quote($attribute, '~') . '(?=[\s=>])~', $tag) !== 1) {
                $found[] = $tag;
            }
        }

        return $found;
    }

    /** @return array<string, string> relativer Pfad => absoluter Pfad */
    private function files(): array
    {
        $roots = ['templates', 'assets/components'];
        foreach (glob(self::ROOT . '/plugins/*/templates', GLOB_ONLYDIR) ?: [] as $dir) {
            $roots[] = substr(str_replace('\\', '/', $dir), strlen(self::ROOT) + 1);
        }

        $files = [];
        foreach ($roots as $root) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT . '/' . $root, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }
                $relative = $root . '/' . str_replace('\\', '/', substr($file->getPathname(), strlen(self::ROOT . '/' . $root) + 1));
                if (preg_match(self::EXCLUDED, $relative) !== 1) {
                    $files[$relative] = $file->getPathname();
                }
            }
        }
        ksort($files);

        return $files;
    }
}
