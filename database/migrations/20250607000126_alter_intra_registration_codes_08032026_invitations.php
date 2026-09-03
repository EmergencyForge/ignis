<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Erweitert Registrierungscodes um Einladungsfeatures:
 * - label: Optionaler Name/Beschreibung (z.B. "Einladung für Max")
 * - expires_at: Optionales Ablaufdatum
 */
class AlterIntraRegistrationCodes08032026Invitations extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_registration_codes');

        if (!$table->hasColumn('label')) {
            $table->addColumn('label', 'string', [
                'limit'   => 255,
                'null'    => true,
                'default' => null,
                'after'   => 'code',
            ]);
        }
        if (!$table->hasColumn('expires_at')) {
            $table->addColumn('expires_at', 'datetime', [
                'null'    => true,
                'default' => null,
                'after'   => 'used_at',
            ]);
        }

        $table->update();
    }
}
