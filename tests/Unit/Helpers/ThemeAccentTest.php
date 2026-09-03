<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\Theme;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * SYSTEM_COLOR wirkt nur, wenn der Betreiber die Farbe geändert hat; sonst
 * entscheiden die Tokens, damit der helle Satz seinen eigenen Akzent behält.
 */
final class ThemeAccentTest extends TestCase
{
    #[Test]
    public function angepasste_farbe_landet_als_style_tag_auf_root(): void
    {
        $tag = Theme::accentStyleTag('#2563EB');

        $this->assertSame(
            '<style id="ignis-accent">:root{--accent:#2563eb;--accent-hover:#2157cf;--accent-rgb:37, 99, 235}</style>',
            $tag,
        );
        $this->assertSame('#2563eb', Theme::accentHex('#2563EB'));
    }

    #[Test]
    public function auslieferungswerte_erzeugen_keinen_tag(): void
    {
        foreach (['#f0500a', '#FF4D00', '#d10000', '', 'rot', '#fff'] as $value) {
            $this->assertSame('', Theme::accentStyleTag($value), "Wert: $value");
            $this->assertSame(Theme::DEFAULT_ACCENT, Theme::accentHex($value), "Wert: $value");
        }
    }

    #[Test]
    public function token_standard_steht_in_den_tokens(): void
    {
        $tokens = (string) file_get_contents(dirname(__DIR__, 3) . '/assets/css/_tokens.scss');

        $this->assertStringContainsString('--accent: ' . Theme::DEFAULT_ACCENT . ';', $tokens);
    }
}
