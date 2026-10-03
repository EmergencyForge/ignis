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
    private ?string $brandingFile = null;

    protected function tearDown(): void
    {
        if ($this->picture !== null && is_file($this->picture)) {
            unlink($this->picture);
        }
        if ($this->brandingFile !== null && is_file($this->brandingFile)) {
            unlink($this->brandingFile);
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

    #[Test]
    public function liefert_hochgeladene_logos_aus_storage(): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/branding';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $name = 'test-' . bin2hex(random_bytes(6)) . '.webp';
        $this->brandingFile = $dir . '/' . $name;
        file_put_contents($this->brandingFile, 'RIFF-test-webp');

        $response = $this->get('/storage/branding/' . $name);

        $this->assertOk($response);
        $this->assertSame('image/webp', $response->headers['Content-Type'] ?? '');
        $this->assertSame('RIFF-test-webp', $response->body);
        $this->assertSame('nosniff', $response->headers['X-Content-Type-Options'] ?? '');
    }

    #[Test]
    public function branding_liefert_kein_svg_aus(): void
    {
        // Kein SVG erlaubt: eine hochgeladene SVG-Datei koennte Skript tragen
        // und wird von FileUpload::store() schon gar nicht angenommen. Das
        // hier prueft zusaetzlich, dass die Ausliefer-Route den Typ selbst
        // auch nicht durchlaesst, falls doch eine Datei dorthin gelangt.
        $dir = dirname(__DIR__, 2) . '/storage/branding';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $name = 'test-' . bin2hex(random_bytes(6)) . '.svg';
        $this->brandingFile = $dir . '/' . $name;
        file_put_contents($this->brandingFile, '<svg onload="alert(1)"></svg>');

        $this->assertNotFound($this->get('/storage/branding/' . $name));
    }
}
