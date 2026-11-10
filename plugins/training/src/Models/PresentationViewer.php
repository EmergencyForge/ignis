<?php

declare(strict_types=1);

namespace Plugin\Training\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Zuschauer eines Vortrags mit der Zeit, die er zugesehen hat.
 *
 * @property int      $id
 * @property int      $presentation_id
 * @property int      $user_id
 * @property int|null $participation_id
 * @property Carbon   $first_seen_at
 * @property Carbon   $last_seen_at
 * @property int      $seconds_watched
 * @property-read User|null          $user
 * @property-read Participation|null $participation
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class PresentationViewer extends EloquentModel
{
    public $timestamps = false;

    protected $table = 'intra_training_presentation_viewers';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'id'               => 'integer',
        'presentation_id'  => 'integer',
        'user_id'          => 'integer',
        'participation_id' => 'integer',
        'first_seen_at'    => 'datetime',
        'last_seen_at'     => 'datetime',
        'seconds_watched'  => 'integer',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Participation, $this> */
    public function participation(): BelongsTo
    {
        return $this->belongsTo(Participation::class, 'participation_id');
    }
}
