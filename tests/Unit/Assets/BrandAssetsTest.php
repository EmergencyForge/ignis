<?php

declare(strict_types=1);

namespace Tests\Unit\Assets;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Logos und App-Icons aus der Punze (docs/design/logos/ignis in WebPackages):
 * Rastergrößen, die kleine Fassung im SVG-Favicon und die Seitenverhältnisse
 * der Verlaufsmaske passend zu den viewBox-Werten.
 */
final class BrandAssetsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';
    private const SMALL_PLATE = 'M5 2H14V11L11 14H2V5ZM6 12H8V8L6 10ZM8 4V6H10V4Z';

    /** @return array<string, array{string, int}> */
    public static function pngs(): array
    {
        return [
            'favicon' => ['favicon-96x96.png', 96],
            'apple' => ['apple-touch-icon.png', 180],
            'manifest 192' => ['web-app-manifest-192x192.png', 192],
            'manifest 512' => ['web-app-manifest-512x512.png', 512],
            'icon 192' => ['web-app-icon-192x192.png', 192],
            'icon 512' => ['web-app-icon-512x512.png', 512],
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

    public function testSvgFaviconIsTheSmallAppIcon(): void
    {
        $svg = (string) file_get_contents(self::ROOT . '/assets/favicon/favicon.svg');
        self::assertStringContainsString('fill="#fff" fill-rule="evenodd" d="' . self::SMALL_PLATE . '"', $svg);
        self::assertStringContainsString('fill="url(#bg)"', $svg);
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
            // iOS und Android schneiden diese Icons selbst zu, eine durchsichtige Ecke würde dort schwarz.
            foreach (['apple-touch-icon.png', 'web-app-manifest-192x192.png', 'web-app-manifest-512x512.png'] as $file) {
                $png = imagecreatefrompng(self::ROOT . '/' . $dir . '/' . $file);
                self::assertNotFalse($png, $file);
                $rgb = imagecolorsforindex($png, imagecolorat($png, 2, 2));
                self::assertSame(0, $rgb['alpha'], $dir . '/' . $file);
                self::assertGreaterThan($rgb['blue'] + 100, $rgb['red'], $dir . '/' . $file . ' ist nicht orange');
            }

            // Alle anderen bringen das Squircle selbst mit.
            foreach (['favicon-96x96.png', 'web-app-icon-192x192.png', 'web-app-icon-512x512.png'] as $file) {
                $png = imagecreatefrompng(self::ROOT . '/' . $dir . '/' . $file);
                self::assertNotFalse($png, $file);
                $corner = imagecolorsforindex($png, imagecolorat($png, 0, 0));
                self::assertSame(127, $corner['alpha'], $dir . '/' . $file . ' Ecke');
                $centre = intdiv(imagesx($png), 2) - 1;
                $rgb = imagecolorsforindex($png, imagecolorat($png, $centre, $centre));
                self::assertSame(0, $rgb['alpha'], $dir . '/' . $file . ' Mitte');
            }
        }
    }

    public function testWebmanifestHasThemeColorAndAnyPurposeIcon(): void
    {
        foreach (['assets/favicon', 'public/assets/favicon'] as $dir) {
            $manifest = json_decode((string) file_get_contents(self::ROOT . '/' . $dir . '/site.webmanifest'), true);
            self::assertIsArray($manifest, $dir);
            self::assertSame('#161616', $manifest['theme_color'] ?? null, $dir);

            $srcs = ['any' => [], 'maskable' => []];
            foreach ($manifest['icons'] ?? [] as $icon) {
                $srcs[(string) ($icon['purpose'] ?? '')][] = (string) ($icon['src'] ?? '');
                self::assertFileExists(self::ROOT . '/' . $dir . '/' . $icon['src']);
            }
            self::assertNotSame([], $srcs['any'], $dir . ': kein Icon mit purpose "any"');
            // Das maskable Bild ist vollflächig, als "any" stünde es ohne Squircle da.
            self::assertSame([], array_intersect($srcs['any'], $srcs['maskable']), $dir);
        }
    }
}
