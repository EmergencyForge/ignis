<?php

declare(strict_types=1);

namespace Plugin\Training\Models;

use App\Models\User;
use EmergencyForge\Training\CourseStatus;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Lehrgang: ein Durchgang einer Lehrgangsart.
 *
 * @property int         $id
 * @property int         $course_type_id
 * @property string      $title
 * @property int         $number
 * @property int         $year
 * @property string      $status
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property Carbon|null $enroll_until
 * @property int|null    $seats
 * @property int|null    $lead_user_id
 * @property int|null    $created_by
 * @property Carbon      $created_at
 * @property Carbon      $updated_at
 * @property-read CourseType $type
 * @property-read User|null  $lead
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CourseStaff>   $staff
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Participation> $participations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Session>       $sessions
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class Course extends EloquentModel
{
    protected $table = 'intra_training_courses';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'id'             => 'integer',
        'course_type_id' => 'integer',
        'number'         => 'integer',
        'year'           => 'integer',
        'seats'          => 'integer',
        'lead_user_id'   => 'integer',
        'created_by'     => 'integer',
        'starts_on'      => 'date',
        'ends_on'        => 'date',
        'enroll_until'   => 'datetime',
    ];

    /** @return BelongsTo<CourseType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(CourseType::class, 'course_type_id');
    }

    /** @return BelongsTo<User, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_user_id');
    }

    /** @return HasMany<CourseStaff, $this> */
    public function staff(): HasMany
    {
        return $this->hasMany(CourseStaff::class, 'course_id');
    }

    /** @return HasMany<Participation, $this> */
    public function participations(): HasMany
    {
        return $this->hasMany(Participation::class, 'course_id');
    }

    /** @return HasMany<Module, $this> */
    public function modules(): HasMany
    {
        $relation = $this->hasMany(Module::class, 'course_id');
        $relation->getQuery()->orderBy('position')->orderBy('id');

        return $relation;
    }

    /** @return HasMany<Session, $this> */
    public function sessions(): HasMany
    {
        $relation = $this->hasMany(Session::class, 'course_id');
        $relation->getQuery()->orderBy('starts_at');

        return $relation;
    }

    public function state(): CourseStatus
    {
        return CourseStatus::tryFrom($this->status) ?? CourseStatus::Planned;
    }

    /** „10.11. bis 28.11.2026“, leer ohne Zeitraum. */
    public function period(): string
    {
        if ($this->starts_on === null) {
            return '';
        }
        if ($this->ends_on === null) {
            return 'ab ' . $this->starts_on->format('d.m.Y');
        }

        return $this->starts_on->format($this->starts_on->year === $this->ends_on->year ? 'd.m.' : 'd.m.Y') . ' bis ' . $this->ends_on->format('d.m.Y');
    }

    public function enrollmentClosed(?Carbon $now = null): bool
    {
        return $this->enroll_until !== null && $this->enroll_until->lessThan($now ?? Carbon::now());
    }
}
