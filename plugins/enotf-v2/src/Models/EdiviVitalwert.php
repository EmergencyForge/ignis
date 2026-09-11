<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Models;

use App\Models\Model;

/**
 * `intra_edivi_vitalparameter_einzelwerte` — ein Messwert pro Zeile,
 * verknüpft über `enr` (varchar(50), KEIN FK) mit intra_edivi.
 *
 * ACHTUNG: Ein BEFORE-DELETE-Trigger in der Datenbank blockiert
 * Hard-Deletes (SIGNAL 45000). Löschen geht AUSSCHLIESSLICH als
 * Soft-Delete über softDelete() — niemals ->delete() auf diesem
 * Model aufrufen. Lesende Queries müssen auf `geloescht = 0`
 * filtern (scopeAktiv).
 *
 * `parameter_name` enthält in Altdaten deutsche Anzeigestrings mit
 * Unicode-Subskript (`SpO₂`, `etCO₂`, …) — v2 mappt beim Lesen auf
 * Codes, muss die Altstrings aber verstehen.
 * Blutzucker wird immer in mg/dl gespeichert (BloodSugarHelper).
 */
class EdiviVitalwert extends Model
{
    protected $table = 'intra_edivi_vitalparameter_einzelwerte';

    /**
     * Scope: nur nicht gelöschte Messwerte.
     */
    public function scopeAktiv($query)
    {
        return $query->where('geloescht', 0);
    }

    /**
     * Soft-Delete — der einzige erlaubte Löschweg (DB-Trigger blockiert
     * DELETE). Setzt geloescht/geloescht_am/geloescht_von.
     */
    public function softDelete(?string $geloeschtVon = null): bool
    {
        $this->geloescht     = 1;
        $this->geloescht_am  = date('Y-m-d H:i:s');
        $this->geloescht_von = $geloeschtVon;

        return $this->save();
    }
}
