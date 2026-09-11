<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Rettungsdienst-Qualifikationen: Anzeigenamen inkl. geschlechtsspezifischer
 * Varianten und Abkürzung, none-Flag für den "Keine"-Platzhalter, trainable
 * markiert Qualis mit Ausbildungsbetrieb.
 */
class CreateIntraMitarbeiterRdquali07062025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_mitarbeiter_rdquali')) {
            return;
        }

        $this->table('intra_mitarbeiter_rdquali', [
            'signed'     => true,
            'engine'     => 'InnoDB',
            'encoding'   => 'utf8mb4',
            'collation'  => 'utf8mb4_general_ci',
            'row_format' => 'DYNAMIC',
        ])
            ->addColumn('priority',   'integer', ['null' => false])
            ->addColumn('name',       'string',    ['limit' => 255, 'null' => false])
            ->addColumn('name_m',     'string',    ['limit' => 255, 'null' => false])
            ->addColumn('name_w',     'string',    ['limit' => 255, 'null' => false])
            ->addColumn('abkuerzung', 'string',    ['limit' => 50, 'null' => true])
            ->addColumn('none',       'boolean',   ['default' => 0, 'null' => false])
            ->addColumn('trainable',  'boolean',   ['default' => 0, 'null' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->create();

        // Der Primärschlüssel trägt im Originalschema ein explizites USING BTREE.
        $this->execute(
            'ALTER TABLE `intra_mitarbeiter_rdquali` DROP PRIMARY KEY, ADD PRIMARY KEY (`id`) USING BTREE'
        );
    }
}
