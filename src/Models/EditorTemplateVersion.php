<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eine Fassung einer Editor-Vorlage. Ein Dokument prüft beim Speichern
 * immer gegen die Fassung, mit der es angelegt wurde, nie gegen die
 * aktuelle Vorlage. Fassungen werden nie geändert, sobald ein Dokument an
 * ihnen hängt (siehe EditorTemplate::saveContent()).
 *
 * @property int $id
 * @property int $template_id
 * @property int $version
 * @property array<string,mixed> $content
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read EditorTemplate|null $template
 */
class EditorTemplateVersion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'intra_document_template_versions';

    public $timestamps = true;

    /** @var array<string,string> */
    protected $casts = [
        'id'          => 'integer',
        'template_id' => 'integer',
        'version'     => 'integer',
        'content'     => 'array',
        'created_by'  => 'integer',
        'created_at'  => 'datetime',
    ];

    /** @return BelongsTo<EditorTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(EditorTemplate::class, 'template_id', 'id');
    }

    /** @return HasMany<EditorDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(EditorDocument::class, 'template_version_id', 'id');
    }
}
