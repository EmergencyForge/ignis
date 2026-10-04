<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\ConfigManager;
use PHPUnit\Framework\TestCase;

/**
 * Die Abschnitte der System-Konfiguration stehen in der Reihenfolge von
 * ConfigManager::CATEGORIES, egal wie display_order sie mischt. Eine
 * Kategorie, die der Kern nicht kennt (etwa aus einem Plugin), kommt
 * dahinter und behält ihre Einträge.
 */
final class ConfigCategoryOrderTest extends TestCase
{
    private \ReflectionProperty $cache;

    protected function setUp(): void
    {
        $this->cache = new \ReflectionProperty(ConfigManager::class, 'configCache');
    }

    protected function tearDown(): void
    {
        $this->cache->setValue(null, null);
    }

    public function testKnownCategoriesFirstUnknownAfter(): void
    {
        $this->cache->setValue(null, [
            ['config_key' => 'API_KEY', 'category' => 'technik'],
            ['config_key' => 'ZZ_PLUGIN', 'category' => 'zubehoer'],
            ['config_key' => 'SYSTEM_URL', 'category' => 'adresse'],
            ['config_key' => 'SYSTEM_NAME', 'category' => 'organisation'],
            ['config_key' => 'SERVER_NAME', 'category' => 'adresse'],
        ]);

        $grouped = (new ConfigManager())->getConfigByCategory();

        $this->assertSame(['organisation', 'adresse', 'technik', 'zubehoer'], array_keys($grouped));
        $this->assertSame(['SYSTEM_URL', 'SERVER_NAME'], array_column($grouped['adresse'], 'config_key'));
    }

    public function testDisplayNames(): void
    {
        $manager = new ConfigManager();

        $this->assertSame('Adresse und Anmeldung', $manager->getCategoryDisplayName('adresse'));
        $this->assertSame('fireTab', $manager->getCategoryDisplayName('firetab'));
        $this->assertSame('Zubehoer', $manager->getCategoryDisplayName('zubehoer'));
    }
}
