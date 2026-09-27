<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * SYSTEM_LOGO stand an mehreren Stellen roh im src: ohne BASE_PATH (404 unter
 * /intra/), unescaped und mit den currentColor-SVGs als schwarzes Bild. Alle
 * <img> gehen über systemLogoUrl().
 */
final class SystemLogoUsageTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    /** @return array<string, array{string}> */
    public static function templates(): array
    {
        return [
            'firetab-sidebar' => ['assets/components/firetab-sidebar.php'],
            'lexicon index' => ['plugins/knowledge-base/templates/lexicon/index.php'],
            'lexicon view' => ['plugins/knowledge-base/templates/lexicon/view.php'],
            'settings config' => ['templates/settings/system/config.php'],
            'topbar' => ['assets/components/topbar.php'],
            'login' => ['login.php'],
        ];
    }

    #[Test]
    #[DataProvider('templates')]
    public function logo_laeuft_ueber_den_helfer(string $file): void
    {
        $source = (string) file_get_contents(self::ROOT . '/' . $file);

        $this->assertStringContainsString('systemLogoUrl(', $source);
        $this->assertDoesNotMatchRegularExpression('~(<\?=|echo)\s*SYSTEM_LOGO~', $source);
    }

    #[Test]
    public function footer_ist_im_hellen_theme_lesbar(): void
    {
        $source = (string) file_get_contents(self::ROOT . '/assets/components/footer.php');

        $this->assertStringNotContainsString('text-white', $source);
        $this->assertStringNotContainsString('defaultLogo.webp', $source);
        $this->assertStringNotContainsString('rgba(255, 255, 255, 0.55)', $source);
        $this->assertStringContainsString('class="ignis-wordmark"', $source);
    }
}
