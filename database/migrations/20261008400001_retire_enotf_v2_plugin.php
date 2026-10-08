<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * eNOTF v2 ist im Plugin eNOTF aufgegangen und kein eigenes Modul mehr.
 * Ohne diese Zeile zeigte die Modulseite einen Eintrag ohne Plugin.
 */
final class RetireEnotfV2Plugin extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('intra_plugins')) {
            return;
        }

        $this->execute("DELETE FROM intra_plugins WHERE plugin_id = 'enotf-v2'");
    }

    public function down(): void
    {
        // Das Plugin gibt es nicht mehr, es gibt nichts wiederherzustellen.
    }
}
