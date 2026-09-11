# eNOTF v2

Neubau des eNOTF-Protokoll-Moduls auf dem v1-Datenbestand. v2 liest und schreibt dieselben `intra_edivi*`- und `intra_enotf_*`-Tabellen wie das v1-Plugin und bringt keine eigenen Migrationen und keine eigenen Permissions mit — bestehende Rollen (edivi.view, edivi.edit, enotf.view, …) gelten unverändert. Das v1-Plugin muss installiert bleiben (`depends => ['enotf']`), es liefert weiterhin Schema, Events, Permissions und den Crew-Session-Service.

Login und PIN sind zwischen beiden Welten geteilt: die Crew-Session (`enotf_session_token`) und die PIN-Verifizierung (`pin_verified`) liegen in denselben Session-Keys. Wer in v1 angemeldet und entsperrt ist, ist es auch in v2 — und umgekehrt.

## Aktivierung

`default_enabled => false`: Neue Installationen bekommen v2 deaktiviert ausgeliefert, Admins schalten es unter Einstellungen → Plugins frei. Bei bestehenden Installationen bleibt der gespeicherte enabled-Zustand erhalten (`PluginRepository::syncDiscovered` legt nur Zeilen für unbekannte Plugins an, Updates fassen `intra_plugins.enabled` nicht an).

Einstieg nach Aktivierung: `/enotf-v2/` (leitet auf die Overview, ohne Crew-Session zur Login-Seite).

## Architektur in Kürze

- **Routen**: Webseiten unter `/enotf-v2/*` (`routes.web.php`), JSON-API unter `/api/enotf-v2/*` (`routes.api.php`). Auth ist auf beiden Seiten config-gated über `ENOTF_REQUIRE_USER_AUTH`; die Crew-Session-Prüfung machen die Controller selbst, damit Crews ohne User-Login arbeiten können, wenn das Gate aus ist.
- **Autosave** (`assets/autosave.js`): debounced Batch-Save — geänderte Felder werden 600 ms gesammelt und als ein JSON-POST auf `/api/enotf-v2/save-fields` geschickt (`{enr, fields: {spalte: wert}}`). Feldfehler kommen pro Spalte zurück, 403 bei freigegebenem Protokoll sperrt den Client. Nach jedem Batch zieht ein Plausibility-GET die Section-Statusanzeigen nach.
- **ConditionsService** (`src/Support/ConditionsService.php`): Port des v1-Pflichtfeldsystems (conditions.php/notify.php), Regeln und transportziel-Abhängigkeiten identisch übernommen. Zwei Semantiken wie in v1: Freigabe-Prüfung (check-Closures) und Füllstands-Anzeige pro Section.
- **Ev2Select** (`assets/ev2-select.js`): Custom-Select als Progressive Enhancement, weil der FiveM-CEF native `<select>`-Popups teils nicht anzeigt. Das native Element bleibt Quelle der Wahrheit, jede Auswahl dispatcht ein echtes change-Event — Autosave und Dialog-Logik brauchen keine Sonderbehandlung.
- **Kataloge** (`src/Catalogs/`): Code→Label-Mappings aus den v1-Templates extrahiert, mit Quellenangaben. Details in `src/Catalogs/README.md`.
- **CEF/Session**: `SessionManager` führt `/enotf-v2/` in seiner iframe-Pfadliste, Session-Cookies auf v2-Pfaden (auch `/api/enotf-v2/…`) kommen darum mit `SameSite=None; Secure` — auch wenn der CEF-Client keinen Sec-Fetch-Dest-Header schickt. Alle v2-Webrouten hängen hinter `FiveMCspMiddleware` (CitizenFX-Requests bekommen CSP/X-Frame-Options entfernt).

## Bewusst auf v1 verlinkt

Nicht neu gebaut, sondern per Link/Redirect auf die v1-Seiten gereicht:

- Print (`enotf/print/index.php`, aus dem Protokoll-Layout)
- Schnittstelle/Voranmeldung (`EnotfUrl::schnittstelle('voranmeldung')`) und Hospital-Availability
- Fahrzeuginfo und Fahrtenbuch (Overview-Topbar)
- QM-Fragmente: die `/enotf-v2/qm/*`-Routen sind dünne Wrapper um die v1-Admin-Fragmente (gleiche Templates, gleiche Gates; POST zusätzlich auf enotf.editProtocol gegated).

## Bekannte Grenzen

- **ZVK**: `zvk` steht in der Server-Whitelist und wird aus Altdaten korrekt angezeigt, aber wie in v1 erzeugt kein Formularblatt neue ZVK-Einträge (keine Orte-Liste dafür).
- **transportziel-Altdaten**: die Spalte `intra_edivi.transportziel` trägt historisch zwei Semantiken — Versorgungsart-Code (dieser Katalog) und POI-Identifier (abschluss/freigabe, schnittstelle). v2 übernimmt das unverändert, `TransportzielCatalog` deckt nur die Versorgungsart ab.

## FiveM-Ingame-Testcheckliste

Für den ersten echten Client-Test im CEF (Reihenfolge so durchgehen):

1. **Login im CEF**: `/enotf-v2/login` im Ingame-Browser öffnen, Fahrzeug wählen, Crew anmelden. Erwartung: kein Redirect-Loop, nach Login landet man auf der Overview.
2. **Cookie/SameSite**: Nach dem ersten Request prüfen (Server-Log oder devtools des CEF, falls verfügbar), dass das Session-Cookie mit `SameSite=None; Secure` gesetzt wurde und Folge-Requests dieselbe Session tragen (Login-Zustand übersteht Seitenwechsel). Wenn nicht: Instanz läuft nicht über HTTPS bzw. der Proxy reicht `X-Forwarded-Proto` nicht durch — `SessionManager::isHttps()` braucht eins von beidem.
3. **Autosave**: Protokoll öffnen, mehrere Felder schnell hintereinander ändern. Erwartung: ein gebündelter POST auf `save-fields`, Topbar-Status meldet Speichern, Section-Indikatoren in der Navigation ziehen nach.
4. **Dropdowns**: Selects in Rettdaten/Maßnahmen öffnen (auch in Dialogen, z. B. Zugang). Erwartung: Ev2Select-Panel öffnet und clippt nicht, Auswahl löst Autosave aus. Native Popups dürften gar nicht erst erscheinen.
5. **Lockscreen**: PIN-Timeout abwarten oder Sperre auslösen, dann eine v2-Seite aufrufen. Erwartung: Redirect auf `/enotf-v2/lockscreen`, nach PIN-Eingabe zurück auf die ursprüngliche Seite (pin_return_url). Gegentest: in v1 entsperren, v2 muss ohne erneute PIN-Eingabe offen sein.
6. **Teilen-Poll**: Protokoll von Fahrzeug A an Fahrzeug B senden. Erwartung: B bekommt die Anfrage über den check-requests-Poll angezeigt, Annehmen merged bzw. legt ein neues Protokoll an, Ablehnen räumt die pending-Anfrage weg.
7. **Logout**: POST auf `/enotf-v2/loggedout` (Selbst/Alle), danach GET derselben Seite. Erwartung: Anzeige-Seite ohne erneuten DB-Write, Crew-Session ist beendet, Overview leitet wieder zur Login-Seite.
