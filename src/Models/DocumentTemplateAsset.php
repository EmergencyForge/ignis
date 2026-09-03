<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent-Model für `intra_dokument_template_assets` — hochgeladene
 * Bilder (Logos, Hintergründe, Signaturen) für Dokument-Templates.
 *
 * `template_id = NULL` markiert globale Assets, die in allen Templates
 * verfügbar sind. Die Dateien selbst liegen unter storage/template-assets.
 *
 * @property int         $id
 * @property int|null    $template_id
 * @property string      $filename
 * @property string      $original_name
 * @property string      $mime_type
 * @property int         $file_size
 * @property int|null    $width_px
 * @property int|null    $height_px
 * @property string      $asset_type
 * @property int|null    $uploaded_by
 */
class DocumentTemplateAsset extends Model
{
    protected $table = 'intra_dokument_template_assets';

    protected $casts = [
        'id'          => 'integer',
        'template_id' => 'integer',
        'file_size'   => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id', 'id');
    }
}
