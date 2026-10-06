<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Titel wie "Dr." für Mitarbeiter: eigene Liste plus Verweis. Der Titel
 * steht nie in fullname, daran hängen Protokollanten-Zuordnung,
 * Mail-Adressen und Federation.
 */
final class PersonnelTitles extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('intra_mitarbeiter_titel')) {
            $this->table('intra_mitarbeiter_titel', [
                'signed'    => true,
                'engine'    => 'InnoDB',
                'encoding'  => 'utf8mb4',
                'collation' => 'utf8mb4_general_ci',
            ])
                ->addColumn('name', 'string', ['limit' => 50, 'null' => false])
                ->addColumn('priority', 'integer', ['default' => 0, 'null' => false])
                ->create();

            $this->table('intra_mitarbeiter_titel')->insert([
                ['name' => 'Dr.', 'priority' => 10],
                ['name' => 'Dr. med.', 'priority' => 20],
                ['name' => 'Prof.', 'priority' => 30],
                ['name' => 'Prof. Dr.', 'priority' => 40],
            ])->saveData();
        }

        $mitarbeiter = $this->table('intra_mitarbeiter');
        if (!$mitarbeiter->hasColumn('titel_id')) {
            $mitarbeiter->addColumn('titel_id', 'integer', ['null' => true, 'after' => 'fullname'])
                ->addForeignKey('titel_id', 'intra_mitarbeiter_titel', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'constraint' => 'fk_mitarbeiter_titel'])
                ->update();
        }
    }

    public function down(): void
    {
        $mitarbeiter = $this->table('intra_mitarbeiter');
        if ($mitarbeiter->hasColumn('titel_id')) {
            $mitarbeiter->dropForeignKey('titel_id')->removeColumn('titel_id')->update();
        }
        if ($this->hasTable('intra_mitarbeiter_titel')) {
            $this->table('intra_mitarbeiter_titel')->drop()->save();
        }
    }
}
