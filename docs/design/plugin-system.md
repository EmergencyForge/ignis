# Design: Plugin-System (First-Party-Modul-Extraktion)

> Status: **Entwurf zur Diskussion** · Autor: EmergencyForge · Betrifft: `EmergencyForge/ignis`, `EmergencyForge/hub`

## Zusammenfassung

ignis bekommt ein Plugin-System. Der Einstieg ist **nicht** ein SDK für Dritte,
sondern die Umwandlung der eigenen Module (eNOTF, fireTab, MANV, …) von fester
Verdrahtung in **mitgelieferte First-Party-Plugins**. Sie bleiben standardmäßig
vorinstalliert, sind aber architektonisch Plugins: einzeln aktivierbar/
deaktivierbar und über eine stabile API in den Kern eingeklinkt.

Getroffene Grundsatzentscheidungen:

| Weiche | Entscheidung |
|--------|--------------|
| **Isolationsgrad** | Convention + Manifest — ein Plugin ist ein Ordner mit deklarativem Manifest, das sich in die vorhandenen Register einklinkt. Keine erzwungene Prozess-Sandbox. |
| **Vertrauensmodell** | Kuratierter Katalog über den Hub. First-Party-Plugins sind signiert und vorinstalliert; Fremd-Plugins nur mit expliziter Entwickler-Freischaltung. |
| **v1-Umfang** | Volle API-Oberfläche (Routen, Nav, Events senden + empfangen, Migrations, Permissions, Templates, Cron, Console, DI), validiert durch Extraktion der eigenen Module. |

## Leitidee: Dogfooding statt Spekulation

Ein Plugin-System, dessen API nur an Spielzeug-Beispielen entworfen wurde, bricht
beim ersten echten Modul. Deshalb ist die erste „Kundschaft" der API ignis selbst:
eNOTF emittiert Events (`EnotfProtocolReleased`), hat Cron-Jobs, viele Migrations
und eigene Templates — wenn die Plugin-API *das* trägt, trägt sie auch alles, was
später von Dritten kommt.

Der spürbare Nutzen für Communities: **weniger Ballast.** Eine reine
Rettungsdienst-Community deaktiviert fireTab, eine reine Feuerwehr eNOTF — die
Navigation, die Berechtigungen und die Cron-Jobs des abgeschalteten Moduls
verschwinden sauber.

## Architektur: Kernel vs. Plugins

Der Kern schrumpft auf einen **Kernel**, der die Infrastruktur stellt, an die sich
Plugins hängen. Alles Fachliche wird Plugin.

**Kernel (bleibt fest verdrahtet):**
Auth/Session, Users & Roles, Permission-System, DI-Container, Router,
Event-Dispatcher, AutoMigrator, Cron-Scheduler, Console-Application, Logging,
Config, das Navigations-Gerüst (Rail/Flyout-Shell), Dashboard, Settings-Rahmen,
SystemUpdater, Telemetrie/Hub-Client.

**First-Party-Plugins (extrahiert, standardmäßig aktiv):**
Genau vier Module werden aus der festen Verdrahtung gelöst:

- **eNOTF** – Notfallprotokolle
- **fireTab** – Fire Incidents
- **MANV-Board**
- **Wissensdatenbank**

**Core (bleibt fest verdrahtet):** alles andere — Kalender, Dokumente, Personal,
Fahrtenbuch, Fahrzeuge, Anträge, Föderation, Dashboard, Settings, Users/Roles,
Auth. Diese Module sind Grundfunktionalität und werden bewusst *nicht* zu Plugins.

## Plugin-Struktur & Manifest

```
plugins/enotf/
  manifest.php        Metadaten, Kompatibilität, Abhängigkeiten, Permissions
  routes.web.php      Fragment: web-Routen (bekommt $router)
  routes.api.php      Fragment: API-Routen
  navigation.php      Fragment: rail/section-Einträge (gemergt in die Shell)
  events.php          Fragment: Event → Listener-Map
  console.php         Fragment: Console-Command-Klassen
  cron.php            Fragment: Cron-Job-Definitionen
  migrations/         Eigene Phinx-Migrations (Prefix plugin_enotf_*)
  templates/          Eigene Views
  src/                Controller, Services, Listener (autowired)
```

Das Manifest ist die einzige Pflichtdatei:

```php
return [
    'id'           => 'enotf',
    'name'         => 'eNOTF – Notfallprotokolle',
    'version'      => '1.0.0',
    'vendor'       => 'EmergencyForge',
    'requires'     => ['ignis' => '>=1.2 <2.0'],  // Kompatibilitätsbereich
    'depends'      => [],                          // andere Plugin-IDs
    'permissions'  => ['enotf.view', 'enotf.edit', 'enotf.admin'],
    'default_enabled' => true,                     // vorinstalliert & aktiv
    'removable'    => true,                        // darf deaktiviert werden
];
```

## Der PluginLoader

Ein `PluginLoader` läuft beim Bootstrap **vor** dem Aufbau der Register. Er:

1. entdeckt alle Plugins unter `plugins/*/manifest.php`,
2. filtert auf die in der DB als **aktiv** markierten (`intra_plugins`-Tabelle),
3. prüft `requires` (ignis-Version) und `depends` (aktive Abhängigkeiten) und
   deaktiviert bei Konflikt mit Warnung,
4. speist die Fragmente in die schon vorhandenen Nahtstellen ein:

| Nahtstelle heute | Plugin-Beitrag | Mechanik |
|------------------|----------------|----------|
| `routes/web.php`, `routes/api.php` | `routes.web.php` / `routes.api.php` | Loader `require`t die Fragmente mit demselben `$router` |
| `config/navigation.php` | `navigation.php` | Fragmente werden in die Rail/Sections gemergt (Sortierung via `order`) |
| `config/events.php` | `events.php` | Einträge werden in die Event→Listener-Map gemergt |
| `config/console.php` | `console.php` | Command-Klassen werden an die Console-Registry angehängt |
| Cron (`intra_cron_jobs`) | `cron.php` | Job-Definitionen werden beim Boot idempotent registriert |
| `AutoMigrator` (`glob('database/migrations/*.php')`) | `migrations/` | Loader ergänzt aktive Plugin-Migrationspfade |
| Container | `src/` (autowired) | Services werden per Autowiring aufgelöst; optional `services.php`-Fragment |
| `config/permissions.php` | `manifest['permissions']` | Werden in den Permission-Katalog gemergt |

Entscheidend: **Der Kern muss dafür kaum umgebaut werden.** Die Register sind
heute schon Arrays bzw. glob-basiert — sie müssen nur „mergefähig" statt
„fest" werden.

## Inter-Plugin-Abhängigkeiten

Module referenzieren sich teils (z. B. fireTab ↔ Fahrzeuge). Das Manifest
deklariert `depends`. Der Loader:

- aktiviert ein Plugin nur, wenn alle `depends` aktiv sind,
- verweigert das Deaktivieren, solange ein aktives Plugin es braucht (mit klarer
  Meldung „fireTab benötigt Fahrzeuge"),
- lädt in topologischer Reihenfolge (Abhängigkeit zuerst).

Fremdreferenzen laufen über **Events oder deklarierte Service-Interfaces**, nie
über direkte Klassenzugriffe in fremde Plugin-`src/`.

## Deaktivieren: Was passiert mit den Daten?

Deaktivieren ≠ Deinstallieren.

- **Deaktivieren:** Routen, Nav, Cron, Listener werden nicht geladen. **Tabellen
  und Daten bleiben unangetastet.** Reaktivieren stellt alles wieder her.
- **Deinstallieren (später, optional):** explizite Aktion mit Warnung; erst dann
  könnten Plugin-Tabellen entfernt werden — mit vorherigem Export.

Das schützt vor versehentlichem Datenverlust und macht Deaktivieren risikolos.

## Der Hub als Plugin-Katalog

Der gerade gebaute Hub wird zum kuratierten Register:

- `GET /v1/plugins` — Liste kuratierter Plugins (id, version, Digest, min-ignis).
- Der SystemUpdater zieht und verifiziert Plugins wie App-Updates (Digest-Check
  ist bereits vorhanden).
- Admin-UI in ignis: verfügbare Plugins, installiert/aktiv, Update verfügbar.
- Fremd-Plugins nur über `?dev`-Freischaltung + deutliche Warnung
  (Code-Ausführung mit vollen Rechten).

## Migrationsplan (Phasen)

**Phase 0 — Fundament.** `PluginLoader`, Manifest-Format, `intra_plugins`-Tabelle,
Register „mergefähig" machen. Noch kein Modul extrahiert.

**Phase 1 — Proof mit einem einfachen Modul.** Ein self-contained Modul zuerst
extrahieren (Kandidat: **Fahrtenbuch** oder **Wissensdatenbank** — wenig
Querbezüge), um Loader + Manifest an echtem Code zu validieren. CI muss grün
bleiben.

**Phase 2 — Flaggschiffe.** eNOTF und fireTab extrahieren (die komplexen Fälle
mit Events, Cron, vielen Migrations). Danach die übrigen Module, je ein PR.

**Phase 3 — Enable/Disable-UX + Hub-Katalog.** Admin-UI zum Schalten,
`/v1/plugins` im Hub, Update-Fluss.

**Phase 4 — Öffnung für Dritte.** Erst wenn die API durch die eigenen Module
bewährt ist: dokumentierte Plugin-SDK, Fremd-Plugin-Freischaltung, ggf.
Community-Einreichungen in den Katalog.

## Offene Risiken

- **Geteilter Code / Kopplung.** Module teilen heute vielleicht Helper/Models.
  Vor der Extraktion inventarisieren; gemeinsam Genutztes wandert in den Kernel
  oder ein Basis-Plugin.
- **Migrations-Reihenfolge.** Plugin-Migrations müssen deterministisch nach den
  Kernel-Migrations laufen; Namespacing (`plugin_<id>_*`) gegen Kollisionen.
- **Kernel-Grenze.** Die Personnel-Frage exemplarisch: Wo genau verläuft die
  Linie zwischen „Infrastruktur" und „Fachlichkeit"?
- **Stabile Event-/Service-API.** Sobald Plugins an Kern-Events hängen, werden
  diese zu Vertragspartnern — ein versionierter „öffentlicher" Event-Katalog ist
  nötig, bevor Phase 4 startet.

## Nicht-Ziele (v1)

- Keine Prozess-/Sicherheits-Sandbox (bei self-hosted PHP illusorisch).
- Kein Marktplatz mit Bezahlung/Reviews durch Dritte.
- Keine Laufzeit-Installation ohne Neustart/Cache-Rebuild.
- Keine Rückwärtskompatibilität für Fremd-Plugins vor Phase 4.
