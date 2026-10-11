<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Atemschutzüberwachung als Trupps, Mitglieder und Ereignisse statt eines
 * JSON-Blocks je Überwacher. Jeder Tipp im Tablet ist ein Ereignis mit
 * Serverzeit; den Zustand eines Trupps rechnet der Client daraus. Die
 * client_uuid macht doppelt gesendete Meldungen (Netzabbruch) harmlos.
 * intra_fire_incident_asu bleibt für alte Protokolle lesbar.
 */
class CreateIntraFireAsuTables extends AbstractMigration
{
    public function change(): void
    {
        $opts = ['signed' => true, 'engine' => 'InnoDB', 'encoding' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci'];

        if (!$this->hasTable('intra_fire_asu_trupps')) {
            $this->table('intra_fire_asu_trupps', $opts)
                ->addColumn('incident_id', 'integer', ['null' => false])
                ->addColumn('name', 'string', ['limit' => 50, 'null' => false])
                ->addColumn('role', 'string', ['limit' => 20, 'null' => false, 'default' => 'angriff'])
                ->addColumn('task', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('expected_minutes', 'smallinteger', ['null' => false, 'default' => 30])
                ->addColumn('vehicle_id', 'integer', ['null' => true])
                ->addColumn('created_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['incident_id'], ['name' => 'idx_incident'])
                ->addForeignKey('incident_id', 'intra_fire_incidents', 'id', ['delete' => 'CASCADE'])
                ->create();
        }

        if (!$this->hasTable('intra_fire_asu_members')) {
            $this->table('intra_fire_asu_members', $opts)
                ->addColumn('trupp_id', 'integer', ['null' => false])
                ->addColumn('position', 'string', ['limit' => 10, 'null' => false])
                ->addColumn('name', 'string', ['limit' => 100, 'null' => false])
                ->addColumn('mitarbeiter_id', 'integer', ['null' => true])
                ->addIndex(['trupp_id', 'position'], ['unique' => true, 'name' => 'uniq_trupp_position'])
                ->addForeignKey('trupp_id', 'intra_fire_asu_trupps', 'id', ['delete' => 'CASCADE'])
                ->create();
        }

        if (!$this->hasTable('intra_fire_asu_events')) {
            $this->table('intra_fire_asu_events', $opts)
                ->addColumn('incident_id', 'integer', ['null' => false])
                ->addColumn('trupp_id', 'integer', ['null' => false])
                ->addColumn('member_id', 'integer', ['null' => true])
                ->addColumn('type', 'string', ['limit' => 20, 'null' => false])
                ->addColumn('bar', 'smallinteger', ['null' => true])
                ->addColumn('note', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('client_uuid', 'string', ['limit' => 36, 'null' => false])
                ->addColumn('device', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('created_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('voided_at', 'timestamp', ['null' => true])
                ->addIndex(['client_uuid'], ['unique' => true, 'name' => 'uniq_client_uuid'])
                ->addIndex(['incident_id', 'id'], ['name' => 'idx_incident_id'])
                ->addForeignKey('incident_id', 'intra_fire_incidents', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('trupp_id', 'intra_fire_asu_trupps', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('member_id', 'intra_fire_asu_members', 'id', ['delete' => 'SET_NULL'])
                ->create();
        }
    }
}
