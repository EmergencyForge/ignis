<?php

declare(strict_types=1);

namespace Plugin\Training;

use DomainException;
use EmergencyForge\Training\CourseStatus;
use EmergencyForge\Training\EnrollmentMode;
use EmergencyForge\Training\ParticipationStatus;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Carbon;
use Plugin\Training\Models\Course;
use Plugin\Training\Models\Participation;

/**
 * Anmelden, Eintragen, Bestätigen, Abbrechen und die Warteliste.
 *
 * Plätze zählt ParticipationStatus::occupiesSeat(). Wird einer frei, rückt
 * der Erste der Warteliste nach, mit dem Status, den er bei freiem Platz
 * gleich bekommen hätte. Die Zählung läuft unter einer Sperre auf den
 * Lehrgang, sonst kämen zwei gleichzeitige Anmeldungen auf denselben
 * letzten Platz.
 *
 * Fehler kommen als DomainException mit einem Satz für die Oberfläche.
 */
final class Enrollment
{
    public function __construct(private readonly Lifecycle $lifecycle = new Lifecycle())
    {
    }

    /** Selbstanmeldung über den Katalog. */
    public function enrollSelf(Course $course, int $personnelId, int $userId): Participation
    {
        $type = $course->type;
        $mode = $type->enrollment();
        if (!$mode->allowsSelfEnrollment()) {
            throw new DomainException('In diesen Lehrgang trägt nur die Leitung ein.');
        }
        if ($course->state() !== CourseStatus::EnrollmentOpen || $course->enrollmentClosed()) {
            throw new DomainException('Die Anmeldung zu diesem Lehrgang ist geschlossen.');
        }
        $missing = Qualifications::missing($personnelId, $type->prerequisiteKeys());
        if ($missing !== []) {
            throw new DomainException('Setzt ' . implode(' und ', $missing) . ' voraus.');
        }

        $participation = $this->create($course, $personnelId, $userId, static fn (bool $seatFree): ParticipationStatus => $mode->statusOnEnroll($seatFree));

        if ($participation->status === ParticipationStatus::Enrolled->value && $course->lead_user_id !== null) {
            Notify::users([$course->lead_user_id], 'Neue Anmeldung für ' . $course->title, 'training/manage/' . $course->id, $participation->name() . ' wartet auf deine Bestätigung.');
        }
        SessionSync::course($course);

        return $participation;
    }

    /** Die Leitung trägt ein, auch ohne erfüllte Voraussetzung. */
    public function enrollByLead(Course $course, int $personnelId, int $userId, string $editor): Participation
    {
        if ($course->state()->isFinal()) {
            throw new DomainException('Der Lehrgang ist beendet.');
        }

        $participation = $this->create($course, $personnelId, $userId, static fn (bool $seatFree): ParticipationStatus => $seatFree ? ParticipationStatus::Admitted : ParticipationStatus::Waitlisted);
        if ($participation->status === ParticipationStatus::Admitted->value && $course->state()->materialsOpen()) {
            $this->lifecycle->startTraining($participation, $editor);
        }

        Notify::personnel([$personnelId], $participation->status === ParticipationStatus::Waitlisted->value
            ? 'Du stehst auf der Warteliste für ' . $course->title
            : 'Du wurdest in ' . $course->title . ' eingetragen', 'training/courses/' . $course->id);
        SessionSync::course($course);

        return $participation;
    }

    /** Anmeldung „Mit Bestätigung“ annehmen oder ablehnen. */
    public function decide(Participation $participation, bool $accept, string $editor): void
    {
        if ($participation->state() !== ParticipationStatus::Enrolled) {
            throw new DomainException('Diese Anmeldung wartet nicht auf eine Entscheidung.');
        }
        $course = $participation->course;

        $participation->status = ($accept ? ParticipationStatus::Admitted : ParticipationStatus::Rejected)->value;
        if ($accept) {
            $participation->admitted_at = Carbon::now();
        } else {
            $participation->finished_at = Carbon::now();
        }
        $participation->save();

        if ($accept && $course->state()->materialsOpen()) {
            $this->lifecycle->startTraining($participation, $editor);
        }
        if ($participation->personnel_id !== null) {
            Notify::personnel([$participation->personnel_id], $accept
                ? 'Du bist für ' . $course->title . ' zugelassen'
                : 'Deine Anmeldung zu ' . $course->title . ' wurde abgelehnt', 'training/courses/' . $course->id);
        }
        if (!$accept) {
            $this->promote($course, $editor);
        }
        SessionSync::course($course);
    }

    /** Teilnahme beenden (Leitung) oder Anmeldung zurückziehen (selbst). */
    public function abort(Participation $participation, string $editor): void
    {
        if (!$participation->state()->canTransitionTo(ParticipationStatus::Aborted)) {
            throw new DomainException('Diese Teilnahme ist schon beendet.');
        }
        $freed = $participation->state()->occupiesSeat();

        $participation->status            = ParticipationStatus::Aborted->value;
        $participation->waitlist_position = null;
        $participation->finished_at       = Carbon::now();
        $participation->save();

        if ($freed) {
            $this->promote($participation->course, $editor);
        }
        SessionSync::course($participation->course);
    }

    /** Selbst zurückziehen geht nur, solange der Lehrgang nicht läuft. */
    public function withdraw(Participation $participation, string $editor): void
    {
        $open = [ParticipationStatus::Waitlisted, ParticipationStatus::Enrolled, ParticipationStatus::Admitted];
        if (!in_array($participation->state(), $open, true) || $participation->course->state()->materialsOpen()) {
            throw new DomainException('Der Lehrgang läuft schon. Wende dich an die Leitung.');
        }
        $this->abort($participation, $editor);
    }

    /** Freie Plätze an die Warteliste vergeben, in Reihenfolge. */
    public function promote(Course $course, string $editor): void
    {
        $mode = $course->type->enrollment();
        while ($this->seatFree($course)) {
            $next = Participation::query()->where('course_id', $course->id)->where('status', ParticipationStatus::Waitlisted->value)
                ->orderBy('waitlist_position')->orderBy('id')->first();
            if ($next === null) {
                return;
            }
            $status = $mode === EnrollmentMode::LeadOnly ? ParticipationStatus::Admitted : $mode->statusOnEnroll(true);

            $next->status            = $status->value;
            $next->waitlist_position = null;
            $next->admitted_at       = $status === ParticipationStatus::Admitted ? Carbon::now() : null;
            $next->save();

            if ($status === ParticipationStatus::Admitted && $course->state()->materialsOpen()) {
                $this->lifecycle->startTraining($next, $editor);
            }
            if ($next->personnel_id !== null) {
                Notify::personnel([$next->personnel_id], 'Ein Platz in ' . $course->title . ' ist frei geworden', 'training/courses/' . $course->id,
                    $status === ParticipationStatus::Enrolled ? 'Deine Anmeldung wartet jetzt auf die Bestätigung der Leitung.' : 'Du bist zugelassen.');
            }
        }
    }

    public function seatFree(Course $course): bool
    {
        if ($course->seats === null) {
            return true;
        }
        $seated = array_map(static fn (ParticipationStatus $s): string => $s->value, array_filter(ParticipationStatus::cases(), static fn (ParticipationStatus $s): bool => $s->occupiesSeat()));

        return Participation::query()->where('course_id', $course->id)->whereIn('status', $seated)->count() < $course->seats;
    }

    /** @param callable(bool): ParticipationStatus $status */
    private function create(Course $course, int $personnelId, int $userId, callable $status): Participation
    {
        return Capsule::connection()->transaction(function () use ($course, $personnelId, $userId, $status): Participation {
            Course::query()->whereKey($course->id)->lockForUpdate()->first();

            if (Participation::query()->where('course_id', $course->id)->where('personnel_id', $personnelId)->exists()) {
                throw new DomainException('Ist schon in diesem Lehrgang.');
            }

            $state = $status($this->seatFree($course));

            $participation = new Participation();
            $participation->course_id    = $course->id;
            $participation->personnel_id = $personnelId;
            $participation->status       = $state->value;
            $participation->enrolled_by  = $userId;
            $participation->enrolled_at  = Carbon::now();
            if ($state === ParticipationStatus::Admitted) {
                $participation->admitted_at = Carbon::now();
            }
            if ($state === ParticipationStatus::Waitlisted) {
                $participation->waitlist_position = 1 + (int) Participation::query()->where('course_id', $course->id)->max('waitlist_position');
            }
            $participation->save();
            $participation->setRelation('course', $course);

            return $participation;
        });
    }
}
