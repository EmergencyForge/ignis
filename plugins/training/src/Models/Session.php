<?php

declare(strict_types=1);

namespace Plugin\Training\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Präsenztermin eines Lehrgangs, eingeplant für eine Lerneinheit „Termin“.
 *
 * @property int         $id
 * @property int         $course_id
 * @property int|null    $module_id
 * @property int|null    $unit_id
 * @property int|null    $slides_file_id
 * @property string      $title
 * @property Carbon      $starts_at
 * @property Carbon      $ends_at
 * @property string|null $location
 * @property bool        $required
 * @property int|null    $calendar_event_id
 * @property-read Course $course
 * @property-read TrainingFile|null $slides
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Attendance> $attendance
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class Session extends EloquentModel
{
    protected $table = 'intra_training_sessions';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'id'                => 'integer',
        'course_id'         => 'integer',
        'module_id'         => 'integer',
        'unit_id'           => 'integer',
        'slides_file_id'    => 'integer',
        'required'          => 'boolean',
        'calendar_event_id' => 'integer',
        'starts_at'         => 'datetime',
        'ends_at'           => 'datetime',
    ];

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    /** @return HasMany<Attendance, $this> */
    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class, 'session_id');
    }

    /** @return BelongsTo<TrainingFile, $this> */
    public function slides(): BelongsTo
    {
        return $this->belongsTo(TrainingFile::class, 'slides_file_id');
    }

    /** @return HasMany<Presentation, $this> */
    public function presentations(): HasMany
    {
        return $this->hasMany(Presentation::class, 'session_id');
    }

    /** „Mo 17.11., 18:00“ */
    public function when(): string
    {
        $days = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];

        return $days[(int) $this->starts_at->format('w')] . ' ' . $this->starts_at->format('d.m., H:i');
    }
}
