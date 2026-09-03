<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent-Model für `intra_dokument_kategorien` — Kategorien für
 * Dokument-Templates (Name, Farbe/Chip-Klasse, Icon, Sortierung).
 *
 * @property int         $id
 * @property string      $name
 * @property string      $color
 * @property string|null $icon
 * @property int         $sort_order
 */
class DocumentCategory extends Model
{
    protected $table = 'intra_dokument_kategorien';

    protected $casts = [
        'id' => 'integer',
    ];

    public function templates(): HasMany
    {
        return $this->hasMany(DocumentTemplate::class, 'category_id', 'id');
    }
}
