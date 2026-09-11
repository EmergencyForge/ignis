<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * asset() baut URLs relativ zum Docroot public/. Templates verlinken die
 * Bundles historisch als `public/assets/dist/...`; die URL darf dieses
 * Präfix nicht mehr tragen, der Cache-Buster muss die Datei trotzdem
 * finden.
 */
final class AssetHelperTest extends TestCase
{
    private function base(): string
    {
        return rtrim(defined('BASE_PATH') ? (string) BASE_PATH : '/', '/');
    }

    #[Test]
    public function public_prefix_faellt_aus_der_url_heraus(): void
    {
        $url = asset('public/assets/dist/vendor.css');

        $this->assertStringStartsWith($this->base() . '/assets/dist/vendor.css?v=', $url);
    }

    #[Test]
    public function gespiegelte_assets_werden_unter_public_gefunden(): void
    {
        $this->assertFileExists(dirname(__DIR__, 3) . '/public/assets/img/defaultLogo.png');

        $url = asset('assets/img/defaultLogo.png');

        $this->assertStringStartsWith($this->base() . '/assets/img/defaultLogo.png?v=', $url);
    }

    #[Test]
    public function plugin_assets_bekommen_ihren_cache_buster_aus_dem_plugin_ordner(): void
    {
        $url = asset('plugins/enotf-v2/assets/wizard.js');

        $this->assertStringStartsWith($this->base() . '/plugins/enotf-v2/assets/wizard.js?v=', $url);
    }

    #[Test]
    public function unbekannte_dateien_bekommen_keinen_cache_buster(): void
    {
        $this->assertSame($this->base() . '/assets/nope.css', asset('assets/nope.css'));
    }
}
