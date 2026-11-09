<?php

declare(strict_types=1);

namespace Plugin\Training\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ausbilder (instructor) oder Prüfer (examiner) in einem Lehrgang.
 *
 * @property int    $id
 * @property int    $course_id
 * @property int    $user_id
 * @property string $role
 * @property-read User|null $user
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class CourseStaff extends EloquentModel
{
    protected $table = 'intra_training_course_staff';

    public $timestamps = false;

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = ['id' => 'integer', 'course_id' => 'integer', 'user_id' => 'integer'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
