<?php

declare(strict_types=1);

namespace Plugin\Training\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Frage eines Teilnehmers während des Vortrags. Sehen und beantworten dürfen
 * sie Leitung und Ausbilder; der Fragende sieht nur die eigenen. Das Ende des
 * Vortrags löscht sie.
 *
 * @property int         $id
 * @property int         $presentation_id
 * @property int         $user_id
 * @property string      $body
 * @property Carbon      $asked_at
 * @property string|null $answer
 * @property int|null    $answered_by
 * @property Carbon|null $answered_at
 * @property Carbon|null $done_at
 * @property-read User|null $user
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class PresentationQuestion extends EloquentModel
{
    public $timestamps = false;

    protected $table = 'intra_training_presentation_questions';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'id'              => 'integer',
        'presentation_id' => 'integer',
        'user_id'         => 'integer',
        'asked_at'        => 'datetime',
        'answered_by'     => 'integer',
        'answered_at'     => 'datetime',
        'done_at'         => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
