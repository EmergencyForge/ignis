<?php

declare(strict_types=1);

namespace Plugin\Training\Models;

use EmergencyForge\Training\EnrollmentMode;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Lehrgangsart: das Muster mit Lehrplan, Anmeldeweg und Ziel-Qualifikation.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $short
 * @property string|null $description
 * @property string      $area                 rd|fw|fd|other
 * @property string|null $start_qualification  z. B. rd:5
 * @property string|null $pass_qualification
 * @property int|null    $validity_months
 * @property int|null    $certificate_template_id
 * @property string      $signer_mode          lead|fixed|grader
 * @property int|null    $signer_user_id
 * @property array<string, mixed>|null $completion_plan     Schritt => an/aus
 * @property array<string, mixed>|null $completion_settings Bekanntgabe und Folgeaufgabe
 * @property string      $enrollment_mode
 * @property bool        $sequential
 * @property bool        $is_active
 * @property int|null    $created_by
 * @property Carbon      $created_at
 * @property Carbon      $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Module>       $modules
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Prerequisite> $prerequisites
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class CourseType extends EloquentModel
{
    protected $table = 'intra_training_course_types';

    protected $guarded = [];

    public const AREAS = [
        'rd'    => 'Rettungsdienst',
        'fw'    => 'Feuerwehr',
        'fd'    => 'Fachdienst',
        'other' => 'Sonstiges',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'id'              => 'integer',
        'validity_months' => 'integer',
        'sequential'      => 'boolean',
        'is_active'       => 'boolean',
        'created_by'      => 'integer',
        'completion_plan' => 'array',
        'completion_settings'     => 'array',
        'certificate_template_id' => 'integer',
        'signer_user_id'          => 'integer',
    ];

    /** @return HasMany<Module, $this> */
    public function modules(): HasMany
    {
        $relation = $this->hasMany(Module::class, 'course_type_id');
        $relation->getQuery()->orderBy('position')->orderBy('id');

        return $relation;
    }

    /** @return HasMany<Prerequisite, $this> */
    public function prerequisites(): HasMany
    {
        return $this->hasMany(Prerequisite::class, 'course_type_id');
    }

    /** @return HasMany<Course, $this> */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'course_type_id');
    }

    public function enrollment(): EnrollmentMode
    {
        return EnrollmentMode::tryFrom($this->enrollment_mode) ?? EnrollmentMode::Open;
    }

    /** @return list<string> */
    public function prerequisiteKeys(): array
    {
        return array_values(array_map('strval', $this->prerequisites->pluck('qualification')->all()));
    }
}
