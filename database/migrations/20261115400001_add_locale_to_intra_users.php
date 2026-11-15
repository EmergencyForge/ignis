<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Sprache pro Konto als BCP-47-Tag (de, en-GB). null heißt Sprache der
 * Installation (APP_LOCALE), damit ein späterer Wechsel dort alle Konten
 * mitnimmt, die nie selbst gewählt haben. Gesetzt über POST /profile/locale.
 */
final class AddLocaleToIntraUsers extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_users');

        if (!$table->hasColumn('locale')) {
            $table->addColumn('locale', 'string', [
                'limit'   => 10,
                'null'    => true,
                'default' => null,
                'after'   => 'theme',
            ])->update();
        }
    }
}
