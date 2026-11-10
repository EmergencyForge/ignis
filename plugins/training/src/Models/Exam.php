<?php

declare(strict_types=1);

namespace Plugin\Training\Models;

use EmergencyForge\Training\Exam\ExamBlueprint;
use EmergencyForge\Training\Exam\ExamKind;
use EmergencyForge\Training\Exam\ExamSettings;
use EmergencyForge\Training\Exam\PracticalSheet;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * Prüfungsteil einer Lehrgangsart: Theorie mit Zusammenstellung oder
 * Praxis mit Bewertungsbogen.
 *
 * `settings` hält neben den Werten von ExamSettings noch `count_focus`
 * (Theorie), `cases` und `case_mode` (Praxis: Fallbeispiele, choose|draw).
 *
 * @property int                             $id
 * @property int                             $course_type_id
 * @property int                             $position
 * @property string                          $kind
 * @property string                          $title
 * @property bool                            $required
 * @property array<string, mixed>|null       $blueprint
 * @property list<array<string, mixed>>|null $sheet
 * @property array<string, mixed>|null       $settings
 * @property-read CourseType $type
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class Exam extends EloquentModel
{
    protected $table = 'intra_training_exams';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'id'             => 'integer',
        'course_type_id' => 'integer',
        'position'       => 'integer',
        'required'       => 'boolean',
        'blueprint'      => 'array',
        'sheet'          => 'array',
        'settings'       => 'array',
    ];

    /** @return BelongsTo<CourseType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(CourseType::class, 'course_type_id');
    }

    public function examKind(): ExamKind
    {
        return ExamKind::tryFrom($this->kind) ?? ExamKind::Theory;
    }

    public function isTheory(): bool
    {
        return $this->examKind() === ExamKind::Theory;
    }

    public function examSettings(): ExamSettings
    {
        try {
            return ExamSettings::fromArray($this->settings ?? []);
        } catch (InvalidArgumentException) {
            return new ExamSettings();
        }
    }

    /** null, solange die Zusammenstellung leer ist. */
    public function examBlueprint(): ?ExamBlueprint
    {
        try {
            return ExamBlueprint::fromArray($this->blueprint ?? []);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /** null, solange der Bogen keine Kriterien hat. */
    public function practicalSheet(): ?PracticalSheet
    {
        try {
            return PracticalSheet::fromArray($this->sheet ?? []);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return ($this->settings ?? [])[$key] ?? $default;
    }

    /** @return list<string> */
    public function cases(): array
    {
        $cases = $this->setting('cases', []);

        return is_array($cases) ? array_values(array_filter(array_map('strval', $cases), static fn (string $c): bool => trim($c) !== '')) : [];
    }
}
