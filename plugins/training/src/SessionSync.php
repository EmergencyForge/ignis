<?php

declare(strict_types=1);

namespace Plugin\Training;

use App\Plugins\PluginLoader;
use EmergencyForge\Training\CourseStatus;
use Illuminate\Database\Capsule\Manager as Capsule;
use Plugin\Calendar\Models\CalendarAttendee;
use Plugin\Calendar\Models\CalendarEvent;
use Plugin\Training\Models\Course;
use Plugin\Training\Models\Participation;
use Plugin\Training\Models\Session;

/**
 * Termine erscheinen als Kalendereintrag „Schulung“ bei den aktiven
 * Teilnehmern. Der Kalender kennt nur Zu- und Absagen, keine Anwesenheit,
 * deshalb bleibt die Anwesenheit in intra_training_attendance. Ohne das
 * Kalender-Plugin passiert hier nichts.
 */
final class SessionSync
{
    public static function course(Course $course): void
    {
        if (!self::enabled()) {
            return;
        }
        foreach (Session::query()->where('course_id', $course->id)->get() as $session) {
            $session->setRelation('course', $course);
            $course->state() === CourseStatus::Cancelled ? self::remove($session) : self::sync($session);
        }
    }

    public static function sync(Session $session): void
    {
        if (!self::enabled()) {
            return;
        }
        $course = $session->course;

        $event = ($session->calendar_event_id !== null ? CalendarEvent::query()->find($session->calendar_event_id) : null) ?? new CalendarEvent();
        $event->title            = $course->title . ': ' . $session->title;
        $event->location         = $session->location;
        $event->starts_at        = $session->starts_at;
        $event->ends_at          = $session->ends_at;
        $event->all_day          = false;
        $event->track_attendance = true;
        $event->category         = 'training';
        $event->color            = 'green';
        $event->visibility       = CalendarEvent::VISIBILITY_ATTENDEES;
        $event->source           = CalendarEvent::SOURCE_TRAINING;
        $event->source_ref_id    = $session->id;
        $event->created_by       = $course->lead_user_id ?? $course->created_by ?? (int) Capsule::table('intra_users')->orderByDesc('full_admin')->orderBy('id')->value('id');
        $event->save();

        if ($session->calendar_event_id !== $event->id) {
            $session->calendar_event_id = $event->id;
            $session->save();
        }

        $people = Participation::query()->where('course_id', $course->id)->whereIn('status', Participation::ACTIVE)
            ->whereNotNull('personnel_id')->pluck('personnel_id')->map(static fn ($id): int => (int) $id)->all();
        CalendarAttendee::query()->where('event_id', $event->id)->where('is_organizer', false)->whereNotIn('mitarbeiter_id', $people)->delete();
        foreach ($people as $personnelId) {
            CalendarAttendee::query()->firstOrCreate(
                ['event_id' => $event->id, 'mitarbeiter_id' => $personnelId],
                ['response' => CalendarAttendee::RESPONSE_PENDING, 'is_organizer' => false],
            );
        }
    }

    public static function remove(Session $session): void
    {
        if ($session->calendar_event_id === null || !self::enabled()) {
            return;
        }
        CalendarEvent::query()->whereKey($session->calendar_event_id)->delete();
        $session->calendar_event_id = null;
        if ($session->exists) {
            $session->save();
        }
    }

    /**
     * Antworten aus dem Kalender als Hinweis neben der Anwesenheit:
     * personnel_id => response.
     *
     * @return array<int, string>
     */
    public static function responses(Session $session): array
    {
        if ($session->calendar_event_id === null || !self::enabled()) {
            return [];
        }

        return CalendarAttendee::query()->where('event_id', $session->calendar_event_id)
            ->pluck('response', 'mitarbeiter_id')->map(static fn ($r): string => (string) $r)->all();
    }

    public static function enabled(): bool
    {
        return class_exists(CalendarEvent::class) && app(PluginLoader::class)->isActive('calendar');
    }
}
