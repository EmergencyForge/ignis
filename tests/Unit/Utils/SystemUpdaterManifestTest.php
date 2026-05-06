<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use App\Utils\SystemUpdater;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class SystemUpdaterManifestTest extends TestCase
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

    private function callPrivate(SystemUpdater $updater, string $method, array $args): mixed
    {
        $r = (new ReflectionClass(SystemUpdater::class))->getMethod($method);
        $r->setAccessible(true);
        return $r->invokeArgs($updater, $args);
    }

    private function makeUpdater(): SystemUpdater
    {
        return new SystemUpdater();
    }

    // ── validateManifestDeletePath ──────────────────────────────────────

    #[Test]
    public function rejects_traversal_and_absolute_paths(): void
    {
        $u = $this->makeUpdater();
        $bad = ['', '/', '.', '..', '../foo', 'foo/../bar', '/etc/passwd', 'C:\\Windows', "foo\0bar"];
        foreach ($bad as $path) {
            $this->assertNull(
                $this->callPrivate($u, 'validateManifestDeletePath', [$path, $this->tempRoot]),
                "Expected null for: $path"
            );
        }
    }

    #[Test]
    public function rejects_protected_system_paths(): void
    {
        $u = $this->makeUpdater();
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
                $this->callPrivate($u, 'validateManifestDeletePath', [$path, $this->tempRoot]),
                "Expected protected path to be rejected: $path"
            );
        }
    }

    #[Test]
    public function accepts_ordinary_module_directory(): void
    {
        $u = $this->makeUpdater();
        mkdir($this->tempRoot . '/enotf', 0755, true);

        $normalized = $this->callPrivate($u, 'validateManifestDeletePath', ['enotf', $this->tempRoot]);
        $this->assertSame('enotf', $normalized);
    }

    #[Test]
    public function normalizes_trailing_slash_and_backslashes(): void
    {
        $u = $this->makeUpdater();
        mkdir($this->tempRoot . '/mitarbeiter', 0755, true);

        $normalized = $this->callPrivate($u, 'validateManifestDeletePath', ['mitarbeiter/', $this->tempRoot]);
        $this->assertSame('mitarbeiter', $normalized);
    }

    // ── applyUpdateManifest ─────────────────────────────────────────────

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
        $u = $this->makeUpdater();
        $source = $this->tempRoot . '/source';
        mkdir($source, 0755, true);

        $result = $this->callPrivate($u, 'applyUpdateManifest', [$source, $this->tempRoot]);
        $this->assertFalse($result['applied']);
        $this->assertEmpty($result['deleted']);
    }

    #[Test]
    public function invalid_json_is_reported_as_error(): void
    {
        $u = $this->makeUpdater();
        $source = $this->tempRoot . '/source';
        mkdir($source, 0755, true);
        file_put_contents($source . '/update-manifest.json', 'not-json');

        $result = $this->callPrivate($u, 'applyUpdateManifest', [$source, $this->tempRoot]);
        $this->assertFalse($result['applied']);
        $this->assertArrayHasKey('error', $result);
    }

    #[Test]
    public function deletes_declared_module_directories(): void
    {
        $u = $this->makeUpdater();
        $source = $this->tempRoot . '/source';
        mkdir($this->tempRoot . '/enotf/protokoll', 0755, true);
        mkdir($this->tempRoot . '/einsatz', 0755, true);
        file_put_contents($this->tempRoot . '/enotf/index.php', '<?php');
        file_put_contents($this->tempRoot . '/enotf/protokoll/a.php', '<?php');

        $this->writeManifest($source, [
            'version'      => '2.0.0',
            'delete_paths' => ['enotf', 'einsatz', 'never-existed'],
        ]);

        $result = $this->callPrivate($u, 'applyUpdateManifest', [$source, $this->tempRoot]);

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
        $u = $this->makeUpdater();
        $source = $this->tempRoot . '/source';
        mkdir($this->tempRoot . '/storage/logs', 0755, true);
        mkdir($this->tempRoot . '/vendor', 0755, true);
        file_put_contents($this->tempRoot . '/.env', 'KEY=secret');
        file_put_contents($this->tempRoot . '/composer.json', '{}');

        $this->writeManifest($source, [
            'delete_paths' => ['storage', 'vendor', '.env', 'composer.json', '../outside'],
        ]);

        $result = $this->callPrivate($u, 'applyUpdateManifest', [$source, $this->tempRoot]);

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
        $u = $this->makeUpdater();
        $source = $this->tempRoot . '/source';
        $this->writeManifest($source, ['delete_paths' => 'enotf']);

        $result = $this->callPrivate($u, 'applyUpdateManifest', [$source, $this->tempRoot]);

        $this->assertFalse($result['applied']);
        $this->assertArrayHasKey('error', $result);
    }

    #[Test]
    public function deletes_single_file_not_just_dirs(): void
    {
        $u = $this->makeUpdater();
        $source = $this->tempRoot . '/source';
        file_put_contents($this->tempRoot . '/obsolete-script.php', '<?php');

        $this->writeManifest($source, ['delete_paths' => ['obsolete-script.php']]);

        $result = $this->callPrivate($u, 'applyUpdateManifest', [$source, $this->tempRoot]);

        $this->assertContains('obsolete-script.php', $result['deleted']);
        $this->assertFalse(is_file($this->tempRoot . '/obsolete-script.php'));
    }
}
