<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Fahrzeug-Sessions für eNOTF: `intra_enotf_sessions` hält die aktive
 * Besatzung eines Fahrzeugs (Fahrer/Beifahrer/Praktikant samt Quali),
 * `intra_enotf_session_members` die einzelnen Browser-Tokens, über die
 * Crew-Mitglieder derselben Session beitreten.
 */
class CreateIntraEnotfSessions11032026 extends AbstractMigration
{
    public function change(): void
    {
        if (!$this->hasTable('intra_enotf_sessions')) {
            $this->table('intra_enotf_sessions', [
                'signed'    => true,
                'engine'    => 'InnoDB',
                'encoding'  => 'utf8mb4',
                'collation' => 'utf8mb4_general_ci',
            ])
                ->addColumn('vehicle_identifier', 'string',   ['limit' => 64, 'null' => false, 'comment' => 'Fahrzeug-Kennung'])
                ->addColumn('fahrername',         'string',   ['limit' => 255, 'null' => true, 'default' => null])
                ->addColumn('fahrerquali',        'string',   ['limit' => 32, 'null' => true, 'default' => null])
                ->addColumn('beifahrername',      'string',   ['limit' => 255, 'null' => true, 'default' => null])
                ->addColumn('beifahrerquali',     'string',   ['limit' => 32, 'null' => true, 'default' => null])
                ->addColumn('praktikantname',     'string',   ['limit' => 255, 'null' => true, 'default' => null])
                ->addColumn('praktikantquali',    'string',   ['limit' => 32, 'null' => true, 'default' => null])
                ->addColumn('active',             'boolean',  ['null' => false, 'default' => 1])
                ->addColumn('created_at',         'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at',         'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['vehicle_identifier', 'active'], ['name' => 'idx_vehicle_active'])
                ->create();
        }

        if (!$this->hasTable('intra_enotf_session_members')) {
            $this->table('intra_enotf_session_members', [
                'signed'    => true,
                'engine'    => 'InnoDB',
                'encoding'  => 'utf8mb4',
                'collation' => 'utf8mb4_general_ci',
            ])
                ->addColumn('session_id',    'integer',  ['null' => false, 'comment' => 'FK zu intra_enotf_sessions.id'])
                ->addColumn('session_token', 'string',   ['limit' => 64, 'null' => false, 'comment' => 'Individueller Browser-Token'])
                ->addColumn('position',      'enum',     ['values' => ['fahrer', 'beifahrer', 'praktikant'], 'null' => false, 'comment' => 'Position im Fahrzeug'])
                ->addColumn('created_at',    'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['session_token'], ['unique' => true, 'name' => 'idx_session_token'])
                ->addIndex(['session_id'],    ['name' => 'idx_session_id'])
                ->addForeignKey('session_id', 'intra_enotf_sessions', 'id', [
                    'delete'     => 'CASCADE',
                    'constraint' => 'fk_session_members_session',
                ])
                ->create();
        }
    }
}
