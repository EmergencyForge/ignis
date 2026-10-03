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
        // Zeichen (96 x 96) und Schriftzug (188 x 97,5) in den Maßen von
        // ignis-lockup.svg: Zeichen 76 hoch, Schriftzug 97,5, Abstand 19.
        $css = (string) file_get_contents(self::ROOT . '/public/assets/dist/ui.css');
        self::assertMatchesRegularExpression('/\.ignis-lockup\{[^}]*gap:calc\(var\(--lockup-h\)\s*\*\s*19\s*\/\s*76\)/', $css);
        self::assertMatchesRegularExpression('/\.ignis-lockup__mark\{[^}]*aspect-ratio:1[;}]/', $css);
        self::assertMatchesRegularExpression('/\.ignis-lockup__word\{[^}]*height:calc\(var\(--lockup-h\)\s*\*\s*97\.5\s*\/\s*76\)[^}]*aspect-ratio:188\s*\/\s*97\.5/', $css);
    }

    public function testLoginDoesNotLoadACurrentColorLogoAsImage(): void
    {
        // Als <img> greift currentColor nicht, das Logo wäre schwarz.
        $login = (string) file_get_contents(self::ROOT . '/templates/partials/login-brand.php');
        self::assertDoesNotMatchRegularExpression('/<img\s[^\n]*ignis-(lockup|mark|wordmark)\.svg/', $login);
    }

    public function testPngPixelsMatchTheBrandPalette(): void
    {
        if (!function_exists('imagecreatefrompng')) {
            self::markTestSkipped('GD (imagecreatefrompng) ist nicht verfügbar.');
        }
        foreach (['assets/favicon', 'public/assets/favicon'] as $dir) {
            $apple = imagecreatefrompng(self::ROOT . '/' . $dir . '/apple-touch-icon.png');
            self::assertNotFalse($apple, $dir);
            $rgb = imagecolorsforindex($apple, imagecolorat($apple, 2, 2));
            self::assertSame('161616', sprintf('%02x%02x%02x', $rgb['red'], $rgb['green'], $rgb['blue']), $dir . '/apple-touch-icon.png');

            $manifest512 = imagecreatefrompng(self::ROOT . '/' . $dir . '/web-app-manifest-512x512.png');
            self::assertNotFalse($manifest512, $dir);
            $rgb = imagecolorsforindex($manifest512, imagecolorat($manifest512, 2, 2));
            self::assertSame('161616', sprintf('%02x%02x%02x', $rgb['red'], $rgb['green'], $rgb['blue']), $dir . '/web-app-manifest-512x512.png');

            // Bildmitte fällt auf den Micro-Motiv-Ausschnitt, nicht den Rand: opak.
            $favicon96 = imagecreatefrompng(self::ROOT . '/' . $dir . '/favicon-96x96.png');
            self::assertNotFalse($favicon96, $dir);
            $centre = intdiv(imagesx($favicon96), 2) - 1;
            $rgb = imagecolorsforindex($favicon96, imagecolorat($favicon96, $centre, $centre));
            self::assertSame(0, $rgb['alpha'], $dir . '/favicon-96x96.png Mitte');
        }
    }

    public function testWebmanifestHasThemeColorAndAnyPurposeIcon(): void
    {
        foreach (['assets/favicon', 'public/assets/favicon'] as $dir) {
            $manifest = json_decode((string) file_get_contents(self::ROOT . '/' . $dir . '/site.webmanifest'), true);
            self::assertIsArray($manifest, $dir);
            self::assertSame('#161616', $manifest['theme_color'] ?? null, $dir);

            $hasAnyPurpose = false;
            foreach ($manifest['icons'] ?? [] as $icon) {
                if (str_contains((string) ($icon['purpose'] ?? ''), 'any')) {
                    $hasAnyPurpose = true;
                    break;
                }
            }
            self::assertTrue($hasAnyPurpose, $dir . ': kein Icon mit purpose "any"');
        }
    }
}
