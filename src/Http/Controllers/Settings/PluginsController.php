<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Auth\Gate;
use App\Config\ConfigManager;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use App\Plugins\CatalogClient;
use App\Plugins\CatalogInstaller;
use App\Plugins\PluginLoader;
use App\Plugins\PluginRegistry;
use App\Plugins\PluginRepository;
use App\Security\CsrfProtection;

/**
 * Plugin-Verwaltung — Liste der installierten Plugins mit Aktiv-Schalter.
 *
 * Aktivieren prüft Kompatibilität und Abhängigkeiten, Deaktivieren
 * respektiert das removable-Flag und blockt, solange ein anderes aktives
 * Plugin das Modul braucht. Daten und Tabellen bleiben beim Deaktivieren
 * unangetastet — nur Routen, Navigation und Listener verschwinden.
 */
final class PluginsController extends Controller
{
    /**
     * GET/POST /settings/system/plugins
     */
    public function index(): void
    {
        $this->requireAuth();
        if (!Gate::allows('system.admin')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('index');
        }

        $registry   = PluginRegistry::fromDirectory(PluginLoader::pluginsDir());
        $repository = new PluginRepository($this->pdo);
        $repository->syncDiscovered($registry->all());
        $catalogClient = $this->catalogClient();
        $installer = $this->catalogInstaller();

        $message     = '';
        $messageType = '';

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            if (!CsrfProtection::validateToken((string) ($_POST['csrf_token'] ?? ''))) {
                $message     = 'Sitzung abgelaufen — bitte Seite neu laden und erneut versuchen.';
                $messageType = 'danger';
            } else {
                $action = (string) ($_POST['plugin_action'] ?? 'toggle');
                $pluginId = (string) ($_POST['plugin_id'] ?? '');
                [$message, $messageType] = match ($action) {
                    'install' => $this->handleInstall($pluginId, $registry, $repository),
                    'catalog_stage' => $this->handleCatalogStage($pluginId, false, $catalogClient, $installer),
                    'catalog_update' => $this->handleCatalogStage($pluginId, true, $catalogClient, $installer),
                    'remove' => $this->handleRemove($pluginId, $registry, $repository, $installer),
                    default => $this->handleToggle($pluginId, $registry, $repository),
                };
            }
        }

        // Aktueller Zustand nach eventueller Änderung
        $registry = PluginRegistry::fromDirectory(PluginLoader::pluginsDir());
        $repository->syncDiscovered($registry->all());
        $enabledIds = $repository->enabledIds();
        $registry->resolve($enabledIds, null);

        $activeIds = [];
        foreach ($registry->active() as $plugin) {
            $activeIds[$plugin->id()] = true;
        }
        $skipReasons = [];
        foreach ($registry->skipped() as $skip) {
            $skipReasons[$skip['id']] = $skip['reason'];
        }

        $rows = [];
        foreach ($registry->all() as $id => $plugin) {
            $rows[] = [
                'id'         => $id,
                'manifest'   => $plugin->manifest,
                'installed'  => PluginLoader::isInstalled($plugin),
                'bundled'    => PluginLoader::isBundled($id),
                'enabled'    => in_array($id, $enabledIds, true),
                'active'     => isset($activeIds[$id]),
                'skipReason' => $skipReasons[$id] ?? null,
                'requiredBy' => $this->enabledDependents($id, $registry, $enabledIds),
            ];
        }
        usort($rows, static fn (array $a, array $b): int => strcasecmp($a['manifest']->name, $b['manifest']->name));

        $installedVersions = [];
        $installedStates = [];
        foreach ($registry->all() as $id => $plugin) {
            $installedVersions[$id] = $plugin->manifest->version;
            $installedStates[$id] = PluginLoader::isInstalled($plugin);
        }
        $catalog = $catalogClient->catalog();
        $catalogRows = [];
        foreach ($catalog['plugins'] as $entry) {
            $installedVersion = $installedVersions[$entry['slug']] ?? null;
            $entry['installed_version'] = $installedVersion;
            $entry['installed'] = $installedStates[$entry['slug']] ?? false;
            $entry['update_available'] = $installedVersion !== null && $entry['installed']
                && version_compare((string) $entry['version'], $installedVersion, '>');
            $catalogRows[] = $entry;
        }

        $this->renderView('settings/system/plugins', [
            'rows'        => $rows,
            'message'     => $message,
            'messageType' => $messageType,
            'catalogRows' => $catalogRows,
            'catalogStale' => $catalog['stale'],
            'catalogFetchedAt' => $catalog['fetched_at'],
            'catalogError' => $catalog['error'],
        ]);
    }

    /** @return array{0:string,1:string} */
    private function handleCatalogStage(
        string $pluginId,
        bool $update,
        CatalogClient $catalog,
        CatalogInstaller $installer,
    ): array {
        $entry = $catalog->find($pluginId);
        if ($entry === null) return ['Plugin wurde im aktuellen Katalog nicht gefunden.', 'danger'];
        if (!($entry['installable'] ?? false)) return ['Installation ist gesperrt: Download oder SHA256-Pin fehlt.', 'warning'];

        try {
            $plugin = $installer->stage($entry, $update);
            if ($update) {
                return ["„{$plugin->manifest->name}“ wurde auf {$plugin->manifest->version} aktualisiert. Der Aktivierungszustand bleibt erhalten.", 'success'];
            }
            return ["„{$plugin->manifest->name}“ wurde geprüft und heruntergeladen. Bitte bestätige jetzt separat die Installation.", 'success'];
        } catch (\Throwable $e) {
            return ['Katalog-Installation abgebrochen: ' . $e->getMessage(), 'danger'];
        }
    }

    /** @return array{0:string,1:string} */
    private function handleRemove(
        string $pluginId,
        PluginRegistry $registry,
        PluginRepository $repository,
        CatalogInstaller $installer,
    ): array {
        $plugin = $registry->get($pluginId);
        if ($plugin === null) return ['Unbekanntes Plugin.', 'danger'];
        if (PluginLoader::isBundled($pluginId)) return ['Mitgelieferte Plugins können nicht entfernt werden.', 'warning'];
        if (in_array($pluginId, $repository->enabledIds(), true)) {
            return ['Plugin muss vor dem Entfernen deaktiviert werden.', 'warning'];
        }
        try {
            $installer->remove($pluginId);
            return ["Plugin-Dateien von „{$plugin->manifest->name}“ wurden entfernt. Tabellen und Daten bleiben erhalten.", 'success'];
        } catch (\Throwable $e) {
            return ['Plugin konnte nicht entfernt werden: ' . $e->getMessage(), 'danger'];
        }
    }

    private function catalogClient(): CatalogClient
    {
        $override = trim((string) ($_ENV['HUB_PLUGINS_URL'] ?? getenv('HUB_PLUGINS_URL') ?: ''));
        $hubUrl = trim((string) (new ConfigManager($this->pdo))->get('HUB_URL', 'https://hub.emergencyforge.de'));
        if ($hubUrl === '') $hubUrl = 'https://hub.emergencyforge.de';
        $endpoint = $override !== '' ? $override : rtrim($hubUrl, '/') . '/v1/plugins';
        $cache = dirname(__DIR__, 4) . '/storage/cache/plugin-catalog.json';
        return new CatalogClient($endpoint, $cache);
    }

    private function catalogInstaller(): CatalogInstaller
    {
        $root = dirname(__DIR__, 4);
        return new CatalogInstaller(
            PluginLoader::pluginsDir(),
            $root . '/storage/cache',
            PluginLoader::ignisVersion(),
        );
    }

    /**
     * Startet die manuelle Installation eines nicht mitgelieferten Plugins:
     * Marker schreiben, Migrationen anstoßen, aktivieren. Der Aufruf ist
     * die bewusste Admin-Entscheidung, fremden Code auszuführen — vorher
     * bleibt ein hochkopiertes Plugin vollständig inert.
     *
     * @return array{0: string, 1: string}
     */
    private function handleInstall(string $pluginId, PluginRegistry $registry, PluginRepository $repository): array
    {
        $plugin = $registry->get($pluginId);
        if ($plugin === null) {
            return ['Unbekanntes Plugin.', 'danger'];
        }
        if (PluginLoader::isInstalled($plugin)) {
            return ["„{$plugin->manifest->name}\u{201c} ist bereits installiert.", 'warning'];
        }

        if (!PluginLoader::markInstalled($plugin)) {
            return ['Installations-Marker konnte nicht geschrieben werden — bitte Schreibrechte im Plugin-Verzeichnis prüfen.', 'danger'];
        }

        // Migrationen des frisch installierten Plugins direkt ausführen,
        // statt auf den nächsten Request zu warten.
        try {
            (new \App\Database\AutoMigrator($this->pdo))->runIfNeeded();
        } catch (\Throwable $e) {
            return [
                "„{$plugin->manifest->name}\u{201c} wurde installiert, aber der Migrationslauf meldete: " . $e->getMessage(),
                'warning',
            ];
        }

        $repository->setEnabled($pluginId, true);
        return ["„{$plugin->manifest->name}\u{201c} wurde installiert und aktiviert.", 'success'];
    }

    /**
     * Schaltet ein Plugin um und liefert [Meldung, Typ] für die Anzeige.
     *
     * @return array{0: string, 1: string}
     */
    private function handleToggle(string $pluginId, PluginRegistry $registry, PluginRepository $repository): array
    {
        $plugin = $registry->get($pluginId);
        if ($plugin === null) {
            return ['Unbekanntes Plugin.', 'danger'];
        }
        if (!PluginLoader::isInstalled($plugin)) {
            return ["„{$plugin->manifest->name}\u{201c} ist noch nicht installiert — bitte zuerst die Installation starten.", 'warning'];
        }

        $enabledIds = $repository->enabledIds();
        $isEnabled  = in_array($pluginId, $enabledIds, true);

        if ($isEnabled) {
            if (!$plugin->manifest->removable) {
                return ["„{$plugin->manifest->name}\u{201c} ist fester Bestandteil und kann nicht deaktiviert werden.", 'warning'];
            }
            $dependents = $this->enabledDependents($pluginId, $registry, $enabledIds);
            if ($dependents !== []) {
                return [
                    "„{$plugin->manifest->name}\u{201c} wird noch benötigt von: " . implode(', ', $dependents) . '. Bitte zuerst dort deaktivieren.',
                    'warning',
                ];
            }
            $repository->setEnabled($pluginId, false);
            return ["„{$plugin->manifest->name}\u{201c} wurde deaktiviert. Daten und Tabellen bleiben erhalten.", 'success'];
        }

        // Aktivieren: fehlende Abhängigkeiten benennen statt still zu scheitern.
        $missing = [];
        foreach ($plugin->manifest->depends as $dep) {
            if (!in_array($dep, $enabledIds, true)) {
                $depPlugin = $registry->get($dep);
                $missing[] = $depPlugin?->manifest->name ?? $dep;
            }
        }
        if ($missing !== []) {
            return [
                "„{$plugin->manifest->name}\u{201c} benötigt zuerst: " . implode(', ', $missing) . '.',
                'warning',
            ];
        }

        $repository->setEnabled($pluginId, true);
        return ["„{$plugin->manifest->name}\u{201c} wurde aktiviert.", 'success'];
    }

    /**
     * Namen aller AKTIVIERTEN Plugins, die auf $pluginId angewiesen sind.
     *
     * @param list<string> $enabledIds
     * @return list<string>
     */
    private function enabledDependents(string $pluginId, PluginRegistry $registry, array $enabledIds): array
    {
        $names = [];
        foreach ($registry->all() as $id => $plugin) {
            if ($id === $pluginId || !in_array($id, $enabledIds, true)) {
                continue;
            }
            if (in_array($pluginId, $plugin->manifest->depends, true)) {
                $names[] = $plugin->manifest->name;
            }
        }
        return $names;
    }
}
