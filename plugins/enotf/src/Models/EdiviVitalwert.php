<?php

declare(strict_types=1);

namespace Plugin\Enotf\Models;

use App\Models\Model;

/**
 * Eloquent-Model für `intra_edivi_vitalparameter_einzelwerte` —
 * einzeln erfasste Vitalwerte im Verlauf eines eNOTF-Protokolls.
 *
 * Eine Zeile pro Messwert (Parameter-Name, Wert, Einheit) zu einem
 * Zeitpunkt, verknüpft über `enr` mit `intra_edivi`. Gelöscht wird nur
 * soft (`geloescht` + `geloescht_am`/`geloescht_von`), Abfragen filtern
 * daher auf `geloescht = 0`.
 *
 * `erstellt_am` hat einen DB-Default (CURRENT_TIMESTAMP) und wird nicht
 * von Eloquent verwaltet.
 */
class EdiviVitalwert extends Model
{
    protected $table = 'intra_edivi_vitalparameter_einzelwerte';
}
