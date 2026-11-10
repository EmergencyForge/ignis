<?php

declare(strict_types=1);

namespace Plugin\Training;

use App\Events\EventDispatcher;
use App\Events\PersonnelSaved;
use App\Personnel\PersonnelQualifications;
use DomainException;
use EmergencyForge\Training\CourseStatus;
use EmergencyForge\Training\ParticipationStatus;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Carbon;
use Plugin\Training\Models\Course;
use Plugin\Training\Models\Participation;

/**
 * Statuswechsel eines Lehrgangs und was daran hängt.
 *
 * Mit „Läuft“ werden Zugelassene zu Teilnehmern in Ausbildung und bekommen
 * die Start-Qualifikation. Die vorherige Stufe merkt sich die Teilnahme,
 * damit eine Absage sie zurücksetzen kann, aber nur wenn der Lehrgang sie
 * wirklich angehoben hat.
 *
 * „Abgeschlossen“ beendet offene Anmeldungen, und wer bis dahin nicht
 * bestanden hat, hat nicht bestanden. Für die startet die Abschluss-Kette
 * mit Ergebnis, Verlauf und Benachrichtigung. Ohne Pflichtprüfung gibt es
 * nichts zu bestehen, dort gilt der Abschluss durch die Leitung als bestanden.
 */
final class Lifecycle
{
    public function __construct(
        private readonly PersonnelQualifications $qualifications = new PersonnelQualifications(),
        private readonly Completions $completions = new Completions(),
    ) {
    }

    public function transition(Course $course, CourseStatus $to, string $editor, bool $resetQualification = false): void
    {
        $from = $course->state();
        if (!$from->canTransitionTo($to)) {
            throw new DomainException(sprintf('Von „%s“ geht es nicht nach „%s“.', $from->label(), $to->label()));
        }

        $started = [];
        $finished = [];
        Capsule::connection()->transaction(function () use ($course, $from, $to, $editor, $resetQualification, &$started, &$finished): void {
            $course->status = $to->value;
            $course->save();

            if ($to->materialsOpen() && !$from->materialsOpen() && $to !== CourseStatus::Completed) {
                foreach ($this->participations($course, [ParticipationStatus::Admitted]) as $participation) {
                    $this->startTraining($participation, $editor);
                    if ($participation->personnel_id !== null) {
                        $started[] = $participation->personnel_id;
                    }
                }
            }

            $open = [ParticipationStatus::Waitlisted, ParticipationStatus::Enrolled, ParticipationStatus::Admitted];
            if ($to === CourseStatus::Completed) {
                $this->abortAll($this->participations($course, $open));
                $passed = !$this->hasRequiredExam($course);
                foreach ($this->participations($course, [ParticipationStatus::InTraining, ParticipationStatus::InExam]) as $participation) {
                    $participation->status      = ($passed ? ParticipationStatus::Passed : ParticipationStatus::Failed)->value;
                    $participation->finished_at = Carbon::now();
                    $participation->save();
                    $this->completions->start($participation, $passed);
                    $finished[] = $participation->id;
                }
            }
            if ($to === CourseStatus::Cancelled) {
                $running = $this->participations($course, [ParticipationStatus::InTraining, ParticipationStatus::InExam]);
                if ($resetQualification) {
                    foreach ($running as $participation) {
                        $this->resetQualification($participation, $editor);
                    }
                }
                $this->abortAll([...$this->participations($course, $open), ...$running]);
            }
        });

        Notify::personnel($started, $course->title . ' hat begonnen', 'training/courses/' . $course->id, 'Die Unterlagen sind jetzt für dich offen.');
        foreach ($finished as $participationId) {
            $this->completions->run($participationId);
        }
        SessionSync::course($course);
    }

    private function hasRequiredExam(Course $course): bool
    {
        return array_filter(ExamBoard::parts($course->course_type_id), static fn ($exam): bool => (bool) $exam->required) !== [];
    }

    /** Zugelassen wird „In Ausbildung“, mit Start-Qualifikation. */
    public function startTraining(Participation $participation, string $editor): void
    {
        if ($participation->state() !== ParticipationStatus::Admitted) {
            return;
        }
        $course = $participation->course;

        $participation->status = ParticipationStatus::InTraining->value;

        $grant = Qualifications::grantable($course->type->start_qualification);
        if ($grant !== null && $participation->personnel_id !== null) {
            [$area, $value] = $grant;
            $column = match ($area) {
                PersonnelQualifications::AREA_RD => 'qualird',
                PersonnelQualifications::AREA_FW => 'qualifw2',
                default                          => null,
            };
            $before = $column !== null ? Capsule::table('intra_mitarbeiter')->where('id', $participation->personnel_id)->value($column) : null;
            $raised = $this->qualifications->grant($participation->personnel_id, $area, $value, 'Beginn von ' . $course->title, $editor);
            if ($raised && $before !== null) {
                $participation->start_qualification_before = strtolower($area) . ':' . (int) $before;
            }
        }
        $participation->save();
    }

    /** Nur zurück, wenn noch die Stufe gilt, die der Lehrgang gesetzt hat. */
    private function resetQualification(Participation $participation, string $editor): void
    {
        $before = $participation->start_qualification_before;
        $grant  = Qualifications::grantable($participation->course->type->start_qualification);
        if ($before === null || $grant === null || $participation->personnel_id === null) {
            return;
        }
        [$area, $value] = $grant;
        $column = $area === PersonnelQualifications::AREA_RD ? 'qualird' : 'qualifw2';
        if ((int) Capsule::table('intra_mitarbeiter')->where('id', $participation->personnel_id)->value($column) !== (int) $value) {
            return;
        }

        $previous = (int) explode(':', $before, 2)[1];
        if ($this->qualifications->set($participation->personnel_id, $area, $previous, $editor)) {
            app(EventDispatcher::class)->fire(new PersonnelSaved($participation->personnel_id));
        }
    }

    /**
     * @param list<ParticipationStatus> $states
     * @return list<Participation>
     */
    private function participations(Course $course, array $states): array
    {
        /** @var list<Participation> $rows */
        $rows = Participation::query()->where('course_id', $course->id)
            ->whereIn('status', array_map(static fn (ParticipationStatus $s): string => $s->value, $states))
            ->get()->all();
        foreach ($rows as $row) {
            $row->setRelation('course', $course);
        }

        return $rows;
    }

    /** @param list<Participation> $participations */
    private function abortAll(array $participations): void
    {
        foreach ($participations as $participation) {
            $participation->status            = ParticipationStatus::Aborted->value;
            $participation->waitlist_position = null;
            $participation->finished_at       = Carbon::now();
            $participation->save();
        }
    }
}
