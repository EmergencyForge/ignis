<?php

declare(strict_types=1);

namespace Tests\Unit\Plugins;

use App\Plugins\CatalogClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CatalogClientTest extends TestCase
{
    #[Test]
    public function it_normalizes_and_caches_catalog_entries(): void
    {
        $cache = sys_get_temp_dir() . '/ignis-catalog-' . getmypid() . '.json';
        @unlink($cache);
        $calls = 0;
        $fetcher = static function () use (&$calls): array {
            $calls++;
            return ['status' => 200, 'body' => json_encode(['plugins' => [[
                'id' => 'demo-plugin',
                'name' => 'Demo',
                'tag' => 'v1.2.0',
                'zip_url' => 'https://github.com/example/demo/releases/download/v1.2.0/demo.zip',
                'sha256' => str_repeat('a', 64),
                'status' => 'verified',
            ]]])];
        };

        try {
            $client = new CatalogClient('https://hub.example/v1/plugins', $cache, $fetcher);
            $first = $client->catalog();
            $second = $client->catalog();

            $this->assertSame(1, $calls);
            $this->assertSame('demo-plugin', $first['plugins'][0]['slug']);
            $this->assertSame('1.2.0', $first['plugins'][0]['version']);
            $this->assertTrue($first['plugins'][0]['installable']);
            $this->assertFalse($second['stale']);
        } finally {
            @unlink($cache);
        }
    }

    #[Test]
    public function it_keeps_publisher_and_source_and_treats_only_official_entries_as_first_party(): void
    {
        $cache = sys_get_temp_dir() . '/ignis-catalog-publisher-' . getmypid() . '.json';
        @unlink($cache);
        $fetcher = static fn (): array => ['status' => 200, 'body' => json_encode(['plugins' => [
            ['slug' => 'official', 'name' => 'Offiziell', 'version' => '1.0.0', 'status' => 'official', 'publisher' => 'EmergencyForge'],
            ['slug' => 'checked', 'name' => 'Geprüft', 'version' => '1.0.0', 'status' => 'verified', 'author' => ['name' => 'Jemand'], 'repository' => 'https://github.com/jemand/checked'],
            ['slug' => 'community', 'name' => 'Community', 'version' => '1.0.0', 'repository' => 'javascript:alert(1)'],
        ]])];

        try {
            $plugins = (new CatalogClient('https://hub.example/v1/plugins', $cache, $fetcher))->catalog()['plugins'];

            $this->assertSame('EmergencyForge', $plugins[0]['publisher']);
            $this->assertFalse(CatalogClient::isThirdParty($plugins[0]));
            $this->assertSame('Jemand', $plugins[1]['publisher']);
            $this->assertSame('https://github.com/jemand/checked', $plugins[1]['source_url']);
            $this->assertTrue(CatalogClient::isThirdParty($plugins[1]), 'Geprüft heißt nicht, dass der Code von EmergencyForge stammt.');
            $this->assertSame('', $plugins[2]['source_url']);
            $this->assertSame('untested', $plugins[2]['trust']);
            $this->assertTrue(CatalogClient::isThirdParty($plugins[2]));
        } finally {
            @unlink($cache);
        }
    }

    #[Test]
    public function it_uses_stale_cache_when_refresh_fails(): void
    {
        $cache = sys_get_temp_dir() . '/ignis-catalog-stale-' . getmypid() . '.json';
        file_put_contents($cache, json_encode([
            'timestamp' => time() - CatalogClient::CACHE_TTL - 1,
            'plugins' => [['slug' => 'cached', 'name' => 'Cached', 'version' => '1.0.0']],
        ]));
        try {
            $client = new CatalogClient('https://hub.example/v1/plugins', $cache, static fn () => null);
            $result = $client->catalog();
            $this->assertTrue($result['stale']);
            $this->assertSame('cached', $result['plugins'][0]['slug']);
        } finally {
            @unlink($cache);
        }
    }
}
