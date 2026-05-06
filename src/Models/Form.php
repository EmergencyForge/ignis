<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent-Model für `intra_antraege` — eingereichte Anträge.
 *
 * Status-Workflow:
 *   0 = In Bearbeitung
 *   1 = Abgelehnt
 *   2 = Aufgeschoben
 *   3 = Angenommen
 *
 * @property int         $id
 * @property string      $uniqueid     6-stellige Public-ID, im URL benutzt
 * @property int         $antragstyp_id
 * @property string      $name_dn      "Vorname Nachname (Dienstnummer)"
 * @property string|null $dienstgrad
 * @property string|null $discordid    Discord-Tag des Einreichers
 * @property int         $cirs_status
 * @property string|null $cirs_manager
 * @property string|null $cirs_text    Bemerkung des Bearbeiters
 * @property \DateTime   $time_added
 * @property \DateTime|null $cirs_time
 * @property-read FormType                                                $typ
 * @property-read \Illuminate\Database\Eloquent\Collection<int, FormData> $daten
 */
class Form extends Model
{
    protected $table = 'intra_antraege';

    public const STATUS_IN_PROGRESS = 0;
    public const STATUS_REJECTED    = 1;
    public const STATUS_DEFERRED    = 2;
    public const STATUS_ACCEPTED    = 3;

    public const STATUS_LABELS = [
        self::STATUS_IN_PROGRESS => 'In Bearbeitung',
        self::STATUS_REJECTED    => 'Abgelehnt',
        self::STATUS_DEFERRED    => 'Aufgeschoben',
        self::STATUS_ACCEPTED    => 'Angenommen',
    ];

    protected $casts = [
        'id'            => 'integer',
        'antragstyp_id' => 'integer',
        'cirs_status'   => 'integer',
        'time_added'    => 'datetime',
        'cirs_time'     => 'datetime',
    ];

    public function typ(): BelongsTo
    {
        return $this->belongsTo(FormType::class, 'antragstyp_id', 'id');
    }

    /**
     * Form-Daten dieses Antrags (eine Row pro Feld).
     */
    public function daten(): HasMany
    {
        return $this->hasMany(FormData::class, 'antrag_id', 'id');
    }

    /**
     * Lookup eines einzelnen Feldwerts aus den daten — bequemer Zugriff für
     * View-Templates.
     */
    public function getFieldValue(string $feldname): ?string
    {
        $row = $this->daten->firstWhere('feldname', $feldname);
        return $row?->wert;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->cirs_status] ?? 'Unbekannt';
    }

    public function isOpen(): bool
    {
        return $this->cirs_status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Eloquent-Model-Boot: registriert einen deleting-Hook, der die
     * Calendar-Bridge automatisch aufraeumt. Damit verschwindet ein
     * gespiegeltes Absence-Event im Kalender, sobald der Antrag selbst
     * geloescht wird — egal ob via Controller, Console oder Test-Code.
     */
    protected static function booted(): void
    {
        static::deleting(static function (Form $antrag): void {
            try {
                \App\Calendar\AbsenceSyncService::removeForAntrag((int) $antrag->id);
            } catch (\Throwable $e) {
                // Calendar-Bridge ist nicht business-kritisch — Antrag-Delete
                // soll auch dann durchgehen, wenn die Bridge wackelt.
                \App\Logging\Logger::warning(
                    'AbsenceSync: removeForAntrag fehlgeschlagen',
                    ['antrag_id' => $antrag->id, 'error' => $e->getMessage()]
                );
            }
        });
    }
}
