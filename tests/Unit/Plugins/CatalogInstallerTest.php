<?php

declare(strict_types=1);

namespace Tests\Unit\Plugins;

use App\Plugins\CatalogInstaller;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class CatalogInstallerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(ZipArchive::class)) $this->markTestSkipped('PHP-ZIP fehlt.');
        $this->root = sys_get_temp_dir() . '/ignis-installer-' . getmypid() . '-' . bin2hex(random_bytes(3));
        mkdir($this->root . '/plugins', 0777, true);
        mkdir($this->root . '/cache', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
        parent::tearDown();
    }

    #[Test]
    public function digest_mismatch_never_creates_a_plugin_directory(): void
    {
        $installer = $this->installerWithPayload('not-a-zip');
        $this->expectExceptionMessage('Digest');
        try {
            $installer->stage($this->entry(str_repeat('0', 64)));
        } finally {
            $this->assertDirectoryDoesNotExist($this->root . '/plugins/demo');
        }
    }

    #[Test]
    public function zip_slip_entries_are_rejected(): void
    {
        $zip = $this->zip([
            'manifest.php' => $this->manifest('demo'),
            '../escape.php' => '<?php echo "escaped";',
        ]);
        $installer = $this->installerWithFile($zip);
        $this->expectExceptionMessage('Unsicherer Pfad');
        try {
            $installer->stage($this->entry(hash_file('sha256', $zip)));
        } finally {
            $this->assertFileDoesNotExist($this->root . '/escape.php');
        }
    }

    #[Test]
    public function manifest_id_must_match_catalog_slug(): void
    {
        $zip = $this->zip(['manifest.php' => $this->manifest('different')]);
        $installer = $this->installerWithFile($zip);
        $this->expectExceptionMessage('Manifest-ID');
        $installer->stage($this->entry(hash_file('sha256', $zip)));
    }

    #[Test]
    public function a_shipped_install_marker_is_stripped_from_catalog_downloads(): void
    {
        $zip = $this->zip(['manifest.php' => $this->manifest('demo'), '.installed' => 'selbst freigeschaltet']);
        $plugin = $this->installerWithFile($zip)->stage($this->entry(hash_file('sha256', $zip)));

        $this->assertSame($this->root . '/plugins/demo', $plugin->directory);
        $this->assertFileDoesNotExist($this->root . '/plugins/demo/.installed');
    }

    #[Test]
    public function an_upload_waits_in_staging_until_it_is_committed(): void
    {
        $zip = $this->zip(['manifest.php' => $this->manifest('demo'), 'src/Thing.php' => '<?php // x', '.installed' => 'x']);
        $installer = $this->installer();

        $pending = $installer->stageUpload($zip);

        $this->assertMatchesRegularExpression('/^upload-[a-f0-9]{24}$/', $pending['token']);
        $this->assertSame('demo', $pending['manifest']->id);
        $this->assertSame(hash_file('sha256', $zip), $pending['sha256']);
        $this->assertFalse($pending['update']);
        $this->assertDirectoryDoesNotExist($this->root . '/plugins/demo');
        $this->assertSame([], glob($this->root . '/plugins/*/manifest.php'), 'Staging darf nicht in der Plugin-Erkennung auftauchen.');

        $plugin = $installer->commitUpload($pending['token'], false);

        $this->assertSame($this->root . '/plugins/demo', $plugin->directory);
        $this->assertFileExists($this->root . '/plugins/demo/src/Thing.php');
        $this->assertFileDoesNotExist($this->root . '/plugins/demo/.installed');
        $this->assertFileDoesNotExist($this->root . '/plugins/demo/.upload.json');
        $this->assertDirectoryDoesNotExist($this->root . '/plugins/.staging/' . $pending['token']);
    }

    #[Test]
    public function a_zipped_folder_with_one_plugin_inside_is_accepted(): void
    {
        $zip = $this->zip(['demo-1.0.0/manifest.php' => $this->manifest('demo'), 'demo-1.0.0/routes.web.php' => '<?php']);
        $installer = $this->installer();

        $plugin = $installer->commitUpload($installer->stageUpload($zip)['token'], false);

        $this->assertFileExists($plugin->directory . '/manifest.php');
        $this->assertFileExists($plugin->directory . '/routes.web.php');
        $this->assertDirectoryDoesNotExist($this->root . '/plugins/demo/demo-1.0.0');
    }

    #[Test]
    public function an_upload_without_manifest_is_rejected_and_leaves_nothing_behind(): void
    {
        $zip = $this->zip(['readme.txt' => 'kein Plugin']);
        try {
            $this->installer()->stageUpload($zip);
            $this->fail('Ohne Manifest darf nichts bereitgestellt werden.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('manifest.php fehlt', $e->getMessage());
        }
        $this->assertSame([], glob($this->root . '/plugins/.staging/upload-*') ?: []);
    }

    #[Test]
    public function an_upload_that_is_no_zip_is_rejected(): void
    {
        $file = $this->root . '/fake.zip';
        file_put_contents($file, '<?php echo "kein zip";');
        $this->expectExceptionMessage('kein gültiges ZIP');
        $this->installer()->stageUpload($file);
    }

    #[Test]
    public function zip_slip_in_an_upload_is_rejected(): void
    {
        $zip = $this->zip(['manifest.php' => $this->manifest('demo'), 'src/../../../escape.php' => '<?php']);
        try {
            $this->installer()->stageUpload($zip);
            $this->fail('Pfade mit .. müssen abgelehnt werden.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Unsicherer Pfad', $e->getMessage());
        }
        $this->assertFileDoesNotExist($this->root . '/escape.php');
        $this->assertFileDoesNotExist($this->root . '/plugins/escape.php');
        $this->assertSame([], glob($this->root . '/plugins/.staging/upload-*') ?: []);
    }

    #[Test]
    public function absolute_paths_in_an_upload_are_rejected(): void
    {
        $zip = $this->zip(['manifest.php' => $this->manifest('demo'), '/etc/evil.php' => '<?php']);
        $this->expectExceptionMessage('Unsicherer Pfad');
        $this->installer()->stageUpload($zip);
    }

    #[Test]
    public function symlinks_in_an_upload_are_rejected(): void
    {
        $path = $this->zip(['manifest.php' => $this->manifest('demo'), 'link' => '/etc/passwd']);
        $zip = new ZipArchive();
        $zip->open($path);
        $zip->setExternalAttributesName('link', ZipArchive::OPSYS_UNIX, (0120777 << 16));
        $zip->close();

        $this->expectExceptionMessage('Symbolische Links');
        $this->installer()->stageUpload($path);
    }

    #[Test]
    public function bundled_plugin_ids_cannot_be_uploaded(): void
    {
        $zip = $this->zip(['manifest.php' => $this->manifest('enotf')]);
        $this->expectExceptionMessage('mitgeliefertes Plugin');
        $this->installer()->stageUpload($zip);
    }

    #[Test]
    public function an_invalid_plugin_id_is_rejected(): void
    {
        $zip = $this->zip(['manifest.php' => $this->manifest('../evil')]);
        $this->expectExceptionMessage('Manifest ist ungültig');
        $this->installer()->stageUpload($zip);
    }

    #[Test]
    public function a_manifest_with_code_is_rejected(): void
    {
        $zip = $this->zip(['manifest.php' => "<?php return ['id' => 'demo', 'name' => strtoupper('x'), 'version' => '1.0.0'];"]);
        $this->expectExceptionMessage('Manifest ist ungültig');
        $this->installer()->stageUpload($zip);
    }

    #[Test]
    public function an_incompatible_plugin_is_rejected(): void
    {
        $zip = $this->zip(['manifest.php' => "<?php return ['id'=>'demo','name'=>'Demo','version'=>'1.0.0','requires'=>['ignis'=>'>=9.0']];"]);
        $this->expectExceptionMessage('benötigt ignis');
        $this->installer()->stageUpload($zip);
    }

    #[Test]
    public function an_existing_plugin_is_only_replaced_by_an_explicit_update(): void
    {
        $this->installPlugin('demo', '1.0.0', installed: true);
        $installer = $this->installer();
        $zip = $this->zip(['manifest.php' => $this->manifest('demo', '1.1.0')]);

        $pending = $installer->stageUpload($zip);
        $this->assertTrue($pending['update']);
        $this->assertSame('1.0.0', $pending['installed_version']);
        try {
            $installer->commitUpload($pending['token'], false);
            $this->fail('Ohne bestätigtes Update darf nichts überschrieben werden.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('existiert bereits', $e->getMessage());
        }
        $this->assertStringContainsString("'1.0.0'", (string) file_get_contents($this->root . '/plugins/demo/manifest.php'));

        $plugin = $installer->commitUpload($installer->stageUpload($zip)['token'], true);

        $this->assertSame('1.1.0', $plugin->manifest->version);
        $this->assertFileExists($this->root . '/plugins/demo/.installed', 'Ein Update behält den Installationsstatus.');
        $this->assertCount(1, glob($this->root . '/plugins/.backup/demo-1.0.0-*') ?: []);
    }

    #[Test]
    public function an_update_never_downgrades(): void
    {
        $this->installPlugin('demo', '2.0.0', installed: true);
        $installer = $this->installer();
        $pending = $installer->stageUpload($this->zip(['manifest.php' => $this->manifest('demo', '1.9.0')]));

        $this->expectExceptionMessage('Downgrades');
        try {
            $installer->commitUpload($pending['token'], true);
        } finally {
            $this->assertStringContainsString("'2.0.0'", (string) file_get_contents($this->root . '/plugins/demo/manifest.php'));
        }
    }

    #[Test]
    public function a_confirmed_new_install_fails_if_the_plugin_appeared_meanwhile(): void
    {
        $installer = $this->installer();
        $pending = $installer->stageUpload($this->zip(['manifest.php' => $this->manifest('demo')]));
        $this->installPlugin('demo', '0.9.0', installed: false);

        $this->expectExceptionMessage('existiert bereits');
        $installer->commitUpload($pending['token'], false);
    }

    #[Test]
    public function the_same_id_in_another_directory_is_rejected(): void
    {
        mkdir($this->root . '/plugins/other', 0777, true);
        file_put_contents($this->root . '/plugins/other/manifest.php', $this->manifest('demo'));

        $this->expectExceptionMessage('plugins/other/');
        $this->installer()->stageUpload($this->zip(['manifest.php' => $this->manifest('demo')]));
    }

    #[Test]
    public function upload_tokens_cannot_point_outside_the_staging_folder(): void
    {
        $this->expectExceptionMessage('Unbekannter Upload');
        $this->installer()->commitUpload('../demo', false);
    }

    #[Test]
    public function a_discarded_upload_is_gone(): void
    {
        $installer = $this->installer();
        $pending = $installer->stageUpload($this->zip(['manifest.php' => $this->manifest('demo')]));

        $installer->discardUpload($pending['token']);

        $this->assertDirectoryDoesNotExist($this->root . '/plugins/.staging/' . $pending['token']);
        $this->expectExceptionMessage('nicht mehr vorhanden');
        $installer->pendingUpload($pending['token']);
    }

    #[Test]
    public function stale_uploads_are_pruned(): void
    {
        $installer = $this->installer();
        $old = $installer->stageUpload($this->zip(['manifest.php' => $this->manifest('demo')]));
        touch($this->root . '/plugins/.staging/' . $old['token'], time() - CatalogInstaller::PENDING_TTL - 10);
        $fresh = $installer->stageUpload($this->zip(['manifest.php' => $this->manifest('other')]));

        $installer->pruneStaging();

        $this->assertDirectoryDoesNotExist($this->root . '/plugins/.staging/' . $old['token']);
        $this->assertDirectoryExists($this->root . '/plugins/.staging/' . $fresh['token']);
    }

    private function installPlugin(string $id, string $version, bool $installed): void
    {
        mkdir($this->root . '/plugins/' . $id, 0777, true);
        file_put_contents($this->root . '/plugins/' . $id . '/manifest.php', $this->manifest($id, $version));
        if ($installed) file_put_contents($this->root . '/plugins/' . $id . '/.installed', date('c'));
    }

    private function installer(): CatalogInstaller
    {
        return new CatalogInstaller($this->root . '/plugins', $this->root . '/cache', '1.2.0');
    }

    /** @return array<string,mixed> */
    private function entry(string $hash): array
    {
        return [
            'slug' => 'demo',
            'zip_url' => 'https://github.com/example/demo/releases/download/v1/demo.zip',
            'sha256' => $hash,
        ];
    }

    private function manifest(string $id, string $version = '1.0.0'): string
    {
        return "<?php return ['id'=>" . var_export($id, true) . ",'name'=>'Demo','version'=>" . var_export($version, true) . ",'requires'=>['ignis'=>'>=1.0']];";
    }

    /** @param array<string,string> $files */
    private function zip(array $files): string
    {
        $path = $this->root . '/fixture-' . bin2hex(random_bytes(3)) . '.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) $zip->addFromString($name, $content);
        $zip->close();
        return $path;
    }

    private function installerWithPayload(string $payload): CatalogInstaller
    {
        return new CatalogInstaller($this->root . '/plugins', $this->root . '/cache', '1.2.0', static function ($url, $target) use ($payload): void {
            file_put_contents($target, $payload);
        });
    }

    private function installerWithFile(string $source): CatalogInstaller
    {
        return new CatalogInstaller($this->root . '/plugins', $this->root . '/cache', '1.2.0', static function ($url, $target) use ($source): void {
            copy($source, $target);
        });
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) return;
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') continue;
            $child = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($child) && !is_link($child)) $this->removeTree($child);
            else @unlink($child);
        }
        @rmdir($path);
    }
}
