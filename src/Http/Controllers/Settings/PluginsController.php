<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Auth\Gate;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
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

        $message     = '';
        $messageType = '';

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            if (!CsrfProtection::validateToken((string) ($_POST['csrf_token'] ?? ''))) {
                $message     = 'Sitzung abgelaufen — bitte Seite neu laden und erneut versuchen.';
                $messageType = 'danger';
            } elseif (($_POST['plugin_action'] ?? '') === 'install') {
                [$message, $messageType] = $this->handleInstall(
                    (string) ($_POST['plugin_id'] ?? ''),
                    $registry,
                    $repository,
                );
            } else {
                [$message, $messageType] = $this->handleToggle(
                    (string) ($_POST['plugin_id'] ?? ''),
                    $registry,
                    $repository,
                );
            }
        }

        // Aktueller Zustand nach eventueller Änderung
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

        $this->renderView('settings/system/plugins', [
            'rows'        => $rows,
            'message'     => $message,
            'messageType' => $messageType,
        ]);
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
