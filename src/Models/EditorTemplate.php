<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent-Model für `intra_document_templates` — die Vorlagen des
 * Dokumenten-Editors aus emergencyforge/editor.
 *
 * `content` ist ProseMirror-JSON aus `docSection`-Knoten: gesperrte
 * Abschnitte, die beim Ausstellen unverändert aus der Vorlage
 * zurückgeholt werden, und freie Abschnitte, in denen der Aussteller
 * schreiben darf. Gerendert wird das nur über
 * `EmergencyForge\Editor\Renderer`.
 *
 * Nicht zu verwechseln mit {@see DocumentTemplate} — das ist die Vorlage
 * des alten Canvas-Systems und verschwindet mit ihm.
 *
 * @property int                      $id
 * @property string                   $name
 * @property string|null              $category
 * @property array<string,mixed>      $content
 * @property bool                     $is_active
 * @property int|null                 $created_by
 * @property \Illuminate\Support\Carbon      $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read int $documents_count  nur mit withCount('documents')
 */
class EditorTemplate extends Model
{
    protected $table = 'intra_document_templates';

    public $timestamps = true;

    /** @var array<string,string> */
    protected $casts = [
        'id'         => 'integer',
        'content'    => 'array',
        'is_active'  => 'boolean',
        'created_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return HasMany<EditorDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(EditorDocument::class, 'template_id', 'id');
    }

    /**
     * Nur Vorlagen, aus denen noch ausgestellt werden darf. Eine
     * deaktivierte bleibt stehen, damit bereits ausgestellte Dokumente
     * ihre Herkunft behalten.
     *
     * @param  Builder<EditorTemplate>  $query
     * @return Builder<EditorTemplate>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
