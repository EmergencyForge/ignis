<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Ein Status vom Tablet kommt über EMD als Statusmeldung zurück. echoed
 * markiert den Queue-Eintrag, dessen Echo schon angekommen ist, damit jede
 * Tablet-Meldung nur ein Echo schluckt.
 */
class AlterIntraFireStatusQueue01112026AddEchoed extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_fire_status_queue');

        if ($table->hasColumn('echoed')) {
            return;
        }

        $table->addColumn('echoed', 'boolean', ['default' => 0, 'null' => false, 'after' => 'delivered'])->update();
    }
}
