<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Models;

use App\Models\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * `intra_edivi_medikamente`: Medikamentenstamm für die Maßnahmen-
 * Dokumentation. `wirkstoff` ist UNIQUE (Duplikat-Insert → SQLSTATE
 * 23000), `dosierungen` ist Freitext/TEXT.
 *
 * @method static Builder<static> active()
 */
class Medikament extends Model
{
    protected $table = 'intra_edivi_medikamente';

    /**
     * @param Builder<self> $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', 1);
    }
}
