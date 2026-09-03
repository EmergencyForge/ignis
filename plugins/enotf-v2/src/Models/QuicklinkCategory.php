<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Models;

use App\Models\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `intra_enotf_categories` — Kategorien für die Quicklinks der Overview.
 * `slug` ist UNIQUE; Löschen wird in v1 blockiert, solange Quicklinks
 * den Slug referenzieren.
 */
class QuicklinkCategory extends Model
{
    protected $table = 'intra_enotf_categories';

    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }

    public function links(): HasMany
    {
        return $this->hasMany(Quicklink::class, 'category_slug', 'slug');
    }
}
