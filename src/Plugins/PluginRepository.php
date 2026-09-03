<?php

declare(strict_types=1);

namespace App\Plugins;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * DB-Zugriff auf den Aktiv-Status der Plugins (Tabelle `intra_plugins`).
 *
 * Trennt die reine Auflösungslogik (PluginRegistry, voll unit-testbar) von
 * der Persistenz. Beim ersten Kontakt mit einem entdeckten Plugin wird ein
 * Zeilen-Eintrag angelegt — `default_enabled` aus dem Manifest bestimmt,
 * ob es direkt aktiv ist (so sind eNOTF & fireTab nach dem Update sofort
 * da, ohne dass jemand sie manuell einschalten muss).
 */
final class PluginRepository
{
    /**
     * Sorgt dafür, dass jedes entdeckte Plugin eine Zeile hat. Neue Plugins
     * bekommen `enabled` gemäß `default_enabled`. Bestehende Zeilen bleiben
     * unangetastet — eine bewusste Nutzer-Deaktivierung wird nie überschrieben.
     *
     * @param array<string, Plugin> $discovered
     */
    public function syncDiscovered(array $discovered): void
    {
        $existing = Capsule::table('intra_plugins')->pluck('plugin_id')->all();
        $known = array_fill_keys($existing, true);

        foreach ($discovered as $id => $plugin) {
            if (isset($known[$id])) {
                continue;
            }
            Capsule::table('intra_plugins')->insert([
                'plugin_id' => $id,
                'enabled' => $plugin->manifest->defaultEnabled ? 1 : 0,
                'installed_version' => $plugin->manifest->version,
            ]);
        }
    }

    /**
     * IDs aller aktivierten Plugins.
     *
     * @return list<string>
     */
    public function enabledIds(): array
    {
        $rows = Capsule::table('intra_plugins')->where('enabled', 1)->pluck('plugin_id')->all();
        return array_values(array_map('strval', $rows));
    }

    public function setEnabled(string $pluginId, bool $enabled): void
    {
        Capsule::table('intra_plugins')
            ->where('plugin_id', $pluginId)
            ->update(['enabled' => $enabled ? 1 : 0]);
    }
}
