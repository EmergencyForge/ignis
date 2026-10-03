<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Models;

use App\Models\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `intra_edivi_pois`: Points of Interest (Kliniken, Einsatzorte, …).
 *
 * `legacy_identifier` (nullable, UNIQUE) ist der alte Ziel-Identifier
 * aus intra_edivi_ziele; Prereg-`ziel`-Strings referenzieren entweder
 * `poi_<id>` oder diesen legacy_identifier.
 *
 * Klinik-Features (Fachabteilungen, Verfügbarkeit, Zugangscode) gibt es
 * nur bei `typ` exakt 'Krankenhaus' oder 'Klinik'.
 *
 * @method static Builder<static> active()
 * @method static Builder<static> kliniken()
 */
class EdiviPoi extends Model
{
    protected $table = 'intra_edivi_pois';

    public const TYP_KLINIKEN = ['Krankenhaus', 'Klinik'];

    /**
     * @param Builder<self> $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', 1);
    }

    /**
     * @param Builder<self> $query
     */
    public function scopeKliniken(Builder $query): void
    {
        $query->whereIn('typ', self::TYP_KLINIKEN);
    }

    /**
     * @return HasMany<HospitalDepartment, $this>
     */
    public function departments(): HasMany
    {
        return $this->hasMany(HospitalDepartment::class, 'poi_id');
    }
}
