<?php

declare(strict_types=1);

namespace Plugin\Training\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Live-Vortrag an einem Termin. Läuft, solange `ended_at` leer ist. `seq`
 * ist die zuletzt vergebene Ereignisnummer, `version` zählt Änderungen an
 * Seite und Modus.
 *
 * @property int         $id
 * @property int         $session_id
 * @property int         $presenter_id
 * @property Carbon      $started_at
 * @property Carbon|null $ended_at
 * @property int         $page  ab 0
 * @property string      $mode  slides|pause|screen
 * @property string|null  $screen_via  livekit|direct, nur im Modus screen
 * @property string|null  $screen_room  LiveKit-Raum, nur bei livekit
 * @property int         $version
 * @property int         $seq
 * @property Carbon      $presenter_seen_at
 * @property int|null    $handover_to
 * @property-read Session $session
 * @property-read User    $presenter
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PresentationEvent>  $events
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PresentationViewer> $viewers
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class Presentation extends EloquentModel
{
    public $timestamps = false;

    protected $table = 'intra_training_presentations';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'id'                => 'integer',
        'session_id'        => 'integer',
        'presenter_id'      => 'integer',
        'started_at'        => 'datetime',
        'ended_at'          => 'datetime',
        'page'              => 'integer',
        'mode'              => 'string',
        'screen_via'        => 'string',
        'screen_room'       => 'string',
        'version'           => 'integer',
        'seq'               => 'integer',
        'presenter_seen_at' => 'datetime',
        'handover_to'       => 'integer',
    ];

    /** @return BelongsTo<Session, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    /** @return BelongsTo<User, $this> */
    public function presenter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'presenter_id');
    }

    /** @return HasMany<PresentationEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(PresentationEvent::class, 'presentation_id');
    }

    /** @return HasMany<PresentationViewer, $this> */
    public function viewers(): HasMany
    {
        return $this->hasMany(PresentationViewer::class, 'presentation_id');
    }

    public function isLive(): bool
    {
        return $this->ended_at === null;
    }

    /** Dauer des Vortrags in Sekunden, bis zum Ende oder bis jetzt. */
    public function talkSeconds(): int
    {
        return max(0, (int) $this->started_at->diffInSeconds($this->ended_at ?? Carbon::now()));
    }
}
