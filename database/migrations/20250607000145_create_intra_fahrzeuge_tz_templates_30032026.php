<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Vorlagen für taktische Zeichen der Fahrzeuge: benannte Kombinationen aus
 * Grundzeichen, Organisation, Fachaufgabe, Einheit, Symbol, Typ und Text,
 * die beim Anlegen von Fahrzeugen wiederverwendet werden können.
 */
class CreateIntraFahrzeugeTzTemplates30032026 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_fahrzeuge_tz_templates')) {
            return;
        }

        $this->table('intra_fahrzeuge_tz_templates', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('name', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('grundzeichen', 'string', ['limit' => 100, 'null' => true, 'default' => null])
            ->addColumn('organisation', 'string', ['limit' => 100, 'null' => true, 'default' => null])
            ->addColumn('fachaufgabe', 'string', ['limit' => 100, 'null' => true, 'default' => null])
            ->addColumn('einheit', 'string', ['limit' => 100, 'null' => true, 'default' => null])
            ->addColumn('symbol', 'string', ['limit' => 100, 'null' => true, 'default' => null])
            ->addColumn('typ', 'string', ['limit' => 100, 'null' => true, 'default' => null])
            ->addColumn('text', 'string', ['limit' => 100, 'null' => true, 'default' => null])
            ->addColumn('created_by', 'integer', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', [
                'null'    => true,
                'default' => null,
                'update'  => 'CURRENT_TIMESTAMP',
            ])
            ->addIndex(['name'], ['unique' => true, 'name' => 'uniq_template_name'])
            ->addForeignKey('created_by', 'intra_users', 'id', ['delete' => 'SET_NULL'])
            ->create();
    }
}
