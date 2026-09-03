<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * MANV-Ressourcen: Fahrzeuge, Personal und Material je Lage mit Status und
 * Position an der Einsatzstelle.
 */
class CreateIntraManvRessourcen14122025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_manv_ressourcen')) {
            return;
        }

        $this->table('intra_manv_ressourcen', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
            'comment'   => 'MANV-Ressourcen (Fahrzeuge, Personal, Material)',
        ])
            ->addColumn('manv_lage_id', 'integer', ['null' => false])
            ->addColumn('typ',          'enum',     ['values' => ['fahrzeug', 'personal', 'material'], 'null' => true, 'default' => 'fahrzeug'])
            ->addColumn('bezeichnung',  'string',   ['limit' => 255, 'null' => false, 'comment' => 'z.B. RTW, NAW, LNA, Behandlungsplatz'])
            ->addColumn('rufname',      'string',   ['limit' => 100, 'null' => true, 'default' => null, 'comment' => 'z.B. Florian ABC 83-1'])
            ->addColumn('fahrzeugtyp',  'string',   ['limit' => 50, 'null' => true, 'default' => null, 'comment' => 'RTW, NAW, RTH, KTW, etc.'])
            ->addColumn('lokalisation', 'string',   ['limit' => 255, 'null' => true, 'default' => null, 'comment' => 'Position an der Einsatzstelle'])
            ->addColumn('status',       'enum',     ['values' => ['verfuegbar', 'im_einsatz', 'nicht_verfuegbar'], 'null' => true, 'default' => 'verfuegbar'])
            ->addColumn('besatzung',    'text',     ['null' => true, 'default' => null])
            ->addColumn('notizen',      'text',     ['null' => true, 'default' => null])
            ->addColumn('erstellt_am',  'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('geaendert_am', 'datetime', ['null' => true, 'default' => null, 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['manv_lage_id'], ['name' => 'idx_manv_lage'])
            ->addIndex(['typ'],          ['name' => 'idx_typ'])
            ->addIndex(['status'],       ['name' => 'idx_status'])
            ->addForeignKey('manv_lage_id', 'intra_manv_lagen', 'id', [
                'delete'     => 'CASCADE',
                'constraint' => 'fk_manv_ressource_lage',
            ])
            ->create();
    }
}
