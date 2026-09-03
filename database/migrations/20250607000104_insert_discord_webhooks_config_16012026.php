<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Legt drei Config-Einträge für Discord-Webhooks an: freigegebene
 * eNOTF-Protokolle, freigegebene Feuerwehr-Protokolle (fireTab) und neue
 * eNOTF-Voranmeldungen. Die URLs sind initial leer und werden vom Admin in
 * den Einstellungen gepflegt.
 */
class InsertDiscordWebhooksConfig16012026 extends AbstractMigration
{
    private const CONFIGS = [
        [
            'config_key'    => 'DISCORD_WEBHOOK_ENOTF_PROTOCOL',
            'config_value'  => '',
            'config_type'   => 'string',
            'category'      => 'integrationen',
            'description'   => 'Discord Webhook URL für freigegebene eNOTF Protokolle',
            'is_editable'   => 1,
            'display_order' => 100,
        ],
        [
            'config_key'    => 'DISCORD_WEBHOOK_FIRE_PROTOCOL',
            'config_value'  => '',
            'config_type'   => 'string',
            'category'      => 'integrationen',
            'description'   => 'Discord Webhook URL für freigegebene Feuerwehr (fireTab) Protokolle',
            'is_editable'   => 1,
            'display_order' => 101,
        ],
        [
            'config_key'    => 'DISCORD_WEBHOOK_ENOTF_PREREG',
            'config_value'  => '',
            'config_type'   => 'string',
            'category'      => 'integrationen',
            'description'   => 'Discord Webhook URL für neue Voranmeldungen im eNOTF',
            'is_editable'   => 1,
            'display_order' => 102,
        ],
    ];

    public function up(): void
    {
        $rows = array_filter(self::CONFIGS, function (array $row): bool {
            return $this->fetchRow(
                "SELECT id FROM intra_config WHERE config_key = '{$row['config_key']}'"
            ) === false;
        });

        if ($rows !== []) {
            $this->table('intra_config')->insert(array_values($rows))->saveData();
        }
    }

    public function down(): void
    {
        $keys = array_map(
            static fn(array $row): string => "'" . $row['config_key'] . "'",
            self::CONFIGS
        );
        $this->execute('DELETE FROM intra_config WHERE config_key IN (' . implode(', ', $keys) . ')');
    }
}
