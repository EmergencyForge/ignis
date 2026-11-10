<?php

declare(strict_types=1);

namespace Plugin\Training;

use DateTimeImmutable;
use EmergencyForge\Training\CourseStatus;
use EmergencyForge\Training\ParticipationStatus;
use EmergencyForge\Training\Progress\Admission;
use Plugin\Training\Models\Attempt;
use Plugin\Training\Models\Course;
use Plugin\Training\Models\Exam;
use Plugin\Training\Models\ExamRelease;
use Plugin\Training\Models\ExtraAttempt;
use Plugin\Training\Models\Participation;

/**
 * Prüfungsteile eines Lehrgangs und wie jede Teilnahme dazu steht:
 * Zulassung, Freigabe, Versuche. Lädt alles für die übergebenen
 * Teilnahmen auf einmal, damit die Zulassungsliste nicht je Zelle fragt.
 */
final class ExamBoard
{
    /** Teilnahmen, die einen Versuch beginnen können. Failed nur mit Zusatzversuch. */
    private const ELIGIBLE = ['in_training', 'in_exam', 'failed'];

    /** @var array<int, array<int, list<Attempt>>> participation => exam => Versuche */
    private array $attempts = [];

    /** @var array<int, array<int, int>> participation => exam => Zusatzversuche */
    private array $extras = [];

    /** @var array<int, true> exam_id => für alle freigegeben */
    private array $releasedAll = [];

    /** @var array<int, array<int, true>> exam => participation => einzeln freigegeben */
    private array $releasedFor = [];

    /** @var array<int, array{states: array<int, array{state: string}>, absences: int}> */
    private array $learning = [];

    private ?Curriculum $curriculum = null;

    /**
     * @param list<Exam>          $parts
     * @param list<Participation> $participations
     */
    private function __construct(public readonly Course $course, public readonly array $parts, array $participations)
    {
        $ids = array_map(static fn (Participation $p): int => $p->id, $participations);
        foreach (Attempt::query()->whereIn('participation_id', $ids ?: [0])->orderBy('number')->get() as $attempt) {
            $this->attempts[$attempt->participation_id][$attempt->exam_id][] = $attempt;
        }
        foreach (ExtraAttempt::query()->whereIn('participation_id', $ids ?: [0])->get() as $extra) {
            $this->extras[$extra->participation_id][$extra->exam_id] = ($this->extras[$extra->participation_id][$extra->exam_id] ?? 0) + 1;
        }
        foreach (ExamRelease::query()->where('course_id', $course->id)->get() as $release) {
            if ($release->participation_id === null) {
                $this->releasedAll[$release->exam_id] = true;
            } else {
                $this->releasedFor[$release->exam_id][$release->participation_id] = true;
            }
        }
    }

    /** @param list<Participation> $participations */
    public static function for(Course $course, array $participations): self
    {
        return new self($course, self::parts($course->course_type_id), $participations);
    }

    /** @return list<Exam> in Prüfungsreihenfolge */
    public static function parts(int $courseTypeId): array
    {
        /** @var list<Exam> $parts */
        $parts = Exam::query()->where('course_type_id', $courseTypeId)->orderBy('position')->orderBy('id')->get()->all();

        return $parts;
    }

    public function part(int $examId): ?Exam
    {
        foreach ($this->parts as $part) {
            if ($part->id === $examId) {
                return $part;
            }
        }

        return null;
    }

    public function releasedForAll(Exam $exam): bool
    {
        return isset($this->releasedAll[$exam->id]) || !$exam->examSettings()->requiresRelease;
    }

    public function state(Participation $participation, Exam $exam, ?DateTimeImmutable $now = null): ExamState
    {
        $now        ??= new DateTimeImmutable();
        $attempts     = $this->attempts[$participation->id][$exam->id] ?? [];
        $extras       = $this->extras[$participation->id][$exam->id] ?? 0;
        $individually = isset($this->releasedFor[$exam->id][$participation->id]);
        $rules        = $exam->examSettings()->attempts;

        $submitted = array_values(array_filter(array_map(static fn (Attempt $a): ?DateTimeImmutable => $a->submitted_at?->toDateTimeImmutable(), $attempts)));
        $check     = $rules->canStart(count($attempts), $submitted === [] ? null : max($submitted), $now, $extras);

        return new ExamState(
            $exam,
            $this->admission($participation, $exam, $individually),
            $individually || $this->releasedForAll($exam),
            $individually,
            $attempts,
            $rules->remaining(count($attempts), $extras),
            $check,
        );
    }

    /** Alle Pflicht-Teile bestanden? */
    public function allRequiredPassed(Participation $participation): bool
    {
        $required = array_filter($this->parts, static fn (Exam $e): bool => $e->required);
        foreach ($required as $part) {
            if (!$this->state($participation, $part)->passed()) {
                return false;
            }
        }

        return $required !== [];
    }

    private function admission(Participation $participation, Exam $exam, bool $byLead): Admission
    {
        if (!in_array($participation->status, self::ELIGIBLE, true)) {
            return Admission::no(match ($participation->state()) {
                ParticipationStatus::Passed => 'Der Lehrgang ist bestanden.',
                ParticipationStatus::Admitted, ParticipationStatus::Enrolled, ParticipationStatus::Waitlisted => 'Der Lehrgang hat noch nicht begonnen.',
                default => 'Die Teilnahme ist beendet.',
            });
        }

        $previousPassed = true;
        foreach ($this->parts as $part) {
            if ($part->id === $exam->id) {
                break;
            }
            if ($part->required && !$this->state($participation, $part)->passed()) {
                $previousPassed = false;
            }
        }

        $learning = $this->learning($participation);
        $progress = $this->curriculum()->progress($learning['states']);

        return $progress->admission($this->course->state() === CourseStatus::ExamPhase, $previousPassed, $learning['absences'], 0, $byLead);
    }

    /** @return array{states: array<int, array{state: string}>, absences: int} */
    private function learning(Participation $participation): array
    {
        if (!isset($this->learning[$participation->id])) {
            $this->learning[$participation->id] = [
                'states'   => $this->curriculum()->states([$participation->id])[$participation->id] ?? [],
                'absences' => $this->curriculum()->unexcusedAbsences($participation->id),
            ];
        }

        return $this->learning[$participation->id];
    }

    private function curriculum(): Curriculum
    {
        return $this->curriculum ??= Curriculum::for($this->course);
    }
}
