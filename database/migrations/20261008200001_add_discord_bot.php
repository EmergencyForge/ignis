<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Der eigene Discord-Bot (App\Discord\DiscordBot).
 *
 * intra_config, Kategorie `discord`, nicht editierbar: die allgemeine
 * System-Konfiguration würde das Token im Klartext zeigen und nichts
 * prüfen. Bearbeitet wird alles unter Einstellungen › System › Discord-Bot.
 *
 * - DISCORD_BOT_ENABLED: schickt der Bot Nachrichten?
 * - DISCORD_BOT_TOKEN: Bot-Token aus dem Discord Developer Portal.
 * - DISCORD_BOT_ID, _NAME, _AVATAR: was Discord zuletzt über den Bot meldete.
 * - DISCORD_BOT_DM_TYPES: Benachrichtigungstypen, die auch per DM gehen.
 *
 * intra_users.discord_dm: jeder kann die DMs für sich abschalten.
 */
final class AddDiscordBot extends AbstractMigration
{
    /** @return list<array<string,mixed>> */
    private function rows(): array
    {
        return [
            ['config_key' => 'DISCORD_BOT_ENABLED', 'config_value' => 'false', 'config_type' => 'boolean', 'description' => 'Discord-Bot aktiv', 'display_order' => 1],
            ['config_key' => 'DISCORD_BOT_TOKEN', 'config_value' => '', 'config_type' => 'string', 'description' => 'Bot-Token', 'display_order' => 2],
            ['config_key' => 'DISCORD_BOT_ID', 'config_value' => '', 'config_type' => 'string', 'description' => 'ID des Bots', 'display_order' => 3],
            ['config_key' => 'DISCORD_BOT_NAME', 'config_value' => '', 'config_type' => 'string', 'description' => 'Name des Bots', 'display_order' => 4],
            ['config_key' => 'DISCORD_BOT_AVATAR', 'config_value' => '', 'config_type' => 'string', 'description' => 'Profilbild des Bots', 'display_order' => 5],
            ['config_key' => 'DISCORD_BOT_DM_TYPES', 'config_value' => 'mail,system', 'config_type' => 'string', 'description' => 'Benachrichtigungen per Direktnachricht', 'display_order' => 6],
        ];
    }

    public function up(): void
    {
        foreach ($this->rows() as $row) {
            if (is_array($this->fetchRow("SELECT 1 FROM intra_config WHERE config_key = '" . $row['config_key'] . "'"))) {
                continue;
            }
            $this->table('intra_config')->insert([$row + [
                'category'    => 'discord',
                'is_editable' => 0,
            ]])->saveData();
        }

        $users = $this->table('intra_users');
        if (!$users->hasColumn('discord_dm')) {
            $users->addColumn('discord_dm', 'boolean', [
                'default' => true,
                'null'    => false,
                'after'   => 'theme',
            ])->update();
        }
    }

    public function down(): void
    {
        $this->execute("DELETE FROM intra_config WHERE config_key LIKE 'DISCORD\\_BOT\\_%'");

        $users = $this->table('intra_users');
        if ($users->hasColumn('discord_dm')) {
            $users->removeColumn('discord_dm')->update();
        }
    }
}
