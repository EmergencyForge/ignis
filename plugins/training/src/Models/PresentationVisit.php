<?php

declare(strict_types=1);

namespace Plugin\Training\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ein Besuch im Vortrag: vom ersten Lebenszeichen bis zur Lücke oder zum Ende.
 * Offen, solange `left_at` leer ist. Bleibt nach dem Vortrag bei seinem Termin.
 *
 * @property int         $id
 * @property int         $presentation_id
 * @property int         $user_id
 * @property Carbon      $joined_at
 * @property Carbon      $last_seen_at
 * @property Carbon|null $left_at
 * @property-read User|null $user
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class PresentationVisit extends EloquentModel
{
    public $timestamps = false;

    protected $table = 'intra_training_presentation_visits';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'id'              => 'integer',
        'presentation_id' => 'integer',
        'user_id'         => 'integer',
        'joined_at'       => 'datetime',
        'last_seen_at'    => 'datetime',
        'left_at'         => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
