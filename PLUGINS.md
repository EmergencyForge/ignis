# Plugins für ıgnıs

Diese Datei beschreibt den öffentlichen Plugin-Vertrag für ıgnıs 1.x. Nur
die hier ausdrücklich dokumentierten Schnittstellen sind stabil. Interne
Klassen, Templates, Datenbanktabellen und nicht aufgeführte Events dürfen sich
innerhalb einer Hauptversion ändern.

## Verzeichnisstruktur

Ein Release-ZIP enthält `manifest.php` direkt im Archiv-Root. Das Manifest ist
eine reine `return [...]`-Datei aus skalaren Literalen und Arrays; ausführbarer
Code, Funktionsaufrufe oder dynamische Konstanten sind nicht erlaubt.

```text
manifest.php
routes.web.php       optional
routes.api.php       optional
navigation.php       optional
permissions.php      optional
events.php           optional
console.php          optional
migrations/          optional
src/                 optional
assets/plugin.css    optional, fertig kompiliert
assets/plugin.js     optional, fertig kompiliert
```

Pflichtfelder des Manifests sind `id`, `name` und `version`. `requires.ignis`
grenzt kompatible ıgnıs-Versionen ein; `depends` nennt andere Plugin-IDs.
`autoload`, `policies`, `permissions`, `default_enabled` und `removable`
entsprechen den Beispielen der mitgelieferten Plugins unter `plugins/`.

Ein heruntergeladenes Plugin bleibt vollständig inert. Erst die separate
Installationsbestätigung in der Verwaltung legt den `.installed`-Marker an,
führt Migrationen aus und aktiviert das Plugin.

## Assets

Plugins bringen kompiliertes `assets/plugin.css` beziehungsweise
`assets/plugin.js` mit. Aktive Plugins werden automatisch in die Basis-Seiten
eingebunden. Ein Tailwind-, Sass- oder JavaScript-Build auf dem Zielserver
findet nicht statt. Öffentlich erreichbar sind ausschließlich statische
Dateien unter `assets/` mit der serverseitigen Endungs-Allowlist.

## Geplante Aufgaben

Ein Plugin registriert einen Symfony-Console-Command über `console.php` und
legt den Zeitplan per Migration in `intra_cron_jobs` mit
`handler_type = console` an. Ist das Plugin deaktiviert, wird der Lauf als
`skipped` protokolliert und in der Cron-Verwaltung als „Plugin inaktiv“
gekennzeichnet. Ein eigenes `cron.php`-Register gibt es nicht.

## Öffentlicher Event-Katalog v1

Die folgenden Events und ihre öffentlichen Konstruktor-Properties gelten in
ıgnıs 1.x als stabil, solange das jeweilige mitgelieferte Plugin aktiv ist:

- `Plugin\Firetab\Events\FireProtocolReleased`: `incidentData` (`array`)
- `Plugin\Enotf\Events\EnotfProtocolReleased`: `protocolData` (`array`)
- `Plugin\Enotf\Events\EnotfPreregistered`: `preregData` (`array`)

Listener werden in `events.php` als Event-FQCN zu einer Liste von
Listener-FQCNs gemappt. Jeder Listener besitzt `handle(EventClass $event):
void`. Listener-Fehler werden geloggt und dürfen den auslösenden Request nicht
blockieren. Nicht in diesem Katalog aufgeführte Events sind intern und können
sich ohne Major-Bump ändern.

## Katalog und Einreichung

Der Katalog verweist auf ein öffentliches GitHub-Release-ZIP und einen
SHA256-Digest. ıgnıs akzeptiert nur HTTPS-Downloads von GitHub, prüft Größe,
Digest, Archivpfade, Manifest-ID und Versionskompatibilität und verschiebt das
Plugin anschließend atomar nach `plugins/<id>/`.

Neue Plugins werden über das GitHub-Issue-Template „Plugin einreichen“
vorgeschlagen. Ungetestete Community-Plugins werden entsprechend markiert;
Quellcode, Support und Release-Artefakte bleiben beim jeweiligen Herausgeber.

## Entfernen und Daten

„Entfernen“ löscht bei einem deaktivierten Community-Plugin nur dessen
Dateien. Tabellen und Daten bleiben absichtlich bestehen. Eine Deinstallation
mit Datenlöschung und verpflichtendem Export ist noch kein öffentlicher
Vertrag und muss separat entworfen werden.
