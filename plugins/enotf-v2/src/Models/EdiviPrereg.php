<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Models;

use App\Models\Model;

/**
 * `intra_edivi_prereg` — Klinik-Voranmeldungen (Arrivalboard).
 *
 * `ziel` ist ein POI-Identifier-String (`poi_<id>` oder ein
 * legacy_identifier). Die Spalte `alter` ist ein MySQL-Keyword —
 * in Raw-Queries immer quoten! Eloquent-Attributzugriff
 * (`$prereg->alter`) ist davon nicht betroffen.
 *
 * Auto-Expiry: Lesepfade setzen active=0 für Einträge mit
 * arrival < NOW() - 10 Minuten.
 */
class EdiviPrereg extends Model
{
    protected $table = 'intra_edivi_prereg';

    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }
}
