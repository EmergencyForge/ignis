<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Eigene Sortierreihenfolge für Krankenhaus-Abteilungen: `sort_order`
 * (niedriger = höhere Priorität, Default 999) samt Index.
 */
class AlterIntraEdiviHospitalDepartments21012026SortOrder extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_edivi_hospital_departments');

        if ($table->hasColumn('sort_order')) {
            return;
        }

        $table->addColumn('sort_order', 'integer', [
            'null'    => false,
            'default' => 999,
            'comment' => 'Custom sort order (lower = higher priority)',
            'after'   => 'name',
        ])
            ->addIndex(['sort_order'], ['name' => 'idx_sort_order'])
            ->update();
    }
}
