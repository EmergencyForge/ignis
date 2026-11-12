<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Merkt an der Einladung, wann der Discord-Bot sie zuletzt zugestellt hat
 * (App\Discord\InvitationDelivery).
 */
final class RegistrationCodeDiscordSent extends AbstractMigration
{
    public function change(): void
    {
        $this->table('intra_registration_codes')
            ->addColumn('discord_sent_at', 'datetime', ['null' => true, 'after' => 'expires_at'])
            ->update();
    }
}
