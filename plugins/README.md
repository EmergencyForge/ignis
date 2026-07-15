# Plugins

Hier liegen die installierten ignis-Plugins — je ein Unterordner mit einer
`manifest.php`. Der `PluginRegistry` entdeckt sie beim Boot, der
`PluginRepository` (Tabelle `intra_plugins`) entscheidet, welche aktiv sind.

Die Module **eNOTF, fireTab, MANV-Board und Wissensdatenbank** werden als
Plugins ausgeliefert; alle übrigen Funktionen sind fester Bestandteil des
Cores. Details zum Aufbau: [docs/design/plugin-system.md](../docs/design/plugin-system.md).

## Aufbau eines Plugins

```
plugins/<id>/
  manifest.php        Pflicht: Metadaten, Kompatibilität, Abhängigkeiten
  routes.web.php      optional: web-Routen (bekommt $router)
  routes.api.php      optional: API-Routen
  navigation.php      optional: Nav-Fragment (rail/sections)
  events.php          optional: Event → Listener-Map
  console.php         optional: Console-Command-Klassen
  cron.php            optional: Cron-Job-Definitionen
  migrations/         optional: eigene Phinx-Migrations (Prefix plugin_<id>_*)
  templates/          optional: Views
  src/                optional: Controller, Services (autowired)
```

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
