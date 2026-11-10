<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent-Model für `intra_document_templates`: die Vorlagen des
 * Dokumenten-Editors aus emergencyforge/editor.
 *
 * `content` ist das Dokumentpaket (editor 0.6) der aktuellen Fassung.
 * Jede Fassung steht zusätzlich in `intra_document_template_versions`, die
 * jüngste immer mit demselben Inhalt wie `content`. Ein Dokument hängt an
 * der Fassung, mit der es angelegt wurde.
 *
 * Nicht zu verwechseln mit {@see DocumentTemplate}. Das ist die Vorlage
 * des alten Canvas-Systems und verschwindet mit ihm.
 *
 * @property int                      $id
 * @property string                   $name
 * @property string|null              $category
 * @property bool                     $print_verification Prüfcode und QR beim Ausstellen
 * @property array<string,mixed>      $content
 * @property bool                     $is_active
 * @property int|null                 $created_by
 * @property \Illuminate\Support\Carbon      $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read int $documents_count  nur mit withCount('documents')
 *
 * @method static Builder<static> active()
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
        'print_verification' => 'boolean',
        'created_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return HasMany<EditorDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(EditorDocument::class, 'template_id', 'id');
    }

    /** @return HasMany<EditorTemplateVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(EditorTemplateVersion::class, 'template_id', 'id');
    }

    public function latestVersion(): ?EditorTemplateVersion
    {
        return $this->versions()->orderBy('version', 'desc')->first();
    }

    /**
     * Speichert Vorlage und Inhalt. Hängt an der jüngsten Fassung schon ein
     * Dokument, entsteht eine neue; sonst wird die jüngste überschrieben,
     * damit nicht jeder Zwischenstand eine Fassung wird.
     *
     * @param array<string,mixed> $content
     */
    public function saveContent(array $content, ?int $userId): void
    {
        Capsule::connection()->transaction(function () use ($content, $userId): void {
            $this->content = $content;
            $this->save();

            // Sperrt die Zeile, damit zwei gleichzeitige Speicherungen nicht
            // dieselbe Fassungsnummer ziehen.
            static::query()->whereKey($this->id)->lockForUpdate()->first();

            $latest = $this->latestVersion();
            if ($latest !== null && !EditorDocument::query()->where('template_version_id', $latest->id)->exists()) {
                $latest->content = $content;
                $latest->save();
                return;
            }

            $this->versions()->create([
                'version'    => ($latest->version ?? 0) + 1,
                'content'    => $content,
                'created_by' => $userId,
            ]);
        });
    }

    /**
     * Felder für ein neues Dokument aus dieser Vorlage: es hängt an der
     * jüngsten Fassung und startet mit deren Inhalt.
     *
     * @return array{template_id: int, template_version_id: int, content: array<string,mixed>}
     */
    public function newDocumentAttributes(): array
    {
        $version = $this->latestVersion();
        if ($version === null) {
            // Vorlage aus der Zeit vor den Fassungen, etwa per Hand eingefügt.
            $this->saveContent($this->content, $this->created_by);
            $version = $this->latestVersion();
        }

        return [
            'template_id'         => $this->id,
            'template_version_id' => (int) $version?->id,
            'content'             => $version->content ?? $this->content,
        ];
    }

    /**
     * Nur Vorlagen, aus denen noch ausgestellt werden darf. Eine
     * deaktivierte bleibt stehen, damit bereits ausgestellte Dokumente
     * ihre Herkunft behalten.
     *
     * @param Builder<self> $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
