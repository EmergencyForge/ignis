# intraRP Migrations

Versionierte Datenbank-Migrations für intraRP, gefahren von [Phinx](https://phinx.org).

## Aufruf

```bash
# Migrations gegen Production-DB fahren (liest .env)
composer db:migrate

# Status anzeigen — welche Migrations sind up/down
composer db:status

# Manuell (gleicher Effekt wie composer db:migrate, lädt aber AutoMigrator + Bridge)
php tools/db-migrate.php
```

Im Web-Kontext wird Phinx automatisch via [`App\Database\AutoMigrator`](../../src/Database/AutoMigrator.php) aus dem Bootstrap aufgerufen — User braucht keinen Shell-Zugang.

## Konvention für neue Migrations

```bash
vendor/bin/phinx create AddNeueSpalteZuTabelle -c phinx.php
```

Phinx erzeugt automatisch eine Datei mit Timestamp-Prefix:

```text
database/migrations/20260408142233_add_neue_spalte_zu_tabelle.php
```

Klassenname muss exakt der CamelCase-Variante des Filename-Stems entsprechen — Phinx prüft das.

### Beispiel: Native Phinx-API (empfohlen für neue Migrations)

```php
<?php
declare(strict_types=1);
use Phinx\Migration\AbstractMigration;

final class AddNeueSpalteZuTabelle extends AbstractMigration
{
    public function change(): void
    {
        $this->table('intra_users')
            ->addColumn('lieblings_farbe', 'string', ['limit' => 32, 'null' => true])
            ->update();
    }
}
```

### Beispiel: Raw SQL (für Edge-Cases)

```php
public function change(): void
{
    $this->execute("ALTER TABLE intra_xy ADD INDEX idx_foo (foo_id)");
}
```

## Legacy-Migrations (Pre-Phinx)

Die ersten 147 Migrations sind **Wrapper** um Original-Files aus der Pre-Phinx-Ära (früher in `assets/database/`, mittlerweile gelöscht). Sie liegen jetzt unter [`database/legacy/`](../legacy/) und werden von den entsprechenden Phinx-Klassen via `require` eingebunden. Das hält das ursprüngliche SQL byte-identisch und vermied 147 manuelle Übersetzungen.

**Dateinamen-Schema dieser Wrapper:**

```text
20250607000001_create_intra_users_roles_07062025.php  ← Phinx-Wrapper
                                                          (Klasse: CreateIntraUsersRoles07062025)
                                                          requires:
database/legacy/create_intra_users_roles_07062025.php ← Original Pre-Phinx-File
```

Die Sequenznummer (000001 ... 000147) bildet die ursprüngliche Reihenfolge aus dem alten `$migrationFiles`-Array ab (das Array war historisch in `setup/database-init.php` und ist beim Phinx-Cutover gelöscht worden). Neue Migrations bekommen reale Timestamps via `vendor/bin/phinx create ...` und werden an diese Sequenz angehängt.

**Bridge für bestehende Installs**: [`AutoMigrator`](../../src/Database/AutoMigrator.php) erkennt eine vorhandene `intra_migrations`-Tabelle (Pre-Phinx) und überträgt deren Einträge bei der ersten Ausführung in `phinxlog`. So sehen bestehende Installationen die historischen Migrations als „erledigt" und Phinx läuft sie nicht erneut.

**Inkrementelle Migration zu nativem Phinx**: Wer Lust hat, kann einzelne Wrapper mit der Zeit auf native Phinx-API umschreiben. Dabei nicht die Klasse umbenennen und keinen Timestamp ändern — Phinx tracked nur den Timestamp und der Klassenname muss zum Filename passen.

## Fresh-Install Test

[`tools/test-fresh-db.php`](../../tools/test-fresh-db.php) erstellt (oder reuses) eine wegwerfbare Test-DB, lässt Phinx alle Migrations gegen sie laufen und vergleicht die Tabellen-Liste mit der Live-DB.

```bash
# Konfiguration: .env.test (siehe Vorlage im Repo-Root)
php tools/test-fresh-db.php
```

Zwei Modi via `TEST_DB_SKIP_CREATE` in `.env.test`:

- `0` (Default): Script droppt+erstellt die Test-DB selbst — braucht User mit CREATE/DROP-Rechten
- `1`: Script nutzt eine bereits angelegte leere DB — funktioniert mit Webspace-Usern ohne CREATE-Rechte
