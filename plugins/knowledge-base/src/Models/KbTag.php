<?php

declare(strict_types=1);

namespace Plugin\KnowledgeBase\Models;

use App\Models\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Eloquent-Model für `intra_kb_tags` — flache Tags der Wissensdatenbank
 * mit Farbcode. Zuordnung zu Einträgen via `intra_kb_entry_tags` (n:m).
 *
 * @property int    $id
 * @property string $name
 * @property string $color
 * @property string $created_at
 */
class KbTag extends Model
{
    protected $table = 'intra_kb_tags';

    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(KbEntry::class, 'intra_kb_entry_tags', 'tag_id', 'entry_id');
    }
}
