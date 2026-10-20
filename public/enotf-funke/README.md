# eNOTF im Funke-Look (Prototyp)

Klickbarer Prototyp des eNOTF-Tablets im Look des Funke-Designsystems. Er
dient dazu, Aufbau, Touch-Bedienung und Pflichtfeld-Logik am Bildschirm zu
prüfen, bevor etwas davon ins Plugin `plugins/enotf` übernommen wird.

## Öffnen

- Apache: `/enotf-funke/`
- nginx oder ohne `DirectoryIndex`: `/enotf-funke/index.php`

`index.php` enthält nur HTML, es läuft keine PHP-Logik und kein
ignis-Bootstrap. Anmeldung, Datenbank und API werden nicht gebraucht.

Gedacht für ein Tablet im Querformat (1280 × 800 und 1024 × 768), bis 1440
Breite getestet, hell und dunkel. Kein `:has()`, keine Container Queries,
kein CSS-Nesting und kein `color-mix()` in der eigenen Schicht, damit sie im
FiveM-Tablet (Chromium 103) läuft.

## Dateien

| Datei | Inhalt |
|---|---|
| `index.php` | HTML-Gerüst, bindet die gebauten Funke-Assets ein |
| `enotf-funke.css` | Tablet-Schicht, Präfix `nf-` |
| `enotf-funke.js` | Hash-Routing, Zustand, Ansichten, Interaktion (ES-Modul ohne Build) |
| `catalogs.js` | Optionslisten, Schwellen der Vitalwerte, Pflichtangaben, Beispielprotokolle |

Eingebunden werden die vorhandenen Dateien von ignis über relative Pfade:
`../assets/dist/ui.css`, `../assets/dist/vendor.css` (Font Awesome 7),
die Geist-Schriften sowie `preferences.js`, `dialog.js`, `snackbar.js` und
`tooltip.js` aus `../assets/js/ui/`.

## Bestehende Dateien

Der Prototyp ändert keine bestehende Datei. Alles liegt in diesem Ordner.
Templates, Routen, CSS, JS und Build-Konfiguration von ignis bleiben, wie
sie sind.

## Was nur Mock ist

- Zustand liegt im `localStorage` des Browsers (Präfix `enotf-funke:`).
  Beim ersten Öffnen gibt es drei Beispielprotokolle: eines in Arbeit, eines
  von der Leitstelle angelegt, eines freigegeben. Zurücksetzen über das
  Kolben-Symbol oder das Besatzungsmenü, „Beispiel zurücksetzen“.
- Alle Namen, Fahrzeuge, Adressen, Kliniken und Codes sind erfunden.
- Leitstelle, Session, Patientendaten-Sync, Autosave, „An Leitstelle
  senden“, Teilen, Klinikcode, Verfügbarkeit, Fahrtenbuch und Quicklinks
  zeigen nur Zustände und Meldungen, es wird nichts übertragen. Ausfälle
  lassen sich im Prototyp-Dialog einschalten, dort auch „Nur Ansicht“.
- Sperr-PIN ist `1234`.
- Die Pflichtangaben folgen den ★-Regeln des eNOTF für die dort genannten
  Felder, inklusive Ausnahmen: „Fehleinsatz - kein Patient“ macht Patienten-
  und Befundangaben optional, die Transport-Versorgungen fordern zusätzlich
  Ziel Adresse, E.-ab (7) und KH an (8). Leiste, Zähler und
  Plausibilitätsprüfung rechnen daraus. Die Server-Prüfung
  (`ConditionsService`) ist damit nicht ersetzt.

## Später ins Paket

Die Tablet-Schicht liest nur Funke-Rollen (`--surface-*`, `--well`,
`--border-*`, `--ok`/`--warn`/`--danger` mit `-soft`/`-line`/`-text`,
`--accent-*`, Radius- und Bewegungsrollen). Sie lässt sich deshalb als
eigener Skin in WebPackages abbilden, etwa als `ef.tablet($p)` neben
`base()` und `look()`:

1. Die `nf-`-Bausteine, die es im Paket noch nicht gibt (Antwort-Kachel mit
   Normalbefund, Abschnittsleiste mit Füllstand, Tastenfeld mit Wertkästen und
   Bereichsleiste, Körperkarte, Zeitenleiste), wandern als `PREFIX-`-Klassen
   nach `packages/ui/scss/`, auf dieselben Rollen.
2. Der Skin setzt die Touch-Maße (`--control-height: 44px`, Tasten 56 px,
   Mindestschrift 12 px) und schaltet die Rollen nicht um, nur Größen.
3. eNOTF bindet `ui.css` mit dem Skin ein und ersetzt die eigenen
   `ev2-`-Regeln in `templates/_layout.php` schrittweise.

Die Kategorienpalette des Verlaufsdiagramms (`--nf-s1` bis `--nf-s8`) ist
validiert und hat feste Reihenfolge. Im Paket gehört sie zu den
Diagramm-Rollen neben `--series-1` bis `--series-4`.
