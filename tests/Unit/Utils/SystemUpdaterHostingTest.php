<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use App\Utils\SystemUpdater;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class SystemUpdaterHostingTest extends TestCase
{
    private function property(SystemUpdater $updater, string $name): mixed
    {
        $property = (new ReflectionClass(SystemUpdater::class))->getProperty($name);
        $property->setAccessible(true);
        return $property->getValue($updater);
    }

    private function callPrivate(SystemUpdater $updater, string $method, array $arguments = []): mixed
    {
        $reflection = (new ReflectionClass(SystemUpdater::class))->getMethod($method);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($updater, $arguments);
    }

    #[Test]
    public function uses_current_ignis_repository_and_persistent_storage_cache(): void
    {
        $updater = new SystemUpdater();

        self::assertSame('EmergencyForge/ignis', $this->property($updater, 'githubRepo'));
        self::assertStringEndsWith('/storage/cache/update-check.json', str_replace('\\', '/', (string) $this->property($updater, 'updateCacheFile')));
    }

    #[Test]
    public function separates_stable_and_prerelease_cache_channels(): void
    {
        $updater = new SystemUpdater();

        self::assertSame('stable', $this->callPrivate($updater, 'updateCacheChannel', [false]));
        self::assertSame('prerelease', $this->callPrivate($updater, 'updateCacheChannel', [true]));
    }

    #[Test]
    public function current_and_legacy_release_urls_reach_checksum_validation(): void
    {
        $updater = new SystemUpdater();

        foreach (['ignis', 'intraRP'] as $repository) {
            $result = $updater->downloadAndApplyUpdate(
                "https://github.com/EmergencyForge/{$repository}/releases/download/v1.2.3/ignis-v1.2.3.zip",
                'v1.2.3',
                false,
                'not-a-checksum'
            );

            self::assertFalse($result['success']);
            self::assertStringContainsString('SHA-256', $result['message']);
            self::assertStringNotContainsString('Ungültige Download-URL', $result['message']);
        }
    }

    #[Test]
    public function archive_validation_rejects_parent_directory_entries(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ZipArchive extension is not available.');
        }

        $file = tempnam(sys_get_temp_dir(), 'ignis-updater-zip-');
        self::assertNotFalse($file);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        self::assertTrue($zip->addFromString('../outside.php', '<?php'));
        self::assertTrue($zip->close());
        self::assertTrue($zip->open($file));

        try {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('unsicheren Pfad');
            $this->callPrivate(new SystemUpdater(), 'validateArchiveEntries', [$zip]);
        } finally {
            $zip->close();
            @unlink($file);
        }
    }
}
