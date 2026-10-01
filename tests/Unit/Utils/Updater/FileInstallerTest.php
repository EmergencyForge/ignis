<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Updater;

use App\Utils\Updater\FileInstaller;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FileInstallerTest extends TestCase
{
    private string $tempRoot = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir() . '/intrarp-updater-test-' . bin2hex(random_bytes(6));
        mkdir($this->tempRoot, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempRoot)) {
            $this->rmrf($this->tempRoot);
        }
        parent::tearDown();
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            if (file_exists($dir)) {
                @unlink($dir);
            }
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->rmrf($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private function makeInstaller(): FileInstaller
    {
        return new FileInstaller($this->tempRoot);
    }

    // ── validateManifestDeletePath ──────────────────────────────────────

    #[Test]
    public function rejects_traversal_and_absolute_paths(): void
    {
        $u = $this->makeInstaller();
        $bad = ['', '/', '.', '..', '../foo', 'foo/../bar', '/etc/passwd', 'C:\\Windows', "foo\0bar"];
        foreach ($bad as $path) {
            $this->assertNull(
                $u->validateManifestDeletePath($path),
                "Expected null for: $path"
            );
        }
    }

    #[Test]
    public function rejects_protected_system_paths(): void
    {
        $u = $this->makeInstaller();
        // Diese Verzeichnisse müssen existieren, damit realpath() greift
        foreach (['storage', 'system', 'vendor', '.git'] as $d) {
            mkdir($this->tempRoot . '/' . $d, 0755, true);
        }
        file_put_contents($this->tempRoot . '/.env', 'FOO=bar');
        file_put_contents($this->tempRoot . '/composer.json', '{}');
        mkdir($this->tempRoot . '/public', 0755, true);
        file_put_contents($this->tempRoot . '/public/index.php', '<?php');

        $protected = [
            'storage', 'storage/logs', 'storage/documents',
            'system', 'system/updates',
            'vendor', 'vendor/autoload.php',
            '.git', '.git/config',
            '.env',
            'composer.json',
            'public/index.php',
        ];
        foreach ($protected as $path) {
            $this->assertNull(
                $u->validateManifestDeletePath($path),
                "Expected protected path to be rejected: $path"
            );
        }
    }

    #[Test]
    public function accepts_ordinary_module_directory(): void
    {
        $u = $this->makeInstaller();
        mkdir($this->tempRoot . '/enotf', 0755, true);

        $normalized = $u->validateManifestDeletePath('enotf');
        $this->assertSame('enotf', $normalized);
    }

    #[Test]
    public function normalizes_trailing_slash_and_backslashes(): void
    {
        $u = $this->makeInstaller();
        mkdir($this->tempRoot . '/mitarbeiter', 0755, true);

        $normalized = $u->validateManifestDeletePath('mitarbeiter/');
        $this->assertSame('mitarbeiter', $normalized);
    }

    // ── applyUpdateManifest ─────────────────────────────────────────────

    /** @param array<string, mixed> $data */
    private function writeManifest(string $sourceDir, array $data): void
    {
        if (!is_dir($sourceDir)) {
            mkdir($sourceDir, 0755, true);
        }
        file_put_contents($sourceDir . '/update-manifest.json', json_encode($data));
    }

    #[Test]
    public function missing_manifest_is_noop(): void
    {
        $u = $this->makeInstaller();
        $source = $this->tempRoot . '/source';
        mkdir($source, 0755, true);

        $result = $u->applyManifest($source);
        $this->assertFalse($result['applied']);
        $this->assertEmpty($result['deleted']);
    }

    #[Test]
    public function invalid_json_is_reported_as_error(): void
    {
        $u = $this->makeInstaller();
        $source = $this->tempRoot . '/source';
        mkdir($source, 0755, true);
        file_put_contents($source . '/update-manifest.json', 'not-json');

        $result = $u->applyManifest($source);
        $this->assertFalse($result['applied']);
        $this->assertArrayHasKey('error', $result);
    }

    #[Test]
    public function deletes_declared_module_directories(): void
    {
        $u = $this->makeInstaller();
        $source = $this->tempRoot . '/source';
        mkdir($this->tempRoot . '/enotf/protokoll', 0755, true);
        mkdir($this->tempRoot . '/einsatz', 0755, true);
        file_put_contents($this->tempRoot . '/enotf/index.php', '<?php');
        file_put_contents($this->tempRoot . '/enotf/protokoll/a.php', '<?php');

        $this->writeManifest($source, [
            'version'      => '2.0.0',
            'delete_paths' => ['enotf', 'einsatz', 'never-existed'],
        ]);

        $result = $u->applyManifest($source);

        $this->assertTrue($result['applied']);
        $this->assertSame(['enotf', 'einsatz'], $result['deleted']);
        $this->assertCount(1, $result['skipped']);
        $this->assertSame('never-existed', $result['skipped'][0]['path']);
        $this->assertFalse(is_dir($this->tempRoot . '/enotf'));
        $this->assertFalse(is_dir($this->tempRoot . '/einsatz'));
    }

    #[Test]
    public function refuses_to_delete_protected_paths_even_if_listed(): void
    {
        $u = $this->makeInstaller();
        $source = $this->tempRoot . '/source';
        mkdir($this->tempRoot . '/storage/logs', 0755, true);
        mkdir($this->tempRoot . '/vendor', 0755, true);
        file_put_contents($this->tempRoot . '/.env', 'KEY=secret');
        file_put_contents($this->tempRoot . '/composer.json', '{}');

        $this->writeManifest($source, [
            'delete_paths' => ['storage', 'vendor', '.env', 'composer.json', '../outside'],
        ]);

        $result = $u->applyManifest($source);

        $this->assertTrue($result['applied']);
        $this->assertEmpty($result['deleted']);
        $this->assertCount(5, $result['skipped']);
        // Kritische Pfade müssen noch da sein
        $this->assertTrue(is_dir($this->tempRoot . '/storage'));
        $this->assertTrue(is_dir($this->tempRoot . '/vendor'));
        $this->assertTrue(is_file($this->tempRoot . '/.env'));
        $this->assertTrue(is_file($this->tempRoot . '/composer.json'));
    }

    #[Test]
    public function non_array_delete_paths_is_rejected(): void
    {
        $u = $this->makeInstaller();
        $source = $this->tempRoot . '/source';
        $this->writeManifest($source, ['delete_paths' => 'enotf']);

        $result = $u->applyManifest($source);

        $this->assertFalse($result['applied']);
        $this->assertArrayHasKey('error', $result);
    }

    #[Test]
    public function deletes_single_file_not_just_dirs(): void
    {
        $u = $this->makeInstaller();
        $source = $this->tempRoot . '/source';
        file_put_contents($this->tempRoot . '/obsolete-script.php', '<?php');

        $this->writeManifest($source, ['delete_paths' => ['obsolete-script.php']]);

        $result = $u->applyManifest($source);

        $this->assertContains('obsolete-script.php', $result['deleted']);
        $this->assertFalse(is_file($this->tempRoot . '/obsolete-script.php'));
    }

    // ── copy ────────────────────────────────────────────────────────────

    /** @param array<string, string> $files */
    private function files(string $dir, array $files): string
    {
        foreach ($files as $path => $content) {
            if (!is_dir(dirname($dir . '/' . $path))) {
                mkdir(dirname($dir . '/' . $path), 0755, true);
            }
            file_put_contents($dir . '/' . $path, $content);
        }

        return $dir;
    }

    #[Test]
    public function copies_the_release_over_the_installation(): void
    {
        $source = $this->files($this->tempRoot . '/source', [
            'index.php' => 'neu',
            'composer.json' => '{"neu":true}',
            'src/Deep/New.php' => 'neu',
            'assets/img/logo.png' => 'standard',
            'assets/img/neu.png' => 'neu',
            'vendor/lib.php' => 'neu',
            'storage/x.json' => 'neu',
            'system/updates/y' => 'neu',
            '.env' => 'neu',
            'nested/.git' => 'neu',
        ]);
        $app = $this->files($this->tempRoot . '/app', [
            'index.php' => 'alt',
            'composer.json' => '{}',
            'assets/img/logo.png' => 'eigenes Logo',
            'vendor/lib.php' => 'alt',
            '.env' => 'geheim',
        ]);

        (new FileInstaller($app))->copy($source, ['vendor', 'storage', 'system/updates'], ['.env', '.git', '.gitignore'], ['assets/img']);

        self::assertSame('neu', file_get_contents($app . '/index.php'));
        self::assertSame('{"neu":true}', file_get_contents($app . '/composer.json'));
        self::assertSame('neu', file_get_contents($app . '/src/Deep/New.php'));
        self::assertSame('eigenes Logo', file_get_contents($app . '/assets/img/logo.png'));
        self::assertSame('neu', file_get_contents($app . '/assets/img/neu.png'));
        self::assertSame('alt', file_get_contents($app . '/vendor/lib.php'));
        self::assertSame('geheim', file_get_contents($app . '/.env'));
        self::assertDirectoryDoesNotExist($app . '/storage');
        self::assertDirectoryDoesNotExist($app . '/system/updates');
        self::assertFileDoesNotExist($app . '/nested/.git');
    }

    #[Test]
    public function a_file_that_cannot_be_written_stops_the_copy(): void
    {
        $source = $this->files($this->tempRoot . '/source', ['src/A.php' => 'neu']);
        mkdir($this->tempRoot . '/app/src/A.php', 0755, true);

        set_error_handler(static fn (): bool => true);
        try {
            (new FileInstaller($this->tempRoot . '/app'))->copy($source, [], [], []);
            self::fail('Kopieren hätte scheitern müssen.');
        } catch (\Exception $error) {
            self::assertSame('Konnte Datei nicht kopieren: src/A.php', $error->getMessage());
        } finally {
            restore_error_handler();
        }
    }

    #[Test]
    public function excludes_directories_by_prefix_and_files_by_name(): void
    {
        $dirs = ['vendor', 'system/updates/'];
        $files = ['.env', '.gitignore'];

        self::assertTrue(FileInstaller::isExcluded('vendor', $dirs, $files));
        self::assertTrue(FileInstaller::isExcluded('vendor/autoload.php', $dirs, $files));
        self::assertTrue(FileInstaller::isExcluded('system/updates/x', $dirs, $files));
        self::assertTrue(FileInstaller::isExcluded('deep/dir/.gitignore', $dirs, $files));
        self::assertFalse(FileInstaller::isExcluded('vendors/x.php', $dirs, $files));
        self::assertFalse(FileInstaller::isExcluded('system/x', $dirs, $files));
        self::assertFalse(FileInstaller::isExcluded('.env.example', $dirs, $files));
    }

    #[Test]
    public function deletes_a_directory_tree(): void
    {
        $this->files($this->tempRoot . '/gone', ['a/b/c.txt' => 'x', 'd.txt' => 'x']);

        FileInstaller::deleteTree($this->tempRoot . '/gone');
        FileInstaller::deleteTree($this->tempRoot . '/never-existed');

        self::assertDirectoryDoesNotExist($this->tempRoot . '/gone');
    }
}
