<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Job-basierte Fahrzeug-Filterung: allowed_jobs enthält kommagetrennte
 * Job-Namen, die dieses Fahrzeug sehen dürfen. NULL = alle Jobs.
 */
class AlterIntraFahrzeuge13032026AllowedJobs extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_fahrzeuge');

        if (!$table->hasColumn('allowed_jobs')) {
            $table->addColumn('allowed_jobs', 'string', [
                'limit'   => 500,
                'null'    => true,
                'default' => null,
                'comment' => 'Kommagetrennte Job-Namen die dieses Fahrzeug sehen duerfen. NULL = alle.',
                'after'   => 'rd_type',
            ])->update();
        }
    }
}
