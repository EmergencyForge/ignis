<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;

/**
 * Plugin-Assets und Uploads liegen außerhalb von public/ und kommen über
 * Routen zum Browser. Geprüft wird gegen ein mitgeliefertes Plugin und
 * gegen eine Wegwerf-Datei unter storage/profile-pictures.
 */
final class StaticFileRoutesTest extends FeatureTestCase
{
    private const PLUGIN_ASSET = 'plugins/enotf-v2/assets/wizard.js';

    private ?string $picture = null;

    protected function tearDown(): void
    {
        if ($this->picture !== null && is_file($this->picture)) {
            unlink($this->picture);
        }
        parent::tearDown();
    }

    #[Test]
    public function liefert_ein_asset_eines_mitgelieferten_plugins(): void
    {
        $file = dirname(__DIR__, 2) . '/' . self::PLUGIN_ASSET;
        $this->assertFileExists($file);

        $response = $this->get('/' . self::PLUGIN_ASSET);

        $this->assertOk($response);
        $this->assertSame('text/javascript; charset=utf-8', $response->headers['Content-Type'] ?? '');
        $this->assertSame(file_get_contents($file), $response->body);
        $this->assertStringContainsString('max-age', $response->headers['Cache-Control'] ?? '');
    }

    #[Test]
    public function weist_pfade_mit_punkt_punkt_ab(): void
    {
        $this->assertNotFound($this->get('/plugins/enotf-v2/assets/../manifest.php'));
        $this->assertNotFound($this->get('/plugins/enotf-v2/assets/../../../composer.json'));
    }

    #[Test]
    public function weist_php_und_andere_nicht_erlaubte_endungen_ab(): void
    {
        $this->assertNotFound($this->get('/plugins/enotf-v2/assets/wizard.php'));
        $this->assertNotFound($this->get('/plugins/enotf-v2/assets/wizard.js.bak'));
    }

    #[Test]
    public function kennt_nur_installierte_plugins_und_nur_deren_assets_ordner(): void
    {
        $this->assertNotFound($this->get('/plugins/does-not-exist/assets/plugin.css'));
        $this->assertNotFound($this->get('/plugins/enotf-v2/manifest.php'));
        $this->assertNotFound($this->get('/plugins/enotf-v2/templates/x.js'));
    }

    #[Test]
    public function liefert_profilbilder_aus_storage(): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/profile-pictures';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $name = 'test-' . bin2hex(random_bytes(6)) . '.png';
        $this->picture = $dir . '/' . $name;
        file_put_contents($this->picture, "\x89PNG-test");

        $response = $this->get('/storage/profile-pictures/' . $name);

        $this->assertOk($response);
        $this->assertSame('image/png', $response->headers['Content-Type'] ?? '');
        $this->assertSame("\x89PNG-test", $response->body);
    }

    #[Test]
    public function storage_kennt_nur_die_freigegebenen_ordner_und_endungen(): void
    {
        $this->assertNotFound($this->get('/storage/logs/app.log'));
        $this->assertNotFound($this->get('/storage/documents/../version.json'));
        $this->assertNotFound($this->get('/storage/profile-pictures/x.php'));
    }
}
