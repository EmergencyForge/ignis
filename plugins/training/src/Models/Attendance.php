<?php

declare(strict_types=1);

namespace Plugin\Training\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * Anwesenheit einer Teilnahme bei einem Termin.
 *
 * @property int      $id
 * @property int      $session_id
 * @property int      $participation_id
 * @property string   $state        present|excused|unexcused
 * @property int|null $recorded_by
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class Attendance extends EloquentModel
{
    protected $table = 'intra_training_attendance';

    protected $guarded = [];

    public const STATES = [
        'present'   => 'Anwesend',
        'excused'   => 'Entschuldigt',
        'unexcused' => 'Unentschuldigt',
    ];

    /** @var array<string, string> */
    protected $casts = ['id' => 'integer', 'session_id' => 'integer', 'participation_id' => 'integer', 'recorded_by' => 'integer'];
}
