<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Models;

use App\Models\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * `intra_enotf_quicklinks`: Schnellzugriff-Kacheln auf der Overview.
 *
 * `category_slug` referenziert intra_enotf_categories.slug (kein FK).
 * `col_width` ist ein Bootstrap-Erbe ('col-6' etc.), v2 rendert
 * eigenes Grid, interpretiert den Wert aber weiterhin als Breiten-Hint.
 *
 * @method static Builder<static> active()
 */
class Quicklink extends Model
{
    protected $table = 'intra_enotf_quicklinks';

    /**
     * @param Builder<self> $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', 1);
    }
}
