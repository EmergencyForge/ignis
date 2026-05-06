<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent-Model fuer `intra_calendar_attendees` — explizit eingeladene
 * Mitarbeiter pro Event. Bei visibility='role' werden Attendees nicht
 * persistiert — siehe AttendeeResolver.
 *
 * @property int            $id
 * @property int            $event_id
 * @property int            $mitarbeiter_id
 * @property string         $response       'pending'|'accepted'|'declined'|'tentative'
 * @property \DateTime|null $responded_at
 * @property bool           $is_organizer
 * @property \DateTime      $created_at
 */
class CalendarAttendee extends EloquentModel
{
    protected $table = 'intra_calendar_attendees';

    /** Diese Tabelle hat nur created_at, kein updated_at. */
    public $timestamps = false;

    protected $guarded = [];

    public const RESPONSE_PENDING   = 'pending';
    public const RESPONSE_ACCEPTED  = 'accepted';
    public const RESPONSE_DECLINED  = 'declined';
    public const RESPONSE_TENTATIVE = 'tentative';

    protected $casts = [
        'id'             => 'integer',
        'event_id'       => 'integer',
        'mitarbeiter_id' => 'integer',
        'is_organizer'   => 'boolean',
        'responded_at'   => 'datetime',
        'created_at'     => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'event_id');
    }

    public function mitarbeiter(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'mitarbeiter_id');
    }
}
