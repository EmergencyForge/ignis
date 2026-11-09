<?php

declare(strict_types=1);

namespace Plugin\Training\Policies;

use App\Auth\Permissions;
use Plugin\Training\Models\Course;
use Plugin\Training\Models\CourseStaff;

/**
 * Wer was in der Ausbildung darf. In einem Lehrgang zählt die Rolle dort
 * (Leitung, Ausbilder) und das Recht: wer Leitung ist, aber kein
 * `training.teach` mehr hat, leitet nicht mehr.
 *
 *     Gate::allows('training.lead', $course)
 */
class TrainingPolicy
{
    public static function view(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'training.view', 'training.teach', 'training.manage']);
    }

    /** Lehrgänge in der Verwaltung sehen, in denen man eine Rolle hat. */
    public static function teach(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'training.teach', 'training.manage']);
    }

    public static function manage(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'training.manage']);
    }

    /** Alles im Lehrgang: Status, Teilnehmer, Zusatz-Unterlagen. */
    public static function lead(?Course $course = null): bool
    {
        if (self::manage()) {
            return true;
        }

        return $course !== null
            && Permissions::check(['training.teach'])
            && $course->lead_user_id !== null
            && $course->lead_user_id === self::userId();
    }

    /** Teilnehmer, Fortschritt und Anwesenheit sehen und pflegen. */
    public static function instruct(?Course $course = null): bool
    {
        if ($course === null) {
            return self::manage();
        }
        if (self::lead($course)) {
            return true;
        }

        return Permissions::check(['training.teach'])
            && CourseStaff::query()->where('course_id', $course->id)->where('user_id', self::userId())->exists();
    }

    private static function userId(): int
    {
        return (int) ($_SESSION['userid'] ?? 0);
    }
}
