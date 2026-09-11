<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * MANV-Lagen (Massenanfall von Verletzten): Stammtabelle je Einsatzlage mit
 * Einsatzdaten, LNA/OrgL-Zuordnung und Statusverlauf.
 */
class CreateIntraManvLagen14122025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_manv_lagen')) {
            return;
        }

        $this->table('intra_manv_lagen', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
            'comment'   => 'MANV-Lagen (Massenanfall von Verletzten)',
        ])
            ->addColumn('einsatznummer',       'string',   ['limit' => 50, 'null' => false])
            ->addColumn('einsatzort',          'string',   ['limit' => 255, 'null' => false])
            ->addColumn('einsatzanlass',       'text',     ['null' => true, 'default' => null])
            ->addColumn('lna_name',            'string',   ['limit' => 255, 'null' => true, 'default' => null, 'comment' => 'Leitender Notarzt'])
            ->addColumn('lna_mitarbeiter_id',  'integer',  ['null' => true, 'default' => null])
            ->addColumn('orgl_name',           'string',   ['limit' => 255, 'null' => true, 'default' => null, 'comment' => 'Organisatorischer Leiter'])
            ->addColumn('orgl_mitarbeiter_id', 'integer',  ['null' => true, 'default' => null])
            ->addColumn('status',              'enum',     ['values' => ['aktiv', 'abgeschlossen', 'archiviert'], 'null' => true, 'default' => 'aktiv'])
            ->addColumn('einsatzbeginn',       'datetime', ['null' => true, 'default' => null])
            ->addColumn('einsatzende',         'datetime', ['null' => true, 'default' => null])
            ->addColumn('erstellt_am',         'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('erstellt_von',        'integer',  ['null' => true, 'default' => null])
            ->addColumn('geaendert_am',        'datetime', ['null' => true, 'default' => null, 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('geaendert_von',       'integer',  ['null' => true, 'default' => null])
            ->addColumn('notizen',             'text',     ['null' => true, 'default' => null])
            ->addIndex(['einsatznummer'],       ['name' => 'idx_einsatznummer'])
            ->addIndex(['status'],              ['name' => 'idx_status'])
            ->addIndex(['lna_mitarbeiter_id'],  ['name' => 'fk_lna_mitarbeiter'])
            ->addIndex(['orgl_mitarbeiter_id'], ['name' => 'fk_orgl_mitarbeiter'])
            ->create();
    }
}
