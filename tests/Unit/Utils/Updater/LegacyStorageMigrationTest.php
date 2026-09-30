<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Updater;

use App\Utils\Updater\LegacyStorageMigration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LegacyStorageMigrationTest extends TestCase
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
    public function moves_files_without_overwriting_and_removes_the_empty_directory(): void
    {
        $this->tree('system/updates', ['a.json' => 'alt a', 'b.json' => 'alt b']);
        $this->tree('storage', ['a.json' => 'neu a']);

        LegacyStorageMigration::run($this->tmp, [
            '/a.json' => $this->tmp . '/storage/a.json',
            '/b.json' => $this->tmp . '/storage/sub/b.json',
            '/c.json' => $this->tmp . '/storage/c.json',
        ]);

        self::assertSame('neu a', file_get_contents($this->tmp . '/storage/a.json'));
        self::assertSame('alt b', file_get_contents($this->tmp . '/storage/sub/b.json'));
        self::assertFileDoesNotExist($this->tmp . '/storage/c.json');
        self::assertDirectoryDoesNotExist($this->tmp . '/system');
    }

    #[Test]
    public function leaves_other_files_in_place(): void
    {
        $this->tree('system/updates', ['a.json' => 'alt', 'backup_1/x.php' => 'x']);

        LegacyStorageMigration::run($this->tmp, ['/a.json' => $this->tmp . '/storage/a.json']);

        self::assertFileExists($this->tmp . '/storage/a.json');
        self::assertFileDoesNotExist($this->tmp . '/system/updates/a.json');
        self::assertFileExists($this->tmp . '/system/updates/backup_1/x.php');
    }
}
