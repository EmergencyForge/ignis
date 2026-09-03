<?php

declare(strict_types=1);

namespace App\Plugins;

use App\Logging\Logger;

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
    /**
     * Offiziell mitgelieferte Plugins. Die Liste lebt bewusst im Core und
     * nicht in den Manifesten — ein fremdes Plugin könnte sich sonst
     * selbst zum vertrauenswürdigen Bestandteil erklären. Nur Plugins auf
     * dieser Liste gelten ohne manuelle Installation als installiert.
     */
    public const BUNDLED = [
        'knowledge-base',
        'manv-board',
        'enotf',
        'enotf-v2',
        'firetab',
    ];

    /**
     * Marker-Datei, die ein manuell installiertes (nicht mitgeliefertes)
     * Plugin als freigegeben kennzeichnet. Eine Datei statt eines
     * DB-Flags, damit auch phinx.php — das ohne Datenbank in der CLI
     * läuft — installierte von bloß hochkopierten Plugins unterscheiden
     * kann.
     */
    private const INSTALLED_MARKER = '.installed';

    /** @var list<Plugin>|null */
    private ?array $active = null;

    public static function pluginsDir(): string
    {
        return dirname(__DIR__, 2) . '/plugins';
    }

    public static function isBundled(string $pluginId): bool
    {
        return in_array($pluginId, self::BUNDLED, true);
    }

    /**
     * Installiert = mitgeliefert ODER vom Admin ausdrücklich freigegeben.
     * Nicht installierte Plugins werden weder geladen noch migriert —
     * ein nach plugins/ kopiertes Fremd-Archiv führt keinerlei Code aus,
     * bis jemand die Installation bewusst startet.
     */
    public static function isInstalledDir(string $pluginId, string $directory): bool
    {
        return self::isBundled($pluginId)
            || is_file($directory . DIRECTORY_SEPARATOR . self::INSTALLED_MARKER);
    }

    public static function isInstalled(Plugin $plugin): bool
    {
        return self::isInstalledDir($plugin->id(), $plugin->directory);
    }

    /**
     * Kennzeichnet ein Fremd-Plugin als installiert. Erst danach nimmt
     * der Migrationslauf seine Migrations mit und der Loader lädt es.
     */
    public static function markInstalled(Plugin $plugin): bool
    {
        $marker = $plugin->directory . DIRECTORY_SEPARATOR . self::INSTALLED_MARKER;
        return @file_put_contents($marker, date('c') . "\n") !== false;
    }

    /**
     * Migrations-Verzeichnisse aller INSTALLIERTEN Plugins — bewusst ohne
     * Blick in die Datenbank. Schema-Migrationen laufen auch für
     * deaktivierte (aber installierte) Plugins, damit Deaktivieren nie
     * Daten oder Schema zurücklässt, das beim Reaktivieren fehlt. Diese
     * Liste wird auch von phinx.php gebraucht, das ohne App-Bootstrap in
     * der CLI läuft.
     *
     * @return list<string>
     */
    public static function migrationPaths(): array
    {
        $paths = [];
        foreach (glob(self::pluginsDir() . '/*/migrations', GLOB_ONLYDIR) ?: [] as $dir) {
            $pluginId = basename(dirname($dir));
            if (self::isInstalledDir($pluginId, dirname($dir))) {
                $paths[] = $dir;
            }
        }
        sort($paths);
        return array_values($paths);
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

            $repository = new PluginRepository();
            $repository->syncDiscovered($registry->all());

            // Nur installierte Plugins kommen in die Auflösung — ein bloß
            // nach plugins/ kopiertes Fremd-Plugin bleibt vollständig
            // inert, bis der Admin die Installation ausdrücklich startet.
            $enabledIds = array_values(array_filter(
                $repository->enabledIds(),
                function (string $id) use ($registry): bool {
                    $plugin = $registry->get($id);
                    if ($plugin === null) {
                        return false;
                    }
                    if (!self::isInstalled($plugin)) {
                        Logger::warning("Plugin '{$id}' übersprungen: Installation wurde noch nicht bestätigt.");
                        return false;
                    }
                    return true;
                },
            ));

            $registry->resolve($enabledIds, self::ignisVersion());

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
     * Hängt die Navigations-Einträge der aktiven Plugins an die Gruppen
     * aus config/navigation.php an. Ein Plugin liefert in navigation.php
     * eine Liste von Fragmenten: Gruppen mit `items` wie in der Kern-Datei.
     *
     * `merge_into`: Statt einer eigenen Gruppe hängt ein Fragment seine
     * Einträge an eine bestehende Gruppe, z.B. unter „Protokolle". Fehlt
     * die Zielgruppe (Kern umgebaut), wird das Fragment eine eigene Gruppe
     * statt zu verschwinden.
     *
     * Fragmente im älteren Rail-Schema (`sections` mit `items`, oder ein
     * Einzellink mit `href`) werden weiterhin gelesen; eNOTF liefert so.
     * Ihre Einträge erben Icon und Permissions der Section bzw. des
     * Fragments, weil das alte Schema die nur dort kannte.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function mergeNavigation(array $config): array
    {
        $groups = is_array($config['groups'] ?? null) ? array_values($config['groups']) : [];

        foreach ($this->active() as $plugin) {
            $file = $plugin->path('navigation.php');
            if ($file === null) {
                continue;
            }
            $fragment = require $file;
            if (!is_array($fragment)) {
                continue;
            }
            foreach ($fragment as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $items = self::navigationItems($entry);

                $target = $entry['merge_into'] ?? null;
                if (is_string($target) && $target !== '') {
                    $merged = false;
                    foreach ($groups as &$group) {
                        if (is_array($group) && ($group['id'] ?? null) === $target) {
                            $existing = is_array($group['items'] ?? null) ? array_values($group['items']) : [];
                            $group['items'] = array_merge($existing, $items);
                            $merged = true;
                            break;
                        }
                    }
                    unset($group);
                    if ($merged) {
                        continue;
                    }
                    unset($entry['merge_into']);
                }

                if ($items === []) {
                    continue;
                }
                unset($entry['sections'], $entry['href'], $entry['quick_action'], $entry['external'], $entry['match'], $entry['data_page']);
                $entry['items'] = $items;
                $groups[] = $entry;
            }
        }

        // Gruppen ohne Einträge sind Anker, an die kein aktives Plugin
        // etwas gehängt hat — „Protokolle" ohne Protokoll-Plugin. Sie
        // fallen weg statt als leere Überschrift stehen zu bleiben.
        $groups = array_values(array_filter($groups, static function ($group): bool {
            return is_array($group) && !empty($group['items']);
        }));

        $config['groups'] = $groups;
        return $config;
    }

    /**
     * Die Einträge eines Navigations-Fragments, gleich in welchem Schema es
     * geliefert wurde: `items` direkt, `sections` mit `items` (altes Rail-
     * Schema) oder ein Einzellink mit `href`.
     *
     * @param array<string, mixed> $entry
     * @return list<array<string, mixed>>
     */
    private static function navigationItems(array $entry): array
    {
        $icon = is_string($entry['icon'] ?? null) && $entry['icon'] !== '' ? $entry['icon'] : 'fa-solid fa-circle-dot';
        $items = [];

        foreach ((array) ($entry['items'] ?? []) as $item) {
            if (is_array($item)) {
                $items[] = $item + ['icon' => $icon];
            }
        }

        foreach ((array) ($entry['sections'] ?? []) as $section) {
            if (!is_array($section)) {
                continue;
            }
            $sectionIcon = is_string($section['icon'] ?? null) && $section['icon'] !== '' ? $section['icon'] : $icon;
            foreach ((array) ($section['items'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $item += ['icon' => $sectionIcon];
                if (!isset($item['permissions']) && isset($section['permissions'])) {
                    $item['permissions'] = $section['permissions'];
                }
                $items[] = $item;
            }
        }

        if ($items === [] && is_string($entry['href'] ?? null) && $entry['href'] !== '') {
            $link = [
                'label' => (string) ($entry['label'] ?? ''),
                'href'  => $entry['href'],
                'icon'  => $icon,
            ];
            foreach (['permissions', 'quick_action', 'external', 'match'] as $key) {
                if (isset($entry[$key])) {
                    $link[$key] = $entry[$key];
                }
            }
            $items[] = $link;
        }

        return $items;
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
     * Kompilierte CSS-/JS-Dateien aktiver Plugins. Plugins liefern diese
     * Dateien fertig gebaut aus; ignis führt keinen Build zur Laufzeit aus.
     *
     * @return array{css:list<string>,js:list<string>}
     */
    public function assetFiles(): array
    {
        $assets = ['css' => [], 'js' => []];
        foreach ($this->active() as $plugin) {
            foreach (['css' => 'assets/plugin.css', 'js' => 'assets/plugin.js'] as $type => $relative) {
                if ($plugin->path($relative) !== null) {
                    $assets[$type][] = 'plugins/' . rawurlencode($plugin->id()) . '/' . $relative;
                }
            }
        }
        return $assets;
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
    public static function ignisVersion(): ?string
    {
        $file = dirname(__DIR__, 2) . '/storage/version.json';
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) && !empty($data['version']) ? (string) $data['version'] : null;
    }
}
