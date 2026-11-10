# ef_mechanic — Architektur (Ist-Stand M0)

Stand: Scaffold only. Keine Spielmechanik außer Tablet-Focus-Toggle und Schema-Ensure.

## Ziele

- Clean-Room-Werkstatt-Resource für ESX Legacy + ox_lib + ox_inventory + oxmysql + ox_target.
- Eigene UI, eigene Item-IDs (`ef_*`), eigene SQL-Prefixes (`ef_mechanic_*`).
- Server-Autorität für Geld, Items und Mods (kommt ab M1).

## Ordner

| Pfad | Rolle |
| --- | --- |
| `config/` | Owner-editierbar, escrow_ignore |
| `locales/` | `de` + `en` |
| `bridges/` | Framework / Inventory / Target / Notify / Banking |
| `client/` | Entry, NUI, Tablet (+ Stubs für M1+) |
| `server/` | Entry, DB ensure, Security (+ Stubs für M1+) |
| `shared/` | Constants + utils (Plate-Normalize, Locale) |
| `sql/` | Idempotentes `install.sql` |
| `install/` | ox_inventory Item-Beispiele |
| `web/` | React + Vite + TypeScript Quelle |
| `nui/` | Build-Output für `ui_page` |

## Runtime-Fluss (M0)

1. Resource startet → Locales/Config/Bridges laden.
2. Server erkennt Framework (ESX voll nutzbar, QB/QBox Stub), führt `sql/install.sql` aus.
3. Client registriert `/workshop` (Config) → öffnet NUI-Placeholder „Workshop“.
4. Escape / Close-Callback → `SetNuiFocus(false)`.

## Exports

- `exports.ef_mechanic:ApplyVehicleState(vehicle, plate)` — Stub, stabile Signatur für Garagen.

## Nächste Meilensteine

- **M1** Locations, Job-Gate, Self-Service-Reparatur
- **M2** Standard-Tuning
- **M3** Mileage + Servicing
- **M4** Rechnungen + Society
- **M5** Shop/Stash/Items/Doku

## Hinweise

- Dieses Verzeichnis wurde vorübergehend im `ignis`-Repo als Top-Level-Ordner geliefert, weil das Anlegen von `emergencyforge/ef_mechanic` mit dem Agent-Token nicht möglich war (403 createRepository). Zielzustand: eigenes privates Repo.
