# Plugins

Hier liegen die installierten ignis-Plugins, je ein Unterordner mit einer
`manifest.php`. Der `PluginRegistry` entdeckt sie beim Boot, der
`PluginRepository` (Tabelle `intra_plugins`) entscheidet, welche aktiv sind.

Die Module **eNOTF, fireTab, MANV-Board und Wissensdatenbank** werden als
Plugins ausgeliefert; alle übrigen Funktionen sind fester Bestandteil des
Cores.

## Aufbau eines Plugins

```
plugins/<id>/
  manifest.php        Pflicht: Metadaten, Kompatibilität, Abhängigkeiten
  routes.web.php      optional: web-Routen (bekommt $router, lädt nach den Kern-Routen)
  routes.api.php      optional: API-Routen
  navigation.php      optional: Liste von Rail-Einträgen (Format wie config/navigation.php)
  events.php          optional: Event → Listener-Map (wird an die Kern-Map angehängt)
  console.php         optional: Liste von Console-Command-Klassen
  permissions.php     optional: Permission-Katalog (Gruppen-Format wie config/permissions.php)
  migrations/         optional: eigene Phinx-Migrations (Tabellen-Prefix plugin_<id>_*)
  templates/          optional: Views
  src/                optional: Controller, Services (autowired)
```

Die Fragmente aktiver Plugins werden beim Boot vom `PluginLoader` in die
jeweiligen Register gemergt. Drei Besonderheiten:

- **Fremde Plugins sind erst nach ausdrücklicher Installation aktiv.** Nur
  die offiziell mitgelieferten Plugins (Liste `PluginLoader::BUNDLED` im
  Core) laufen ohne weiteres Zutun. Alles andere bleibt nach dem Ablegen in
  `plugins/` vollständig inert (kein Code, keine Migration), bis ein Admin
  die Installation in den Systemeinstellungen startet (schreibt die
  Marker-Datei `.installed` in den Plugin-Ordner).
- **Migrations installierter Plugins laufen immer**, auch für deaktivierte.
  Deaktivieren entfernt Routen/Nav/Listener, lässt Tabellen und Daten aber
  unangetastet, damit beim Reaktivieren nichts fehlt.
- **Plugin-Routen können Kern-Routen nicht überschreiben**, sie werden nach
  den Kern-Routen registriert.

Verwaltet werden Plugins unter **Einstellungen → System → Plugins**.

Das Manifest ist die einzige Pflichtdatei:

```php
<?php

return [
    'id'              => 'enotf',
    'name'            => 'eNOTF',
    'version'         => '1.0.0',
    'vendor'          => 'EmergencyForge',
    'requires'        => ['ignis' => '>=2026.0.6-beta'],
    'depends'         => [],
    'permissions'     => ['enotf.view', 'enotf.edit', 'enotf.admin'],
    // PSR-4-Autoloading für die Plugin-Klassen (Prefix => Ordner im Plugin)
    'autoload'        => ['Plugin\\Enotf\\' => 'src/'],
    // Gate-Policies: Ressource => Policy-Klasse
    'policies'        => ['enotf' => 'Plugin\\Enotf\\Policies\\EnotfPolicy'],
    'default_enabled' => true,
    'removable'       => true,
];
```

Controller in Plugins erben von `App\Http\Controllers\Controller` und
überschreiben `viewBasePath()`, damit `renderView()` die Views aus dem
eigenen `templates/`-Verzeichnis lädt.

## Eigene Quelle für die globale Suche

Das Manifest-Feld `search` trägt eine Liste voll qualifizierter
Klassennamen ein, die `App\Search\SearchSourceInterface` implementieren:

```php
'search' => ['Plugin\\Firetab\\Search\\IncidentSource'],
```

`SearchRegistry` fragt jede Quelle, die `allowed()` bejaht, mit demselben
Suchwort ab (Palette in der Topbar, `GET /api/system/global-search`) und
zeigt höchstens fünf Treffer je Quelle. Eine Klasse, die nicht existiert
oder das Interface nicht implementiert, wird beim Laden übersprungen statt
den Boot abzubrechen.

Geht es um Namen oder Titel statt um Kennungen, kann die Quelle zusätzlich
`App\Search\FuzzySearchSource` implementieren (`extends
SearchSourceInterface`, eine Methode `vocabulary(): iterable<string>`).
Liefert `search()` dann weniger als das Limit, erweitert die Registry die
Suchworte über ein aus `vocabulary()` gebautes, gecachtes Vokabular
(Tippfehler- und Umlauttoleranz über `emergencyforge/fuzzy-search`) und
fragt dieselbe `search()` erneut ab, Rechte und Zeilenfilter der Quelle
bleiben dabei unverändert, weil sie sich selbst wieder abfragt. Treffer aus
dieser zweiten Runde tragen `approx: true`, die Palette zeigt dafür den
Zusatz „ähnlich". Kennungen (IDs, Aktenzeichen, Einsatznummern) gehören nie
ins Vokabular. Ein Tippfehler dort soll keinen falschen Treffer erzeugen.
Das Opt-in ist rein additiv: eine Quelle ohne `FuzzySearchSource` bleibt
unverändert bei der scharfen Suche.
