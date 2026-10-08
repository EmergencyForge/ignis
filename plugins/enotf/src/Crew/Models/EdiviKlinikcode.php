<?php

declare(strict_types=1);

namespace Plugin\Enotf\Crew\Models;

use App\Models\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * `intra_edivi_klinikcodes`: 6-stellige Einmalcodes (A–Z, 0–9) für den
 * Klinik-Zugriff auf ein Protokoll.
 *
 * `code` ist UNIQUE, `expires_at` NOT NULL, Gültigkeit 1 Stunde.
 * Ein noch gültiger Code für dieselbe ENR wird wiederverwendet statt
 * neu erzeugt.
 *
 * @method static Builder<static> gueltig()
 */
class EdiviKlinikcode extends Model
{
    protected $table = 'intra_edivi_klinikcodes';

    /**
     * @param Builder<self> $query
     */
    public function scopeGueltig(Builder $query): void
    {
        $query->where('expires_at', '>', date('Y-m-d H:i:s'));
    }
}
