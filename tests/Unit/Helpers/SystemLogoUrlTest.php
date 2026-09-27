<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `systemLogoUrl()` (src/helpers.php) macht aus SYSTEM_LOGO eine URL für ein
 * <img>: Standardlogos werden zur Wortmarke, relative Pfade bekommen
 * BASE_PATH (sonst 404 unter einem Unterpfad wie /intra/), die Ausgabe ist
 * für ein Attribut escaped.
 */
final class SystemLogoUrlTest extends TestCase
{
    private function base(): string
    {
        return rtrim(defined('BASE_PATH') ? (string) BASE_PATH : '/', '/');
    }

    #[Test]
    public function standardlogos_werden_zur_wortmarke_unter_base_path(): void
    {
        $wordmark = $this->base() . '/assets/img/ignis-wordmark.svg';

        foreach (['', '  ', '/assets/img/ignis-wordmark.svg', '/assets/img/defaultLogo.webp', '/assets/img/defaultLogo.png'] as $logo) {
            $this->assertSame($wordmark, systemLogoUrl($logo), var_export($logo, true));
            $this->assertTrue(systemLogoIsDefault($logo), var_export($logo, true));
        }
    }

    #[Test]
    public function lockup_und_bildmarke_werden_als_img_nicht_schwarz(): void
    {
        // Beide füllen mit currentColor; als <img> ist das Schwarz.
        $wordmark = $this->base() . '/assets/img/ignis-wordmark.svg';

        $this->assertSame($wordmark, systemLogoUrl('/assets/img/ignis-lockup.svg'));
        $this->assertSame($wordmark, systemLogoUrl('assets/img/ignis-mark.svg'));
    }

    #[Test]
    public function relative_pfade_bekommen_base_path(): void
    {
        $this->assertSame($this->base() . '/uploads/logo.png', systemLogoUrl('uploads/logo.png'));
        $this->assertSame($this->base() . '/uploads/logo.png', systemLogoUrl('/uploads/logo.png'));
        $this->assertFalse(systemLogoIsDefault('/uploads/logo.png'));
    }

    /**
     * Api\SystemController::uploadLogo() legt SYSTEM_LOGO ohne BASE_PATH ab
     * (`/storage/branding/<datei>`) — genau wie jeder andere relative Pfad.
     * Ein gebackenes BASE_PATH im Config-Wert wuerde hier doppelt landen.
     */
    #[Test]
    public function hochgeladene_logos_bekommen_base_path_genau_einmal(): void
    {
        $uploaded = '/storage/branding/' . bin2hex(random_bytes(16)) . '.png';

        $url = systemLogoUrl($uploaded);

        $this->assertSame($this->base() . $uploaded, $url);
        $this->assertSame(1, substr_count($url, $this->base() . '/storage/branding/'));
        $this->assertFalse(systemLogoIsDefault($uploaded));
    }

    #[Test]
    public function absolute_urls_bleiben_unveraendert(): void
    {
        $this->assertSame('https://cdn.example.org/logo.png', systemLogoUrl('https://cdn.example.org/logo.png'));
        $this->assertSame('//cdn.example.org/logo.png', systemLogoUrl('//cdn.example.org/logo.png'));
    }

    #[Test]
    public function ausgabe_ist_fuer_attribute_escaped(): void
    {
        $this->assertSame(
            'https://x.example/l.png?a=1&amp;b=&quot;&gt;&lt;script&gt;&#039;',
            systemLogoUrl('https://x.example/l.png?a=1&b="><script>\'')
        );
    }

    #[Test]
    public function ohne_argument_gilt_system_logo(): void
    {
        $expected = defined('SYSTEM_LOGO') ? systemLogoUrl((string) SYSTEM_LOGO) : systemLogoUrl('');

        $this->assertSame($expected, systemLogoUrl());
    }
}
