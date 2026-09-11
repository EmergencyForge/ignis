<?php

declare(strict_types=1);

namespace Plugin\KnowledgeBase\Models;

use App\Models\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent-Model für `intra_kb_categories` — hierarchische Kategorien
 * der Wissensdatenbank (Baum via parent_id).
 *
 * @property int         $id
 * @property int|null    $parent_id   FK → intra_kb_categories (self)
 * @property string      $name
 * @property string      $slug
 * @property string|null $icon
 * @property int         $sort_order
 * @property string      $created_at
 */
class KbCategory extends Model
{
    protected $table = 'intra_kb_categories';

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(KbEntry::class, 'category_id', 'id');
    }
}
