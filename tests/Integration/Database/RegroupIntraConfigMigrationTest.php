<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Config\ConfigManager;
use Phinx\Db\Adapter\MysqlAdapter;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

/**
 * Migration 20261004100001: ordnet intra_config in die neun Abschnitte der
 * System-Konfiguration und legt die Spalte hint an. Der Test fährt die
 * Migration auf der Test-Datenbank zurück und wieder vor, ohne phinxlog
 * anzufassen. ALTER TABLE beendet jede offene Transaktion, deshalb ohne
 * Transaktion; am Ende steht immer der Stand nach up().
 */
final class RegroupIntraConfigMigrationTest extends IntegrationTestCase
{
    protected bool $useTransactions = false;

    private function migration(): \RegroupIntraConfig
    {
        require_once dirname(__DIR__, 3) . '/database/migrations/20261004100001_regroup_intra_config.php';

        $adapter = new MysqlAdapter(['name' => (string) $_ENV['DB_NAME']]);
        $adapter->setConnection($this->pdo);

        $migration = new \RegroupIntraConfig('test', 20261004100001);
        $migration->setAdapter($adapter);

        return $migration;
    }

    private function category(string $key): ?string
    {
        $value = $this->pdo->query("SELECT category FROM intra_config WHERE config_key = " . $this->pdo->quote($key))->fetchColumn();

        return $value === false ? null : (string) $value;
    }

    private function hasHint(): bool
    {
        return $this->pdo->query("SHOW COLUMNS FROM intra_config LIKE 'hint'")->fetch() !== false;
    }

    #[Test]
    public function jeder_sichtbare_schluessel_liegt_in_einem_bekannten_abschnitt(): void
    {
        $this->migration()->up();

        // Was die Konfigurationsseite zeigt: editierbar, dazu API-Schlüssel und Installations-ID.
        $rows = $this->pdo->query(
            "SELECT config_key, category FROM intra_config WHERE (is_editable = 1 OR config_key IN ('API_KEY', 'INSTALLATION_ID')) AND category NOT IN ('mail', 'telemetrie')"
        )->fetchAll(\PDO::FETCH_KEY_PAIR);

        $this->assertNotEmpty($rows);
        foreach ($rows as $key => $category) {
            $this->assertArrayHasKey($category, ConfigManager::CATEGORIES, "$key liegt in der unbekannten Kategorie $category.");
        }
        $this->assertSame('adresse', $this->category('SYSTEM_URL'));
        $this->assertSame('enotf', $this->category('ENOTF_PIN'));
        $this->assertSame('technik', $this->category('API_KEY'));
        $this->assertTrue($this->hasHint());
    }

    #[Test]
    public function down_stellt_den_alten_stand_her_und_up_ist_wiederholbar(): void
    {
        $migration = $this->migration();
        try {
            $migration->down();
            $this->assertFalse($this->hasHint());
            $this->assertSame('basis', $this->category('SYSTEM_URL'));
            $this->assertSame('funktionen', $this->category('REGISTRATION_MODE'));
            $this->assertSame('integrationen', $this->category('DISCORD_WEBHOOK_ENOTF_PREREG'));
        } finally {
            $migration->up();
        }

        $migration->up();
        $this->assertTrue($this->hasHint());
        $this->assertSame('adresse', $this->category('REGISTRATION_MODE'));
        $this->assertSame('webhooks', $this->category('DISCORD_WEBHOOK_ENOTF_PREREG'));
        $label = $this->pdo->query("SELECT description FROM intra_config WHERE config_key = 'REGISTRATION_MODE'")->fetchColumn();
        $this->assertSame('Registrierung', $label);
    }
}
