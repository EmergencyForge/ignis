<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent-Model für `intra_dokument_template_fields` — Eingabefelder
 * eines Dokument-Templates (Name, Label, Typ, Optionen).
 *
 * @property int    $id
 * @property int    $template_id
 * @property string $field_name
 * @property string $field_label
 * @property string $field_type
 * @property int    $is_required
 */
class DocumentTemplateField extends Model
{
    protected $table = 'intra_dokument_template_fields';

    protected $casts = [
        'id'          => 'integer',
        'template_id' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id', 'id');
    }
}
