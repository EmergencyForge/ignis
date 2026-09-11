<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Default-Konfiguration für die Sichtbarkeit der Wissensdatenbank:
 * `KB_PUBLIC_ACCESS` steuert, ob die KB ohne Login einsehbar ist.
 */
class InsertIntraKbConfig29112025 extends AbstractMigration
{
    public function up(): void
    {
        // Idempotent wie das ursprüngliche ON DUPLICATE KEY UPDATE:
        // vorhandener Eintrag bleibt unangetastet.
        $exists = $this->fetchRow("SELECT 1 FROM intra_config WHERE config_key = 'KB_PUBLIC_ACCESS'");
        if ($exists !== false && $exists !== null) {
            return;
        }

        $this->table('intra_config')->insert([
            [
                'config_key'    => 'KB_PUBLIC_ACCESS',
                'config_value'  => 'false',
                'config_type'   => 'boolean',
                'category'      => 'funktionen',
                'description'   => 'Soll die Wissensdatenbank ohne Login einsehbar sein?',
                'is_editable'   => 1,
                'display_order' => 60,
            ],
        ])->saveData();
    }

    public function down(): void
    {
        $this->execute("DELETE FROM intra_config WHERE config_key = 'KB_PUBLIC_ACCESS'");
    }
}
