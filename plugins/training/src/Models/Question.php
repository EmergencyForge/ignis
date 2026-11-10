<?php

declare(strict_types=1);

namespace Plugin\Training\Models;

use EmergencyForge\Training\Question\Question as PoolQuestion;
use EmergencyForge\Training\Question\QuestionType;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Frage im Fragenpool. Die ID bleibt über alle Versionen gleich; frühere
 * Fassungen liegen als weich gelöschte Zeilen hinter `previous_id`.
 *
 * @property int                       $id
 * @property int                       $course_type_id
 * @property int|null                  $topic_id
 * @property string                    $type
 * @property string                    $body
 * @property string|null               $image_path  ID einer TrainingFile
 * @property float                     $points
 * @property array<string, mixed>|null $spec
 * @property string|null               $explanation
 * @property int                       $version
 * @property int|null                  $previous_id
 * @property bool                      $is_active
 * @property bool                      $ai_draft    von der KI vorgeschlagen und noch nicht angeschaltet
 * @property string|null               $source      Herkunft eines KI-Entwurfs, z. B. „Skript.pdf, S. 12“
 * @property Carbon|null               $deleted_at
 * @property int|null                  $created_by
 * @property Carbon                    $created_at
 * @property Carbon                    $updated_at
 * @property-read Topic|null $topic
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class Question extends EloquentModel
{
    protected $table = 'intra_training_questions';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'id'             => 'integer',
        'course_type_id' => 'integer',
        'topic_id'       => 'integer',
        'points'         => 'float',
        'spec'           => 'array',
        'version'        => 'integer',
        'previous_id'    => 'integer',
        'is_active'      => 'boolean',
        'ai_draft'       => 'boolean',
        'deleted_at'     => 'datetime',
        'created_by'     => 'integer',
    ];

    /** @return BelongsTo<Topic, $this> */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }

    public function questionType(): QuestionType
    {
        return QuestionType::from($this->type);
    }

    public function toDomain(): PoolQuestion
    {
        return PoolQuestion::fromArray([
            'id'          => $this->id,
            'version'     => $this->version,
            'type'        => $this->type,
            'topic_id'    => $this->topic_id,
            'body'        => $this->body,
            'points'      => $this->points,
            'explanation' => $this->explanation,
            'image'       => $this->image_path,
            'spec'        => $this->spec,
        ]);
    }
}
