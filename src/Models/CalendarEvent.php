<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent-Model fuer `intra_calendar_events` — Termine, role-getaggte
 * Dienste, Recurring-Series. Hat beide Timestamps, also direkt von
 * EloquentModel statt App\Models\Model.
 *
 * @property int            $id
 * @property string         $title
 * @property string|null    $description
 * @property string|null    $location
 * @property \DateTime      $starts_at
 * @property \DateTime      $ends_at
 * @property bool           $all_day
 * @property string         $color
 * @property string         $category
 * @property string         $visibility           'private'|'attendees'|'role'|'all'
 * @property string         $source               'manual'|'antrag'
 * @property int|null       $source_ref_id
 * @property int            $created_by
 * @property string|null    $recurrence_rule
 * @property \DateTime|null $recurrence_until
 * @property int|null       $parent_event_id
 * @property \DateTime      $created_at
 * @property \DateTime      $updated_at
 */
class CalendarEvent extends EloquentModel
{
    protected $table = 'intra_calendar_events';

    public $timestamps = true;

    protected $guarded = [];

    public const VISIBILITY_PRIVATE   = 'private';
    public const VISIBILITY_ATTENDEES = 'attendees';
    public const VISIBILITY_ROLE      = 'role';
    public const VISIBILITY_ALL       = 'all';

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_ANTRAG = 'antrag';

    /**
     * Kategorie-Tokens — UI-Farbgebung + Filter-Auswahl haengen daran.
     * 'absence' wird nur in Phase 2 vom AbsenceSyncService befuellt.
     */
    public const CATEGORIES = [
        'general'  => 'Allgemein',
        'meeting'  => 'Besprechung',
        'training' => 'Schulung',
        'dienst'   => 'Dienst',
        'absence'  => 'Abwesenheit',
    ];

    /**
     * Brand-Color-Tokens (passen zu --main-color, --success usw.).
     * Mapping zu Hex/CSS-Vars erfolgt im Frontend.
     */
    public const COLORS = [
        'orange', 'blue', 'green', 'red', 'purple', 'gray',
    ];

    protected $casts = [
        'id'               => 'integer',
        'source_ref_id'    => 'integer',
        'created_by'       => 'integer',
        'parent_event_id'  => 'integer',
        'all_day'          => 'boolean',
        'track_attendance' => 'boolean',
        'starts_at'        => 'datetime',
        'ends_at'          => 'datetime',
        'recurrence_until' => 'datetime',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    public function attendees(): HasMany
    {
        return $this->hasMany(CalendarAttendee::class, 'event_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Mehrere Rollen die ein Event sehen duerfen (visibility='role').
     * Pivot-Tabelle: intra_calendar_event_roles.
     */
    public function visibilityRoles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'intra_calendar_event_roles',
            'event_id',
            'role_id'
        );
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_event_id');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_event_id');
    }

    /**
     * Scope: Events, die im Bereich [from, to] liegen oder sich damit ueberschneiden.
     * Recurring-Series werden NICHT expandiert — das macht der RecurrenceExpander
     * spaeter. Hier reicht "starts_at <= to AND (ends_at >= from OR recurrence_until >= from)".
     */
    public function scopeInRange(Builder $query, DateTimeInterface $from, DateTimeInterface $to): Builder
    {
        $fromStr = $from->format('Y-m-d H:i:s');
        $toStr   = $to->format('Y-m-d H:i:s');

        return $query->where(function (Builder $q) use ($fromStr, $toStr) {
            $q->where(function (Builder $sq) use ($fromStr, $toStr) {
                // Single-Event-Overlap
                $sq->whereNull('recurrence_rule')
                    ->where('starts_at', '<=', $toStr)
                    ->where('ends_at',   '>=', $fromStr);
            })->orWhere(function (Builder $sq) use ($fromStr, $toStr) {
                // Recurring-Series, die im Bereich liegen koennten
                $sq->whereNotNull('recurrence_rule')
                    ->where('starts_at', '<=', $toStr)
                    ->where(function (Builder $until) use ($fromStr) {
                        $until->whereNull('recurrence_until')
                            ->orWhere('recurrence_until', '>=', $fromStr);
                    });
            });
        });
    }

    /**
     * Scope: nur Events, die der gegebene User sehen darf. Spiegelt die
     * Logik in CalendarPolicy::view(). Role-Membership-Check braucht
     * eine Subquery auf die Pivot-Tabelle, weil ein Event mehrere Rollen
     * tragen kann.
     */
    public function scopeVisibleTo(Builder $query, int $userId, ?int $roleId, ?int $mitarbeiterId): Builder
    {
        return $query->where(function (Builder $q) use ($userId, $roleId, $mitarbeiterId) {
            $q->where('created_by', $userId)
                ->orWhere('visibility', self::VISIBILITY_ALL);

            if ($roleId !== null) {
                $q->orWhere(function (Builder $sq) use ($roleId) {
                    $sq->where('visibility', self::VISIBILITY_ROLE)
                        ->whereIn('id', function ($sub) use ($roleId) {
                            $sub->select('event_id')
                                ->from('intra_calendar_event_roles')
                                ->where('role_id', $roleId);
                        });
                });
            }

            if ($mitarbeiterId !== null) {
                $q->orWhereIn('id', function ($sub) use ($mitarbeiterId) {
                    $sub->select('event_id')
                        ->from('intra_calendar_attendees')
                        ->where('mitarbeiter_id', $mitarbeiterId);
                });
            }
        });
    }
}
