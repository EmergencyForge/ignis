# _**ıgnıs**_ — Struktur für jeden Einsatz

[![PHP Composer](https://github.com/intraRP/intraRP/actions/workflows/php.yml/badge.svg)](https://github.com/intraRP/intraRP/actions/workflows/php.yml) ![GitHub commit activity](https://img.shields.io/github/commit-activity/m/intraRP/intraRP)

Das Ziel von _**ıgnıs**_ (vormals intraRP) ist es eine Allround-Lösung für die Fraktionsverwaltung fiktiver Feuerwehren & Rettungsdienste vor allem für FiveM und andere ähnliche Settings anzubieten. Das System ist grundsätzlich zur Verwaltung eines deutschen Systems ausgelegt, kann aber mit eigenen Veränderungen durchaus auch an ein amerikanisches oder anderes Setting angepasst werden. Es handelt sich hierbei um eine Weiterentwicklung bzw. den Nachfolger von [intra.stettbeck.de](https://github.com/itshypax/intra.stettbeck.de). Das System befindet sich in aktuell in Entwicklung und wird stetig verändert.

### **Der Vorteil - immer kostenlos & immer Open Source!**

Das Projekt wird hobbymäßig weiterentwickelt und ist für jegliche Unterstützung, Anpassungen, Wünsche & Ideen offen. Einen Vorteil kann man jedoch dauerhaft genießen: Das Projekt ist vollkommen Open Source und kann von jedem angewandt, umgesetzt und verändert/angepasst werden.

Plugins können über die Systemverwaltung installiert und entwickelt werden.
Struktur, Sicherheitsmodell, Assets, Cron-Pattern und der versionierte
Event-Vertrag stehen in [PLUGINS.md](PLUGINS.md).

### Benutzte Assets

- [Font Awesome 7 (Free)](https://fontawesome.com/)
- [Tailwind Plus](https://tailwindcss.com/plus) als lizenzierte Designreferenz; Original-Komponenten und Pakete sind nicht Bestandteil des Repositories. Details und Mitwirkenden-Regeln stehen in [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).
- [CKEditor5](https://ckeditor.com/)
- Beinhaltete Schriftarten von [Google Fonts](https://fonts.google.com/)
- [Chart.js](https://www.chartjs.org/)
- [SortableJS](https://github.com/SortableJS/Sortable)
- [Taktische Zeichen](https://taktische-zeichen.dev/)
- [Leaflet](https://leafletjs.com/)

> [!CAUTION]
> Es handelte sich hierbei um ein **kontinuierliches Entwicklungsprojekt**! Es kommt zu stetigen Anpassungen. Wir garantieren **nicht** für Fehlerfreiheit und Datensicherheit!

### Hosting und URL-Rewriting

Der Document-Root muss auf das Verzeichnis `public/` zeigen. Dort liegen nur
der Front-Controller `index.php`, `cron.php` und die gebauten Assets; Quellcode,
Plugins, `storage/` und die `.env` bleiben außerhalb und sind vom Webserver
aus nicht erreichbar. Plugin-Dateien und Uploads liefert die Anwendung über
eigene Routen aus.

Apache braucht `mod_rewrite` und für `public/` mindestens
`AllowOverride FileInfo Options` (oder `AllowOverride All`), damit die
mitgelieferte `public/.htaccess` greift. Bei einer Installation in einem
Unterverzeichnis muss `BASE_PATH` einschließlich abschließendem Slash gesetzt
sein, zum Beispiel `/ignis/`.

Lässt sich der Document-Root auf dem Webspace nicht umstellen, kann er auf das
Projektverzeichnis zeigen: Die `.htaccess` im Projekt-Root reicht dann jede
Anfrage nach `public/` durch und sperrt die internen Verzeichnisse. Das
funktioniert, ist aber die zweite Wahl, weil der Schutz des Quellcodes dann
allein an dieser Datei hängt. Das Dashboard weist auf diese Konfiguration hin.

Nach der Anmeldung ruft das Dashboard `/api/health` auf. Erscheint die Warnung
„URL-Rewriting funktioniert nicht“, prüfe zuerst:

1. Zeigt der Document-Root auf `public/` (oder ersatzweise auf das Projekt)?
2. Ist `mod_rewrite` aktiviert?
3. Darf der Webserver die mitgelieferte `.htaccess` lesen und anwenden?

Für nginx gibt es keine Durchreichung: `root` muss auf `public/` zeigen.
[`nginx.conf.example`](nginx.conf.example) enthält die passenden
`try_files`-Regeln. `/api/health` meldet außerdem fehlende PHP-Erweiterungen,
HTTP-Transport, eingeschränkte Prozessfunktionen und unter `rewrite`, ob der
Document-Root auf `public/` zeigt.

### Anmeldung über ignisTab

Discord-OAuth funktioniert im Browser von FiveM nicht. Für das Tablet-Skript
ignisTab gibt es deshalb eine eigene Anmeldung, die unter Einstellungen ›
System-Konfiguration › Funktionen mit `TABLET_LOGIN_ENABLED` eingeschaltet
wird (ab Werk aus). Der FiveM-Server schickt `POST /api/tablet/login-token`
mit dem API-Schlüssel im Header `X-API-Key` und `{"discord_id": "…"}` und
bekommt einen Token, der 60 Sekunden und genau einmal gilt. Das Tablet öffnet
damit `/auth/tablet?token=…` und ist angemeldet wie nach dem Discord-Login.

Es funktioniert nur für bestehende, aktive Benutzer, deren Discord-ID in
ignis hinterlegt ist; ein Konto entsteht dabei nie. Pro Discord-ID gibt es
höchstens zehn Token pro Minute. Die Discord-ID stammt vom FiveM-Server, sie
ist also nur so verlässlich wie dessen Discord-Pflicht.
