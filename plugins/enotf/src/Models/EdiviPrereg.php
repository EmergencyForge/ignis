<?php

declare(strict_types=1);

namespace Plugin\Enotf\Models;

use App\Models\Model;

/**
 * Eloquent-Model für `intra_edivi_prereg` — Klinik-Voranmeldungen
 * aus dem eNOTF-Protokoll (Schnittstelle Leitstelle/Klinik).
 *
 * Die Spalte `alter` ist ein reserviertes MySQL-Wort — Eloquent quotet
 * Spaltennamen automatisch, dadurch ist das hier kein Sonderfall mehr.
 * `timestamp` und `active` haben DB-Defaults und müssen beim Insert
 * nicht gesetzt werden.
 */
class EdiviPrereg extends Model
{
    protected $table = 'intra_edivi_prereg';
}
