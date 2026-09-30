<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use App\Utils\SystemUpdater;
use App\Utils\Updater\GitHubReleaseSource;
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

        self::assertSame('https://api.github.com/repos/EmergencyForge/ignis', (new GitHubReleaseSource())->apiUrl());
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
}
