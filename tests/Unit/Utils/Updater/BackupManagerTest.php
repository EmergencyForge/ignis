<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Updater;

use App\Utils\Updater\BackupManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BackupManagerTest extends TestCase
{
    use TempDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTempDir();
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tmp);
        parent::tearDown();
    }

    #[Test]
    public function backs_up_every_file_the_release_would_overwrite(): void
    {
        $source = $this->tree('source', [
            'index.php' => 'neu',
            'src/Keep.php' => 'neu',
            'src/Created.php' => 'neu',
            'vendor/lib.php' => 'neu',
            'storage/x.json' => 'neu',
            '.env' => 'neu',
            'sub/.gitignore' => 'neu',
        ]);
        $app = $this->tree('app', [
            'index.php' => 'alt',
            'src/Keep.php' => 'alt',
            'vendor/lib.php' => 'alt',
            'storage/x.json' => 'alt',
            'storage/version.json' => '{"version":"v2026.0.8"}',
            '.env' => 'alt',
            'sub/.gitignore' => 'alt',
        ]);
        $backups = new BackupManager($app);

        $backup = $backups->reserve();
        $summary = $backups->backUp($backup, $source, ['vendor', 'storage', 'system/updates'], ['.env', '.git', '.gitignore'], $app . '/storage/version.json');

        self::assertMatchesRegularExpression('#^' . preg_quote($app, '#') . '/storage/backups/updates/backup_\d{4}-\d\d-\d\d_\d\d-\d\d-\d\d$#', $backup);
        self::assertSame(2, $summary['backed_up_files']);
        self::assertSame(['src/Created.php'], $summary['created_files']);
        self::assertSame('alt', file_get_contents($backup . '/index.php'));
        self::assertSame('alt', file_get_contents($backup . '/src/Keep.php'));
        self::assertSame('{"version":"v2026.0.8"}', file_get_contents($backup . '/storage/version.json'));
        self::assertFileDoesNotExist($backup . '/vendor/lib.php');
        self::assertFileDoesNotExist($backup . '/storage/x.json');
        self::assertFileDoesNotExist($backup . '/.env');
        $manifest = json_decode((string) file_get_contents($backup . '/_update-backup.json'), true);
        self::assertIsArray($manifest);
        self::assertSame(['created_at', 'backed_up_files', 'created_files'], array_keys($manifest));
        self::assertSame(2, $manifest['backed_up_files']);
    }

    #[Test]
    public function reserving_only_creates_the_base_directory(): void
    {
        $app = $this->tree('app');

        $backup = (new BackupManager($app))->reserve();

        self::assertDirectoryExists($app . '/storage/backups/updates');
        self::assertDirectoryDoesNotExist($backup);
    }
}
