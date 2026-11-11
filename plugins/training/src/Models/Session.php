<?php

declare(strict_types=1);

namespace Plugin\Training\Models;

use EmergencyForge\Training\UnitKind;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Präsenztermin eines Lehrgangs, eingeplant für eine Lerneinheit „Termin“ oder „Präsentation“.
 *
 * @property int         $id
 * @property int         $course_id
 * @property int|null    $module_id
 * @property int|null    $unit_id
 * @property string      $title
 * @property Carbon      $starts_at
 * @property Carbon      $ends_at
 * @property string|null $location
 * @property bool        $required
 * @property int|null    $calendar_event_id
 * @property-read Course $course
 * @property-read Unit|null $unit
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

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    /** Ob der Termin an einer Präsentation hängt, zu der sich ein Vortrag starten lässt. */
    public function isPresentation(): bool
    {
        return $this->unit?->type() === UnitKind::Presentation;
    }

    /** Die Folien der Präsentation; null ohne Präsentation oder ohne Datei. */
    public function presentationFile(): ?TrainingFile
    {
        return $this->isPresentation() ? $this->unit?->file : null;
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
