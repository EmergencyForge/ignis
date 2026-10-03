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
        // border-radius als Kurzform sowie die Langformen je Ecke (border-top-left-radius
        // usw., dazu die logischen border-start-start-radius usw.), sonst schlüpft ein
        // fester Wert am Wächter vorbei, indem er nur eine Ecke direkt benennt.
        $property = '(?:border-radius|border-(?:top|bottom)-(?:left|right)-radius|border-(?:start|end)-(?:start|end)-radius)';
        $bad = array_values(array_filter($this->values($property), static fn (string $entry): bool =>
            ! preg_match('/: (var\(--[\w-]+(?:, *[^)]+)?\)|0|50%|999px|inherit)$/', $entry)));
        self::assertSame([], $bad);
    }

    public function testShadowsCarryNoColourLiteral(): void
    {
        $bad = array_values(array_filter($this->values('box-shadow'), static fn (string $entry): bool =>
            (bool) preg_match('/#[0-9a-f]{3,8}\b|rgba?\(\s*\d|hsla?\(/i', $entry)));
        self::assertSame([], $bad);
    }

    /**
     * Hover färbt, bewegt aber nichts. transform: none darf stehen (hebt nur
     * auf), ebenso ein ::before/::after, das es erst beim Hover gibt: dessen
     * transform setzt es an seinen Platz, verschiebt aber nichts Sichtbares.
     */
    public function testHoverMovesNothing(): void
    {
        $bad = [];
        foreach ($this->sources() as $name => $code) {
            preg_match_all('/:hover[^{};]*\{/', $code, $openers, PREG_OFFSET_CAPTURE);
            foreach ($openers[0] as [$selector, $offset]) {
                if (preg_match('/::?(?:before|after)\s*\{$/', $selector)) {
                    continue;
                }
                $start = $offset + strlen($selector);
                $end = $start;
                for ($depth = 1; $depth > 0 && $end < strlen($code); $end++) {
                    $depth += ['{' => 1, '}' => -1][$code[$end]] ?? 0;
                }
                $body = substr($code, $start, $end - $start);
                if (preg_match_all('/(?<![-\w])transform\s*:\s*([^;{}]+)/', $body, $m)) {
                    foreach ($m[1] as $value) {
                        if (trim($value) !== 'none') {
                            $bad[] = $name . ': ' . trim($selector) . ' ' . trim($value);
                        }
                    }
                }
            }
        }
        self::assertSame([], $bad);
    }

    /**
     * Die Farben des Looks sind oklch-Werte. Am Hex-Wächter in
     * ThemeTokensTest kämen sie vorbei, deshalb stehen sie nur im
     * Token-Block des Skins.
     */
    public function testOklchColoursStayInTheSkinTokens(): void
    {
        $bad = [];
        foreach ($this->sources() as $name => $code) {
            if ($name !== '_look-aliases.scss' && preg_match_all('/\boklch\(/', $code, $m)) {
                $bad[] = $name . ': ' . count($m[0]);
            }
        }
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

    /**
     * Im hellen Skin hält das Orange auf Weiß nur 3,6 : 1. Schrift im Akzent
     * nimmt deshalb --accent-text, eine Fläche im Akzent trägt --on-accent
     * statt Weiß.
     */
    public function testAccentTextAndFillsUseTheirRoles(): void
    {
        $text = array_values(array_filter($this->values('color'), static fn (string $entry): bool =>
            preg_match('/: var\(--accent\)/', $entry) === 1));
        self::assertSame([], $text, 'Schrift im Akzent: var(--accent-text)');

        $white = [];
        foreach ($this->sources() as $name => $code) {
            preg_match_all('/([^{};]*)\{([^{}]*)\}/', $code, $blocks, PREG_SET_ORDER);
            foreach ($blocks as [, $selector, $body]) {
                if (preg_match('/(?<![-\w])background(?:-color)?\s*:\s*var\(--accent\)/', $body) === 1
                    && preg_match('/(?<![-\w])color\s*:\s*(?:var\(--white\)|white|#fff\b)/', $body) === 1) {
                    $white[] = $name . ': ' . trim($selector);
                }
            }
        }
        self::assertSame([], $white, 'Weiß auf dem Akzent: Fläche var(--accent-fill), Schrift var(--on-accent)');
    }
}
