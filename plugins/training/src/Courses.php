<?php

declare(strict_types=1);

namespace Plugin\Training;

use EmergencyForge\Training\ParticipationStatus;
use Plugin\Training\Models\Course;
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
}
