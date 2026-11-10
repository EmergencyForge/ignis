<?php

declare(strict_types=1);

namespace Plugin\Training\Models;

use App\Models\Personnel;
use EmergencyForge\Training\ParticipationStatus;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ein Mitarbeiter in einem Lehrgang. Ohne `personnel_id` ist der
 * Mitarbeiter gelöscht worden; die Teilnahme bleibt für die Zahlen stehen.
 *
 * @property int         $id
 * @property int         $course_id
 * @property int|null    $personnel_id
 * @property string      $status
 * @property int|null    $waitlist_position
 * @property int|null    $enrolled_by
 * @property Carbon      $enrolled_at
 * @property Carbon|null $admitted_at
 * @property Carbon|null $finished_at
 * @property string|null $start_qualification_before
 * @property float|string|null $result_percent
 * @property string|null $grade
 * @property Carbon|null $expires_on
 * @property Carbon|null $expiry_notified_at
 * @property-read Course         $course
 * @property-read Personnel|null $personnel
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class Participation extends EloquentModel
{
    protected $table = 'intra_training_participations';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'id'                => 'integer',
        'course_id'         => 'integer',
        'personnel_id'      => 'integer',
        'waitlist_position' => 'integer',
        'enrolled_by'       => 'integer',
        'enrolled_at'       => 'datetime',
        'admitted_at'       => 'datetime',
        'finished_at'       => 'datetime',
        'expires_on'        => 'date',
        'expiry_notified_at' => 'datetime',
    ];

    /** Status, in denen die Unterlagen offen sind. */
    public const LEARNING = ['in_training', 'in_exam', 'passed', 'failed'];

    /** Status, die zu den Teilnehmern eines Termins zählen. */
    public const ACTIVE = ['admitted', 'in_training', 'in_exam'];

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    /** @return BelongsTo<Personnel, $this> */
    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function state(): ParticipationStatus
    {
        return ParticipationStatus::from($this->status);
    }

    public function canLearn(): bool
    {
        return in_array($this->status, self::LEARNING, true);
    }

    public function name(): string
    {
        return $this->personnel?->formalName() ?? 'Gelöschter Mitarbeiter';
    }
}
