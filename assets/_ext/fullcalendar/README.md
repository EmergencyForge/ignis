# FullCalendar v6 — lokale Einbindung

Dieses Verzeichnis hostet das FullCalendar-Bundle. Wir binden den
**Global-Build** von `@fullcalendar/core` zusammen mit `@fullcalendar/standard`
ein — kein npm/Bundler nötig, einfach `<script src="…">`.

## Benötigte Files

- `index.global.min.js` — das Standard-Bundle (Core + DayGrid + TimeGrid + List
  + Interaction)
- `locales/de.global.min.js` — deutsche Locale

## Bezug

Variante A (npm + Copy):

```bash
npm pack @fullcalendar/standard
# tarball entpacken und index.global.min.js + locales/de.global.min.js
# nach assets/_ext/fullcalendar/ kopieren
```

Variante B (CDN-Mirror lokal cachen):

```
https://cdn.jsdelivr.net/npm/@fullcalendar/standard@6.1.18/index.global.min.js
https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.18/locales/de.global.min.js
```

Version-Pin: `6.1.x` (MIT-Lizenz, premium-Features sind nicht enthalten,
brauchen wir auch nicht). Beim Update: Browser-Smoke-Test `assets/js/pages/calendar.js`
auf API-Breaking-Changes prüfen — FullCalendar hält sich an SemVer.

## Größen-Check

`index.global.min.js` ist ~280 KB minified, ~80 KB gzipped — passt in unsere
"keine externen CDN"-Politik (siehe Geist-Font, ckeditor5). Falls jemand
unbenutzte Plugins ausschließen will, separate `@fullcalendar/<plugin>/index.global.min.js`
einbinden statt des Standard-Bundles.
