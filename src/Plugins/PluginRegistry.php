<?php

declare(strict_types=1);

namespace App\Plugins;

/**
 * Entdeckt Plugins auf der Platte und löst auf, welche davon aktiv sind.
 *
 * Ein Plugin ist aktiv, wenn ALLE gelten:
 *   1. es ist in der DB als aktiviert markiert (enabled-Set),
 *   2. sein `requires: ignis` passt zur laufenden ignis-Version,
 *   3. alle `depends` sind selbst aktiv (transitiv).
 *
 * Nicht erfüllte Bedingungen führen nicht zum Fehler, sondern zum
 * Überspringen mit einem nachvollziehbaren Grund (siehe skipped()). So
 * bootet ignis auch dann sauber, wenn ein einzelnes Plugin inkompatibel
 * ist. Aktive Plugins werden in Abhängigkeitsreihenfolge zurückgegeben
 * (Abhängigkeit vor Abhängigem), damit die Register-Merger deterministisch
 * arbeiten.
 */
final class PluginRegistry
{
    /** @var array<string, Plugin> discovered plugins keyed by id */
    private array $discovered;

    /** @var list<Plugin> resolved active plugins in load order */
    private array $active = [];

    /** @var list<array{id: string, reason: string}> */
    private array $skipped = [];

    /**
     * @param array<string, Plugin> $discovered
     */
    public function __construct(array $discovered)
    {
        $this->discovered = $discovered;
    }

    /**
     * Scannt ein Plugins-Verzeichnis (`plugins/<id>/manifest.php`). Ordner
     * ohne Manifest werden ignoriert; ein defektes Manifest wird als
     * übersprungen vermerkt statt den Boot zu sprengen.
     */
    public static function fromDirectory(string $pluginsDir): self
    {
        $discovered = [];
        $skipped = [];

        foreach (glob($pluginsDir . '/*/manifest.php') ?: [] as $manifestFile) {
            $dir = dirname($manifestFile);
            try {
                $data = require $manifestFile;
                if (!is_array($data)) {
                    throw new \InvalidArgumentException('manifest.php gibt kein Array zurück.');
                }
                $manifest = PluginManifest::fromArray($data);
                $discovered[$manifest->id] = new Plugin($manifest, $dir);
            } catch (\Throwable $e) {
                $skipped[] = ['id' => basename($dir), 'reason' => 'Ungültiges Manifest: ' . $e->getMessage()];
            }
        }

        $registry = new self($discovered);
        $registry->skipped = $skipped;
        return $registry;
    }

    /**
     * Berechnet das aktive Set für die gegebenen aktivierten IDs und die
     * laufende ignis-Version. Ohne bekannte Version (Entwicklungs-Checkout
     * ohne Release-Build) entfällt die Kompatibilitätsprüfung. Idempotent —
     * kann erneut mit anderem Set aufgerufen werden.
     *
     * @param list<string> $enabledIds
     */
    public function resolve(array $enabledIds, ?string $ignisVersion): void
    {
        $this->active = [];
        $enabled = array_fill_keys($enabledIds, true);

        // 1) Kandidaten: entdeckt UND aktiviert.
        $candidates = [];
        foreach ($this->discovered as $id => $plugin) {
            if (!isset($enabled[$id])) {
                continue; // deaktiviert — kein Skip-Grund, bewusst aus
            }
            if ($ignisVersion !== null && !$plugin->manifest->isCompatibleWith($ignisVersion)) {
                $this->skipped[] = [
                    'id' => $id,
                    'reason' => "Benötigt ignis {$plugin->manifest->ignisRequire}, läuft auf {$ignisVersion}.",
                ];
                continue;
            }
            $candidates[$id] = $plugin;
        }

        // 2) Kaskadierend Kandidaten entfernen, deren Abhängigkeiten fehlen.
        do {
            $removed = false;
            foreach ($candidates as $id => $plugin) {
                foreach ($plugin->manifest->depends as $dep) {
                    if (!isset($candidates[$dep])) {
                        $reason = isset($this->discovered[$dep])
                            ? "Abhängigkeit '{$dep}' ist nicht aktiv."
                            : "Abhängigkeit '{$dep}' ist nicht installiert.";
                        $this->skipped[] = ['id' => $id, 'reason' => $reason];
                        unset($candidates[$id]);
                        $removed = true;
                        break;
                    }
                }
            }
        } while ($removed);

        // 3) Topologisch sortieren (Kahn): Abhängigkeiten zuerst.
        $this->active = $this->topologicalSort($candidates);
    }

    /**
     * @param array<string, Plugin> $candidates
     * @return list<Plugin>
     */
    private function topologicalSort(array $candidates): array
    {
        $sorted = [];
        $visited = [];

        // Deterministische Reihenfolge über alphabetische ID-Sortierung.
        $ids = array_keys($candidates);
        sort($ids);

        foreach ($ids as $id) {
            $this->visit($id, $candidates, [], $visited, $sorted);
        }

        return $sorted;
    }

    /**
     * Tiefensuche für die topologische Sortierung. Besuchte Knoten werden
     * markiert; ein Knoten, der bereits im aktuellen Pfad liegt, zeigt einen
     * Zyklus an und wird mit Grund übersprungen.
     *
     * @param array<string, Plugin> $candidates
     * @param list<string>          $trail
     * @param array<string, true>   $visited
     * @param list<Plugin>          $sorted
     */
    private function visit(string $id, array $candidates, array $trail, array &$visited, array &$sorted): void
    {
        if (isset($visited[$id])) {
            return;
        }
        if (in_array($id, $trail, true)) {
            $this->skipped[] = [
                'id' => $id,
                'reason' => 'Zyklische Abhängigkeit: ' . implode(' → ', [...$trail, $id]),
            ];
            return;
        }

        $deps = $candidates[$id]->manifest->depends;
        sort($deps);
        foreach ($deps as $dep) {
            if (isset($candidates[$dep])) {
                $this->visit($dep, $candidates, [...$trail, $id], $visited, $sorted);
            }
        }

        $visited[$id] = true;
        $sorted[] = $candidates[$id];
    }

    /**
     * Aktive Plugins in Ladereihenfolge.
     *
     * @return list<Plugin>
     */
    public function active(): array
    {
        return $this->active;
    }

    /**
     * Alle entdeckten Plugins (unabhängig vom Aktiv-Status).
     *
     * @return array<string, Plugin>
     */
    public function all(): array
    {
        return $this->discovered;
    }

    public function get(string $id): ?Plugin
    {
        return $this->discovered[$id] ?? null;
    }

    /**
     * Diagnose: welche Plugins wurden warum übersprungen.
     *
     * @return list<array{id: string, reason: string}>
     */
    public function skipped(): array
    {
        return $this->skipped;
    }
}
