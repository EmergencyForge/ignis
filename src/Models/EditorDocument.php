<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent-Model für `intra_documents` — Dokumente des Editors, im Entwurf
 * oder ausgestellt, immer an genau einem Mitarbeiter.
 *
 * `docid` ist die siebenstellige öffentliche Kennung (siehe
 * {@see \App\Documents\Editor\DocumentId}), unabhängig vom internen
 * Primärschlüssel: sie steht auf dem PDF und wird weitergereicht, ohne zu
 * verraten, wie viele Dokumente es insgesamt gibt.
 *
 * Zustandsmaschine: `entwurf` → `ausgestellt`, einmalig. Beim Ausstellen
 * werden die Variablenwerte in `frozen_values` eingefroren und
 * `pdf_path`/`issued_by`/`issued_at` gesetzt. Danach ändert sich am Inhalt
 * nichts mehr — {@see isIssued()} ist die Bedingung, an der Controller und
 * Policy das festmachen.
 *
 * Nicht zu verwechseln mit {@see PersonnelDocument} — das sind die
 * Dokumente des alten Canvas-Systems.
 *
 * @property int                      $id
 * @property string                   $docid
 * @property int|null                 $template_id
 * @property int                      $mitarbeiter_id
 * @property string                   $title
 * @property array<string,mixed>      $content
 * @property string                   $status
 * @property array<string,string>|null $frozen_values
 * @property string|null              $pdf_path
 * @property int|null                 $created_by
 * @property int|null                 $issued_by
 * @property \Illuminate\Support\Carbon|null $issued_at
 * @property \Illuminate\Support\Carbon      $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read EditorTemplate|null $template
 * @property-read Personnel|null      $mitarbeiter
 */
class EditorDocument extends Model
{
    public const STATUS_DRAFT  = 'entwurf';
    public const STATUS_ISSUED = 'ausgestellt';

    protected $table = 'intra_documents';

    public $timestamps = true;

    /** @var array<string,string> */
    protected $casts = [
        'id'             => 'integer',
        'template_id'    => 'integer',
        'mitarbeiter_id' => 'integer',
        'content'        => 'array',
        'frozen_values'  => 'array',
        'created_by'     => 'integer',
        'issued_by'      => 'integer',
        'issued_at'      => 'datetime',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    /** @return BelongsTo<EditorTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(EditorTemplate::class, 'template_id', 'id');
    }

    /** @return BelongsTo<Personnel, $this> */
    public function mitarbeiter(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'mitarbeiter_id', 'id');
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }
}
