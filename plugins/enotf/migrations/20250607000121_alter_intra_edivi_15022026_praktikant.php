<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Praktikant (3. Person) auf Fahrzeugen: dritte Personal-Spalte für
 * Transport- und NA-Fahrzeug.
 */
class AlterIntraEdivi15022026Praktikant extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_edivi');

        if (!$table->hasColumn('fzg_transp_perso_3')) {
            $table->addColumn('fzg_transp_perso_3', 'string', [
                'limit'   => 255,
                'null'    => true,
                'default' => null,
                'after'   => 'fzg_transp_perso_2',
            ]);
        }

        if (!$table->hasColumn('fzg_na_perso_3')) {
            $table->addColumn('fzg_na_perso_3', 'string', [
                'limit'   => 255,
                'null'    => true,
                'default' => null,
                'after'   => 'fzg_na_perso_2',
            ]);
        }

        $table->update();
    }
}
