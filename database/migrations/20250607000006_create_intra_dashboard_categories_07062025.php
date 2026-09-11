<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Dashboard-Kategorien: gruppieren die Kacheln auf der Startseite,
 * sortiert über priority.
 */
class CreateIntraDashboardCategories07062025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_dashboard_categories')) {
            return;
        }

        $this->table('intra_dashboard_categories', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('title',      'string',    ['limit' => 255, 'null' => false])
            ->addColumn('priority',   'integer',   ['default' => 0, 'null' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->create();
    }
}
