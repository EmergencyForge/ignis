<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Models;

use App\Models\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * `intra_edivi_prereg`: Klinik-Voranmeldungen (Arrivalboard).
 *
 * `ziel` ist ein POI-Identifier-String (`poi_<id>` oder ein
 * legacy_identifier). Die Spalte `alter` ist ein MySQL-Keyword,
 * in Raw-Queries immer quoten! Eloquent-Attributzugriff
 * (`$prereg->alter`) ist davon nicht betroffen.
 *
 * Auto-Expiry: Lesepfade setzen active=0 für Einträge mit
 * arrival < NOW() - 10 Minuten.
 *
 * @method static Builder<static> active()
 */
class EdiviPrereg extends Model
{
    protected $table = 'intra_edivi_prereg';

    /**
     * @param Builder<self> $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', 1);
    }
}
