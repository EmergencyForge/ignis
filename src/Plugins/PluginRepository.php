<?php

declare(strict_types=1);

namespace App\Plugins;

use PDO;

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
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * Sorgt dafür, dass jedes entdeckte Plugin eine Zeile hat. Neue Plugins
     * bekommen `enabled` gemäß `default_enabled`. Bestehende Zeilen bleiben
     * unangetastet — eine bewusste Nutzer-Deaktivierung wird nie überschrieben.
     *
     * @param array<string, Plugin> $discovered
     */
    public function syncDiscovered(array $discovered): void
    {
        $stmt = $this->pdo->query('SELECT plugin_id FROM intra_plugins');
        $existing = $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_COLUMN);
        $known = array_fill_keys($existing, true);

        $insert = $this->pdo->prepare(
            'INSERT INTO intra_plugins (plugin_id, enabled, installed_version)
             VALUES (:id, :enabled, :version)'
        );

        foreach ($discovered as $id => $plugin) {
            if (isset($known[$id])) {
                continue;
            }
            $insert->execute([
                'id' => $id,
                'enabled' => $plugin->manifest->defaultEnabled ? 1 : 0,
                'version' => $plugin->manifest->version,
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
        $rows = $this->pdo->query('SELECT plugin_id FROM intra_plugins WHERE enabled = 1')->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_map('strval', $rows));
    }

    public function setEnabled(string $pluginId, bool $enabled): void
    {
        $stmt = $this->pdo->prepare('UPDATE intra_plugins SET enabled = :enabled WHERE plugin_id = :id');
        $stmt->execute(['enabled' => $enabled ? 1 : 0, 'id' => $pluginId]);
    }
}
