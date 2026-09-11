<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Sonderrechte Anfahrt & Transport Felder.
 *
 * Fügt zwei neue Spalten hinzu:
 * - sonderrechte_anfahrt: Tri-State (NULL=leer, 'nein', 'ja') - Pflichtfeld
 * - sonderrechte_transport: Tri-State (NULL=leer, 'nein', 'ja') - optional
 */
class AlterIntraEdivi12032026Sonderrechte extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_edivi');

        if (!$table->hasColumn('sonderrechte_anfahrt')) {
            $table->addColumn('sonderrechte_anfahrt', 'string', [
                'limit'   => 4,
                'null'    => true,
                'default' => null,
                'after'   => 'ebesonderheiten',
            ]);
        }

        if (!$table->hasColumn('sonderrechte_transport')) {
            $table->addColumn('sonderrechte_transport', 'string', [
                'limit'   => 4,
                'null'    => true,
                'default' => null,
                'after'   => 'sonderrechte_anfahrt',
            ]);
        }

        $table->update();
    }
}
