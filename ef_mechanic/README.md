# ef_mechanic

Clean-Room-Werkstatt-Resource für FiveM (ESX-first).  
**Milestone 0 — Scaffold.** Noch keine vollständige Spielmechanik.

## Abhängigkeiten (MVP)

- [oxmysql](https://github.com/overextended/oxmysql)
- [ox_lib](https://github.com/overextended/ox_lib)
- [ox_inventory](https://github.com/overextended/ox_inventory)
- [ox_target](https://github.com/overextended/ox_target) (ab M1 für Shops/Stashes)
- ESX Legacy 1.3+

QBCore / QBox: Bridge-Signaturen sind vorbereitet, Implementierung folgt.

## Installation (Kurz)

1. Ordner `ef_mechanic` nach `resources/[local]/` (oder vergleichbar) kopieren.
2. SQL ausführen **oder** Resource starten (legt Tabellen idempotent an):

   ```sql
   -- siehe sql/install.sql
   ```

3. Items aus `install/ox_inventory/items.lua` in `ox_inventory/data/items.lua` übernehmen (eigene Icons bereitstellen).
4. In `server.cfg`:

   ```cfg
   ensure oxmysql
   ensure ox_lib
   ensure ox_inventory
   ensure ox_target
   ensure ef_mechanic
   ```

5. Locations in `config/locations.lua` anpassen.

## Escrow

Folgende Pfade bleiben editierbar (`escrow_ignore`):

- `config/**`
- `locales/**`
- `sql/**`
- `install/**`
- `README.md`

## NUI bauen

```bash
cd web
npm install
npm run build
```

Output landet in `nui/`.

## Commands (Scaffold)

| Command | Beschreibung |
| --- | --- |
| `/workshop` | Öffnet das Platzhalter-Tablet (Config: `Config.TabletCommand`) |

## Clean Room

Kein Code, keine Assets und keine Marken von JG Scripts / jg-mechanic.  
Eigene Architektur, eigene UI, eigene Item-IDs (`ef_*`).

## Status

Siehe `ARCHITECTURE.md` für den technischen Ist-Stand.
