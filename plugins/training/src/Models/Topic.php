<?php

declare(strict_types=1);

namespace Plugin\Training\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * Thema im Fragenpool einer Lehrgangsart.
 *
 * @property int    $id
 * @property int    $course_type_id
 * @property int    $position
 * @property string $name
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class Topic extends EloquentModel
{
    protected $table = 'intra_training_topics';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = ['id' => 'integer', 'course_type_id' => 'integer', 'position' => 'integer'];
}
