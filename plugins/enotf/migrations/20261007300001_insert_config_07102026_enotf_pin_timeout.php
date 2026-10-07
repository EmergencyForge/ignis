<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * ENOTF_PIN_TIMEOUT: nach wie vielen Minuten ohne Eingabe das eNOTF wieder
 * nach der PIN fragt. Vorher fest 5 Minuten, das bleibt der Startwert.
 * Gelesen über App\Session\SessionManager::pinTimeout().
 */
class InsertConfig07102026EnotfPinTimeout extends AbstractMigration
{
    public function up(): void
    {
        if ($this->fetchRow("SELECT config_key FROM intra_config WHERE config_key = 'ENOTF_PIN_TIMEOUT'")) {
            return;
        }

        $row = [
            'config_key'    => 'ENOTF_PIN_TIMEOUT',
            'config_value'  => '5',
            'config_type'   => 'integer',
            'category'      => 'enotf',
            'description'   => 'Sperren nach',
            'is_editable'   => 1,
            'display_order' => 15,
        ];
        if ($this->table('intra_config')->hasColumn('hint')) {
            $row['hint'] = 'So lange darf das eNOTF unbenutzt sein, bevor es wieder nach der PIN fragt.';
        }

        $this->table('intra_config')->insert([$row])->saveData();
    }

    public function down(): void
    {
        $this->execute("DELETE FROM intra_config WHERE config_key = 'ENOTF_PIN_TIMEOUT'");
    }
}
