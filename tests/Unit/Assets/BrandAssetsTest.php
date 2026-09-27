<?php

declare(strict_types=1);

namespace Tests\Unit\Assets;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Logos und Favicons aus der Punze (docs/design/logos/ignis in WebPackages):
 * Rastergrößen, das Micro-Motiv im SVG-Favicon und die Seitenverhältnisse
 * der Verlaufsmaske passend zu den viewBox-Werten.
 */
final class BrandAssetsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';
    private const MICRO_PATH = 'M4 0H16V12L12 16H0V4ZM6 14H9V8L6 11ZM8 5H11V2H8Z';

    /** @return array<string, array{string, int}> */
    public static function pngs(): array
    {
        return [
            'favicon' => ['favicon-96x96.png', 96],
            'apple' => ['apple-touch-icon.png', 180],
            'manifest 192' => ['web-app-manifest-192x192.png', 192],
            'manifest 512' => ['web-app-manifest-512x512.png', 512],
        ];
    }

    #[DataProvider('pngs')]
    public function testPngHasItsSize(string $file, int $size): void
    {
        $info = getimagesize(self::ROOT . '/assets/favicon/' . $file);
        self::assertNotFalse($info, $file);
        self::assertSame([$size, $size, IMAGETYPE_PNG], [$info[0], $info[1], $info[2]], $file);
    }

    public function testIcoCarries16And32And48(): void
    {
        $ico = (string) file_get_contents(self::ROOT . '/assets/favicon/favicon.ico');
        $head = unpack('vreserved/vtype/vcount', $ico);
        self::assertSame(['reserved' => 0, 'type' => 1, 'count' => 3], $head);
        $sizes = [];
        for ($i = 0; $i < 3; $i++) {
            $entry = unpack('Cw/Ch/Cc/Cr/vplanes/vbpp/Vlen/Voffset', substr($ico, 6 + 16 * $i, 16));
            self::assertIsArray($entry);
            $sizes[] = $entry['w'];
            // Eingebettete PNG-Daten, Größe steht im IHDR.
            $png = substr($ico, $entry['offset'], $entry['len']);
            self::assertStringStartsWith("\x89PNG", $png);
            self::assertSame($entry['w'], unpack('N', substr($png, 16, 4))[1]);
        }
        self::assertSame([16, 32, 48], $sizes);
    }

    public function testSvgFaviconIsTheMicroMarkInTheAccent(): void
    {
        $svg = (string) file_get_contents(self::ROOT . '/assets/favicon/favicon.svg');
        self::assertStringContainsString('d="' . self::MICRO_PATH . '"', $svg);
        self::assertStringContainsString('fill="#ff4d00"', $svg);
    }

    public function testLogosAreThePunze(): void
    {
        // Die Wortmarke ist SYSTEM_LOGO-Vorgabe und steht in Plugins als <img>,
        // dort greift currentColor nicht: sie trägt den Akzent fest. Als Maske
        // (Topbar, Anmeldung) zählt ohnehin nur die Deckkraft.
        $logos = [
            'ignis-mark.svg' => ['0 0 96 96', 'currentColor'],
            'ignis-wordmark.svg' => ['0 0 188 97.5', '#ff4d00'],
            'ignis-lockup.svg' => ['0 0 283 98', 'currentColor'],
        ];
        foreach ($logos as $file => [$viewBox, $fill]) {
            $svg = (string) file_get_contents(self::ROOT . '/assets/img/' . $file);
            self::assertStringContainsString('viewBox="' . $viewBox . '"', $svg, $file);
            self::assertStringContainsString('fill="' . $fill . '"', $svg, $file);
        }
    }

    public function testMaskAspectRatiosMatchTheViewBoxes(): void
    {
        $css = (string) file_get_contents(self::ROOT . '/public/assets/dist/ui.css');
        self::assertMatchesRegularExpression('/\.ignis-wordmark\{[^}]*aspect-ratio:188\s*\/\s*97\.5/', $css);
        self::assertMatchesRegularExpression('/\.ignis-wordmark--login\{[^}]*aspect-ratio:283\s*\/\s*98/', $css);
    }

    public function testLoginDoesNotLoadACurrentColorLogoAsImage(): void
    {
        // Als <img> greift currentColor nicht, das Logo wäre schwarz.
        $login = (string) file_get_contents(self::ROOT . '/login.php');
        self::assertDoesNotMatchRegularExpression('/<img\s[^\n]*ignis-(lockup|mark|wordmark)\.svg/', $login);
    }
}
