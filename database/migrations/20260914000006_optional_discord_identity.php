<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class OptionalDiscordIdentity extends AbstractMigration
{
    public function up(): void
    {
        $this->table('intra_users')->changeColumn('discord_id', 'string', ['limit' => 255, 'null' => true])->update();
    }

    public function down(): void
    {
        if ($this->fetchRow('SELECT id FROM intra_users WHERE discord_id IS NULL LIMIT 1') !== false) {
            throw new RuntimeException('Sync-Konten ohne Discord-ID verhindern das Zurücknehmen dieser Migration.');
        }
        $this->table('intra_users')->changeColumn('discord_id', 'string', ['limit' => 255, 'null' => false])->update();
    }
}
