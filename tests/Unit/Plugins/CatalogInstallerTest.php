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

    /** @return array<string,mixed> */
    private function entry(string $hash): array
    {
        return [
            'slug' => 'demo',
            'zip_url' => 'https://github.com/example/demo/releases/download/v1/demo.zip',
            'sha256' => $hash,
        ];
    }

    private function manifest(string $id): string
    {
        return "<?php return ['id'=>" . var_export($id, true) . ",'name'=>'Demo','version'=>'1.0.0','requires'=>['ignis'=>'>=1.0']];";
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
