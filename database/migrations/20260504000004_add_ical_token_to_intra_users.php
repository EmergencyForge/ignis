<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Persoenlicher iCal-Token pro User — wird beim ersten Aufruf der
 * "Kalender abonnieren"-Funktion in der Calendar-Page gesetzt. Externe
 * Kalender-Apps (Google, Apple, Outlook) abonnieren via
 *   GET /api/kalender/ical/{token}
 * Cookie-Auth funktioniert dort nicht — der Token ist die einzige
 * Authentifizierung. Daher unique + zufaellig genug (32+ Zeichen Hex).
 */
class AddIcalTokenToIntraUsers extends AbstractMigration
{
    public function change(): void
    {
        $users = $this->table('intra_users');
        if (!$users->hasColumn('ical_token')) {
            $users
                ->addColumn('ical_token', 'string', [
                    'limit'   => 64,
                    'null'    => true,
                    'default' => null,
                    'after'   => 'discord_id',
                ])
                ->addIndex(['ical_token'], ['unique' => true, 'name' => 'uniq_users_ical_token'])
                ->update();
        }
    }
}
