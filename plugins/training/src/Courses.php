<?php

declare(strict_types=1);

namespace Plugin\Training;

use EmergencyForge\Training\ParticipationStatus;
use App\Personnel\AccountLink;
use Plugin\Training\Models\Attempt;
use Plugin\Training\Models\Course;
use Plugin\Training\Models\CourseStaff;
use Plugin\Training\Models\Participation;
use Plugin\Training\Policies\TrainingPolicy;

final class Courses
{
    /** Anmeldungen, die auf die Bestätigung durch diesen Nutzer warten. */
    public static function pendingConfirmations(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }
        $query = Participation::query()->where('status', ParticipationStatus::Enrolled->value);
        if (!TrainingPolicy::manage()) {
            $query->whereIn('course_id', Course::query()->where('lead_user_id', $userId)->select('id'));
        }

        return $query->count();
    }

    /** Laufende Nummer je Lehrgangsart und Jahr, ab 1. */
    public static function nextNumber(int $courseTypeId, int $year): int
    {
        return 1 + (int) Course::query()->where('course_type_id', $courseTypeId)->where('year', $year)->max('number');
    }

    /**
     * Wer im Lehrgang bewertet: die eingetragenen Prüfer, sonst Leitung und
     * Ausbilder.
     *
     * @return list<int> Konto-IDs
     */
    public static function examiners(Course $course): array
    {
        /** @var list<CourseStaff> $staff */
        $staff     = CourseStaff::query()->where('course_id', $course->id)->get(['user_id', 'role'])->all();
        $examiners = array_map(static fn (CourseStaff $s): int => $s->user_id, array_filter($staff, static fn (CourseStaff $s): bool => $s->role === 'examiner'));
        if ($examiners === []) {
            $examiners = array_map(static fn (CourseStaff $s): int => $s->user_id, $staff);
            if ($course->lead_user_id !== null) {
                $examiners[] = $course->lead_user_id;
            }
        }

        return array_values(array_unique($examiners));
    }

    /**
     * Versuche, die auf den angemeldeten Nutzer als Prüfer warten: Bewertung
     * von Hand oder Gegenzeichnung, nie die eigenen.
     *
     * @return list<Attempt>
     */
    public static function reviewQueue(?int $courseId = null): array
    {
        if ((int) ($_SESSION['userid'] ?? 0) <= 0) {
            return [];
        }
        $query = Attempt::query()->with(['exam', 'participation.course.type', 'participation.personnel'])
            ->whereIn('status', [Attempt::REVIEW, Attempt::COSIGN])->orderBy('submitted_at');
        if ($courseId !== null) {
            $query->whereIn('participation_id', Participation::query()->where('course_id', $courseId)->select('id'));
        }

        /** @var list<Attempt> $rows */
        $rows = $query->get()->all();

        return array_values(array_filter($rows, static fn (Attempt $attempt): bool => TrainingPolicy::grade($attempt)));
    }

    /** Prüfungen, die der angemeldete Teilnehmer jetzt beginnen kann oder die laufen. */
    public static function openExams(): int
    {
        $personnelId = AccountLink::currentId();
        if ($personnelId === null) {
            return 0;
        }
        $count = 0;
        /** @var list<Participation> $rows */
        $rows = Participation::query()->with('course')->where('personnel_id', $personnelId)->whereIn('status', ['in_training', 'in_exam', 'failed'])->get()->all();
        foreach ($rows as $participation) {
            $board = ExamBoard::for($participation->course, [$participation]);
            foreach ($board->parts as $part) {
                $state = $board->state($participation, $part);
                if ($part->isTheory() && ($state->canStart() || $state->open() !== null)) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
