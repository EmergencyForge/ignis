<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent-Model für `intra_dokument_template_layouts` — versionierte
 * Canvas-Layouts (Fabric.js-JSON) des visuellen Dokument-Editors.
 *
 * Pro Template ist genau eine Version aktiv (`is_active = 1`); ältere
 * Versionen bleiben für die Historie/Wiederherstellung erhalten.
 *
 * @property int         $id
 * @property int         $template_id
 * @property int         $version
 * @property string      $canvas_json
 * @property float|null  $page_width_mm
 * @property float|null  $page_height_mm
 * @property int         $is_active
 * @property int|null    $created_by
 */
class DocumentTemplateLayout extends Model
{
    protected $table = 'intra_dokument_template_layouts';

    protected $casts = [
        'id'          => 'integer',
        'template_id' => 'integer',
        'version'     => 'integer',
        'is_active'   => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id', 'id');
    }
}
