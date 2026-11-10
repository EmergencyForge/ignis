<?php

declare(strict_types=1);

namespace Plugin\Training;

use EmergencyForge\Training\CourseStatus;
use Illuminate\Support\Carbon;
use Plugin\Training\Models\Course;
use Plugin\Training\Models\Participation;

/**
 * Erinnert vor dem Ablauf eines bestandenen Lehrgangs, je Teilnahme einmal.
 * Die Qualifikation bleibt dabei stehen: ob sie nach dem Ablauf fällt,
 * entscheidet die Fraktion von Hand.
 */
final class Expiry
{
    public const DAYS_AHEAD = 30;

    /** @return int Anzahl erinnerter Teilnahmen */
    public static function remind(?Carbon $today = null): int
    {
        $today ??= Carbon::today();
        /** @var list<Participation> $due */
        $due = Participation::query()
            ->with(['course.type', 'personnel'])
            ->where('status', 'passed')
            ->whereNotNull('personnel_id')
            ->whereNull('expiry_notified_at')
            ->whereBetween('expires_on', [$today->toDateString(), $today->copy()->addDays(self::DAYS_AHEAD)->toDateString()])
            ->get()->all();

        $next = [];
        foreach ($due as $p) {
            $typeId = $p->course->course_type_id;
            $course = array_key_exists($typeId, $next) ? $next[$typeId] : ($next[$typeId] = self::nextCourse($typeId, $today));
            $date   = $p->expires_on?->format('d.m.Y');
            $link   = $course !== null ? 'training/courses/' . $course->id : 'training';
            $hint   = $course !== null
                ? 'Nächster Lehrgang: ' . $course->title . ($course->starts_on !== null ? ' ab ' . $course->starts_on->format('d.m.Y') : '') . '.'
                : 'Gerade ist kein Lehrgang dieser Art ausgeschrieben.';

            // Erst vermerken, dann senden: bricht der Lauf ab, kommt lieber keine als eine doppelte Erinnerung.
            if (Participation::query()->whereKey($p->id)->whereNull('expiry_notified_at')->update(['expiry_notified_at' => Carbon::now()]) === 0) {
                continue;
            }
            Notify::personnel([(int) $p->personnel_id], $p->course->type->name . ' läuft am ' . $date . ' ab', $link, 'Abgeschlossen mit dem ' . $p->course->title . '. ' . $hint);
            if ($p->course->lead_user_id !== null) {
                Notify::users([$p->course->lead_user_id], $p->course->type->name . ' von ' . $p->name() . ' läuft am ' . $date . ' ab', $link, 'Abgeschlossen mit dem ' . $p->course->title . '. ' . $hint);
            }
        }

        return count($due);
    }

    /** Der nächste Lehrgang derselben Art, für den man sich noch anmelden kann. */
    private static function nextCourse(int $typeId, Carbon $today): ?Course
    {
        return Course::query()
            ->where('course_type_id', $typeId)
            ->where('status', CourseStatus::EnrollmentOpen->value)
            ->where(static fn ($q) => $q->whereNull('enroll_until')->orWhere('enroll_until', '>=', $today->toDateString()))
            ->orderByRaw('starts_on IS NULL, starts_on')
            ->first();
    }
}
