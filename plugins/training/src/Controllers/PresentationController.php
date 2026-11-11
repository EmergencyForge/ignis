<?php

declare(strict_types=1);

namespace Plugin\Training\Controllers;

use App\Models\User;
use App\Utils\AuditLogger;
use DateTimeImmutable;
use DomainException;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Plugin\Training\AiDrafts;
use Plugin\Training\Models\Course;
use Plugin\Training\Models\Participation;
use Plugin\Training\Models\Presentation;
use Plugin\Training\Models\PresentationEvent;
use Plugin\Training\Models\PresentationViewer;
use Plugin\Training\Models\Session;
use Plugin\Training\Policies\TrainingPolicy;
use Plugin\Training\Presentations;

/**
 * Vortrag an einem Termin unter /training/courses/{id}/sessions/{sid}.
 * Starten und Beenden sind Formulare, alles andere JSON. Schreiben darf nur
 * der Host (beenden auch die Leitung), lesen alle, die dem Vortrag folgen
 * dürfen. Fragen stellen Folgende außer dem Host, sehen und beantworten
 * Leitung und Ausbilder. Das Abfragen gibt die Sitzung frei, damit viele
 * Zuschauer sich nicht gegenseitig auf dem Sitzungsschloss warten lassen.
 */
final class PresentationController extends BaseController
{
    private const MAX_EVENTS = 200;
    private const MAX_PAGES  = 500;
    /** Schlüssel je Nutzer und Minute; jeder ist eine Anfrage an die Konsole mit bis zu 5 s Wartezeit. */
    private const TOKEN_LIMIT = 10;

    /** GET /training/courses/{course}/sessions/{session}/slides */
    public function slides(Request $request, string $course, string $session): Response
    {
        $courseModel  = self::findCourse($course);
        $sessionModel = $courseModel !== null ? Session::query()->where('course_id', $courseModel->id)->with('unit.file')->find((int) $session) : null;
        $file         = $sessionModel?->presentationFile();
        // Die Route lässt jeden mit Ausbildungssicht herein; Folien sind Lehrgangsmaterial.
        if ($courseModel === null || !TrainingPolicy::follow($courseModel, self::ownParticipation($courseModel)) || $file === null || !$file->viewsAsPdf()) {
            return self::notFound();
        }
        // Mit abgeschaltetem Schalter an der Einheit gibt es die Folien nur im Vortrag.
        if (!TrainingPolicy::instruct($courseModel) && !Presentations::slidesOpen($sessionModel->unit, $courseModel, new DateTimeImmutable())) {
            return self::notFound();
        }

        return self::deliver($file, false);
    }

    /** POST …/presentation/start */
    public function start(Request $request, string $course, string $session): Response
    {
        $ctx = $this->context($course, $session);
        if ($ctx === null) {
            return self::notFound();
        }
        [$courseModel, $sessionModel] = $ctx;
        if (!TrainingPolicy::instruct($courseModel)) {
            return self::back('training/courses/' . $courseModel->id, 'Einen Vortrag starten Leitung und Ausbilder des Lehrgangs.');
        }
        if (!$sessionModel->isPresentation()) {
            return self::back('training/manage/' . $courseModel->id . '#termine', 'Dieser Termin hat keine Präsentation.');
        }
        // Ohne Folien läuft der Vortrag auch; vorhandene müssen sich aber anzeigen lassen.
        $slides = $sessionModel->presentationFile();
        if ($slides !== null && !$slides->viewsAsPdf()) {
            return self::back('training/manage/' . $courseModel->id . '#termine', 'Die Folien werden noch umgewandelt.');
        }
        $live = Presentations::start($sessionModel, self::userId(), AiDrafts::client());
        if ($live->wasRecentlyCreated) {
            (new AuditLogger())->log(self::userId(), 'Vortrag gestartet', $sessionModel->title, 'Ausbildung', 0, ['change_type' => 'training_presentation_started']);
        }

        return Response::redirect(self::url($courseModel, $sessionModel));
    }

    /** GET …/presentation */
    public function show(Request $request, string $course, string $session): Response
    {
        $ctx = $this->context($course, $session);
        if ($ctx === null) {
            return self::notFound();
        }
        [$courseModel, $sessionModel] = $ctx;
        $live = Presentations::live($sessionModel, new DateTimeImmutable());
        if ($live === null) {
            return self::back(TrainingPolicy::instruct($courseModel) ? 'training/manage/' . $courseModel->id . '#termine' : 'training/courses/' . $courseModel->id, 'Der Vortrag ist beendet.');
        }
        $host = $live->presenter_id === self::userId();

        return $this->page($host ? 'presentation/present' : 'presentation/follow', [
            'course'       => $courseModel,
            'session'      => $sessionModel,
            'presentation' => $live,
            'url'          => self::url($courseModel, $sessionModel),
            'slidesUrl'    => $sessionModel->presentationFile()?->viewsAsPdf() === true
                ? self::base() . 'training/courses/' . $courseModel->id . '/sessions/' . $sessionModel->id . '/slides'
                : '',
            'userId'       => self::userId(),
            'targets'      => $host ? Presentations::targets($live) : [],
        ]);
    }

    /** GET …/presentation/state: Stand und neue Ereignisse, zugleich der Herzschlag des Abfragenden. */
    public function state(Request $request, string $course, string $session): Response
    {
        $userId = self::userId();
        // Nutzer und Rechte stehen nun im Speicher; ab hier nichts mehr in die Sitzung schreiben.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $ctx = $this->context($course, $session);
        if ($ctx === null) {
            return self::json(['error' => 'not_found'], 404);
        }
        [$courseModel, $sessionModel, $own] = $ctx;
        $now  = new DateTimeImmutable();
        $live = Presentations::live($sessionModel, $now);
        if ($live === null) {
            return self::json(['status' => 'ended']);
        }
        Presentations::heartbeat($live, $userId, $own?->id, $now);

        $host  = $live->presenter_id === $userId;
        $staff = TrainingPolicy::instruct($courseModel);
        // Mit der ID, denn ein neuer Lauf desselben Termins beginnt wieder bei 0-0-0.
        $etag = '"' . $live->id . '-' . $live->version . '-' . $live->seq . '-' . $live->q_version . '"';
        // Dem Host und den Ausbildern ändern sich Zuschauer, Fragen und Besuche ohne neue Version; sie bekommen immer Inhalt.
        // Vary: der Inhalt gehört dem Angemeldeten, ein Browser darf ihn nicht für einen anderen Nutzer wiederverwenden.
        if (!$host && !$staff && $request->header('If-None-Match') === $etag) {
            return new Response(304, '', ['ETag' => $etag, 'Cache-Control' => 'private, no-cache', 'Vary' => 'Cookie']);
        }

        $after  = max(0, (int) $request->queryString('after'));
        // Signale sind an genau einen gerichtet; wer neu einsteigt, braucht weder alte Signale noch Laser.
        $events = PresentationEvent::query()->where('presentation_id', $live->id)->where('seq', '>', $after)
            ->where(static fn ($q) => $q->whereNull('to_user_id')->orWhere('to_user_id', $userId));
        if ($after === 0) {
            $events->whereNotIn('kind', ['laser', 'signal']);
        }
        $events    = $events->oldest('seq')->get();
        $to        = $live->handover_to !== null ? User::query()->with('mitarbeiter')->find($live->handover_to) : null;
        $presenter = Presentations::identity(User::query()->with('mitarbeiter')->find($live->presenter_id));

        $body = [
            'status'    => 'live',
            'id'        => $live->id,
            'mode'      => $live->mode,
            'page'      => $live->page,
            'version'   => $live->version,
            'seq'       => $live->seq,
            'presenter' => ['id' => $live->presenter_id, 'name' => $presenter['name'], 'initials' => $presenter['initials']],
            'handover'  => $to === null ? null : ['to_id' => (int) $to->id, 'to_name' => Presentations::identity($to)['name']],
            'screen'    => $live->mode === 'screen' && $live->screen_via !== null ? ['via' => $live->screen_via] : null,
            'room'      => $live->room !== null,
            'slides'    => $sessionModel->presentationFile()?->viewsAsPdf() === true,
            'questions' => Presentations::questions($live, $userId, $staff),
            'events'    => $events->map(static fn (PresentationEvent $e): array => ['seq' => $e->seq, 'kind' => $e->kind] + (array) json_decode($e->data, true))->all(),
        ];
        if ($staff) {
            $body['visits'] = Presentations::visits([$live]);
        }
        if ($host) {
            $body['viewers'] = PresentationViewer::query()->with('user.mitarbeiter')->where('presentation_id', $live->id)
                ->where('last_seen_at', '>=', date('Y-m-d H:i:s', $now->getTimestamp() - Presentations::VIEWER_WINDOW))
                ->oldest('first_seen_at')->get()
                ->map(static function (PresentationViewer $v): array {
                    $who = Presentations::identity($v->user);

                    return ['id' => (int) $v->user_id, 'name' => $who['name'], 'initials' => $who['initials'], 'since' => $v->first_seen_at->toIso8601String(), 'in_course' => $v->participation_id !== null];
                })->all();
        }

        return self::json($body)->withHeader('ETag', $etag)->withHeader('Cache-Control', 'private, no-cache')->withHeader('Vary', 'Cookie');
    }

    /** POST …/presentation/events: Zeichnungen, Seite und Modus vom Host. */
    public function events(Request $request, string $course, string $session): Response
    {
        $live = $this->hostPresentation($course, $session, $error);
        if ($live === null) {
            return $error;
        }
        $json      = $request->json() ?? [];
        $events    = $json['events'] ?? [];
        $pageCount = $json['page_count'] ?? 1;
        $page      = $json['page'] ?? null;
        $mode      = $json['mode'] ?? null;
        // Ohne Folien ist page_count 0: dann gibt es keine Seite, nur Striche auf dem geteilten Bildschirm (Seite -1).
        if (!is_int($pageCount) || $pageCount < 0 || $pageCount > self::MAX_PAGES
            || !is_array($events) || !array_is_list($events) || count($events) > self::MAX_EVENTS
            || ($page !== null && (!is_int($page) || $page < 0 || $page >= $pageCount))
            || ($mode !== null && !in_array($mode, Presentations::MODES, true))) {
            return self::json(['error' => 'invalid'], 422);
        }

        try {
            $seq = Presentations::apply($live, $events, $pageCount, $page, $mode);
        } catch (InvalidArgumentException) {
            return self::json(['error' => 'invalid'], 422);
        } catch (DomainException) {
            return self::json(['error' => 'ended'], 409);
        }

        return self::json(['ok' => true, 'seq' => $seq]);
    }

    /** POST …/presentation/screen: Host beginnt das Teilen; der Weg entscheidet sich hier. */
    public function screen(Request $request, string $course, string $session): Response
    {
        $live = $this->hostPresentation($course, $session, $error);
        if ($live === null) {
            return $error;
        }
        $via = ($request->json() ?? [])['via'] ?? null;
        if ($via !== null && $via !== 'direct') {
            return self::json(['error' => 'invalid'], 422);
        }
        try {
            // `direct`: der Host bekam einen LiveKit-Schlüssel, kam aber nicht in den Raum.
            $result = Presentations::startScreen($live, self::userId(), $via === 'direct' ? null : AiDrafts::client());
        } catch (DomainException $e) {
            return self::json(['error' => $e->getMessage()], 409);
        }

        return self::json(['ok' => true] + $result);
    }

    /** POST …/presentation/room/token: LiveKit-Schlüssel für jeden, der den Vortrag sehen darf. */
    public function roomToken(Request $request, string $course, string $session): Response
    {
        $ctx  = $this->context($course, $session);
        $live = $ctx === null ? null : Presentations::live($ctx[1], new DateTimeImmutable());
        if ($live === null) {
            return self::json(['error' => 'not_found'], 404);
        }
        $userId = self::userId();
        $key    = 'training_room_tokens_' . $userId;
        $now    = Carbon::now()->getTimestamp();
        $recent = array_values(array_filter((array) ($_SESSION[$key] ?? []), static fn ($at): bool => is_int($at) && $at > $now - 60));
        if (count($recent) >= self::TOKEN_LIMIT) {
            return self::json(['error' => 'too_many_tokens'], 429);
        }
        $_SESSION[$key] = [...$recent, $now];
        try {
            $token = Presentations::roomToken($live, $userId, AiDrafts::client());
        } catch (DomainException) {
            // Auch wenn die Konsole eben ausfiel: der Raum ist weg, die Abfrage trägt den Vortrag weiter.
            return self::json(['error' => 'no_room'], 409);
        }

        return self::json(['ok' => true] + $token);
    }

    /** POST …/presentation/questions: Frage an die Ausbilder, von jedem Folgenden außer dem Host. */
    public function ask(Request $request, string $course, string $session): Response
    {
        $ctx  = $this->context($course, $session);
        $live = $ctx === null ? null : Presentations::live($ctx[1], new DateTimeImmutable());
        if ($live === null) {
            return self::json(['error' => 'not_found'], 404);
        }
        if ($live->presenter_id === self::userId()) {
            return self::json(['error' => 'forbidden'], 403);
        }
        $body = ($request->json() ?? [])['body'] ?? null;
        try {
            $question = Presentations::ask($live, self::userId(), is_string($body) ? $body : '');
        } catch (DomainException $e) {
            return match ($e->getMessage()) {
                'too_many' => self::json(['error' => 'too_many'], 429),
                'ended'    => self::json(['error' => 'ended'], 409),
                default    => self::json(['error' => 'invalid'], 422),
            };
        }

        return self::json(['ok' => true, 'id' => $question->id]);
    }

    /** POST …/presentation/questions/{id}/answer: Antwort (optional) und abhaken, nur mit Rechten am Lehrgang. */
    public function answer(Request $request, string $course, string $session, string $questionId): Response
    {
        $answer = ($request->json() ?? [])['answer'] ?? null;

        return $this->closeQuestion($course, $session, (int) $questionId, is_string($answer) ? $answer : null);
    }

    /** POST …/presentation/questions/{id}/done */
    public function done(Request $request, string $course, string $session, string $questionId): Response
    {
        return $this->closeQuestion($course, $session, (int) $questionId, null);
    }

    private function closeQuestion(string $course, string $session, int $questionId, ?string $answer): Response
    {
        $ctx  = $this->context($course, $session);
        $live = $ctx === null ? null : Presentations::live($ctx[1], new DateTimeImmutable());
        if ($live === null) {
            return self::json(['error' => 'not_found'], 404);
        }
        // Fragen sehen und beantworten alle mit Rechten am Lehrgang, nicht nur der Host.
        if (!TrainingPolicy::instruct($ctx[0])) {
            return self::json(['error' => 'forbidden'], 403);
        }
        try {
            $answer === null ? Presentations::done($live, $questionId, self::userId()) : Presentations::answer($live, $questionId, self::userId(), $answer);
        } catch (DomainException $e) {
            return match ($e->getMessage()) {
                'not_found' => self::json(['error' => 'not_found'], 404),
                'ended'     => self::json(['error' => 'ended'], 409),
                default     => self::json(['error' => 'invalid'], 422),
            };
        }

        return self::json(['ok' => true]);
    }

    /** POST …/presentation/signal: Verbindungsaufbau für das direkte Teilen, vom Host oder von einem Zuschauer. */
    public function signal(Request $request, string $course, string $session): Response
    {
        $ctx  = $this->context($course, $session);
        $live = $ctx === null ? null : Presentations::live($ctx[1], new DateTimeImmutable());
        if ($live === null) {
            return self::json(['error' => 'not_found'], 404);
        }
        $json = $request->json() ?? [];
        $to   = $json['to'] ?? null;
        try {
            $seq = Presentations::signal($live, self::userId(), is_int($to) ? $to : null, $json);
        } catch (InvalidArgumentException) {
            return self::json(['error' => 'invalid'], 422);
        } catch (DomainException $e) {
            return $e->getMessage() === 'too_many_signals'
                ? self::json(['error' => 'too_many_signals'], 429)
                : self::json(['error' => 'not_direct'], 409);
        }

        return self::json(['ok' => true, 'seq' => $seq]);
    }

    /** POST …/presentation/handover: Anfrage an Leitung oder Ausbilder. */
    public function handover(Request $request, string $course, string $session): Response
    {
        $live = $this->hostPresentation($course, $session, $error);
        if ($live === null) {
            return $error;
        }
        $to = ($request->json() ?? [])['user_id'] ?? null;
        try {
            Presentations::requestHandover($live, is_int($to) ? $to : 0);
        } catch (InvalidArgumentException $e) {
            return self::json(['error' => $e->getMessage()], 422);
        }

        return self::json(['ok' => true]);
    }

    /** POST …/presentation/handover/cancel: Der Host nimmt eine offene Anfrage zurück. */
    public function cancel(Request $request, string $course, string $session): Response
    {
        $live = $this->hostPresentation($course, $session, $error);
        if ($live === null) {
            return $error;
        }
        Presentations::cancelHandover($live);

        return self::json(['ok' => true]);
    }

    /** POST …/presentation/handover/accept */
    public function accept(Request $request, string $course, string $session): Response
    {
        return $this->answerHandover($course, $session, true);
    }

    /** POST …/presentation/handover/decline */
    public function decline(Request $request, string $course, string $session): Response
    {
        return $this->answerHandover($course, $session, false);
    }

    /** POST …/presentation/end */
    public function end(Request $request, string $course, string $session): Response
    {
        $ctx = $this->context($course, $session);
        if ($ctx === null) {
            return self::notFound();
        }
        [$courseModel, $sessionModel] = $ctx;
        $live = Presentations::live($sessionModel, new DateTimeImmutable());
        if ($live !== null) {
            if ($live->presenter_id !== self::userId() && !TrainingPolicy::lead($courseModel)) {
                return self::back(self::path($courseModel, $sessionModel), 'Den Vortrag beendet der Host oder die Leitung.');
            }
            if (Presentations::end($live)) {
                (new AuditLogger())->log(self::userId(), 'Vortrag beendet', $sessionModel->title, 'Ausbildung', 0, ['change_type' => 'training_presentation_ended']);
            }
        }

        return self::back('training/manage/' . $courseModel->id . '?attendance=' . $sessionModel->id . '#termine');
    }

    private function answerHandover(string $course, string $session, bool $accept): Response
    {
        $ctx  = $this->context($course, $session);
        $live = $ctx === null ? null : Presentations::live($ctx[1], new DateTimeImmutable());
        if ($live === null) {
            return self::json(['error' => 'not_found'], 404);
        }
        $userId = self::userId();
        try {
            if ($live->handover_to !== $userId) {
                throw new InvalidArgumentException('handover');
            }
            $accept ? Presentations::acceptHandover($live, $userId) : Presentations::declineHandover($live, $userId);
        } catch (InvalidArgumentException) {
            // Auch wenn der Host die Anfrage gerade zurückgenommen oder umgeleitet hat.
            return self::json(['error' => 'forbidden'], 403);
        }

        return self::json(['ok' => true]);
    }

    /** Laufender Vortrag, wenn der Angemeldete Host ist; sonst steht in $error die Antwort. */
    private function hostPresentation(string $course, string $session, ?Response &$error): ?Presentation
    {
        $ctx  = $this->context($course, $session);
        $live = $ctx === null ? null : Presentations::live($ctx[1], new DateTimeImmutable());
        if ($live === null) {
            $error = self::json(['error' => 'not_found'], 404);

            return null;
        }
        if ($live->presenter_id !== self::userId()) {
            $error = self::json(['error' => 'forbidden'], 403);

            return null;
        }

        return $live;
    }

    /**
     * Lehrgang, Termin (über den Lehrgang gefunden) und eigene Teilnahme. Null,
     * wenn etwas fehlt oder der Angemeldete dem Vortrag nicht folgen darf; die
     * Route allein ließe jeden mit Ausbildungssicht herein.
     *
     * @return array{0: Course, 1: Session, 2: ?Participation}|null
     */
    private function context(string $course, string $session): ?array
    {
        $courseModel  = self::findCourse($course);
        $sessionModel = $courseModel !== null ? Session::query()->where('course_id', $courseModel->id)->with('unit.file')->find((int) $session) : null;
        if ($courseModel === null || $sessionModel === null) {
            return null;
        }
        $own = self::ownParticipation($courseModel);
        if (!TrainingPolicy::follow($courseModel, $own)) {
            return null;
        }
        $sessionModel->setRelation('course', $courseModel);

        return [$courseModel, $sessionModel, $own];
    }

    private static function path(Course $course, Session $session): string
    {
        return 'training/courses/' . $course->id . '/sessions/' . $session->id . '/presentation';
    }

    private static function url(Course $course, Session $session): string
    {
        return self::base() . self::path($course, $session);
    }
}
