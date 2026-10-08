# eNOTF

Elektronisches Notfallprotokoll für den Rettungsdienst. Die Crew arbeitet unter `/enotf/` (Anmeldung, Übersicht, Protokoll), das Back-Office in der Prüfliste unter `/enotf/admin/list`, Kliniken über die Schnittstelle unter `/enotf/schnittstelle/`.

## Aufbau

- **Crew-Oberfläche** (`src/Crew/`, `templates/*.php`, `templates/protokoll/`, `assets/`): Anmeldung, Übersicht, Anlegen und der Protokoll-Editor mit sieben Abschnitten unter `/enotf/p/{enr}[/{abschnitt}]`. Unterseiten eines Abschnitts sind Fokusansichten über `?t=`, etwa `abschluss?t=freigabe`.
- **Back-Office und Klinik** (`src/Controllers/`, `templates/enotf/`): Prüfliste mit QM, Druckansicht, Ankunftstafel, Klinikcode, Voranmeldung, Klinik-Verfügbarkeit, Fahrzeuginfo und Fahrtenbuch.
- **Einstellungen** (`templates/settings/`): Medikamente, POIs mit Fachabteilungen und Zugangscodes, Schnellzugriff.
- **Schema, Rechte, Events**: alle Migrationen, `permissions.php` und die Discord-Webhooks (`events.php`) liegen hier.

## Wie es arbeitet

- **Autosave** (`assets/autosave.js`): geänderte Felder werden 600 ms gesammelt und als ein JSON-POST auf `/api/enotf/save-fields` geschickt (`{enr, fields: {spalte: wert}}`). Fehler kommen pro Spalte zurück, ein freigegebenes Protokoll antwortet mit 403.
- **Pflichtfelder** (`src/Crew/Support/ConditionsService.php`): Freigabe-Prüfung und Füllstand je Abschnitt aus einem Regelwerk.
- **Zugriff** (`src/Crew/Support/ProtokollAccessGuard.php`): eine Crew sieht und schreibt nur Protokolle ihres Fahrzeugs. Panel-Nutzer mit `edivi.view` lesen jedes Protokoll ohne Fahrzeuganmeldung, die Seite ist für sie schreibgeschützt.
- **Auth**: `ENOTF_REQUIRE_USER_AUTH` entscheidet, ob ein ignis-Konto nötig ist. Die Crew-Sitzung prüfen die Controller selbst, damit Crews ohne Konto arbeiten können.
- **FiveM**: `SessionManager` setzt auf `/enotf/`-Pfaden `SameSite=None; Secure`, alle Seiten hängen hinter `FiveMCspMiddleware`. Deshalb schicken die Formulare ein eigenes CSRF-Token (`src/Crew/Http/Csrf.php`).
- **Ev2Select** (`assets/ev2-select.js`): eigenes Aufklappmenü, weil der FiveM-Browser native `<select>`-Popups teils nicht zeigt. Das native Element bleibt die Quelle der Wahrheit.

## Bekannte Grenzen

- **ZVK**: steht in der Whitelist und wird aus Altdaten angezeigt, aber kein Formular legt neue ZVK-Einträge an.
- **transportziel**: die Spalte trägt historisch zwei Bedeutungen, Versorgungsart-Code und POI-Kennung. `TransportzielCatalog` deckt nur die Versorgungsart ab.
- Die Crew-Oberfläche hat eigene Models unter `src/Crew/Models/` neben denen unter `src/Models/`, beide auf denselben Tabellen.
