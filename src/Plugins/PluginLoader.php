<?php

declare(strict_types=1);

namespace App\Plugins;

use App\Logging\Logger;
use PDO;

/**
 * Verbindet die entdeckten Plugins mit den Registern der Anwendung.
 *
 * Der Loader wird einmal pro Request aus dem Container aufgelöst und
 * cached das aktive Plugin-Set. Die Call-Sites (Front-Controller,
 * Navigation, Event-Dispatcher, Console) holen sich hier die Fragmente
 * der aktiven Plugins und mergen sie in ihre jeweilige Struktur.
 *
 * Fehlertoleranz ist Pflicht: Wenn die Plugin-Tabelle (noch) nicht
 * existiert — etwa bei einer frischen Installation vor dem ersten
 * Migrationslauf — verhält sich die Anwendung, als gäbe es keine
 * Plugins, statt den Boot zu brechen.
 */
class PluginLoader
{
    /** @var list<Plugin>|null */
    private ?array $active = null;

    public function __construct(
        private readonly PDO $pdo,
    ) {}

    public static function pluginsDir(): string
    {
        return dirname(__DIR__, 2) . '/plugins';
    }

    /**
     * Migrations-Verzeichnisse ALLER installierten Plugins — bewusst ohne
     * Blick in die Datenbank. Schema-Migrationen laufen auch für
     * deaktivierte Plugins, damit Deaktivieren nie Daten oder Schema
     * zurücklässt, das beim Reaktivieren fehlt. Außerdem wird diese Liste
     * von phinx.php gebraucht, das auch ohne App-Bootstrap (CLI) läuft.
     *
     * @return list<string>
     */
    public static function migrationPaths(): array
    {
        $dirs = glob(self::pluginsDir() . '/*/migrations', GLOB_ONLYDIR) ?: [];
        sort($dirs);
        return array_values($dirs);
    }

    /**
     * Aktive Plugins in Ladereihenfolge (Abhängigkeiten zuerst).
     *
     * @return list<Plugin>
     */
    public function active(): array
    {
        if ($this->active !== null) {
            return $this->active;
        }

        try {
            $registry = PluginRegistry::fromDirectory(self::pluginsDir());
            if ($registry->all() === []) {
                return $this->active = [];
            }

            $repository = new PluginRepository($this->pdo);
            $repository->syncDiscovered($registry->all());
            $registry->resolve($repository->enabledIds(), self::ignisVersion());

            foreach ($registry->skipped() as $skip) {
                Logger::warning("Plugin '{$skip['id']}' übersprungen: {$skip['reason']}");
            }

            return $this->active = $registry->active();
        } catch (\Throwable $e) {
            // z.B. intra_plugins existiert noch nicht — Boot geht ohne
            // Plugins weiter, die nächste Anfrage nach den Migrationen
            // lädt sie dann normal.
            Logger::warning('Plugins konnten nicht geladen werden: ' . $e->getMessage());
            return $this->active = [];
        }
    }

    /**
     * Ist ein bestimmtes Plugin installiert UND aktiv? Für Kern-Code, der
     * modulübergreifende Inhalte nur anbieten soll, wenn das Modul läuft
     * (z.B. Suchergebnisse eines abgeschalteten Moduls unterdrücken).
     */
    public function isActive(string $pluginId): bool
    {
        foreach ($this->active() as $plugin) {
            if ($plugin->id() === $pluginId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Registriert die PSR-4-Autoload-Maps der aktiven Plugins. Muss im
     * Bootstrap laufen, bevor Plugin-Klassen (Controller, Listener,
     * Policies) referenziert werden.
     */
    public function registerAutoloading(): void
    {
        foreach ($this->active() as $plugin) {
            foreach ($plugin->manifest->autoload as $prefix => $relativeDir) {
                $baseDir = $plugin->directory . DIRECTORY_SEPARATOR . trim($relativeDir, '/\\');
                spl_autoload_register(static function (string $class) use ($prefix, $baseDir): void {
                    if (!str_starts_with($class, $prefix)) {
                        return;
                    }
                    $relative = substr($class, strlen($prefix));
                    $file = $baseDir . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
                    if (is_file($file)) {
                        require $file;
                    }
                });
            }
        }
    }

    /**
     * Registriert die Gate-Policies der aktiven Plugins (Manifest-Feld
     * `policies`: Ressource => Policy-FQCN).
     */
    public function registerPolicies(): void
    {
        foreach ($this->active() as $plugin) {
            foreach ($plugin->manifest->policies as $resource => $policyClass) {
                \App\Auth\Gate::registerPolicy($resource, $policyClass);
            }
        }
    }

    /**
     * Routen-Fragmente der aktiven Plugins (web zuerst, dann api —
     * gleiche Reihenfolge wie die Kern-Routen).
     *
     * @return list<string>
     */
    public function routeFiles(): array
    {
        $files = [];
        foreach (['routes.web.php', 'routes.api.php'] as $fragment) {
            foreach ($this->active() as $plugin) {
                $file = $plugin->path($fragment);
                if ($file !== null) {
                    $files[] = $file;
                }
            }
        }
        return $files;
    }

    /**
     * Hängt die Navigations-Einträge der aktiven Plugins an die Rail an.
     * Ein Plugin liefert in navigation.php eine Liste von Rail-Einträgen
     * im selben Format wie config/navigation.php.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function mergeNavigation(array $config): array
    {
        $rail = is_array($config['rail'] ?? null) ? $config['rail'] : [];

        foreach ($this->active() as $plugin) {
            $file = $plugin->path('navigation.php');
            if ($file === null) {
                continue;
            }
            $fragment = require $file;
            if (is_array($fragment)) {
                foreach ($fragment as $entry) {
                    if (is_array($entry)) {
                        $rail[] = $entry;
                    }
                }
            }
        }

        $config['rail'] = $rail;
        return $config;
    }

    /**
     * Mergt die Event→Listener-Maps der aktiven Plugins in die Kern-Map.
     * Listener desselben Events werden angehängt, nie ersetzt.
     *
     * @param array<string, list<string>> $eventMap Event-FQCN => Listener-FQCNs
     * @return array<string, list<string>>
     */
    public function mergeEventMap(array $eventMap): array
    {
        foreach ($this->active() as $plugin) {
            $file = $plugin->path('events.php');
            if ($file === null) {
                continue;
            }
            $fragment = require $file;
            if (!is_array($fragment)) {
                continue;
            }
            foreach ($fragment as $eventClass => $listeners) {
                foreach ((array) $listeners as $listener) {
                    $eventMap[$eventClass][] = $listener;
                }
            }
        }
        return $eventMap;
    }

    /**
     * Hängt die Console-Commands der aktiven Plugins an die Kern-Liste an.
     *
     * @param list<string> $commands Command-FQCNs
     * @return list<string>
     */
    public function mergeConsoleCommands(array $commands): array
    {
        foreach ($this->active() as $plugin) {
            $file = $plugin->path('console.php');
            if ($file === null) {
                continue;
            }
            $fragment = require $file;
            if (is_array($fragment)) {
                foreach ($fragment as $commandClass) {
                    if (is_string($commandClass)) {
                        $commands[] = $commandClass;
                    }
                }
            }
        }
        return $commands;
    }

    /**
     * Mergt die Permission-Kataloge der aktiven Plugins (permissions.php,
     * gleiche Gruppen-Struktur wie config/permissions.php) in den
     * Kern-Katalog. Gleichnamige Gruppen werden zusammengeführt.
     *
     * @param array<string, array<string, string>> $groups
     * @return array<string, array<string, string>>
     */
    public function mergePermissionGroups(array $groups): array
    {
        foreach ($this->active() as $plugin) {
            $file = $plugin->path('permissions.php');
            if ($file === null) {
                continue;
            }
            $fragment = require $file;
            if (!is_array($fragment)) {
                continue;
            }
            foreach ($fragment as $group => $permissions) {
                if (!is_array($permissions)) {
                    continue;
                }
                $groups[$group] = array_merge($groups[$group] ?? [], $permissions);
            }
        }
        return $groups;
    }

    /**
     * Laufende ignis-Version aus storage/version.json; null in
     * Entwicklungs-Checkouts ohne Release-Build (dann gelten alle
     * Plugins als kompatibel).
     */
    private static function ignisVersion(): ?string
    {
        $file = dirname(__DIR__, 2) . '/storage/version.json';
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) && !empty($data['version']) ? (string) $data['version'] : null;
    }
}
