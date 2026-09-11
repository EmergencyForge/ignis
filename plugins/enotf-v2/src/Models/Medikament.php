<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Models;

use App\Models\Model;

/**
 * `intra_edivi_medikamente` — Medikamentenstamm für die Maßnahmen-
 * Dokumentation. `wirkstoff` ist UNIQUE (Duplikat-Insert → SQLSTATE
 * 23000), `dosierungen` ist Freitext/TEXT.
 */
class Medikament extends Model
{
    protected $table = 'intra_edivi_medikamente';

    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }
}
