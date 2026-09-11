<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * JSON-Spalte für die persönliche Theme-Konfiguration der Benutzer
 * (Farbschema, Akzentfarben etc.). NULL = Systemstandard.
 */
class AlterIntraUsers09032026Theme extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_users');

        if (!$table->hasColumn('theme_config')) {
            $table->addColumn('theme_config', 'json', [
                'null'    => true,
                'default' => null,
            ])->update();
        }
    }
}
