<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Plugins\PluginLoader;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;
use ZipArchive;

/**
 * Plugin-Upload und Katalog-Installation unter /settings/system/plugins.
 *
 * Ein Upload landet erst in plugins/.staging/ und wird nach der
 * Bestätigung verschoben. Der Hinweis zu Drittanbietern ist dort Pflicht,
 * und zwar auf dem Server: ohne `accept_risk` passiert nichts, egal was
 * der Browser schickt. Die Tests schreiben in das echte plugins/ und
 * räumen ihre Ordner danach wieder weg.
 */
final class PluginInstallTest extends FeatureTestCase
{
    private const CATALOG_CACHE = __DIR__ . '/../../storage/cache/plugin-catalog.json';

    private string $pluginId;

    /** @var list<string> */
    private array $cleanupFiles = [];

    private ?string $catalogBackup = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(ZipArchive::class)) $this->markTestSkipped('PHP-ZIP fehlt.');
        $this->pluginId = 'zz-upload-test-' . bin2hex(random_bytes(3));
        // Kein Netz im Test: der Katalog-Abruf scheitert sofort und fällt
        // auf den Cache zurück.
        $_ENV['HUB_PLUGINS_URL'] = 'http://127.0.0.1:9/v1/plugins';
        $this->catalogBackup = is_file(self::CATALOG_CACHE) ? (string) file_get_contents(self::CATALOG_CACHE) : null;
    }

    protected function tearDown(): void
    {
        unset($_ENV['HUB_PLUGINS_URL']);
        $dir = PluginLoader::pluginsDir() . '/' . $this->pluginId;
        if (is_dir($dir)) $this->removeTree($dir);
        foreach (glob(PluginLoader::pluginsDir() . '/.staging/upload-*') ?: [] as $staged) {
            if (str_contains((string) @file_get_contents($staged . '/manifest.php'), $this->pluginId)) $this->removeTree($staged);
        }
        foreach ($this->cleanupFiles as $file) @unlink($file);
        if ($this->catalogBackup !== null) {
            file_put_contents(self::CATALOG_CACHE, $this->catalogBackup);
        } else {
            @unlink(self::CATALOG_CACHE);
        }
        parent::tearDown();
    }

    private function actingAsAdmin(): void
    {
        $user = FixtureFactory::user(['full_admin' => true]);
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
    }

    /** @return array<string,mixed> */
    private function zipUpload(?string $manifest = null): array
    {
        $path = sys_get_temp_dir() . '/plugin-upload-' . bin2hex(random_bytes(6)) . '.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('manifest.php', $manifest ?? "<?php return ['id' => '{$this->pluginId}', 'name' => 'Upload-Test', 'version' => '1.0.0', 'vendor' => 'Jemand'];");
        $zip->addFromString('routes.web.php', '<?php');
        $zip->close();
        $this->cleanupFiles[] = $path;

        return ['name' => 'plugin.zip', 'type' => 'application/zip', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path)];
    }

    private function upload(?string $manifest = null): string
    {
        $response = $this->post('/settings/system/plugins', ['plugin_action' => 'upload'], ['files' => ['plugin_zip' => $this->zipUpload($manifest)]]);
        $this->assertRedirect($response);
        $location = (string) ($response->headers['Location'] ?? '');
        $this->assertStringContainsString('confirm=upload', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        return (string) $query['token'];
    }

    #[Test]
    public function ohne_admin_recht_keine_plugin_verwaltung(): void
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['personnel.view'], 'cirs_username' => $user->username]);

        $response = $this->post('/settings/system/plugins', ['plugin_action' => 'upload'], ['files' => ['plugin_zip' => $this->zipUpload()]]);

        $this->assertRedirect($response);
        $this->assertStringNotContainsString('confirm=upload', (string) ($response->headers['Location'] ?? ''));
        $this->assertDirectoryDoesNotExist(PluginLoader::pluginsDir() . '/' . $this->pluginId);
    }

    #[Test]
    public function die_seite_zeigt_die_dropzone(): void
    {
        $this->actingAsAdmin();

        $response = $this->get('/settings/system/plugins');

        $this->assertOk($response);
        $this->assertBodyContains('enctype="multipart/form-data"', $response);
        $this->assertBodyContains('name="plugin_zip"', $response);
        $this->assertBodyContains('Plugin-ZIP hierher ziehen', $response);
    }

    #[Test]
    public function eine_datei_ohne_zip_wird_abgelehnt(): void
    {
        $this->actingAsAdmin();
        $path = sys_get_temp_dir() . '/plugin-upload-' . bin2hex(random_bytes(6)) . '.zip';
        file_put_contents($path, '<?php echo "kein Archiv";');
        $this->cleanupFiles[] = $path;

        $response = $this->post('/settings/system/plugins', ['plugin_action' => 'upload'], ['files' => ['plugin_zip' => [
            'name' => 'plugin.zip', 'type' => 'application/zip', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path),
        ]]]);

        $this->assertOk($response);
        $this->assertBodyContains('Upload abgelehnt', $response);
    }

    #[Test]
    public function ein_mitgeliefertes_plugin_laesst_sich_nicht_hochladen(): void
    {
        $this->actingAsAdmin();

        $response = $this->post('/settings/system/plugins', ['plugin_action' => 'upload'], ['files' => ['plugin_zip' => $this->zipUpload(
            "<?php return ['id' => 'firetab', 'name' => 'Fake', 'version' => '99.0.0'];",
        )]]);

        $this->assertOk($response);
        $this->assertBodyContains('mitgeliefertes Plugin', $response);
    }

    #[Test]
    public function upload_zeigt_die_warnung_und_installiert_erst_mit_haekchen(): void
    {
        $this->actingAsAdmin();
        $target = PluginLoader::pluginsDir() . '/' . $this->pluginId;

        $token = $this->upload();
        $this->assertDirectoryDoesNotExist($target, 'Vor der Bestätigung liegt nichts in plugins/<id>/.');

        $confirm = $this->get('/settings/system/plugins', ['query' => ['confirm' => 'upload', 'token' => $token]]);
        $this->assertOk($confirm);
        $this->assertBodyContains('Plugin eines Drittanbieters', $confirm);
        $this->assertBodyContains('name="accept_risk"', $confirm);
        $this->assertBodyContains($this->pluginId, $confirm);

        // Ohne Häkchen: abgelehnt, der Upload wartet weiter.
        $refused = $this->post('/settings/system/plugins', ['plugin_action' => 'upload_commit', 'upload_token' => $token, 'expect_update' => '0', 'install_now' => '0']);
        $this->assertBodyContains('Hinweis zu Plugins von Drittanbietern', $refused);
        $this->assertDirectoryDoesNotExist($target);

        $done = $this->post('/settings/system/plugins', [
            'plugin_action' => 'upload_commit', 'upload_token' => $token, 'expect_update' => '0', 'install_now' => '0', 'accept_risk' => '1',
        ]);
        $this->assertOk($done);
        $this->assertBodyContains('liegt inaktiv bereit', $done);
        $this->assertFileExists($target . '/manifest.php');
        $this->assertFileDoesNotExist($target . '/.installed', 'Ohne „Direkt installieren“ bleibt das Plugin inert.');

        $this->assertTrue(Capsule::table('intra_audit_log')
            ->where('module', 'Plugins')
            ->where('action', 'Plugin bereitgestellt')
            ->where('context', 'like', '%' . $this->pluginId . '%')
            ->exists());
    }

    #[Test]
    public function direkt_installieren_schaltet_das_plugin_frei_und_aktiviert_es(): void
    {
        $this->actingAsAdmin();
        $target = PluginLoader::pluginsDir() . '/' . $this->pluginId;
        $token = $this->upload();

        $done = $this->post('/settings/system/plugins', [
            'plugin_action' => 'upload_commit', 'upload_token' => $token, 'expect_update' => '0', 'install_now' => '1', 'accept_risk' => '1',
        ]);

        $this->assertBodyContains('installiert und aktiviert', $done);
        $this->assertFileExists($target . '/.installed');
        $this->assertSame(1, (int) Capsule::table('intra_plugins')->where('plugin_id', $this->pluginId)->value('enabled'));
        $this->assertTrue(Capsule::table('intra_audit_log')->where('action', 'Plugin installiert')->where('context', 'like', '%' . $this->pluginId . '%')->exists());
    }

    #[Test]
    public function ein_upload_aktualisiert_ein_vorhandenes_plugin_nur_wie_bestaetigt(): void
    {
        $this->actingAsAdmin();
        $target = PluginLoader::pluginsDir() . '/' . $this->pluginId;
        mkdir($target, 0775, true);
        file_put_contents($target . '/manifest.php', "<?php return ['id' => '{$this->pluginId}', 'name' => 'Alt', 'version' => '0.9.0'];");

        $token = $this->upload();
        $confirm = $this->get('/settings/system/plugins', ['query' => ['confirm' => 'upload', 'token' => $token]]);
        $this->assertBodyContains('Update einspielen', $confirm);
        $this->assertBodyContains('name="expect_update" value="1"', $confirm);

        // Ein als „neu“ bestätigter Upload überschreibt nichts.
        $refused = $this->post('/settings/system/plugins', [
            'plugin_action' => 'upload_commit', 'upload_token' => $token, 'expect_update' => '0', 'accept_risk' => '1',
        ]);
        $this->assertBodyContains('existiert bereits', $refused);
        $this->assertStringContainsString('0.9.0', (string) file_get_contents($target . '/manifest.php'));

        $token = $this->upload();
        $done = $this->post('/settings/system/plugins', [
            'plugin_action' => 'upload_commit', 'upload_token' => $token, 'expect_update' => '1', 'accept_risk' => '1',
        ]);
        $this->assertBodyContains('auf 1.0.0 aktualisiert', $done);
        $this->assertStringContainsString('1.0.0', (string) file_get_contents($target . '/manifest.php'));
        foreach (glob(PluginLoader::pluginsDir() . '/.backup/' . $this->pluginId . '-*') ?: [] as $backup) $this->removeTree($backup);
    }

    #[Test]
    public function ein_fremder_upload_token_wird_nicht_angenommen(): void
    {
        $this->actingAsAdmin();
        $token = $this->upload();
        unset($_SESSION['plugin_uploads'][$token]);

        $response = $this->post('/settings/system/plugins', [
            'plugin_action' => 'upload_commit', 'upload_token' => $token, 'expect_update' => '0', 'install_now' => '0', 'accept_risk' => '1',
        ]);

        $this->assertBodyContains('nicht mehr vorhanden', $response);
        $this->assertDirectoryDoesNotExist(PluginLoader::pluginsDir() . '/' . $this->pluginId);
    }

    #[Test]
    public function verwerfen_loescht_den_upload(): void
    {
        $this->actingAsAdmin();
        $token = $this->upload();

        $response = $this->post('/settings/system/plugins', ['plugin_action' => 'upload_discard', 'upload_token' => $token]);

        $this->assertBodyContains('verworfen', $response);
        $this->assertDirectoryDoesNotExist(PluginLoader::pluginsDir() . '/.staging/' . $token);
    }

    #[Test]
    public function installieren_eines_bereitliegenden_plugins_braucht_das_haekchen(): void
    {
        $this->actingAsAdmin();
        $target = PluginLoader::pluginsDir() . '/' . $this->pluginId;
        mkdir($target, 0775, true);
        file_put_contents($target . '/manifest.php', "<?php return ['id' => '{$this->pluginId}', 'name' => 'Bereit', 'version' => '1.0.0'];");

        $confirm = $this->get('/settings/system/plugins', ['query' => ['confirm' => 'install', 'plugin' => $this->pluginId]]);
        $this->assertBodyContains('name="accept_risk"', $confirm);

        $response = $this->post('/settings/system/plugins', ['plugin_action' => 'install', 'plugin_id' => $this->pluginId]);

        $this->assertBodyContains('Hinweis zu Plugins von Drittanbietern', $response);
        $this->assertFileDoesNotExist($target . '/.installed');
    }

    #[Test]
    public function ein_unbekanntes_feld_installiert_nichts(): void
    {
        $this->actingAsAdmin();
        $target = PluginLoader::pluginsDir() . '/' . $this->pluginId;
        mkdir($target, 0775, true);
        file_put_contents($target . '/manifest.php', "<?php return ['id' => '{$this->pluginId}', 'name' => 'Bereit', 'version' => '1.0.0'];");

        $response = $this->post('/settings/system/plugins', ['plugin_action' => 'install', 'plugin_id' => $this->pluginId, 'accept_risk' => '1', 'force' => '1']);

        $this->assertBodyContains('Das Formular enthält unbekannte Felder.', $response);
        $this->assertFileDoesNotExist($target . '/.installed');
    }

    #[Test]
    public function katalog_plugins_von_drittanbietern_brauchen_das_haekchen(): void
    {
        $this->actingAsAdmin();
        $this->seedCatalog([
            'slug' => $this->pluginId,
            'name' => 'Katalog-Test',
            'description' => '',
            'version' => '1.0.0',
            'publisher' => 'Jemand',
            'source_url' => 'https://github.com/jemand/katalog-test',
            'zip_url' => 'https://github.com/jemand/katalog-test/releases/download/v1.0.0/plugin.zip',
            'sha256' => str_repeat('a', 64),
            'trust' => 'untested',
            'installable' => true,
        ]);

        $confirm = $this->get('/settings/system/plugins', ['query' => ['confirm' => 'catalog', 'plugin' => $this->pluginId]]);
        $this->assertOk($confirm);
        $this->assertBodyContains('Plugin eines Drittanbieters', $confirm);
        $this->assertBodyContains('„Ungetestet“', $confirm);
        $this->assertBodyContains('GitHub · jemand/katalog-test', $confirm);
        $this->assertBodyContains('name="accept_risk"', $confirm);

        // Ohne Häkchen wird nicht einmal heruntergeladen.
        $response = $this->post('/settings/system/plugins', ['plugin_action' => 'catalog_install', 'plugin_id' => $this->pluginId, 'install_now' => '1']);
        $this->assertBodyContains('Hinweis zu Plugins von Drittanbietern', $response);
        $this->assertDirectoryDoesNotExist(PluginLoader::pluginsDir() . '/' . $this->pluginId);
    }

    #[Test]
    public function offizielle_katalog_plugins_zeigen_keinen_drittanbieter_hinweis(): void
    {
        $this->actingAsAdmin();
        $this->seedCatalog([
            'slug' => $this->pluginId,
            'name' => 'Offiziell-Test',
            'description' => '',
            'version' => '1.0.0',
            'publisher' => 'EmergencyForge',
            'source_url' => '',
            'zip_url' => 'https://github.com/EmergencyForge/x/releases/download/v1.0.0/plugin.zip',
            'sha256' => str_repeat('b', 64),
            'trust' => 'official',
            'installable' => true,
        ]);

        $confirm = $this->get('/settings/system/plugins', ['query' => ['confirm' => 'catalog', 'plugin' => $this->pluginId]]);

        $this->assertOk($confirm);
        $this->assertBodyContains('Offizielles Plugin von EmergencyForge', $confirm);
        $this->assertStringNotContainsString('name="accept_risk"', $confirm->body);
    }

    /** @param array<string,mixed> $entry */
    private function seedCatalog(array $entry): void
    {
        $dir = dirname(self::CATALOG_CACHE);
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        file_put_contents(self::CATALOG_CACHE, json_encode(['timestamp' => time(), 'plugins' => [$entry]]));
    }

    private function removeTree(string $path): void
    {
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') continue;
            $child = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($child) && !is_link($child)) $this->removeTree($child);
            else @unlink($child);
        }
        @rmdir($path);
    }
}
