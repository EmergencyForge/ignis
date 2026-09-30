<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Updater;

use App\Utils\Updater\VersionStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VersionStoreTest extends TestCase
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
    public function writes_and_reads_back_version_json(): void
    {
        $file = $this->tmp . '/storage/version.json';
        $store = new VersionStore($file);
        self::assertSame('v0.5.0', $store->current()['version']);

        $data = ['version' => 'v2026.0.9', 'updated_at' => date('Y-m-d H:i:s', time() - 3 * 86400 - 60), 'build_number' => 13];
        self::assertTrue($store->write($data));

        self::assertSame($data, $store->current());
        self::assertSame($data, (new VersionStore($file))->current());
        self::assertSame(3, $store->ageInDays());
    }

    #[Test]
    public function a_failed_write_is_reported_and_keeps_the_old_version(): void
    {
        $file = $this->tmp . '/version.json';
        $store = new VersionStore($file);
        mkdir($file);

        set_error_handler(static fn (): bool => true);
        try {
            $written = $store->write(['version' => 'v2026.0.9']);
        } finally {
            restore_error_handler();
        }

        self::assertFalse($written);
        self::assertSame('v0.5.0', $store->current()['version']);
    }

    #[Test]
    public function the_prerelease_flag_wins_over_the_name(): void
    {
        $file = $this->tree('storage') . '/version.json';

        file_put_contents($file, '{"version":"v2026.1.0-beta.1","prerelease":false}');
        self::assertFalse((new VersionStore($file))->isPreRelease());

        file_put_contents($file, '{"version":"v2026.1.0-beta.1"}');
        self::assertTrue((new VersionStore($file))->isPreRelease());
        self::assertSame(0, (new VersionStore($file))->ageInDays());
    }
}
