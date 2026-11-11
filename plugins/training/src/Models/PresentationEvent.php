<?php

declare(strict_types=1);

namespace Plugin\Training\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ein Ereignis eines Vortrags (Zeichnung, Zeiger), das Zuschauer über
 * `seq` nachspielen. `data` hält das geprüfte JSON.
 *
 * @property int    $id
 * @property int    $presentation_id
 * @property int    $seq
 * @property string $kind
 * @property int|null $to_user_id  Empfänger; leer heißt für alle
 * @property string $data
 * @property Carbon $created_at
 * @property-read Presentation $presentation
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class PresentationEvent extends EloquentModel
{
    public $timestamps = false;

    protected $table = 'intra_training_presentation_events';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'id'              => 'integer',
        'presentation_id' => 'integer',
        'seq'             => 'integer',
        'to_user_id'      => 'integer',
        'created_at'      => 'datetime',
    ];

    /** @return BelongsTo<Presentation, $this> */
    public function presentation(): BelongsTo
    {
        return $this->belongsTo(Presentation::class, 'presentation_id');
    }
}
