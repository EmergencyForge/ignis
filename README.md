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

ıgnıs benötigt Apache mit `mod_rewrite` und aktivem `.htaccess`-Override. In
der VirtualHost-Konfiguration muss für das Installationsverzeichnis mindestens
`AllowOverride FileInfo Options` (oder `AllowOverride All`) gelten. Bei einer
Installation in einem Unterverzeichnis muss `BASE_PATH` einschließlich
abschließendem Slash gesetzt sein, zum Beispiel `/ignis/`.

Nach der Anmeldung prüft das Dashboard automatisch sowohl eine Router-URL als
auch die extensionlose Auflösung einer echten PHP-Datei. Erscheint die Warnung
„URL-Rewriting funktioniert nicht“, prüfe zuerst:

1. Ist `mod_rewrite` aktiviert?
2. Darf der Webserver die mitgelieferte `.htaccess` lesen und anwenden?
3. Zeigt der Document-Root auf das Repository beziehungsweise ist die
   Weiterleitung nach `public/index.php` erlaubt?

Für nginx enthält [`nginx.conf.example`](nginx.conf.example) die entsprechenden
`try_files`-Regeln. Der öffentliche Endpunkt `/api/health` meldet zusätzlich
fehlende PHP-Erweiterungen, HTTP-Transport und eingeschränkte Prozessfunktionen.
