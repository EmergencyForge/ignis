<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * POI-Kategorisierung: neue Spalte `typ` (z. B. Klinik, Wache, Objekt)
 * hinter `ortsteil`.
 */
class AlterIntraEdiviPois08122025AddTyp extends AbstractMigration
{
    public function change(): void
    {
        $this->table('intra_edivi_pois')
            ->addColumn('typ', 'string', ['limit' => 50, 'null' => true, 'after' => 'ortsteil'])
            ->update();
    }
}
