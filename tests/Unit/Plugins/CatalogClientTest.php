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
