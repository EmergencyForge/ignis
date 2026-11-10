<?php

declare(strict_types=1);

namespace Plugin\Training\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * Antwort auf eine Frage eines Versuchs. `answer` steht in den Indizes der
 * Frage, nicht in der gemischten Anzeige. Es zählen die Punkte von Hand,
 * sobald es sie gibt, sonst die automatischen.
 *
 * @property int         $id
 * @property int         $attempt_id
 * @property int         $position
 * @property int         $question_id
 * @property int         $question_version
 * @property mixed       $answer
 * @property float|null  $auto_points
 * @property float|null  $manual_points
 * @property bool        $needs_review
 * @property string|null $comment
 * @property int|null    $graded_by
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class AttemptAnswer extends EloquentModel
{
    protected $table = 'intra_training_attempt_answers';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'id'               => 'integer',
        'attempt_id'       => 'integer',
        'position'         => 'integer',
        'question_id'      => 'integer',
        'question_version' => 'integer',
        'answer'           => 'json',
        'auto_points'      => 'float',
        'manual_points'    => 'float',
        'needs_review'     => 'boolean',
        'graded_by'        => 'integer',
    ];

    public function points(): float
    {
        return $this->manual_points ?? $this->auto_points ?? 0.0;
    }

    /** Ein Prüfer hat die automatische Bewertung geändert. */
    public function isOverridden(): bool
    {
        return $this->manual_points !== null && $this->auto_points !== null && abs($this->manual_points - $this->auto_points) > 1e-9;
    }
}
