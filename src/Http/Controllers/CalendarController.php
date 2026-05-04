<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Gate;
use App\Calendar\AttendeeResolver;
use App\Calendar\RecurrenceExpander;
use App\Exceptions\ValidationException;
use App\Helpers\Flash;
use App\Http\Requests\Calendar\CreateEventRequest;
use App\Http\Requests\Calendar\UpdateEventRequest;
use App\Http\Response;
use App\Models\CalendarAttendee;
use App\Models\CalendarEvent;
use App\Models\Mitarbeiter;
use App\Notifications\NotificationManager;
use App\Utils\AuditLogger;
use DateTimeImmutable;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * CalendarController — Termine, role-getaggte Dienste, Recurring-Series.
 *
 * URL-Mapping:
 *   GET  /kalender                 → index()         (Page mit FullCalendar-Mount)
 *   GET  /kalender/view?id=X       → show()          (Detail-Modal-HTML-Fragment)
 *   POST /kalender/create          → store()
 *   POST /kalender/update?id=X     → update()
 *   POST /kalender/delete?id=X     → destroy()
 *   POST /kalender/respond?id=X    → respondInvite()
 *   GET  /api/kalender/events      → eventsJson()    (FullCalendar-Feed)
 */
class CalendarController extends Controller
{
    /**
     * GET /kalender — Page mit FullCalendar + Sidebar (Filter, Verfügbarkeits-Widget).
     */
    public function index(): void
    {
        $this->requireAuth();
        $this->ensure('calendar.view', redirectTo: 'index');

        // Mitarbeiter-Liste fuer Attendee-Picker
        $mitarbeiter = Mitarbeiter::query()
            ->orderBy('fullname')
            ->get(['id', 'fullname', 'dienstnr']);

        // Rollen fuer Visibility=role-Auswahl
        $roles = Capsule::table('intra_users_roles')
            ->orderBy('priority')
            ->orderBy('name')
            ->get(['id', 'name', 'color'])
            ->map(fn ($r) => (array) $r)
            ->all();

        $this->renderView('kalender/index', [
            'mitarbeiter' => $mitarbeiter,
            'roles'       => $roles,
            'categories'  => CalendarEvent::CATEGORIES,
            'colors'      => CalendarEvent::COLORS,
        ]);
    }

    /**
     * GET /api/kalender/events?from=...&to=...
     *
     * FullCalendar-EventSource — liefert ein Array von EventInput-Objekten
     * im Format, das FullCalendar 6 erwartet. Recurring-Events werden via
     * RecurrenceExpander in Einzelvorkommen aufgeloest.
     */
    public function eventsJson(): Response
    {
        $this->requireAuth();
        $this->ensure('calendar.view', redirectTo: 'index');

        $from = $this->parseRange($_GET['from'] ?? null, '-1 month');
        $to   = $this->parseRange($_GET['to']   ?? null, '+2 months');

        $userId        = (int) ($_SESSION['userid'] ?? 0);
        $roleId        = (int) ($_SESSION['role_id'] ?? 0) ?: null;
        $mitarbeiterId = $this->resolveMitarbeiterId();

        $events = CalendarEvent::query()
            ->visibleTo($userId, $roleId, $mitarbeiterId)
            ->inRange($from, $to)
            // Exception-Rows fuer Recurring-Series werden NICHT als eigenstaendige
            // Events geliefert — der Expander zieht sie sich.
            ->where(function ($q) {
                $q->whereNull('parent_event_id')
                    ->orWhereNotNull('recurrence_rule');
            })
            ->get();

        $output = [];
        foreach ($events as $event) {
            if (!empty($event->recurrence_rule)) {
                $expanded = RecurrenceExpander::expand($event, $from, $to);
                foreach ($expanded as $occ) {
                    $output[] = $this->toFullCalendarEvent($occ, true, $event->id);
                }
            } else {
                $output[] = $this->toFullCalendarEvent($event, false, null);
            }
        }

        return Response::json($output);
    }

    /**
     * GET /api/kalender/event?id=X — JSON-Detail fuer Edit-Prefill.
     *
     * Gibt das volle Event-Datenmodell zurueck, sodass das Frontend das
     * Edit-Form korrekt befuellen kann. Sichtbarkeit wie bei show().
     */
    public function eventJson(): Response
    {
        $this->requireAuth();
        $this->ensure('calendar.view', redirectTo: 'index');

        $id = (int) ($_GET['id'] ?? 0);
        $event = $id > 0 ? CalendarEvent::with(['attendees', 'visibilityRoles'])->find($id) : null;

        if ($event === null || Gate::denies('calendar.view', $event)) {
            return Response::json(['success' => false, 'message' => 'Nicht gefunden'], 404);
        }

        $startsAt = $event->starts_at instanceof \DateTimeInterface
            ? $event->starts_at->format('Y-m-d\TH:i')
            : substr((string) $event->starts_at, 0, 16);
        $endsAt = $event->ends_at instanceof \DateTimeInterface
            ? $event->ends_at->format('Y-m-d\TH:i')
            : substr((string) $event->ends_at, 0, 16);
        $until = $event->recurrence_until instanceof \DateTimeInterface
            ? $event->recurrence_until->format('Y-m-d')
            : ($event->recurrence_until ? substr((string) $event->recurrence_until, 0, 10) : null);

        return Response::json([
            'success' => true,
            'event'   => [
                'id'                  => (int) $event->id,
                'title'               => (string) $event->title,
                'description'         => (string) ($event->description ?? ''),
                'location'            => (string) ($event->location ?? ''),
                'starts_at'           => $startsAt,
                'ends_at'             => $endsAt,
                'all_day'             => (bool) $event->all_day,
                'color'               => (string) $event->color,
                'category'            => (string) $event->category,
                'visibility'          => (string) $event->visibility,
                'visibility_role_ids' => $event->visibilityRoles->pluck('id')->map(fn ($v) => (int) $v)->all(),
                'attendees'           => $event->attendees->pluck('mitarbeiter_id')->map(fn ($v) => (int) $v)->all(),
                'recurrence_rule'     => $event->recurrence_rule,
                'recurrence_until'    => $until,
            ],
        ]);
    }

    /**
     * GET /kalender/view?id=X — Detail-HTML-Fragment fuer das Detail-Modal.
     */
    public function show(): void
    {
        $this->requireAuth();
        $this->ensure('calendar.view', redirectTo: 'index');

        $id = (int) ($_GET['id'] ?? 0);
        $event = $id > 0 ? CalendarEvent::with(['attendees.mitarbeiter', 'creator', 'visibilityRoles'])->find($id) : null;

        if ($event === null || Gate::denies('calendar.view', $event)) {
            Flash::error('Termin nicht gefunden oder keine Berechtigung.');
            $this->redirect('kalender');
        }

        $myMitarbeiterId = $this->resolveMitarbeiterId();
        $myAttendeeRow   = $myMitarbeiterId
            ? $event->attendees->firstWhere('mitarbeiter_id', $myMitarbeiterId)
            : null;

        $this->renderView('kalender/view', [
            'event'           => $event,
            'attendees'       => AttendeeResolver::resolve($event),
            'attendeeCount'   => AttendeeResolver::count($event),
            'canEdit'         => Gate::allows('calendar.update', $event),
            'canDelete'       => Gate::allows('calendar.delete', $event),
            'myResponse'      => $myAttendeeRow?->response,
            'categoriesLabel' => CalendarEvent::CATEGORIES[$event->category] ?? $event->category,
        ]);
    }

    /**
     * POST /kalender/create — neuen Termin anlegen.
     */
    public function store(): void
    {
        $this->requireAuth();
        $this->ensure('calendar.create', redirectTo: 'kalender');

        try {
            $data = CreateEventRequest::validate($_POST);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect('kalender');
        }

        $event = $this->buildFromValidated(new CalendarEvent(), $data);
        $event->created_by = (int) $_SESSION['userid'];
        $event->source     = CalendarEvent::SOURCE_MANUAL;
        $event->save();

        $this->syncVisibilityRoles($event, $data['visibility_role_ids'] ?? []);
        $this->syncAttendees($event, $data['attendees'] ?? [], (int) $_SESSION['userid']);
        $this->notifyAttendees($event, isUpdate: false);
        $this->auditLog('Termin erstellt', "ID: {$event->id}, Titel: {$event->title}");

        Flash::success('Termin erstellt.');
        $this->redirect('kalender');
    }

    /**
     * POST /kalender/update?id=X — bestehenden Termin aendern.
     */
    public function update(): void
    {
        $this->requireAuth();

        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $event = $id > 0 ? CalendarEvent::find($id) : null;
        if ($event === null) {
            Flash::error('Termin nicht gefunden.');
            $this->redirect('kalender');
        }

        Gate::authorize('calendar.update', $event);

        try {
            $data = UpdateEventRequest::validate($_POST);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect('kalender');
        }

        $this->buildFromValidated($event, $data);
        $event->save();

        $this->syncVisibilityRoles($event, $data['visibility_role_ids'] ?? []);
        $this->syncAttendees($event, $data['attendees'] ?? [], (int) $event->created_by);
        $this->notifyAttendees($event, isUpdate: true);
        $this->auditLog('Termin bearbeitet', "ID: {$event->id}, Titel: {$event->title}");

        Flash::success('Termin aktualisiert.');
        $this->redirect('kalender');
    }

    /**
     * POST /kalender/delete?id=X — Termin loeschen.
     */
    public function destroy(): void
    {
        $this->requireAuth();

        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $event = $id > 0 ? CalendarEvent::find($id) : null;
        if ($event === null) {
            Flash::error('Termin nicht gefunden.');
            $this->redirect('kalender');
        }

        Gate::authorize('calendar.delete', $event);

        $eventId    = $event->id;
        $eventTitle = $event->title;
        $event->delete();

        $this->auditLog('Termin gelöscht', "ID: {$eventId}, Titel: {$eventTitle}");

        Flash::success('Termin gelöscht.');
        $this->redirect('kalender');
    }

    /**
     * POST /kalender/respond?id=X — Attendee setzt Response (accepted/declined/tentative).
     */
    public function respondInvite(): void
    {
        $this->requireAuth();

        $id       = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $response = (string) ($_POST['response'] ?? '');
        $allowed  = [
            CalendarAttendee::RESPONSE_ACCEPTED,
            CalendarAttendee::RESPONSE_DECLINED,
            CalendarAttendee::RESPONSE_TENTATIVE,
        ];

        if (!in_array($response, $allowed, true)) {
            Flash::error('Ungültige Antwort.');
            $this->redirect('kalender');
        }

        $mitarbeiterId = $this->resolveMitarbeiterId();
        if ($mitarbeiterId === null) {
            Flash::error('Kein Mitarbeiter-Profil verknüpft.');
            $this->redirect('kalender');
        }

        $attendee = CalendarAttendee::where('event_id', $id)
            ->where('mitarbeiter_id', $mitarbeiterId)
            ->first();

        if ($attendee === null) {
            Flash::error('Du bist nicht eingeladen.');
            $this->redirect('kalender');
        }

        $attendee->response     = $response;
        $attendee->responded_at = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $attendee->save();

        Flash::success('Antwort gespeichert.');
        $this->redirect('kalender');
    }

    // -----------------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------------

    /**
     * Uebernimmt validierte Felder in das Event-Model (nicht gespeichert).
     */
    private function buildFromValidated(CalendarEvent $event, array $data): CalendarEvent
    {
        $event->title            = $data['title'];
        $event->description      = $data['description'];
        $event->location         = $data['location'];
        $event->starts_at        = $data['starts_at'];
        $event->ends_at          = $data['ends_at'];
        $event->all_day          = (bool) $data['all_day'];
        $event->color            = $data['color'];
        $event->category         = $data['category'];
        $event->visibility       = $data['visibility'];
        $event->recurrence_rule  = $data['recurrence_rule'];
        $event->recurrence_until = $data['recurrence_until'];
        // visibility_role_ids[] wird per Pivot synchronisiert nach $event->save() —
        // siehe syncVisibilityRoles().
        return $event;
    }

    /**
     * Synct die Pivot-Tabelle intra_calendar_event_roles. Bei
     * visibility != 'role' werden alle Pivot-Rows entfernt.
     */
    private function syncVisibilityRoles(CalendarEvent $event, array $roleIds): void
    {
        if ($event->visibility !== CalendarEvent::VISIBILITY_ROLE) {
            $event->visibilityRoles()->detach();
            return;
        }
        $event->visibilityRoles()->sync(array_map('intval', $roleIds));
    }

    /**
     * Synct Attendee-Rows fuer ein Event. Bei visibility != 'attendees' loescht
     * es alle persistierten Attendees. Bei 'attendees' fuegt es Differenzen
     * hinzu/entfernt sie. Der Ersteller ist immer als Organizer-Attendee drin
     * (egal welche Visibility).
     */
    private function syncAttendees(CalendarEvent $event, array $mitarbeiterIds, int $creatorUserId): void
    {
        if ($event->visibility !== CalendarEvent::VISIBILITY_ATTENDEES
            && $event->visibility !== CalendarEvent::VISIBILITY_PRIVATE
        ) {
            CalendarAttendee::where('event_id', $event->id)->delete();
            return;
        }

        $current = CalendarAttendee::where('event_id', $event->id)->pluck('mitarbeiter_id')->all();
        $current = array_map('intval', $current);

        $desired = array_values(array_unique(array_map('intval', $mitarbeiterIds)));

        // Creator zwangsweise als Organizer dazu, falls er ein Mitarbeiter-Profil hat
        $creatorMitarbeiterId = $this->mitarbeiterIdForUser($creatorUserId);
        if ($creatorMitarbeiterId !== null && !in_array($creatorMitarbeiterId, $desired, true)) {
            $desired[] = $creatorMitarbeiterId;
        }

        $toAdd    = array_diff($desired, $current);
        $toRemove = array_diff($current, $desired);

        foreach ($toAdd as $mid) {
            CalendarAttendee::create([
                'event_id'       => $event->id,
                'mitarbeiter_id' => $mid,
                'response'       => $mid === $creatorMitarbeiterId
                    ? CalendarAttendee::RESPONSE_ACCEPTED
                    : CalendarAttendee::RESPONSE_PENDING,
                'is_organizer'   => $mid === $creatorMitarbeiterId,
            ]);
        }

        if (!empty($toRemove)) {
            CalendarAttendee::where('event_id', $event->id)
                ->whereIn('mitarbeiter_id', $toRemove)
                ->delete();
        }
    }

    /**
     * Schickt eine Notification an alle aktuellen Attendees des Events.
     * Bei Recurring-Series: nur EINE Notification pro User (nicht pro Vorkommen).
     */
    private function notifyAttendees(CalendarEvent $event, bool $isUpdate): void
    {
        $userIds = AttendeeResolver::resolveUserIds($event);
        if ($userIds === []) {
            return;
        }

        $notifier = new NotificationManager($this->pdo);
        $verb     = $isUpdate ? 'aktualisiert' : 'angelegt';
        $when     = (string) $event->starts_at;
        $msg      = "Termin am {$when}" . ($event->location ? " · {$event->location}" : '');
        $link     = (defined('BASE_PATH') ? (string) BASE_PATH : '/') . 'kalender/view?id=' . $event->id;

        $creatorUserId = (int) $event->created_by;
        foreach ($userIds as $uid) {
            if ($uid === $creatorUserId) {
                continue; // Creator bekommt keine Self-Notification
            }
            $notifier->create($uid, 'system', "Termin {$verb}: {$event->title}", $msg, $link);
        }
    }

    /**
     * Konvertiert ein Event in das FullCalendar-EventInput-Format.
     */
    private function toFullCalendarEvent(CalendarEvent $event, bool $isRecurring, ?int $seriesId): array
    {
        $colors = [
            'orange' => '#ff4d00',
            'blue'   => '#3b82f6',
            'green'  => '#16a34a',
            'red'    => '#dc2626',
            'purple' => '#a855f7',
            'gray'   => '#6b7280',
        ];
        $hex = $colors[$event->color] ?? '#ff4d00';

        return [
            'id'              => $isRecurring ? "{$seriesId}-" . substr((string) $event->starts_at, 0, 10) : (string) $event->id,
            'title'           => $event->title,
            'start'           => $this->formatForFullCalendar($event->starts_at, (bool) $event->all_day, false),
            'end'             => $this->formatForFullCalendar($event->ends_at,   (bool) $event->all_day, true),
            'allDay'          => (bool) $event->all_day,
            'backgroundColor' => $hex,
            'borderColor'     => $hex,
            'extendedProps'   => [
                'eventId'             => (int) ($seriesId ?? $event->id),
                'category'            => $event->category,
                'location'            => $event->location,
                'visibility'          => $event->visibility,
                'isRecurringInstance' => $isRecurring,
                'attendeeCount'       => AttendeeResolver::count($event),
                'source'              => $event->source,
            ],
        ];
    }

    private function formatForFullCalendar(mixed $value, bool $allDay, bool $isEnd = false): string
    {
        if ($value instanceof \DateTimeInterface) {
            $dt = $value;
        } else {
            try {
                $dt = new \DateTimeImmutable((string) $value);
            } catch (\Throwable) {
                return (string) $value;
            }
        }

        if ($allDay) {
            // FullCalendar All-Day-Events haben einen EXKLUSIVEN end-Wert.
            // Wir speichern inklusiv (User denkt: "geht bis Freitag"), also
            // ist der serialisierte end fuer FC = letzter Tag + 1.
            if ($isEnd) {
                $dt = $dt->modify('+1 day');
            }
            return $dt->format('Y-m-d');
        }

        return $dt->format('Y-m-d\TH:i:s');
    }

    private function parseRange(?string $value, string $fallbackOffset): DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return (new DateTimeImmutable())->modify($fallbackOffset);
        }
        try {
            return new DateTimeImmutable($value);
        } catch (\Throwable) {
            return (new DateTimeImmutable())->modify($fallbackOffset);
        }
    }

    private function resolveMitarbeiterId(): ?int
    {
        if (isset($_SESSION['mitarbeiter_id']) && (int) $_SESSION['mitarbeiter_id'] > 0) {
            return (int) $_SESSION['mitarbeiter_id'];
        }
        $discordId = $_SESSION['discordtag'] ?? null;
        if (!$discordId) {
            return null;
        }
        try {
            $row = Mitarbeiter::query()->where('discordtag', $discordId)->first(['id']);
            return $row ? (int) $row->id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function mitarbeiterIdForUser(int $userId): ?int
    {
        if ($userId <= 0) {
            return null;
        }
        $discordId = Capsule::table('intra_users')->where('id', $userId)->value('discord_id');
        if (!$discordId) {
            return null;
        }
        $row = Mitarbeiter::query()->where('discordtag', $discordId)->first(['id']);
        return $row ? (int) $row->id : null;
    }

    private function auditLog(string $action, string $details): void
    {
        $userId = (int) ($_SESSION['userid'] ?? 0);
        if ($userId <= 0) {
            return;
        }
        (new AuditLogger($this->pdo))->log($userId, $action, $details, 'Kalender', 1);
    }
}
