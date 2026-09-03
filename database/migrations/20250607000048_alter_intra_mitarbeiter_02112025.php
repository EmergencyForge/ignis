<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Profilbild für Mitarbeiter: intra_mitarbeiter bekommt die Spalte `pfp`
 * (Pfad zum hochgeladenen Bild).
 */
class AlterIntraMitarbeiter02112025 extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_mitarbeiter');

        if ($table->hasColumn('pfp')) {
            return;
        }

        $table
            ->addColumn('pfp', 'string', [
                'limit'   => 255,
                'null'    => true,
                'default' => null,
                'after'   => 'fachdienste',
            ])
            ->update();
    }
}
