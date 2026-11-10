<?php

declare(strict_types=1);

namespace Plugin\Training\Models;

use EmergencyForge\Training\Exam\DrawnExam;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Ein Durchgang eines Prüfungsteils durch eine Teilnahme.
 *
 * Theorie: `drawn` ist DrawnExam::toArray(). Praxis: `drawn` hält den Bogen
 * vom Start (`sheet`), das Fallbeispiel (`case`), die Bewertung je
 * Kriterium (`ratings`), Kommentare je Gruppe (`comments`) und am Ende
 * (`comment`).
 *
 * Status: open (läuft), review (wartet auf Bewertung von Hand), cosign
 * (wartet auf Gegenzeichnung), done.
 *
 * @property int                       $id
 * @property int                       $participation_id
 * @property int                       $exam_id
 * @property int                       $number
 * @property int                       $seed
 * @property array<string, mixed>|null $drawn
 * @property Carbon                    $started_at
 * @property Carbon|null               $deadline_at
 * @property Carbon|null               $submitted_at
 * @property string                    $status
 * @property float|null                $points
 * @property float|null                $max_points
 * @property float|null                $percent
 * @property bool|null                 $passed
 * @property int                       $focus_lost
 * @property int|null                  $graded_by
 * @property int|null                  $cosigned_by
 * @property-read Participation $participation
 * @property-read Exam          $exam
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AttemptAnswer> $answers
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class Attempt extends EloquentModel
{
    public const OPEN   = 'open';
    public const REVIEW = 'review';
    public const COSIGN = 'cosign';
    public const DONE   = 'done';

    protected $table = 'intra_training_attempts';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'id'               => 'integer',
        'participation_id' => 'integer',
        'exam_id'          => 'integer',
        'number'           => 'integer',
        'seed'             => 'integer',
        'drawn'            => 'array',
        'started_at'       => 'datetime',
        'deadline_at'      => 'datetime',
        'submitted_at'     => 'datetime',
        'points'           => 'float',
        'max_points'       => 'float',
        'percent'          => 'float',
        'passed'           => 'boolean',
        'focus_lost'       => 'integer',
        'graded_by'        => 'integer',
        'cosigned_by'      => 'integer',
    ];

    /** @return BelongsTo<Participation, $this> */
    public function participation(): BelongsTo
    {
        return $this->belongsTo(Participation::class, 'participation_id');
    }

    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id');
    }

    /** @return HasMany<AttemptAnswer, $this> */
    public function answers(): HasMany
    {
        $relation = $this->hasMany(AttemptAnswer::class, 'attempt_id');
        $relation->getQuery()->orderBy('position');

        return $relation;
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    public function drawnExam(): DrawnExam
    {
        return DrawnExam::fromArray($this->drawn ?? []);
    }

    /** „Bestanden, 88 %“, „Wird bewertet“ */
    public function label(): string
    {
        return match ($this->status) {
            self::OPEN   => 'Läuft',
            self::REVIEW => 'Wird bewertet',
            self::COSIGN => 'Wartet auf Gegenzeichnung',
            default      => ($this->passed ? 'Bestanden' : 'Nicht bestanden') . ', ' . self::percentLabel((float) $this->percent),
        };
    }

    public static function percentLabel(float $percent): string
    {
        return str_replace('.', ',', (string) round($percent, 1)) . ' %';
    }
}
