<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Die Discord-ID an der Tokenzeile: die zentrale Anmeldung prüft sie beim
 * Einlösen, und beim Rückfall über den discordtag trägt das Konto noch keine.
 * Tokens leben 60 Sekunden, alte Zeilen brauchen keinen Wert.
 */
final class TabletLoginDiscordId extends AbstractMigration
{
    public function change(): void
    {
        $this->table('intra_tablet_login_tokens')
            ->addColumn('discord_id', 'string', ['limit' => 20, 'null' => true, 'after' => 'user_id'])
            ->update();
    }
}
