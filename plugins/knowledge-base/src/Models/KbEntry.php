<?php

declare(strict_types=1);

namespace Plugin\KnowledgeBase\Models;

use App\Models\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Eloquent-Model für `intra_kb_entries` — Einträge der Wissensdatenbank
 * (Lexikon). Drei Typen: general, medication (med_*-Felder), measure
 * (mass_*-Felder).
 *
 * `created_at`/`updated_at` werden von den Controllern explizit gepflegt
 * (updated_at nur bei inhaltlichen Änderungen, nicht bei jedem Save),
 * daher kein Eloquent-Timestamp-Handling.
 *
 * @property int         $id
 * @property string      $type              enum: general|medication|measure
 * @property int|null    $category_id       FK → intra_kb_categories
 * @property string      $title
 * @property string|null $subtitle
 * @property string|null $competency_level
 * @property string|null $content
 * @property int         $is_pinned
 * @property int         $is_archived
 * @property int         $hide_editor
 * @property int|null    $created_by        FK → intra_users
 * @property int|null    $updated_by        FK → intra_users
 */
class KbEntry extends Model
{
    protected $table = 'intra_kb_entries';

    public function category(): BelongsTo
    {
        return $this->belongsTo(KbCategory::class, 'category_id', 'id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(KbTag::class, 'intra_kb_entry_tags', 'entry_id', 'tag_id');
    }
}
