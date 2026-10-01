<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Updater;

use App\Utils\Updater\UpdateCheckCache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UpdateCheckCacheTest extends TestCase
{
    use TempDirectory;

    private string $file = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTempDir();
        $this->file = $this->tmp . '/cache/update-check.json';
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tmp);
        parent::tearDown();
    }

    #[Test]
    public function keeps_one_entry_per_channel(): void
    {
        $cache = new UpdateCheckCache($this->file);
        self::assertNull($cache->get('stable', 'v2026.0.8'));

        $cache->put('stable', 'v2026.0.8', ['latest_version' => 'v2026.0.9']);
        $cache->put('prerelease', 'v2026.0.8', ['latest_version' => 'v2026.1.0-beta.1']);

        $stable = $cache->get('stable', 'v2026.0.8');
        self::assertIsArray($stable);
        self::assertSame('v2026.0.9', $stable['latest_version']);
        self::assertArrayHasKey('checked_at', $stable);
        self::assertSame('v2026.1.0-beta.1', $cache->get('prerelease', 'v2026.0.8')['latest_version'] ?? null);
    }

    #[Test]
    public function an_entry_ends_after_six_hours_or_with_another_version(): void
    {
        $cache = new UpdateCheckCache($this->file);
        $this->write(['channels' => ['stable' => ['timestamp' => time() - 21601, 'current_version' => 'v1', 'data' => ['x' => 1]]]]);

        self::assertNull($cache->get('stable', 'v1'));
        self::assertNotNull($cache->get('stable', 'v1', true));

        $this->write(['channels' => ['stable' => ['timestamp' => time(), 'current_version' => 'v1', 'data' => ['x' => 1]]]]);
        self::assertNull($cache->get('stable', 'v2'));
        self::assertNotNull($cache->get('stable', null));
    }

    #[Test]
    public function reads_the_format_from_before_channels_and_replaces_it(): void
    {
        $cache = new UpdateCheckCache($this->file);
        $this->write(['timestamp' => time(), 'data' => ['x' => 'alt']]);

        self::assertSame('alt', $cache->get('prerelease', 'v1')['x'] ?? null);

        $cache->put('stable', 'v1', ['x' => 'neu']);
        self::assertSame(['channels'], array_keys((array) json_decode((string) file_get_contents($this->file), true)));
    }

    #[Test]
    public function clearing_deletes_the_file(): void
    {
        $cache = new UpdateCheckCache($this->file);
        self::assertTrue($cache->clear());

        $cache->put('stable', 'v1', []);
        self::assertTrue($cache->clear());
        self::assertFileDoesNotExist($this->file);
    }

    /** @param array<string, mixed> $data */
    private function write(array $data): void
    {
        $this->tree('cache');
        file_put_contents($this->file, json_encode($data));
    }
}
