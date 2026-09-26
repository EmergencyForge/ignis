<?php

declare(strict_types=1);

namespace Tests\Unit\Assets;

use PHPUnit\Framework\TestCase;

/**
 * Ecken, Schatten und Bedeutungsfarben kommen aus Tokens. Ein fester Wert
 * außerhalb von _tokens.scss würde den gemeinsamen Look an genau dieser
 * Stelle aushebeln, ohne dass es jemand bemerkt.
 */
final class StyleLiteralsTest extends TestCase
{
    private const DIR = __DIR__ . '/../../../assets/css';

    /** Dateien mit eigenem Look oder den Tokens selbst. */
    private const EXCEPTIONS = ['_tokens.scss', '_tokens-legacy.scss', '_enotf-skin.scss', 'divi.scss', 'print.scss'];

    /** @return array<string, string> Pfad => Inhalt ohne Kommentare */
    private function sources(): array
    {
        $files = array_merge(glob(self::DIR . '/*.scss') ?: [], glob(self::DIR . '/pages/*.css') ?: []);
        $sources = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, self::EXCEPTIONS, true) || str_starts_with($name, 'enotf')) {
                continue;
            }
            $code = (string) file_get_contents($file);
            $code = (string) preg_replace('#/\*.*?\*/#s', '', $code);
            $code = (string) preg_replace('#(^|[^:])//[^\n]*#', '$1', $code);
            $sources[$name] = $code;
        }
        return $sources;
    }

    /** @return list<string> "datei: wert" */
    private function values(string $property): array
    {
        $found = [];
        foreach ($this->sources() as $name => $code) {
            preg_match_all('/(?<![-\w])' . $property . '\s*:\s*([^;{}]+)/', $code, $matches);
            foreach ($matches[1] as $value) {
                $found[] = $name . ': ' . trim($value);
            }
        }
        return $found;
    }

    public function testRadiiComeFromTokens(): void
    {
        $bad = array_values(array_filter($this->values('border-radius'), static fn (string $entry): bool =>
            ! preg_match('/: (var\(--[\w-]+(?:, *[^)]+)?\)|0|50%|999px|inherit)$/', $entry)));
        self::assertSame([], $bad);
    }

    public function testShadowsCarryNoColourLiteral(): void
    {
        $bad = array_values(array_filter($this->values('box-shadow'), static fn (string $entry): bool =>
            (bool) preg_match('/#[0-9a-f]{3,8}\b|rgba?\(\s*\d|hsla?\(/i', $entry)));
        self::assertSame([], $bad);
    }

    public function testSemanticColoursAreNotRgbTriples(): void
    {
        $bad = [];
        foreach ($this->sources() as $name => $code) {
            if (preg_match_all('/var\(--(ok|warn|danger|info)-rgb\)/', $code, $m)) {
                $bad[] = $name . ': ' . count($m[0]);
            }
        }
        self::assertSame([], $bad);
    }
}
