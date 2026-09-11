<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Fügt die Konfigurationsoption ENOTF_BZ_UNIT hinzu: Einheit für
 * Blutzuckerwerte im eNOTF (mg/dl oder mmol/l), Default mg/dl.
 */
class InsertConfig09122025BzUnit extends AbstractMigration
{
    public function up(): void
    {
        $exists = $this->fetchRow("SELECT id FROM intra_config WHERE config_key = 'ENOTF_BZ_UNIT'");
        if ($exists !== false) {
            return;
        }

        $this->table('intra_config')->insert([
            [
                'config_key'    => 'ENOTF_BZ_UNIT',
                'config_value'  => 'mg/dl',
                'config_type'   => 'string',
                'category'      => 'funktionen',
                'description'   => 'Einheit für Blutzuckerwerte (mg/dl oder mmol/l)',
                'is_editable'   => 1,
                'display_order' => 37,
            ],
        ])->saveData();
    }

    public function down(): void
    {
        $this->execute("DELETE FROM intra_config WHERE config_key = 'ENOTF_BZ_UNIT'");
    }
}
