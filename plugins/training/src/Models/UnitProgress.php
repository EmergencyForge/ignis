<?php

declare(strict_types=1);

namespace Plugin\Training\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Carbon;

/**
 * Stand einer Lerneinheit für eine Teilnahme. `pages` hält bei PDFs die
 * gesehenen Seiten als Bereiche, die Seitenzahl und die zuletzt gesehene
 * Seite: ['seen' => [[1,12]], 'total' => 40, 'last' => 12].
 * `completed_by` ist gesetzt, wenn ein Ausbilder die Einheit von Hand
 * abgehakt hat.
 *
 * @property int                       $id
 * @property int                       $participation_id
 * @property int                       $unit_id
 * @property string                    $status   started|done
 * @property array<string, mixed>|null $pages
 * @property Carbon|null               $completed_at
 * @property int|null                  $completed_by
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class UnitProgress extends EloquentModel
{
    protected $table = 'intra_training_unit_progress';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'id'               => 'integer',
        'participation_id' => 'integer',
        'unit_id'          => 'integer',
        'completed_by'     => 'integer',
        'pages'            => 'array',
        'completed_at'     => 'datetime',
    ];
}
