<?php

declare(strict_types=1);

namespace Tests\Unit\Assets;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Die Tokens in assets/css/_tokens.scss sind die einzige Quelle für Farben.
 * Drei Regeln halten das:
 *
 *   1. Jeder Token des dunklen Satzes mit einem Farbwert hat im hellen Satz
 *      einen Gegenwert, sonst bleibt an der Stelle im hellen Modus eine
 *      dunkle Farbe stehen.
 *   2. Außerhalb von _tokens.scss steht in keinem Stylesheet unter assets/css
 *      eine Hex-Farbe.
 *   3. Weiß-Transparenzen als Fläche (rgba(255,255,255,<0.5)) laufen über die
 *      --fill-Tokens, damit der helle Satz sie umdrehen kann.
 *
 * Ausnahmen (EXCEPTIONS) sind die eNOTF-Stylesheets: eNOTF v1 und v2 sind
 * nicht Teil des Redesigns und behalten ihre Farben.
 */
final class ThemeTokensTest extends TestCase
{
    /**
     * Dateien unter assets/css, die Hex-Farben tragen dürfen, mit Grund.
     */
    private const EXCEPTIONS = [
        '_tokens.scss'      => 'die Tokens selbst',
        '_enotf-skin.scss'  => 'eNOTF-Skin der ignis-Komponenten, aus ui.scss herausgelöst',
        'divi.scss'         => 'eNOTF-Stylesheet (nur von eNOTF v1/v2 geladen)',
        'print.scss'        => 'eNOTF-Druckansicht',
        'enotf-custom-dropdown.min.css' => 'eNOTF, ohne SCSS-Quelle',
        'enotf-modals.css'      => 'eNOTF, ohne SCSS-Quelle',
        'enotf-modals.min.css'  => 'eNOTF, ohne SCSS-Quelle',
        'enotf-toast.css'       => 'eNOTF, ohne SCSS-Quelle',
        'enotf-toast.min.css'   => 'eNOTF, ohne SCSS-Quelle',
    ];

    /** Feste Werte, die in beiden Sätzen gleich bleiben (Text auf Akzent, Druck, Farbregler). */
    private const FIXED = ['--white', '--black'];

    private function cssDir(): string
    {
        return dirname(__DIR__, 3) . '/assets/css';
    }

    private function tokens(): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents($this->cssDir() . '/_tokens.scss'));
    }

    /**
     * @return array{root: array<string,string>, light: array<string,string>}
     */
    private function tokenSets(): array
    {
        $scss = $this->tokens();
        $this->assertSame(1, preg_match('/^:root \{\n(.*?)\n\}\n/ms', $scss, $root), ':root-Block fehlt');
        $this->assertSame(1, preg_match('/^\[data-theme="light"\] \{\n(.*?)\n\}\n/ms', $scss, $light), 'Heller Satz fehlt');

        $values = static function (string $block): array {
            preg_match_all('/^\s*(--[a-z0-9-]+):\s*([^;]+);/m', $block, $m, PREG_SET_ORDER);
            $out = [];
            foreach ($m as $decl) {
                $out[$decl[1]] = trim($decl[2]);
            }
            return $out;
        };

        return ['root' => $values($root[1]), 'light' => $values($light[1])];
    }

    /**
     * @return list<string> Stylesheets, die keine Hex-Farbe tragen dürfen.
     */
    private function guardedFiles(): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->cssDir(), \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            if (!in_array($file->getExtension(), ['scss', 'css'], true)) {
                continue;
            }
            if (isset(self::EXCEPTIONS[$file->getFilename()])) {
                continue;
            }
            $files[] = $file->getPathname();
        }
        sort($files);
        $this->assertNotEmpty($files);

        return $files;
    }

    private function withoutComments(string $css): string
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

        return (string) preg_replace('#(^|\s)//[^\n]*#', '$1', $css);
    }

    #[Test]
    public function heller_satz_ueberschreibt_jeden_farbwert_des_dunklen(): void
    {
        $sets = $this->tokenSets();
        $missing = [];
        foreach ($sets['root'] as $token => $value) {
            $hasColour = preg_match('/#[0-9a-f]{3,8}\b|rgba?\(\s*\d/i', $value) === 1;
            if ($hasColour && !isset($sets['light'][$token]) && !in_array($token, self::FIXED, true)) {
                $missing[] = $token;
            }
        }

        $this->assertSame([], $missing, "Diese Farb-Tokens fehlen im hellen Satz:\n  " . implode("\n  ", $missing));
    }

    #[Test]
    public function heller_satz_kennt_keine_tokens_die_der_dunkle_nicht_hat(): void
    {
        $sets = $this->tokenSets();
        $unknown = array_diff(array_keys($sets['light']), array_keys($sets['root']));

        $this->assertSame([], array_values($unknown));
    }

    #[Test]
    public function keine_hexfarbe_ausserhalb_der_tokens(): void
    {
        $hits = [];
        foreach ($this->guardedFiles() as $path) {
            $css = $this->withoutComments((string) file_get_contents($path));
            if (preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $css, $m) > 0) {
                $hits[] = basename($path) . ': ' . implode(', ', array_unique($m[0]));
            }
        }

        $this->assertSame([], $hits, "Hex-Farben gehören nach _tokens.scss:\n  " . implode("\n  ", $hits));
    }

    #[Test]
    public function weiss_transparenzen_als_flaeche_nur_in_den_tokens(): void
    {
        $hits = [];
        foreach ($this->guardedFiles() as $path) {
            $css = $this->withoutComments((string) file_get_contents($path));
            preg_match_all('/rgba\(\s*255,\s*255,\s*255,\s*(0?\.\d+)\s*\)/', $css, $m);
            $low = array_filter($m[1], static fn (string $a): bool => (float) $a < 0.5);
            if ($low !== []) {
                $hits[] = basename($path) . ': ' . count($low);
            }
        }

        $this->assertSame([], $hits, "Weiß-Transparenzen gehören auf die --fill-Tokens:\n  " . implode("\n  ", $hits));
    }

    #[Test]
    public function ausnahmeliste_nennt_nur_vorhandene_dateien(): void
    {
        foreach (array_keys(self::EXCEPTIONS) as $name) {
            $this->assertFileExists($this->cssDir() . '/' . $name);
        }
    }
}
