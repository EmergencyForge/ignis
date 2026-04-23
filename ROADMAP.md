# intraRP — Modernisierungs-Roadmap

Arbeitspunkte außerhalb der laufenden Bootstrap→Tailwind-Template-Migration.
Reihenfolge ist Empfehlung, keine Pflicht — Abhängigkeiten sind pro Punkt vermerkt.

---

## Phase A — Hygiene (kurz, macht Folgearbeit angenehmer)

### A1. Route-Duplikation eindampfen
**Problem:** [routes/web.php](routes/web.php) und [routes/api.php](routes/api.php) registrieren jede Route doppelt (`/foo` + `/foo.php`). ~600 Zeilen davon sind reine Duplikate.
**Lösung:** Neue Router-Methode `$router->both($methods, $path, $handler, $middleware)` die intern beide Varianten registriert. Oder Middleware `StripPhpExtension` die `.php` abschneidet und auf die Clean-URL 308-redirectet.
**Aufwand:** 1 halber Tag für Router-Erweiterung + Route-Files umstellen.
**Abhängigkeiten:** Keine.
**Verifikation:** Alle bestehenden Tests grün, manuelles Smoke-Test auf Legacy-URLs (`/benachrichtigungen/index.php`) und Clean-URLs (`/benachrichtigungen`).

### A9. JS-Module statt Inline-Scripts
**Problem:** Dieselben Patterns (Modal move-to-body, Edit-Button-Listeners, Confirm-Dialogs) sind in vielen Templates copy-pasted. Jede Änderung muss an N Stellen nachgezogen werden.
**Lösung:** `assets/js/modules/` mit z.B. `modal-manager.js`, `form-populate.js`, `confirm-dialog.js`. Import in Vite-Entry, Templates nutzen nur noch `<script type="module">…</script>` mit Aufruf der Module.
**Aufwand:** ~1 Tag für initiales Setup + Modul-Extraktion aus 3-4 Stellen als Pattern, dann schrittweise.
**Abhängigkeiten:** Keine.

### A10. Timezone-Handling zentralisieren
**Problem:** UTC ↔ Europe/Berlin-Konvertierung wird in `einsatz/list.php:14`, `einsatz/view.php:19`, `fahrtenbuch/index.php:14` und anderen Templates inline gemacht, Formatierung `date('d.m.Y H:i', strtotime(...))` wiederholt sich.
**Lösung:** [src/Helpers/DateTime.php](src/Helpers/) (neu) mit statischen Methoden `toLocal(?string $utc): ?DateTimeImmutable`, `formatShort(?string $utc): string`, `formatLong(...)`. Templates rufen nur noch Helper, keine eigene DateTime-Logik.
**Aufwand:** 2-3 Stunden (Helper + Templates umstellen).
**Abhängigkeiten:** Keine.
**Risiko:** Niedrig — rein funktionale Refactoring, Output identisch.

---

## Phase B — Architektur (mittel)

### A4. Session-Mutation kapseln
**Problem:** `$_SESSION['userid'] = …` wird direkt in Controllern, Middleware und Templates gesetzt. Bei 5 parallelen Auth-Systemen (Standard / eNOTF-Crew / FireTab / API-Key / Federation — siehe `reference_auth_contexts.md`) ist das ein Minenfeld.
**Lösung:** `App\Session\SessionManager` (existiert) zum einzigen Schreibweg machen. Methoden wie `loginUser(int $id)`, `loginEnotfCrew(...)`, `logout()`. Direkte `$_SESSION[...]`-Zuweisungen entfernen.
**Aufwand:** 1 Tag (API-Design + Umstellung aller Schreibstellen).
**Abhängigkeiten:** Keine — aber A5 profitiert davon.
**Verifikation:** Neue Feature-Tests: pro Auth-Kontext ein Login-Flow.

### A5. Policies flächendeckend
**Problem:** Controller checken Permissions inkonsistent — manche via `Permissions::check('admin')`, andere via `Gate::authorize(...)`, manche haben keine Policy-Klasse (z.B. Antrag, Fahrtenbuch, MANV).
**Lösung:** Für jeden Controller eine passende Policy unter [src/Policies/](src/Policies/). PolicyMiddleware in den Routes statt Inline-Checks. Einheitliches Pattern `$this->authorize('ability', $model)` im Controller-Body wo nötig.
**Aufwand:** ~2-3 Tage (pro Modul eine Policy + Route-Middleware + Tests).
**Abhängigkeiten:** Kein Blocker — aber `reference_auth_contexts.md` sollte vor Start aktuell sein.

### A7. Test-Coverage gezielt ausbauen
**Problem:** `FeatureTestCase` ist gebaut aber nur 1 Pilot-Test. Die 5 Auth-Kontexte haben keine Regressionstests — Session-Leaks oder Middleware-Änderungen werden nicht automatisch gefangen.
**Lösung:** Pro Auth-Kontext mindestens ein `AuthContextTest` (Login → geschützte Route → Logout). Dann modulspezifisch je CRUD-Happy-Path.
**Ziel-Metriken:**
- 5 Auth-Regression-Tests (je einer pro Kontext)
- 1 CRUD-Feature-Test pro Controller (~15 insgesamt)
- Line-Coverage ≥ 30% nach diesen Tests
**Aufwand:** Je Test ~30-60 Min, d.h. ~15 Stunden für den initialen Ausbau.
**Abhängigkeiten:** Läuft parallel zu A4/A5.

---

## Phase C — Ergonomie (Template-Split + API)

### A8. Große Templates splitten
**Problem:** [templates/mitarbeiter/profile.php](templates/mitarbeiter/profile.php) (655), [templates/settings/system/logs.php](templates/settings/system/logs.php) (1125), [templates/settings/system/index.php](templates/settings/system/index.php) (1122), [templates/settings/documents/templates.php](templates/settings/documents/templates.php) (1801). Nicht mehr wartbar, jede Änderung riskiert Kollateralschäden.
**Lösung:** Komponenten-Partials unter [assets/components/<modul>/](assets/components/) extrahieren. Pro Template 3-6 Partials (Header, Filter, Main-List, Modal-Edit, Modal-Create, Sidebar).
**Aufwand:** Pro Template 1-2 Stunden, zusammen ~ 1 Woche wenn's alle sein sollen.
**Abhängigkeiten:** Keine — kann parallel zu Tailwind-Migration laufen, sollte idealerweise vor dem Tailwind-Pass für die großen Files gemacht werden.

### A11. API-Versionierung einführen
**Problem:** `/api/notifications/poll`, `/api/fire/status.php` etc. sind unversioniert. Sobald externe Clients (FireTab-Native-App? Discord-Bot?) gegen die API sprechen und sich die Response-Shape ändert, brechen sie lautlos.
**Lösung:** Neue Routes unter `/api/v1/...` registrieren. Alte Pfade als Alias behalten (`/api/notifications/poll` → intern zu `/api/v1/notifications/poll`), aber Deprecation-Header (`Sunset: <datum>`, `Link: </api/v1/notifications/poll>; rel="successor-version"`) anhängen. Nach 6 Monaten entfernen.
**Aufwand:** 1-2 Tage für Router-Setup + alle API-Routes umbiegen + Deprecation-Header-Middleware.
**Abhängigkeiten:** Nach A1 (Route-Dedup) einfacher umzusetzen.

### A12. Health-Endpoint
**Problem:** Kein standardisierter Check ob System healthy ist. Monitoring-Tools (UptimeRobot, Grafana Synthetic, Telemetrie-Hub) müssen sich aktuell an einer UI-Seite festbeißen und HTML parsen.
**Lösung:** `/healthz` (unauthentifiziert, oder mit Secret-Query-Param) liefert JSON:
```json
{
  "status": "ok|degraded|down",
  "checks": {
    "db":          {"status": "ok", "ms": 4},
    "queue":       {"status": "ok", "pending": 3, "failed": 0},
    "storage":     {"status": "ok", "free_mb": 1234},
    "migrations":  {"status": "ok", "latest": "20250408000042"}
  },
  "version": "v1.0.0",
  "uptime_s": 12345
}
```
HTTP 200 bei ok, 503 bei down. Minimal-impact auf die Performance.
**Aufwand:** 2-3 Stunden.
**Abhängigkeiten:** Keine.

---

## Reihenfolge-Empfehlung

1. **A10 Timezone** + **A1 Route-Dedup** — beides Hygiene, macht Folgearbeit angenehmer, niedriges Risiko
2. **A12 Health-Endpoint** — quick-win für Monitoring, isoliert
3. **A9 JS-Module** — beschleunigt alle zukünftigen Template-Änderungen
4. **A4 Session-Kapselung** — Basis für saubere Auth-Tests
5. **A7 Test-Coverage** — kann parallel zu A4/A5 laufen, sichert die nächsten Refactorings ab
6. **A5 Policies flächendeckend** — nach A4, weil es von der SessionManager-API profitiert
7. **A8 Templates splitten** — läuft lose parallel zur Tailwind-Migration
8. **A11 API-Versionierung** — wenn/sobald externe Clients in Sicht kommen

---

## Nicht in dieser Roadmap

- **FormRequest-Flächendeckung**: bereits als eigener Modernisierungs-Punkt in Memory (`project_refactor_status.md`) getrackt
- **Tailwind-Template-Migration**: läuft separat, siehe offene TODOs im aktuellen Session-Stream
- **i18n**: bewusst nicht aufgenommen — Deutsch-only Scope ist OK
- **Service-Layer für Business-Logik**: hoher Nutzen, hoher Aufwand. Wenn Tests (A7) da sind, kann man das Modul-für-Modul nachziehen. Eigenes Thema sobald A1-A12 durch sind.
