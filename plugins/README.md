# Plugins

Hier liegen die installierten ignis-Plugins — je ein Unterordner mit einer
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
jeweiligen Register gemergt. Zwei Besonderheiten:

- **Migrations laufen immer** — auch für deaktivierte Plugins. Deaktivieren
  entfernt Routen/Nav/Listener, lässt Tabellen und Daten aber unangetastet,
  damit beim Reaktivieren nichts fehlt.
- **Plugin-Routen können Kern-Routen nicht überschreiben**, sie werden nach
  den Kern-Routen registriert.

Das Manifest ist die einzige Pflichtdatei:

```php
<?php

return [
    'id'              => 'enotf',
    'name'            => 'eNOTF – Notfallprotokolle',
    'version'         => '1.0.0',
    'vendor'          => 'EmergencyForge',
    'requires'        => ['ignis' => '>=1.2 <2.0'],
    'depends'         => [],
    'permissions'     => ['enotf.view', 'enotf.edit', 'enotf.admin'],
    'default_enabled' => true,
    'removable'       => true,
];
```
