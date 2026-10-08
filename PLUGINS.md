# Plugins für ıgnıs

Diese Datei beschreibt den öffentlichen Plugin-Vertrag für ıgnıs 2026.x
(Versionen nach dem Schema `vJAHR.GROSS.KLEIN`, etwa `v2026.0.13-beta`). Nur
die hier ausdrücklich dokumentierten Schnittstellen sind stabil. Interne
Klassen, Templates, Datenbanktabellen und nicht aufgeführte Events dürfen sich
auch innerhalb von 2026.x ändern.

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
counters.php         optional
migrations/          optional
src/                 optional
assets/plugin.css    optional, fertig kompiliert
assets/plugin.js     optional, fertig kompiliert
```

Pflichtfelder des Manifests sind `id`, `name` und `version`. `requires.ignis`
grenzt kompatible ıgnıs-Versionen ein, etwa `>=2026.0.6-beta`. Ältere Manifeste
mit `>=1.1` passen weiterhin, weil jede Version im Jahresschema größer ist als
1.x. `depends` nennt andere Plugin-IDs.
`autoload`, `policies`, `permissions`, `default_enabled` und `removable`
entsprechen den Beispielen der mitgelieferten Plugins unter `plugins/`.

`search` nennt Klassen, die `App\Search\SearchSourceInterface` umsetzen
(`key()`, `label()`, `allowed()`, `search(string $q, int $limit)`); die
globale Suche in der Topbar fragt sie neben den Kern-Quellen ab und zeigt
je Quelle eine Gruppe mit `label`, `sub` und `href` pro Treffer. fireTab
(`Plugin\Firetab\Search\IncidentSource`) und die Wissensdatenbank
(`Plugin\KnowledgeBase\Search\LexiconSource`) sind die Vorlagen.

`notifications` nennt Klassen, die `App\Notifications\NotificationTypeInterface`
umsetzen (`key()`, `label()`, `icon()`, `allowed()`, `link(array $row)`); der
`NotificationManager` registriert sie neben den Kern-Typen. Ein Plugin legt
Einträge über `notify($type, $userIds, ['title' => …, 'message' => …, 'link' => …])`
an; Glocke, Posteingang und Zähler zeigen sie nur Nutzern, für die `allowed()`
zutrifft. Einträge eines abgeschalteten Plugins bleiben lesbar (Rohtext, Link).
fireTab (`Plugin\Firetab\Notifications\FireProtocolType`) ist die Vorlage.

`counters.php` liefert Zähler für die Sidebar: eine Map Schlüssel =>
`callable(): ?int`. Ein Navigationseintrag mit `'counter' => '<schlüssel>'`
zeigt den Wert, `null` oder 0 blendet ihn aus. Das Mail-Plugin
(`plugins/mail/counters.php`) ist die Vorlage.

Ein heruntergeladenes Plugin bleibt vollständig inert. Erst die
Installationsbestätigung in der Verwaltung legt den `.installed`-Marker an,
führt Migrationen aus und aktiviert das Plugin. Ein `.installed` im Archiv
wird beim Entpacken entfernt.

## Installieren

Unter Einstellungen → Wartung und Diagnose → Plugins gibt es drei Wege, die alle auf
derselben Bestätigungsseite enden:

- **Hochladen**: Ein Plugin-ZIP auf die Dropzone ziehen oder auswählen. Das
  Archiv wird geprüft und wartet in `plugins/.staging/`, bis die Installation
  bestätigt oder verworfen ist (nach einer Stunde räumt ignis es weg). Das
  Manifest liegt im Archiv-Root; ein gezippter Plugin-Ordner mit genau einem
  Unterverzeichnis wird ebenfalls angenommen.
- **Aus dem Katalog**: Das ZIP wird erst nach der Bestätigung von GitHub
  geladen und gegen den SHA256-Digest des Katalogs geprüft.
- **Bereitliegend**: Ein Plugin, das ohne Installation in `plugins/` liegt
  (von Hand kopiert oder nur heruntergeladen), wird aus der Liste installiert.

Die Bestätigungsseite zeigt ID, Version, Herausgeber, Quelle, Prüfsumme,
Abhängigkeiten und die mitgebrachten Rechte. Bei Plugins von Drittanbietern
verlangt sie ein Häkchen zum Risiko-Hinweis, und der Server lehnt die
Installation ohne dieses Häkchen ab. Als Drittanbieter gilt jeder Upload und
jeder Katalog-Eintrag, der nicht den Status `official` trägt; auch `verified`
heißt nur, dass EmergencyForge die Version angesehen hat. Wahlweise bleibt ein
neues Plugin nach der Prüfung erst einmal inaktiv liegen.

Vor dem Verschieben prüft ignis bei jedem Weg dasselbe: höchstens 50 MB
Archiv, 5000 Dateien und 200 MB entpackt, keine absoluten Pfade, kein `..`,
keine Symlinks oder Gerätedateien, ein literal-only Manifest mit gültiger ID,
keine ID eines mitgelieferten Plugins und keine, die schon ein anderer Ordner
trägt, und die `requires.ignis`-Angabe. Ein vorhandenes Plugin gleicher ID
wird nur ersetzt, wenn die Bestätigungsseite es als Update angekündigt hat;
die alte Version landet in `plugins/.backup/`, ältere Versionen werden nicht
eingespielt. Jeder Schritt steht im Audit Log unter „Plugins“.

Für Uploads müssen `upload_max_filesize` und `post_max_size` (PHP) sowie
`client_max_body_size` (nginx) groß genug sein; das Docker-Image und
`nginx.conf.example` erlauben 50 MB.

## Assets

Plugins bringen kompiliertes `assets/plugin.css` beziehungsweise
`assets/plugin.js` mit. Aktive Plugins werden automatisch in die Basis-Seiten
eingebunden. Ein Tailwind-, Sass- oder JavaScript-Build auf dem Zielserver
findet nicht statt.

Das Docroot ist `public/`, der Plugin-Ordner liegt außerhalb. Dateien aus
`assets/` erreicht der Browser über die Route
`/plugins/<id>/assets/<pfad>`, die nur die Endungen css, js, map, woff2,
png, svg und webp ausliefert und nur für installierte Plugins antwortet.
Im Template `asset('plugins/<id>/assets/plugin.js')` verwenden, dann stimmt
auch der Cache-Buster.

## Geplante Aufgaben

Ein Plugin registriert einen Symfony-Console-Command über `console.php` und
legt den Zeitplan per Migration in `intra_cron_jobs` mit
`handler_type = console` an. Ist das Plugin deaktiviert, wird der Lauf als
`skipped` protokolliert und in der Cron-Verwaltung als „Plugin inaktiv“
gekennzeichnet. Ein eigenes `cron.php`-Register gibt es nicht.

## Öffentlicher Event-Katalog v1

Die folgenden Events und ihre öffentlichen Konstruktor-Properties gelten in
ıgnıs 2026.x als stabil, solange das jeweilige mitgelieferte Plugin aktiv ist:

- `Plugin\Firetab\Events\FireProtocolReleased`: `incidentData` (`array`)
- `Plugin\Enotf\Events\EnotfProtocolReleased`: `protocolData` (`array`)
- `Plugin\Enotf\Events\EnotfPreregistered`: `preregData` (`array`)

Listener werden in `events.php` als Event-FQCN zu einer Liste von
Listener-FQCNs gemappt. Jeder Listener besitzt `handle(EventClass $event):
void`. Listener-Fehler werden geloggt und dürfen den auslösenden Request nicht
blockieren. Nicht in diesem Katalog aufgeführte Events sind intern und können
sich mit jeder Version ändern.

## Katalog und Einreichung

Der Katalog verweist auf ein öffentliches GitHub-Release-ZIP und einen
SHA256-Digest. ıgnıs akzeptiert nur HTTPS-Downloads von GitHub, prüft Größe,
Digest, Archivpfade, Manifest-ID und Versionskompatibilität und verschiebt das
Plugin anschließend atomar nach `plugins/<id>/`. Ein Katalog-Eintrag kann
`publisher` (oder `vendor`/`author`) und `repository` (HTTPS-URL) mitbringen;
beides steht auf der Bestätigungsseite. `status` ist `official`, `verified`,
`tested` oder `untested` (Standard).

Neue Plugins werden über das GitHub-Issue-Template „Plugin einreichen“
vorgeschlagen. Ungetestete Community-Plugins werden entsprechend markiert;
Quellcode, Support und Release-Artefakte bleiben beim jeweiligen Herausgeber.

## Entfernen und Daten

„Entfernen“ löscht bei einem deaktivierten Community-Plugin nur dessen
Dateien. Tabellen und Daten bleiben absichtlich bestehen. Eine Deinstallation
mit Datenlöschung und verpflichtendem Export ist noch kein öffentlicher
Vertrag und muss separat entworfen werden.
