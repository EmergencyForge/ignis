<?php

declare(strict_types=1);

namespace Plugin\Training\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * Zusatzversuch der Leitung für einen Prüfungsteil, mit Begründung.
 *
 * @property int      $id
 * @property int      $participation_id
 * @property int      $exam_id
 * @property string   $reason
 * @property int|null $granted_by
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class ExtraAttempt extends EloquentModel
{
    protected $table = 'intra_training_extra_attempts';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = ['id' => 'integer', 'participation_id' => 'integer', 'exam_id' => 'integer', 'granted_by' => 'integer'];
}
