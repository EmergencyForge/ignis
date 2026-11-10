<?php

declare(strict_types=1);

namespace Plugin\Training\Controllers;

use App\Models\Personnel;
use App\Models\User;
use DomainException;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use EmergencyForge\Training\CourseStatus;
use EmergencyForge\Training\CourseTitle;
use EmergencyForge\Training\MaterialType;
use EmergencyForge\Training\ParticipationStatus;
use EmergencyForge\Training\UnitKind;
use Illuminate\Support\Carbon;
use Plugin\Training\Completion\CompletionTable;
use Plugin\Training\Completions;
use Plugin\Training\Converter;
use Plugin\Training\Courses;
use Plugin\Training\FileStore;
use Plugin\Training\FollowUps;
use Plugin\Training\Curriculum;
use Plugin\Training\Enrollment;
use Plugin\Training\ExamBoard;
use Plugin\Training\Lifecycle;
use Plugin\Training\Models\ExamRelease;
use Plugin\Training\Models\Attendance;
use Plugin\Training\Models\Course;
use Plugin\Training\Models\CourseStaff;
use Plugin\Training\Models\CourseType;
use Plugin\Training\Models\Participation;
use Plugin\Training\Models\Session;
use Plugin\Training\Models\TrainingFile;
use Plugin\Training\Policies\TrainingPolicy;
use Plugin\Training\SessionSync;

/**
 * Lehrgänge für Leitung und Ausbilder unter /training/manage.
 *
 * Anlegen dürfen nur Verwalter. Die Leitung eines Lehrgangs trägt ein,
 * bestätigt, beendet Teilnahmen, plant Termine und schaltet den Status.
 * Ausbilder sehen alles und setzen Fortschritt und Anwesenheit.
 */
final class ManageController extends BaseController
{
    private const NEEDS_CONVERTER = 'PowerPoint braucht hier den Umwandler. Bitte als PDF hochladen.';

    public function __construct(
        private readonly Enrollment $enrollment = new Enrollment(),
        private readonly Lifecycle $lifecycle = new Lifecycle(),
    ) {
    }

    /** GET /training/manage */
    public function index(Request $request): Response
    {
        $query = Course::query()->with(['type', 'lead'])->orderByRaw("FIELD(status, 'running', 'exam_phase', 'enrollment_open', 'planned', 'completed', 'cancelled')")->orderByDesc('starts_on');
        if (!TrainingPolicy::manage()) {
            $uid = self::userId();
            $query->where(static fn ($q) => $q->where('lead_user_id', $uid)->orWhereIn('id', CourseStaff::query()->where('user_id', $uid)->select('course_id')));
        }
        /** @var list<Course> $courses */
        $courses = $query->get()->all();

        $counts = [];
        /** @var list<Participation> $rows */
        $rows = Participation::query()->whereIn('course_id', array_map(static fn (Course $c): int => $c->id, $courses))->get(['course_id', 'status'])->all();
        foreach ($rows as $row) {
            $state = $row->state();
            $counts[$row->course_id]['seated']  = ($counts[$row->course_id]['seated'] ?? 0) + ($state->occupiesSeat() ? 1 : 0);
            $counts[$row->course_id]['waiting'] = ($counts[$row->course_id]['waiting'] ?? 0) + ($state === ParticipationStatus::Waitlisted ? 1 : 0);
            $counts[$row->course_id]['pending'] = ($counts[$row->course_id]['pending'] ?? 0) + ($state === ParticipationStatus::Enrolled ? 1 : 0);
        }

        return $this->page('manage/index', ['courses' => $courses, 'counts' => $counts, 'canManage' => TrainingPolicy::manage()]);
    }

    /** GET /training/manage/create */
    public function create(Request $request): Response
    {
        $typeId = (int) $request->queryString('type');

        return $this->form(null, [
            'course_type_id' => $typeId, 'year' => (int) date('Y'), 'starts_on' => '', 'ends_on' => '', 'enroll_until' => '',
            'seats' => '', 'lead_user_id' => self::userId(), 'instructors' => [], 'examiners' => [],
        ]);
    }

    /** POST /training/manage */
    public function store(Request $request): Response
    {
        return $this->save(new Course(), $request->post);
    }

    /** GET /training/manage/{id}/edit */
    public function edit(Request $request, string $id): Response
    {
        $course = self::findCourse($id);
        if ($course === null || !TrainingPolicy::lead($course)) {
            return self::notFound();
        }

        return $this->form($course, [
            'course_type_id' => $course->course_type_id,
            'year'           => $course->year,
            'starts_on'      => $course->starts_on?->format('Y-m-d') ?? '',
            'ends_on'        => $course->ends_on?->format('Y-m-d') ?? '',
            'enroll_until'   => $course->enroll_until?->format('Y-m-d\TH:i') ?? '',
            'seats'          => $course->seats ?? '',
            'lead_user_id'   => $course->lead_user_id,
            'instructors'    => self::staffIds($course, 'instructor'),
            'examiners'      => self::staffIds($course, 'examiner'),
        ]);
    }

    /** POST /training/manage/{id} */
    public function update(Request $request, string $id): Response
    {
        $course = self::findCourse($id);
        if ($course === null || !TrainingPolicy::lead($course)) {
            return self::notFound();
        }

        return $this->save($course, $request->post);
    }

    /** GET /training/manage/{id} */
    public function show(Request $request, string $id): Response
    {
        $course = self::findCourse($id);
        if ($course === null || (!TrainingPolicy::instruct($course) && !TrainingPolicy::examine($course))) {
            return self::notFound();
        }
        $course->load(['lead', 'staff.user']);

        /** @var list<Participation> $participations */
        $participations = Participation::query()->with('personnel')->where('course_id', $course->id)
            ->orderByRaw("FIELD(status, 'enrolled', 'admitted', 'in_training', 'in_exam', 'passed', 'failed', 'waitlisted', 'rejected', 'aborted')")
            ->orderBy('waitlist_position')->orderBy('id')->get()->all();
        foreach ($participations as $p) {
            $p->setRelation('course', $course);
        }

        $curriculum = Curriculum::for($course);
        $learners   = array_values(array_filter($participations, static fn (Participation $p): bool => in_array($p->status, Participation::LEARNING, true)));
        $states     = $curriculum->states(array_map(static fn (Participation $p): int => $p->id, $learners));
        $percent    = [];
        foreach ($learners as $p) {
            $percent[$p->id] = $curriculum->progress($states[$p->id] ?? [])->percent();
        }

        /** @var list<Session> $sessions */
        $sessions   = Session::query()->with('slides')->where('course_id', $course->id)->orderBy('starts_at')->get()->all();
        $attendance = [];
        foreach (Attendance::query()->whereIn('session_id', array_map(static fn (Session $s): int => $s->id, $sessions))->get() as $row) {
            $attendance[$row->session_id][$row->participation_id] = $row->state;
        }
        $responses = [];
        foreach ($sessions as $session) {
            $responses[$session->id] = SessionSync::responses($session);
        }

        $joined    = array_filter(array_map(static fn (Participation $p): ?int => $p->personnel_id, $participations));
        $personnel = Personnel::query()->whereNotIn('id', $joined)->orderBy('fullname')->get(['id', 'fullname'])->all();

        $byIds = [];
        foreach ($states as $unitStates) {
            foreach ($unitStates as $s) {
                if ($s['manual'] && $s['by'] !== null) {
                    $byIds[$s['by']] = true;
                }
            }
        }
        $names = [];
        foreach (User::query()->whereIn('id', array_keys($byIds) ?: [0])->get(['id', 'fullname', 'username']) as $u) {
            $names[(int) $u->id] = (string) ($u->fullname ?: $u->username);
        }

        $scheduled  = array_filter(array_map(static fn (Session $s): ?int => $s->unit_id, $sessions));
        $openSlots  = array_values(array_filter($curriculum->units(), static fn ($u): bool => $u->type() === UnitKind::Session && !in_array($u->id, $scheduled, true)));
        $average    = $percent !== [] ? (int) round(array_sum($percent) / count($percent)) : 0;

        return $this->page('manage/show', [
            'course'         => $course,
            'participations' => $participations,
            'learners'       => $learners,
            'curriculum'     => $curriculum,
            'states'         => $states,
            'percent'        => $percent,
            'average'        => $average,
            'sessions'       => $sessions,
            'attendance'     => $attendance,
            'responses'      => $responses,
            'personnel'      => $personnel,
            'names'          => $names,
            'openSlots'      => $openSlots,
            'seated'         => count(array_filter($participations, static fn (Participation $p): bool => $p->state()->occupiesSeat())),
            'waiting'        => count(array_filter($participations, static fn (Participation $p): bool => $p->state() === ParticipationStatus::Waitlisted)),
            'canLead'        => TrainingPolicy::lead($course),
            'canInstruct'    => TrainingPolicy::instruct($course),
            'converter'      => Converter::configured() !== null,
            'calendar'      => SessionSync::enabled(),
            'board'          => ExamBoard::for($course, $learners),
            'canExamine'     => TrainingPolicy::examine($course),
            'reviews'        => Courses::reviewQueue($course->id),
            'releases'       => ExamRelease::query()->with('releaser')->where('course_id', $course->id)->whereNull('participation_id')->get()->keyBy('exam_id')->all(),
            'completions'    => (new CompletionTable())->forParticipations(array_map(static fn (Participation $p): int => $p->id, $learners)),
        ]);
    }

    /** POST /training/follow-ups/{id}/done */
    public function followUpDone(Request $request, string $id): Response
    {
        $done = FollowUps::close((int) $id, self::userId(), TrainingPolicy::manage());
        $back = (string) ($request->post['back'] ?? '');
        $back = str_starts_with($back, 'personnel/profile?id=') ? $back : 'inbox';

        return self::back($back, $done ? null : 'Diese Aufgabe ist schon erledigt oder nicht deine.', $done ? 'Aufgabe erledigt.' : null);
    }

    /** POST /training/manage/{id}/completions/{pid}/retry: „Erneut versuchen“ ohne Wartezeit. */
    public function retry(Request $request, string $id, string $pid): Response
    {
        [$course, $participation] = $this->leadParticipation($id, $pid);
        if ($course === null || $participation === null) {
            return self::notFound();
        }
        $completion = (new Completions())->run($participation->id, true);
        if ($completion === null) {
            return self::back('training/manage/' . $course->id . '#abschluss', 'Der Abschluss läuft gerade schon. Gleich noch einmal ansehen.');
        }

        return $completion->isFinished()
            ? self::back('training/manage/' . $course->id . '#abschluss', null, 'Abschluss von ' . $participation->name() . ' ist durch.')
            : self::back('training/manage/' . $course->id . '#abschluss', 'Ein Schritt ist wieder gescheitert. Der Hinweis in der Liste nennt den Grund.');
    }

    /** POST /training/manage/{id}/status */
    public function status(Request $request, string $id): Response
    {
        $course = self::findCourse($id);
        if ($course === null || !TrainingPolicy::lead($course)) {
            return self::notFound();
        }
        $to = CourseStatus::tryFrom((string) ($request->post['status'] ?? ''));
        if ($to === null) {
            return self::back('training/manage/' . $course->id, 'Unbekannter Status.');
        }
        try {
            $this->lifecycle->transition($course, $to, self::editor(), ($request->post['reset_qualification'] ?? '') === '1');
        } catch (DomainException $e) {
            return self::back('training/manage/' . $course->id, $e->getMessage());
        }

        return self::back('training/manage/' . $course->id, null, 'Status ist jetzt „' . $to->label() . '“.');
    }

    /** POST /training/manage/{id}/participants */
    public function enroll(Request $request, string $id): Response
    {
        $course = self::findCourse($id);
        if ($course === null || !TrainingPolicy::lead($course)) {
            return self::notFound();
        }
        $personnelId = (int) ($request->post['personnel_id'] ?? 0);
        if (Personnel::query()->find($personnelId) === null) {
            return self::back('training/manage/' . $course->id, 'Bitte einen Mitarbeiter wählen.');
        }
        try {
            $participation = $this->enrollment->enrollByLead($course, $personnelId, self::userId(), self::editor());
        } catch (DomainException $e) {
            return self::back('training/manage/' . $course->id, $e->getMessage());
        }

        return self::back('training/manage/' . $course->id, null, $participation->name() . ($participation->status === 'waitlisted' ? ' steht auf der Warteliste.' : ' ist eingetragen.'));
    }

    /** POST /training/manage/{id}/participants/{pid}/decide */
    public function decide(Request $request, string $id, string $pid): Response
    {
        [$course, $participation] = $this->leadParticipation($id, $pid);
        if ($participation === null) {
            return self::notFound();
        }
        $accept = ($request->post['decision'] ?? '') === 'accept';
        try {
            $this->enrollment->decide($participation, $accept, self::editor());
        } catch (DomainException $e) {
            return self::back('training/manage/' . $course->id, $e->getMessage());
        }

        return self::back('training/manage/' . $course->id, null, $participation->name() . ($accept ? ' ist zugelassen.' : ' ist abgelehnt.'));
    }

    /** POST /training/manage/{id}/participants/{pid}/abort */
    public function abort(Request $request, string $id, string $pid): Response
    {
        [$course, $participation] = $this->leadParticipation($id, $pid);
        if ($participation === null) {
            return self::notFound();
        }
        try {
            $this->enrollment->abort($participation, self::editor());
        } catch (DomainException $e) {
            return self::back('training/manage/' . $course->id, $e->getMessage());
        }

        return self::back('training/manage/' . $course->id, null, 'Die Teilnahme von ' . $participation->name() . ' ist beendet.');
    }

    /** POST /training/manage/{id}/progress: Zelle der Matrix von Hand setzen. */
    public function progress(Request $request, string $id): Response
    {
        $course = self::findCourse($id);
        if ($course === null || !TrainingPolicy::instruct($course)) {
            return self::json(['error' => 'Nicht gefunden.'], 404);
        }
        $participation = Participation::query()->where('course_id', $course->id)->find((int) ($request->post['participation'] ?? 0));
        $unit          = Curriculum::for($course)->unit((int) ($request->post['unit'] ?? 0));
        if ($participation === null || $unit === null || !in_array($participation->status, Participation::LEARNING, true) || $unit->type() === UnitKind::Session) {
            return self::json(['error' => 'Diese Einheit lässt sich hier nicht setzen.'], 422);
        }
        $participation->setRelation('course', $course);
        $done = ($request->post['done'] ?? '') === '1';
        Curriculum::setByHand($participation, $unit, $done, self::userId(), self::editor());

        return self::json(['done' => $done]);
    }

    /** POST /training/manage/{id}/sessions */
    public function storeSession(Request $request, string $id): Response
    {
        $course = self::findCourse($id);
        if ($course === null || !TrainingPolicy::lead($course)) {
            return self::notFound();
        }
        $post   = $request->post;
        $date   = self::date((string) ($post['date'] ?? ''));
        $from   = (string) ($post['from'] ?? '');
        $until  = (string) ($post['until'] ?? '');
        $unitId = (int) ($post['unit_id'] ?? 0);
        $unit   = $unitId > 0 ? Curriculum::for($course)->unit($unitId) : null;
        $title  = trim((string) ($post['title'] ?? '')) ?: ($unit->title ?? '');

        if ($date === null || !preg_match('/^\d{2}:\d{2}$/', $from) || !preg_match('/^\d{2}:\d{2}$/', $until) || $until <= $from) {
            return self::back('training/manage/' . $course->id . '#termine', 'Bitte Datum und eine Uhrzeit von/bis angeben.');
        }
        if ($title === '' || ($unitId > 0 && ($unit === null || $unit->type() !== UnitKind::Session))) {
            return self::back('training/manage/' . $course->id . '#termine', 'Bitte einen Titel angeben.');
        }
        if ($unit !== null && Session::query()->where('course_id', $course->id)->where('unit_id', $unit->id)->exists()) {
            return self::back('training/manage/' . $course->id . '#termine', 'Für diese Einheit gibt es schon einen Termin.');
        }

        $session = new Session();
        $session->course_id = $course->id;
        $session->unit_id   = $unit?->id;
        $session->module_id = $unit?->module_id;
        $session->title     = mb_substr($title, 0, 200);
        $session->starts_at = $date->copy()->setTimeFromTimeString($from);
        $session->ends_at   = $date->copy()->setTimeFromTimeString($until);
        $session->location  = trim((string) ($post['location'] ?? '')) ?: null;
        $session->required  = ($post['required'] ?? '') === '1';
        $session->save();
        $session->setRelation('course', $course);
        SessionSync::sync($session);

        return self::back('training/manage/' . $course->id . '#termine', null, 'Termin angelegt.');
    }

    /** POST /training/manage/{id}/sessions/{sid}/delete */
    public function deleteSession(Request $request, string $id, string $sid): Response
    {
        $course  = self::findCourse($id);
        $session = $course !== null ? Session::query()->where('course_id', $course->id)->find((int) $sid) : null;
        if ($session === null || !TrainingPolicy::lead($course)) {
            return self::notFound();
        }
        SessionSync::remove($session);
        $session->delete();

        return self::back('training/manage/' . $course->id . '#termine', null, 'Termin gelöscht.');
    }

    /** POST /training/manage/{id}/sessions/{sid}/attendance */
    public function attendance(Request $request, string $id, string $sid): Response
    {
        $course  = self::findCourse($id);
        $session = $course !== null ? Session::query()->where('course_id', $course->id)->find((int) $sid) : null;
        if ($session === null || !TrainingPolicy::instruct($course)) {
            return self::notFound();
        }
        $states = is_array($request->post['state'] ?? null) ? $request->post['state'] : [];
        $valid  = Participation::query()->where('course_id', $course->id)->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        foreach ($states as $participationId => $state) {
            $participationId = (int) $participationId;
            if (!in_array($participationId, $valid, true)) {
                continue;
            }
            if (!is_string($state) || !isset(Attendance::STATES[$state])) {
                Attendance::query()->where('session_id', $session->id)->where('participation_id', $participationId)->delete();
                continue;
            }
            Attendance::query()->updateOrCreate(
                ['session_id' => $session->id, 'participation_id' => $participationId],
                ['state' => $state, 'recorded_by' => self::userId()],
            );
        }

        return self::back('training/manage/' . $course->id . '#termine', null, 'Anwesenheit gespeichert.');
    }

    /**
     * POST /training/manage/{id}/sessions/{sid}/slides
     *
     * Ein Upload schlägt die Auswahl aus dem Lehrplan. Geprüft wird, bevor
     * etwas gespeichert wird, damit kein Termin auf Folien zeigt, die sich
     * nicht anzeigen lassen.
     */
    public function slides(Request $request, string $id, string $sid): Response
    {
        $course  = self::findCourse($id);
        $session = $course !== null ? Session::query()->where('course_id', $course->id)->find((int) $sid) : null;
        if ($course === null || $session === null || !TrainingPolicy::instruct($course)) {
            return self::notFound();
        }
        $back   = 'training/manage/' . $course->id . '#termine';
        $post   = $request->post;
        $upload = $request->files['slides'] ?? null;

        // „Entfernen“ geht vor der Auswahl, die das Formular mit der aktuellen Wahl vorbelegt.
        if (($post['remove'] ?? '') === '1') {
            $session->slides_file_id = null;
        } elseif (is_array($upload) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            // Ein abgebrochener Upload hat keine Datei zum Prüfen; den Grund nennt FileStore::store() unten.
            $failed = (int) $upload['error'] !== UPLOAD_ERR_OK;
            $kind   = $failed ? null : MaterialType::detect(is_string($upload['tmp_name'] ?? null) ? $upload['tmp_name'] : '')?->kind;
            if (!$failed && $kind !== 'pdf' && $kind !== 'presentation') {
                return self::back($back, 'Folien müssen ein PDF oder eine PowerPoint-Datei sein.');
            }
            // Ohne Umwandler würde PowerPoint nie zur PDF und der Vortrag ließe sich nicht starten.
            if ($kind === 'presentation' && Converter::configured() === null) {
                return self::back($back, self::NEEDS_CONVERTER);
            }
            try {
                $session->slides_file_id = (new FileStore())->store($upload, self::userId())->id;
            } catch (DomainException | \RuntimeException $e) {
                return self::back($back, $e->getMessage());
            }
        } elseif ((int) ($post['slides_file_id'] ?? 0) > 0) {
            $fileId = (int) $post['slides_file_id'];
            $file   = Curriculum::for($course)->usesFile($fileId) ? TrainingFile::query()->find($fileId) : null;
            if ($file === null) {
                return self::back($back, 'Diese Unterlage gehört nicht zu diesem Lehrgang.');
            }
            // Nur eine neue Wahl prüfen, damit sich ältere Termine weiter speichern lassen.
            if ($file->id !== $session->slides_file_id && !$file->viewsAsPdf()) {
                if ($file->mime !== Converter::PPTX) {
                    return self::back($back, 'Folien müssen ein PDF oder eine PowerPoint-Datei sein.');
                }
                if (Converter::configured() === null) {
                    return self::back($back, self::NEEDS_CONVERTER);
                }
            }
            $session->slides_file_id = $file->id;
        }

        $session->save();

        return self::back($back, null, $session->slides_file_id === null ? 'Folien entfernt.' : 'Folien gespeichert.');
    }

    /** @param array<string, mixed> $form */
    private function form(?Course $course, array $form, int $status = 200): Response
    {
        return $this->page('manage/form', [
            'course'  => $course,
            'form'    => $form,
            'types'   => CourseType::query()->where('is_active', true)->orderBy('name')->get()->all(),
            'users'   => User::query()->where('is_active', true)->orderBy('fullname')->get(['id', 'fullname', 'username'])->all(),
        ], $status);
    }

    /** @param array<string, mixed> $post */
    private function save(Course $course, array $post): Response
    {
        $isNew = !$course->exists;
        if ($isNew && !TrainingPolicy::manage()) {
            return self::notFound();
        }
        $form = [
            'course_type_id' => (int) ($post['course_type_id'] ?? 0),
            'year'           => (int) ($post['year'] ?? 0),
            'starts_on'      => (string) ($post['starts_on'] ?? ''),
            'ends_on'        => (string) ($post['ends_on'] ?? ''),
            'enroll_until'   => (string) ($post['enroll_until'] ?? ''),
            'seats'          => trim((string) ($post['seats'] ?? '')),
            'lead_user_id'   => (int) ($post['lead_user_id'] ?? 0),
            'instructors'    => array_values(array_unique(array_map('intval', is_array($post['instructors'] ?? null) ? $post['instructors'] : []))),
            'examiners'      => array_values(array_unique(array_map('intval', is_array($post['examiners'] ?? null) ? $post['examiners'] : []))),
        ];

        $type   = CourseType::query()->find($isNew ? $form['course_type_id'] : $course->course_type_id);
        $starts = $form['starts_on'] !== '' ? self::date($form['starts_on']) : null;
        $ends   = $form['ends_on'] !== '' ? self::date($form['ends_on']) : null;
        $until  = $form['enroll_until'] !== '' ? self::dateTime($form['enroll_until']) : null;
        $error  = match (true) {
            $type === null                                      => 'Bitte eine Lehrgangsart wählen.',
            $form['year'] < 2000 || $form['year'] > 2100        => 'Bitte ein Jahr angeben.',
            ($form['starts_on'] !== '' && $starts === null) || ($form['ends_on'] !== '' && $ends === null) => 'Bitte gültige Daten angeben.',
            $starts !== null && $ends !== null && $ends < $starts => 'Das Ende liegt vor dem Beginn.',
            $form['enroll_until'] !== '' && $until === null     => 'Bitte einen gültigen Anmeldeschluss angeben.',
            $form['seats'] !== '' && (!ctype_digit($form['seats']) || (int) $form['seats'] < 1) => 'Plätze sind eine Zahl ab 1, leer heißt unbegrenzt.',
            default                                             => null,
        };
        if ($error !== null) {
            \App\Helpers\Flash::error($error);

            return $this->form($isNew ? null : $course, $form, 422);
        }

        if ($isNew || $course->year !== $form['year']) {
            $course->course_type_id = $type->id;
            $course->year           = $form['year'];
            $course->number         = Courses::nextNumber($type->id, $form['year']);
            $course->title          = CourseTitle::make($type->short, $course->number, $form['year']);
        }
        if ($isNew) {
            $course->status     = CourseStatus::Planned->value;
            $course->created_by = self::userId();
        }
        $course->starts_on    = $starts;
        $course->ends_on      = $ends;
        $course->enroll_until = $until;
        $course->seats        = $form['seats'] !== '' ? (int) $form['seats'] : null;
        $course->lead_user_id = User::query()->find($form['lead_user_id']) !== null ? $form['lead_user_id'] : null;
        $course->save();

        CourseStaff::query()->where('course_id', $course->id)->delete();
        foreach (['instructor' => $form['instructors'], 'examiner' => $form['examiners']] as $role => $ids) {
            foreach (User::query()->whereIn('id', $ids ?: [0])->pluck('id') as $userId) {
                CourseStaff::query()->create(['course_id' => $course->id, 'user_id' => (int) $userId, 'role' => $role]);
            }
        }

        return self::back('training/manage/' . $course->id, null, $isNew ? $course->title . ' ist angelegt.' : 'Gespeichert.');
    }

    /** @return list<int> */
    private static function staffIds(Course $course, string $role): array
    {
        return CourseStaff::query()->where('course_id', $course->id)->where('role', $role)->pluck('user_id')->map(static fn ($id): int => (int) $id)->all();
    }

    /** @return array{0: ?Course, 1: ?Participation} */
    private function leadParticipation(string $id, string $pid): array
    {
        $course = self::findCourse($id);
        if ($course === null || !TrainingPolicy::lead($course)) {
            return [null, null];
        }
        $participation = Participation::query()->where('course_id', $course->id)->find((int) $pid);
        $participation?->setRelation('course', $course);

        return [$course, $participation];
    }

    private static function date(string $value): ?Carbon
    {
        return self::parse('!Y-m-d', 'Y-m-d', $value);
    }

    private static function dateTime(string $value): ?Carbon
    {
        return self::parse('Y-m-d\TH:i', 'Y-m-d\TH:i', $value);
    }

    /** Carbon wirft bei kaputter Eingabe, ein Formularfeld soll nur leer bleiben. */
    private static function parse(string $format, string $check, string $value): ?Carbon
    {
        try {
            $date = Carbon::createFromFormat($format, $value);
        } catch (\Throwable) {
            return null;
        }

        return $date !== null && $date->format($check) === $value ? $date : null;
    }
}
