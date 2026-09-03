<?php

declare(strict_types=1);

namespace Plugin\Enotf\Models;

use App\Models\Model;

/**
 * Eloquent-Model für `intra_edivi` — die eNOTF-Protokolle (eDIVI).
 *
 * Eine Zeile pro Einsatzprotokoll. Primärschlüssel ist `id`, praktisch
 * laufen aber fast alle Zugriffe über die Einsatznummer `enr` (NOT NULL,
 * varchar) — die Protokoll-Templates laden ihren Datensatz mit
 * `Edivi::where('enr', ...)->first()`.
 *
 * Die Tabelle ist über viele ALTER-Migrations gewachsen (Vitalwerte,
 * Abschluss-/Freigabefelder, Billing, Sonderrechte, ...) und hat weit über
 * 100 Spalten. Die Templates greifen per Array-Syntax (`$daten['spalte']`)
 * zu — das funktioniert über den ArrayAccess des Models unverändert.
 *
 * Kein `created_at`/`updated_at`-Paar: Zeitstempel sind `sendezeit`
 * (DB-Default CURRENT_TIMESTAMP) und `last_edit` (wird von den Templates
 * explizit per NOW() gesetzt).
 */
class Edivi extends Model
{
    protected $table = 'intra_edivi';
}
