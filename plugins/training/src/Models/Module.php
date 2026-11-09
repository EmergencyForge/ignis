<?php

declare(strict_types=1);

namespace Plugin\Training\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Abschnitt eines Lehrplans. Gehört einer Lehrgangsart oder, als Zusatz,
 * einem einzelnen Lehrgang.
 *
 * @property int      $id
 * @property int|null $course_type_id
 * @property int|null $course_id
 * @property int      $position
 * @property string   $title
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Unit> $units
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class Module extends EloquentModel
{
    protected $table = 'intra_training_modules';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'id'             => 'integer',
        'course_type_id' => 'integer',
        'course_id'      => 'integer',
        'position'       => 'integer',
    ];

    /** @return HasMany<Unit, $this> */
    public function units(): HasMany
    {
        $relation = $this->hasMany(Unit::class, 'module_id');
        $relation->getQuery()->orderBy('position')->orderBy('id');

        return $relation;
    }
}
