<?php

declare(strict_types=1);

namespace Plugin\Training\Policies;

use App\Auth\Permissions;
use App\Personnel\AccountLink;
use Plugin\Training\Courses;
use Plugin\Training\Models\Attempt;
use Plugin\Training\Models\Course;
use Plugin\Training\Models\CourseStaff;
use Plugin\Training\Models\Participation;

/**
 * Wer was in der Ausbildung darf. In einem Lehrgang zählt die Rolle dort
 * (Leitung, Ausbilder, Prüfer) und das Recht: wer Leitung ist, aber kein
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
            && CourseStaff::query()->where('course_id', $course->id)->where('user_id', self::userId())->where('role', 'instructor')->exists();
    }

    /**
     * Dem Vortrag eines Termins folgen: Leitung und Ausbilder, dazu Lernende,
     * deren Lehrgang die Unterlagen schon freigibt. Ein bloßes Teilnehmen
     * reicht nicht, denn Folien sind Lehrgangsmaterial.
     */
    public static function follow(Course $course, ?Participation $own): bool
    {
        return self::instruct($course)
            || ($own !== null && $own->canLearn() && $course->state()->materialsOpen());
    }

    /** Fragenpools und Prüfungsordnungen sehen und pflegen. */
    public static function exams(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'training.exams']);
    }

    /** Im Lehrgang prüfen: eingetragener Prüfer, ohne Prüfer Leitung oder Ausbilder. */
    public static function examine(?Course $course = null): bool
    {
        if (self::manage()) {
            return true;
        }

        return $course !== null && Permissions::check(['training.teach']) && in_array(self::userId(), Courses::examiners($course), true);
    }

    /**
     * Diesen Versuch bewerten oder gegenzeichnen. Niemand bewertet sich
     * selbst, und wer bewertet hat, zeichnet nicht gegen.
     */
    public static function grade(Attempt $attempt): bool
    {
        $participation = $attempt->participation;
        $own           = AccountLink::currentId();
        if ($own !== null && $participation->personnel_id === $own) {
            return false;
        }
        if ($attempt->status === Attempt::COSIGN && $attempt->graded_by === self::userId()) {
            return false;
        }

        return self::examine($participation->course);
    }

    /** Versuch ansehen: die Person selbst, Lehrgangspersonal, Verwalter. */
    public static function viewAttempt(Attempt $attempt): bool
    {
        $participation = $attempt->participation;
        $own           = AccountLink::currentId();

        return ($own !== null && $participation->personnel_id === $own)
            || self::instruct($participation->course)
            || self::examine($participation->course);
    }

    private static function userId(): int
    {
        return (int) ($_SESSION['userid'] ?? 0);
    }
}
