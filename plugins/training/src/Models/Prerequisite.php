<?php

declare(strict_types=1);

namespace Plugin\Training\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * @property int    $id
 * @property int    $course_type_id
 * @property string $qualification
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class Prerequisite extends EloquentModel
{
    protected $table = 'intra_training_prerequisites';

    public $timestamps = false;

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = ['id' => 'integer', 'course_type_id' => 'integer'];
}
