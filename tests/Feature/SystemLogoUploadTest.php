<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * `POST /api/system/logo` (Upload) und `/api/system/logo/remove` hinter dem
 * Package-Dropzone auf der System-Konfiguration: SYSTEM_LOGO zeigt danach auf
 * die hochgeladene Datei unter storage/branding, „Logo entfernen" setzt auf
 * den Standard zurueck. Beide Endpoints brauchen die `admin`-Permission,
 * genau wie der Rest der System-Admin-API.
 */
final class SystemLogoUploadTest extends FeatureTestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    /** @var list<string> */
    private array $cleanupFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->cleanupFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    private function brandingDir(): string
    {
        return dirname(__DIR__, 2) . '/storage/branding';
    }

    /** @return array<string,mixed> */
    private function pngFile(string $name = 'logo.png'): array
    {
        $content = (string) base64_decode(self::PNG);
        $tmp = sys_get_temp_dir() . '/system_logo_' . bin2hex(random_bytes(6));
        file_put_contents($tmp, $content);
        $this->cleanupFiles[] = $tmp;

        return [
            'name'     => $name,
            'type'     => 'image/png',
            'tmp_name' => $tmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => strlen($content),
        ];
    }

    private function currentSystemLogo(): string
    {
        return (string) Capsule::table('intra_config')->where('config_key', 'SYSTEM_LOGO')->value('config_value');
    }

    private function actingAsAdmin(): void
    {
        $user = FixtureFactory::user(['full_admin' => true]);
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
    }

    #[Test]
    public function laedt_ein_gueltiges_png_hoch_und_speichert_den_pfad_ohne_base_path(): void
    {
        $this->actingAsAdmin();

        $response = $this->post('/api/system/logo', [], ['files' => ['logo' => $this->pngFile()]]);

        $body = $this->assertJsonResponse($response);
        $this->assertTrue($body['success']);

        $stored = $this->currentSystemLogo();
        $this->assertStringStartsWith('/storage/branding/', $stored);
        $this->assertStringEndsWith('.png', $stored);
        $this->assertFileExists($this->brandingDir() . '/' . basename($stored));
        $this->cleanupFiles[] = $this->brandingDir() . '/' . basename($stored);

        // BASE_PATH genau einmal — nicht im Config-Wert gebacken, sonst
        // haengt systemLogoUrl() sie ein zweites Mal an.
        $this->assertSame(systemLogoUrl($stored), $body['url']);
        $this->assertStringNotContainsString('//storage', $body['url']);
    }

    #[Test]
    public function lehnt_svg_ab(): void
    {
        $this->actingAsAdmin();

        $tmp = sys_get_temp_dir() . '/system_logo_svg_' . bin2hex(random_bytes(6));
        file_put_contents($tmp, '<svg onload="alert(1)"></svg>');
        $this->cleanupFiles[] = $tmp;

        $before = $this->currentSystemLogo();

        $response = $this->post('/api/system/logo', [], ['files' => ['logo' => [
            'name' => 'logo.svg', 'type' => 'image/svg+xml', 'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp),
        ]]]);

        $body = $this->assertJsonResponse($response);
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('Dateityp', $body['message']);
        $this->assertSame($before, $this->currentSystemLogo());
    }

    #[Test]
    public function lehnt_zu_grosse_dateien_ab(): void
    {
        $this->actingAsAdmin();

        $content = str_repeat('x', 2 * 1024 * 1024 + 1);
        $tmp = sys_get_temp_dir() . '/system_logo_big_' . bin2hex(random_bytes(6));
        file_put_contents($tmp, $content);
        $this->cleanupFiles[] = $tmp;

        $response = $this->post('/api/system/logo', [], ['files' => ['logo' => [
            'name' => 'logo.png', 'type' => 'image/png', 'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK, 'size' => strlen($content),
        ]]]);

        $body = $this->assertJsonResponse($response);
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('groß', $body['message']);
    }

    #[Test]
    public function ein_zweiter_upload_ersetzt_die_alte_datei(): void
    {
        $this->actingAsAdmin();

        $first = $this->assertJsonResponse($this->post('/api/system/logo', [], ['files' => ['logo' => $this->pngFile()]]));
        $firstPath = $this->brandingDir() . '/' . basename($this->currentSystemLogo());
        $this->assertFileExists($firstPath);

        $second = $this->assertJsonResponse($this->post('/api/system/logo', [], ['files' => ['logo' => $this->pngFile()]]));
        $secondPath = $this->brandingDir() . '/' . basename($this->currentSystemLogo());
        $this->cleanupFiles[] = $secondPath;

        $this->assertTrue($second['success']);
        $this->assertNotSame($firstPath, $secondPath);
        $this->assertFileDoesNotExist($firstPath);
        $this->assertFileExists($secondPath);
    }

    #[Test]
    public function entfernen_setzt_auf_den_standard_zurueck_und_loescht_die_datei(): void
    {
        $this->actingAsAdmin();

        $this->assertJsonResponse($this->post('/api/system/logo', [], ['files' => ['logo' => $this->pngFile()]]));
        $uploaded = $this->brandingDir() . '/' . basename($this->currentSystemLogo());
        $this->assertFileExists($uploaded);

        $response = $this->post('/api/system/logo/remove');
        $body = $this->assertJsonResponse($response);

        $this->assertTrue($body['success']);
        $this->assertTrue(systemLogoIsDefault($this->currentSystemLogo()));
        $this->assertFileDoesNotExist($uploaded);
        $this->assertSame(systemLogoUrl(''), $body['url']);
    }

    #[Test]
    public function ohne_admin_recht_gibt_es_403(): void
    {
        $user = FixtureFactory::user(['full_admin' => false]);
        $this->actingAs($user->id, ['permissions' => [], 'cirs_username' => $user->username]);

        $this->assertForbidden($this->post('/api/system/logo', [], ['files' => ['logo' => $this->pngFile()]]));
        $this->assertForbidden($this->post('/api/system/logo/remove'));
    }

    #[Test]
    public function ein_erfolgreicher_upload_schreibt_einen_audit_log_eintrag(): void
    {
        $this->actingAsAdmin();

        $this->assertJsonResponse($this->post('/api/system/logo', [], ['files' => ['logo' => $this->pngFile()]]));
        $stored = $this->currentSystemLogo();
        $this->cleanupFiles[] = $this->brandingDir() . '/' . basename($stored);

        $entry = Capsule::table('intra_audit_log')
            ->where('action', 'Config SYSTEM_LOGO bearbeitet')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($entry);
        $this->assertStringContainsString($stored, (string) $entry->details);
    }

    /**
     * SYSTEM_LOGO ist ein normales Textfeld — ein Admin kann dort per Hand
     * einen Pfad mit `..` eintragen. Ersetzt oder entfernt er das Logo
     * danach ueber die Dropzone, darf deleteOldLogoFile() diesem Pfad nicht
     * blind folgen: sonst loescht ein Wert wie
     * "/storage/branding/../../evil-marker.txt" eine Datei ausserhalb von
     * storage/branding.
     */
    #[Test]
    public function ein_manipulierter_alter_pfad_loescht_keine_datei_ausserhalb_von_branding(): void
    {
        $this->actingAsAdmin();

        $projectRoot = dirname(__DIR__, 2);
        $marker = $projectRoot . '/evil-marker-' . bin2hex(random_bytes(6)) . '.txt';
        file_put_contents($marker, 'sollte ueberleben');
        $this->cleanupFiles[] = $marker;

        // Loest sich beim alten Code in $projectRoot . 'storage/branding/../../' . basename
        // = $projectRoot . basename auf.
        $traversal = '/storage/branding/../../' . basename($marker);
        Capsule::table('intra_config')->where('config_key', 'SYSTEM_LOGO')->update(['config_value' => $traversal]);

        $response = $this->post('/api/system/logo/remove');
        $body = $this->assertJsonResponse($response);

        $this->assertTrue($body['success']);
        $this->assertFileExists($marker, 'Traversal-Pfad hat eine Datei ausserhalb von storage/branding geloescht');
        $this->assertTrue(systemLogoIsDefault($this->currentSystemLogo()));
    }
}
