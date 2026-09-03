<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Einsatznummern werden eindeutig: Unique-Key `uk_enr` auf
 * `intra_edivi.enr`, damit kein Protokoll doppelt angelegt werden kann.
 */
class UpdateIntraEdivi06072025 extends AbstractMigration
{
    public function change(): void
    {
        $this->table('intra_edivi')
            ->addIndex(['enr'], ['unique' => true, 'name' => 'uk_enr'])
            ->update();
    }
}
