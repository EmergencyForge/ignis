<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * SETUP_MODULES_DONE: hat die Installation ihre Module schon ausgewählt
 * (App\Plugins\ModuleSelection)? Solange nicht, ist „Module auswählen" der
 * erste Schritt der Einrichtungs-Checkliste auf dem Dashboard.
 *
 * Eine Installation, die schon Mitarbeiter hat, ist in Benutzung; dort
 * gilt der Schritt als erledigt, sonst tauchte die Checkliste überall
 * wieder auf. Nicht editierbar: die Seite Einstellungen › System › Module
 * setzt den Wert beim Speichern.
 */
final class SetupModulesDone extends AbstractMigration
{
    public function up(): void
    {
        if ($this->fetchRow("SELECT id FROM intra_config WHERE config_key = 'SETUP_MODULES_DONE'") !== false) {
            return;
        }

        $inUse = $this->hasTable('intra_mitarbeiter')
            && (int) ($this->fetchRow('SELECT COUNT(*) AS n FROM intra_mitarbeiter')['n'] ?? 0) > 0;

        $this->table('intra_config')->insert([
            [
                'config_key'    => 'SETUP_MODULES_DONE',
                'config_value'  => $inUse ? 'true' : 'false',
                'config_type'   => 'boolean',
                'category'      => 'technik',
                'description'   => 'Module ausgewählt',
                'is_editable'   => 0,
                'display_order' => 90,
            ],
        ])->saveData();
    }

    public function down(): void
    {
        $this->execute("DELETE FROM intra_config WHERE config_key = 'SETUP_MODULES_DONE'");
    }
}
