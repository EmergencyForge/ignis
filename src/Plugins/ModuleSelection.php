<?php

declare(strict_types=1);

namespace App\Plugins;

use App\Utils\AuditLogger;
use EmergencyForge\Plugins\PluginRegistry;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Die Modulauswahl: welche der mitgelieferten Plugins eine Installation
 * benutzt. Grundmodule (Personal, Benutzer, Dokumente, Fahrzeuge) gehören
 * zum Kern und stehen hier nicht zur Wahl.
 *
 * Die Seite Einstellungen › System › Module zeigt die Auswahl; beim ersten
 * Start ist sie der erste Schritt der Einrichtungs-Checkliste. Gespeichert
 * wird über PluginRepository wie beim Schalter in der Plugin-Verwaltung,
 * danach gilt der Schritt als erledigt (`SETUP_MODULES_DONE`).
 */
final class ModuleSelection
{
    public const DONE_KEY = 'SETUP_MODULES_DONE';

    /**
     * Mitgelieferte, abschaltbare Plugins in Anzeigereihenfolge: ID => [Gruppe,
     * Beschreibung]. Die Texte stehen hier, weil Manifeste keine Beschreibung
     * kennen und die Liste ohnehin zu PluginLoader::BUNDLED gehört.
     */
    public const MODULES = [
        'enotf'          => ['Einsatz', 'Elektronisches Notfallprotokoll für den Rettungsdienst, mit Voranmeldung im Krankenhaus.'],
        'firetab'        => ['Einsatz', 'Einsatzprotokolle der Feuerwehr mit Lagekarte und Atemschutzüberwachung.'],
        'manv-board'     => ['Einsatz', 'Übersicht bei einem Massenanfall von Verletzten.'],
        'logbook'        => ['Einsatz', 'Fahrtenbuch der Fahrzeuge, auch aus eNOTF und fireTab heraus.'],
        'forms'          => ['Verwaltung', 'Anträge wie Urlaub oder Beförderung, mit eigenen Antragstypen.'],
        'calendar'       => ['Verwaltung', 'Termine und Dienste; genehmigter Urlaub erscheint als Abwesenheit.'],
        'mail'           => ['Verwaltung', 'Internes Mailsystem mit Postfächern für Mitarbeiter und Gruppen.'],
        'knowledge-base' => ['Verwaltung', 'Lexikon mit Artikeln, Kategorien und Schlagworten.'],
    ];

    public function __construct(
        private readonly PluginRegistry $registry,
        private readonly PluginRepository $repository,
    ) {
    }

    public static function fromDirectory(): self
    {
        return new self(PluginRegistry::fromDirectory(PluginLoader::pluginsDir()), new PluginRepository());
    }

    /**
     * Die wählbaren Module, die es in dieser Installation gibt.
     *
     * @return list<array{id:string, name:string, group:string, text:string, enabled:bool, depends:list<string>}>
     */
    public function modules(): array
    {
        $this->repository->syncDiscovered($this->registry->all());
        $enabled = $this->repository->enabledIds();

        $rows = [];
        foreach (self::MODULES as $id => [$group, $text]) {
            $plugin = $this->registry->get($id);
            if ($plugin === null || !$plugin->manifest->removable) {
                continue;
            }
            $rows[] = [
                'id'      => $id,
                'name'    => $plugin->manifest->name,
                'group'   => $group,
                'text'    => $text,
                'enabled' => in_array($id, $enabled, true),
                'depends' => array_map('strval', $plugin->manifest->depends),
            ];
        }

        return $rows;
    }

    /**
     * Prüft eine Auswahl gegen die Abhängigkeiten: jedes gewählte Modul
     * braucht seine Abhängigkeiten ebenfalls gewählt.
     *
     * @param list<string>                $selected IDs der gewählten Module
     * @param array<string, list<string>> $depends  ID => IDs, die es braucht
     * @param array<string, string>       $names    ID => Anzeigename
     * @return list<string> Meldungen, leer heißt in Ordnung
     */
    public static function validate(array $selected, array $depends, array $names): array
    {
        $errors = [];
        foreach ($selected as $id) {
            foreach ($depends[$id] ?? [] as $dependency) {
                if (array_key_exists($dependency, $depends) && !in_array($dependency, $selected, true)) {
                    $errors[] = ($names[$id] ?? $id) . ' braucht ' . ($names[$dependency] ?? $dependency) . '.';
                }
            }
        }

        return $errors;
    }

    /**
     * Übernimmt die Auswahl. Schaltet nur, was sich ändert, schreibt jede
     * Änderung ins Audit-Log und markiert den Einrichtungsschritt als
     * erledigt.
     *
     * @param list<string> $selected
     * @return list<string> Fehlermeldungen; bei Fehlern ändert sich nichts
     */
    public function apply(array $selected, int $userId): array
    {
        $modules = $this->modules();
        $depends = array_column($modules, 'depends', 'id');
        $names   = array_column($modules, 'name', 'id');
        $selected = array_values(array_intersect(array_keys($depends), $selected));

        $errors = self::validate($selected, $depends, $names);
        if ($errors !== []) {
            return $errors;
        }

        foreach ($modules as $module) {
            $want = in_array($module['id'], $selected, true);
            if ($want === $module['enabled']) {
                continue;
            }
            $this->repository->setEnabled($module['id'], $want);
            (new AuditLogger())->log(
                $userId,
                $want ? 'Plugin aktiviert' : 'Plugin deaktiviert',
                $module['name'],
                'Plugins',
                1,
                ['plugin_id' => $module['id'], 'source' => 'module_selection'],
            );
        }

        self::markDone();
        return [];
    }

    /** Hat die Installation ihre Module schon ausgewählt? */
    public static function isDone(): bool
    {
        try {
            $value = Capsule::table('intra_config')->where('config_key', self::DONE_KEY)->value('config_value');
        } catch (\Throwable) {
            return true;
        }

        // Ohne Zeile (Migration noch nicht gelaufen) nicht nachfragen.
        return $value === null || $value === 'true' || $value === '1';
    }

    public static function markDone(): void
    {
        Capsule::table('intra_config')
            ->where('config_key', self::DONE_KEY)
            ->update(['config_value' => 'true']);
    }
}
