<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Plugin-Registrierung.
 *
 * Hält pro Plugin den installierten Zustand und ob es aktiv ist. Der
 * PluginRegistry entdeckt Plugins über ihre `manifest.php` auf der Platte;
 * diese Tabelle ist die Quelle der Wahrheit dafür, *welche* davon geladen
 * werden. First-Party-Plugins mit `default_enabled` werden beim ersten
 * Boot hier mit `enabled = 1` angelegt.
 */
class CreateIntraPlugins extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_plugins')) {
            return;
        }

        $this->table('intra_plugins', ['id' => false, 'primary_key' => ['plugin_id']])
            // Die stabile Plugin-ID aus dem Manifest (kein Auto-Increment,
            // die ID selbst ist der natürliche Schlüssel).
            ->addColumn('plugin_id', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('enabled', 'boolean', ['default' => true])
            // Zuletzt gesehene Version des Manifests — erlaubt später,
            // Plugin-Updates zu erkennen.
            ->addColumn('installed_version', 'string', ['limit' => 32, 'null' => true])
            ->addColumn('installed_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->create();
    }
}
