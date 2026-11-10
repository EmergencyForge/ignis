<?php

declare(strict_types=1);

namespace Plugin\Training\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Freigabe eines Prüfungsteils in einem Lehrgang, ohne participation_id
 * für alle. Eine Freigabe für eine Person lässt sie auch ohne erfüllte
 * Zulassung zu.
 *
 * @property int      $id
 * @property int      $exam_id
 * @property int      $course_id
 * @property int|null $participation_id
 * @property int|null $released_by
 * @property Carbon   $released_at
 * @property-read User|null $releaser
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class ExamRelease extends EloquentModel
{
    protected $table = 'intra_training_exam_releases';

    public $timestamps = false;

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'id'               => 'integer',
        'exam_id'          => 'integer',
        'course_id'        => 'integer',
        'participation_id' => 'integer',
        'released_by'      => 'integer',
        'released_at'      => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
