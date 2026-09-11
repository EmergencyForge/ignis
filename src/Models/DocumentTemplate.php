<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent-Model für `intra_dokument_templates` — Dokumentvorlagen.
 *
 * `config` hält JSON (u.a. geschlechtsspezifische Select-Labels und das
 * is_draft-Flag) — bewusst als roher String, weil die bestehenden
 * Manager-Klassen selbst (de)serialisieren.
 *
 * @property int         $id
 * @property string      $name
 * @property string|null $category
 * @property int|null    $category_id
 * @property string|null $description
 * @property string|null $template_file
 * @property string      $editor_type
 * @property string|null $config
 */
class DocumentTemplate extends Model
{
    protected $table = 'intra_dokument_templates';

    protected $casts = [
        'id' => 'integer',
    ];

    public function categoryModel(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'category_id', 'id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(DocumentTemplateField::class, 'template_id', 'id');
    }
}
