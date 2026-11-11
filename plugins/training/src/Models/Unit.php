<?php

declare(strict_types=1);

namespace Plugin\Training\Models;

use EmergencyForge\Training\UnitKind;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lerneinheit: Datei, Text, Link, Wissensartikel, Termin oder Präsentation.
 *
 * @property int                       $id
 * @property int                       $module_id
 * @property int                       $position
 * @property string                    $kind
 * @property string                    $title
 * @property bool                      $required
 * @property int|null                  $file_id
 * @property array<string, mixed>|null $content  Text-Einheit: ['doc' => Editor-JSON]
 * @property string|null               $url
 * @property int|null                  $kb_entry_id
 * @property bool                      $allow_download
 * @property bool                      $slides_after_talk  Präsentation: Folien auch nach dem Vortrag einsehbar
 * @property Carbon                    $updated_at
 * @property-read Module            $module
 * @property-read TrainingFile|null $file
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class Unit extends EloquentModel
{
    protected $table = 'intra_training_units';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'id'             => 'integer',
        'module_id'      => 'integer',
        'position'       => 'integer',
        'required'       => 'boolean',
        'file_id'        => 'integer',
        'kb_entry_id'    => 'integer',
        'allow_download' => 'boolean',
        'slides_after_talk' => 'boolean',
        'content'        => 'array',
    ];

    /** @return BelongsTo<Module, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id');
    }

    /** @return BelongsTo<TrainingFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(TrainingFile::class, 'file_id');
    }

    public function type(): UnitKind
    {
        return UnitKind::tryFrom($this->kind) ?? UnitKind::Text;
    }

    public function icon(): string
    {
        return match ($this->type()) {
            UnitKind::File             => $this->file?->icon() ?? 'fa-solid fa-file',
            UnitKind::Text             => 'fa-solid fa-align-left',
            UnitKind::Link             => 'fa-solid fa-link',
            UnitKind::KnowledgeArticle => 'fa-solid fa-book-medical',
            UnitKind::Session          => 'fa-solid fa-calendar',
            UnitKind::Presentation     => 'fa-solid fa-person-chalkboard',
            UnitKind::Check            => 'fa-solid fa-list-check',
        };
    }
}
