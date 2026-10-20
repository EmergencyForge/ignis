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
| `tablet.css` | Tablet-Bausteine aus WebPackages (`tablet-tokens()` und `tablet('ignis')`), erzeugt, nicht von Hand ändern |
| `enotf-funke.css` | eNOTF-eigene Teile und Abweichungen von den Bausteinen, Präfix `nf-` |
| `enotf-funke.js` | Hash-Routing, Zustand, Ansichten, Interaktion (ES-Modul ohne Build) |
| `catalogs.js` | Optionslisten, Schwellen der Vitalwerte, Pflichtangaben, Beispielprotokolle |

Eingebunden werden die vorhandenen Dateien von ignis über relative Pfade:
`../assets/dist/ui.css`, `../assets/dist/vendor.css` (Font Awesome 7),
die Geist-Schriften sowie `preferences.js`, `dialog.js`, `snackbar.js`,
`tooltip.js` und `dropdown.js` aus `../assets/js/ui/`. Stand ist ui 1.0
(Zweig `claude/ui-1-0`).

`tablet.css` baut ignis selbst noch nicht, weil nur eNOTF-Seiten die
Bausteine brauchen. Bis zur Übernahme kommt die Datei aus WebPackages,
`packages/ui/scss/_tablet.scss`, kompiliert mit Sass:

```scss
@use 'tablet' as t;
body[data-ui-skin="core"] { @include t.tablet-tokens; @include t.tablet('ignis'); }
```

Der body trägt dazu die Klasse `ignis-tablet`.

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
  Ziel Adresse, E.-ab (7) und KH an (8). Ampel und Plausibilitätsprüfung
  rechnen daraus. Die Server-Prüfung
  (`ConditionsService`) ist damit nicht ersetzt.

## Übernahme

Die Tablet-Bausteine liegen seit ui 1.0 im Paket (`tablet-tokens`,
`tablet($p)`), der Prototyp nutzt sie mit den Klassen `ignis-tablet-*`.
In `enotf-funke.css` bleibt, was nur eNOTF braucht (Übersichtskacheln,
Formulare, Diagnose, Zugang, Medikamente, Verlaufsdiagramm, Anmeldung,
Sperre) und wenige Abweichungen, etwa breitere Antwortkacheln.

Für die Übernahme ins Plugin:

1. eNOTF-Seiten binden `tablet-tokens` hinter `look-tokens` und
   `tablet('ignis')` hinter `look('ignis')` ein, der body bekommt
   `ignis-tablet`.
2. Die `nf-`-Regeln wandern in die eNOTF-Styles des Plugins und ersetzen
   die `ev2-`-Regeln in `templates/_layout.php` schrittweise.

Die Kategorienpalette des Verlaufsdiagramms (`--nf-s1` bis `--nf-s8`) ist
validiert und hat feste Reihenfolge. Im Paket gehört sie zu den
Diagramm-Rollen neben `--series-1` bis `--series-4`.
