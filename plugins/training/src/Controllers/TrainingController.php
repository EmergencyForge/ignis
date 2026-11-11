<?php

declare(strict_types=1);

namespace Plugin\Training\Controllers;

use DomainException;
use EmergencyForge\Editor\Renderer;
use EmergencyForge\Training\CourseStatus;
use EmergencyForge\Training\Progress\ProgressUnit;
use EmergencyForge\Training\UnitKind;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use Plugin\Training\Completion\CompletionTable;
use Plugin\Training\Curriculum;
use Plugin\Training\Enrollment;
use Plugin\Training\ExamBoard;
use Plugin\Training\ExamState;
use Plugin\Training\Models\Exam;
use Plugin\Training\Models\Course;
use Plugin\Training\Models\Participation;
use Plugin\Training\Models\TrainingFile;
use Plugin\Training\Models\Unit;
use Plugin\Training\Policies\TrainingPolicy;
use Plugin\Training\Presentations;
use Plugin\Training\Qualifications;

/**
 * Die Seiten der Teilnehmer: Übersicht, Lehrgang, Einheit, Dateien.
 *
 * Unterlagen sieht, wer „In Ausbildung“ oder weiter ist, solange der
 * Lehrgang läuft oder abgeschlossen ist. Leitung und Ausbilder sehen sie
 * immer, als Vorschau ohne eigenen Fortschritt.
 */
final class TrainingController extends BaseController
{
    public function __construct(private readonly Enrollment $enrollment = new Enrollment())
    {
    }

    /** GET /training */
    public function index(Request $request): Response
    {
        $personnelId = self::personnelId();
        $mine        = [];
        if ($personnelId !== null) {
            /** @var list<Participation> $rows */
            $rows = Participation::query()->with('course.type')->where('personnel_id', $personnelId)->orderByDesc('id')->get()->all();
            foreach ($rows as $participation) {
                $percent = null;
                if ($participation->canLearn()) {
                    $curriculum = Curriculum::for($participation->course);
                    $percent    = $curriculum->progress($curriculum->states([$participation->id])[$participation->id])->percent();
                }
                $mine[] = ['participation' => $participation, 'percent' => $percent];
            }
        }

        $joined = array_map(static fn (array $row): int => $row['participation']->course_id, $mine);
        $open   = [];
        /** @var list<Course> $courses */
        $courses = Course::query()->with('type')->where('status', CourseStatus::EnrollmentOpen->value)->orderBy('starts_on')->get()->all();
        foreach ($courses as $course) {
            if (in_array($course->id, $joined, true) || !$course->type->enrollment()->allowsSelfEnrollment()) {
                continue;
            }
            $open[] = [
                'course'  => $course,
                'missing' => $personnelId !== null ? Qualifications::missing($personnelId, $course->type->prerequisiteKeys()) : [],
                'closed'  => $course->enrollmentClosed(),
            ];
        }

        return $this->page('training/index', [
            'mine'      => $mine,
            'open'      => $open,
            'linked'    => $personnelId !== null,
            'canTeach'  => TrainingPolicy::teach(),
            'canManage' => TrainingPolicy::manage(),
        ]);
    }

    /** GET /training/courses/{id} */
    public function course(Request $request, string $id): Response
    {
        $course = self::findCourse($id);
        if ($course === null) {
            return self::notFound();
        }
        $participation = $this->participation($course);
        $instructor    = TrainingPolicy::instruct($course);
        if ($participation === null && !$instructor && $course->state() !== CourseStatus::EnrollmentOpen) {
            return self::notFound();
        }

        $curriculum = Curriculum::for($course);
        $state      = $participation !== null ? ($curriculum->states([$participation->id])[$participation->id] ?? []) : [];
        $progress   = $curriculum->progress($state);
        // Laufende Vorträge der Termine an Präsentationen; wer nicht folgen darf, sieht keinen Hinweis,
        // der Host keinen zum eigenen Vortrag.
        $lives = [];
        if (TrainingPolicy::follow($course, $participation)) {
            $now = new \DateTimeImmutable();
            $course->loadMissing('sessions.unit');
            foreach ($course->sessions as $session) {
                if ($session->isPresentation() && ($live = Presentations::live($session, $now)) !== null && $live->presenter_id !== self::userId()) {
                    $lives[$session->id] = [$session, $live];
                }
            }
        }
        $next       = $progress->next();
        $nextUnit   = $next !== null ? $curriculum->unit($next->id) : null;

        return $this->page('training/course', [
            'course'        => $course,
            'participation' => $participation,
            'curriculum'    => $curriculum,
            'state'         => $state,
            'progress'      => $progress,
            'nextUnit'      => $nextUnit,
            'resume'        => $nextUnit !== null ? $curriculum->resumeLabel($nextUnit, $state[$nextUnit->id] ?? null) : null,
            'learning'      => $this->canLearn($course, $participation),
            'instructor'    => $instructor,
            'lives'         => $lives,
            'missing'       => ($pid = self::personnelId()) !== null ? Qualifications::missing($pid, $course->type->prerequisiteKeys()) : [],
            'linked'        => self::personnelId() !== null,
            'exams'         => self::examStates($course, $participation),
            'completion'    => $participation !== null && in_array($participation->status, ['passed', 'failed'], true) ? (new CompletionTable())->find($participation->id) : null,
        ]);
    }

    /** @return list<ExamState> */
    private static function examStates(Course $course, ?Participation $participation): array
    {
        if ($participation === null || !in_array($participation->status, Participation::LEARNING, true)) {
            return [];
        }
        $board = ExamBoard::for($course, [$participation]);

        return array_map(static fn (Exam $exam): ExamState => $board->state($participation, $exam), $board->parts);
    }

    /** POST /training/courses/{id}/enroll */
    public function enroll(Request $request, string $id): Response
    {
        $course      = self::findCourse($id);
        $personnelId = self::personnelId();
        if ($course === null) {
            return self::notFound();
        }
        if ($personnelId === null) {
            return self::back('training/courses/' . $course->id, 'Dein Konto ist mit keinem Mitarbeiter verknüpft.');
        }
        try {
            $participation = $this->enrollment->enrollSelf($course, $personnelId, self::userId());
        } catch (DomainException $e) {
            return self::back('training/courses/' . $course->id, $e->getMessage());
        }

        return self::back('training/courses/' . $course->id, null, match ($participation->status) {
            'waitlisted' => 'Der Lehrgang ist voll. Du stehst auf der Warteliste.',
            'enrolled'   => 'Angemeldet. Die Leitung bestätigt deine Anmeldung.',
            default      => 'Du bist für den Lehrgang zugelassen.',
        });
    }

    /** POST /training/courses/{id}/withdraw */
    public function withdraw(Request $request, string $id): Response
    {
        $course        = self::findCourse($id);
        $participation = $course !== null ? $this->participation($course) : null;
        if ($participation === null) {
            return self::notFound();
        }
        try {
            $this->enrollment->withdraw($participation, self::editor());
        } catch (DomainException $e) {
            return self::back('training/courses/' . $course->id, $e->getMessage());
        }

        return self::back('training', null, 'Deine Anmeldung zu ' . $course->title . ' ist zurückgezogen.');
    }

    /** GET /training/courses/{course}/units/{unit} */
    public function unit(Request $request, string $course, string $unit): Response
    {
        $context = $this->learnContext($course, $unit);
        if ($context instanceof Response) {
            return $context;
        }
        [$courseModel, $participation, $curriculum, $unitModel] = $context;

        $state    = $participation !== null ? ($curriculum->states([$participation->id])[$participation->id] ?? []) : [];
        $progress = $curriculum->progress($state);
        if ($participation !== null && !$progress->isUnlocked(self::progressUnit($unitModel))) {
            return self::back('training/courses/' . $courseModel->id, 'Diese Einheit ist erst nach dem vorigen Modul offen.');
        }

        // Ein Bild ist mit dem Ansehen gelesen.
        if ($participation !== null && $unitModel->file?->isImage()) {
            Curriculum::markDone($participation, $unitModel);
            $state[$unitModel->id] = ['state' => 'done', 'manual' => false, 'by' => null, 'pages' => null, 'attendance' => null];
        }

        // Folien einer Präsentation: Ausbilder immer, Teilnehmer nach der Regel der Einheit. Ein laufender Vortrag bekommt seinen Knopf.
        $slidesOpen = $unitModel->type() === UnitKind::Presentation && (TrainingPolicy::instruct($courseModel) || Presentations::slidesOpen($unitModel, $courseModel, new \DateTimeImmutable()));
        $talk       = $unitModel->type() === UnitKind::Presentation ? Presentations::liveSessionFor($unitModel, $courseModel, new \DateTimeImmutable()) : null;

        $units = $curriculum->units();
        $index = array_search($unitModel, $units, true);
        $html  = null;
        if ($unitModel->type() === UnitKind::Text) {
            $doc  = is_array($unitModel->content['doc'] ?? null) ? $unitModel->content['doc'] : ['type' => 'doc', 'content' => []];
            $html = (new Renderer())->render($doc, allowLinks: true)->html;
        }

        return $this->page('training/unit', [
            'course'        => $courseModel,
            'participation' => $participation,
            'curriculum'    => $curriculum,
            'unit'          => $unitModel,
            'state'         => $state,
            'progress'      => $progress,
            'unitState'     => $state[$unitModel->id] ?? null,
            'nextUnit'      => is_int($index) ? ($units[$index + 1] ?? null) : null,
            'session'       => $curriculum->session($unitModel),
            'slidesOpen'    => $slidesOpen,
            'talk'          => $talk,
            'html'          => $html,
        ]);
    }

    /** POST /training/courses/{course}/units/{unit}/done */
    public function done(Request $request, string $course, string $unit): Response
    {
        $context = $this->learnContext($course, $unit);
        if ($context instanceof Response) {
            return $context;
        }
        [$courseModel, $participation, , $unitModel] = $context;

        $markable = in_array($unitModel->type(), [UnitKind::Text, UnitKind::File], true);
        if ($participation !== null && $markable) {
            Curriculum::markDone($participation, $unitModel);
        }
        if ($request->header('Accept') === 'application/json') {
            return self::json(['done' => $participation !== null && $markable]);
        }

        return self::back('training/courses/' . $courseModel->id . '/units/' . $unitModel->id);
    }

    /**
     * POST /training/courses/{course}/units/{unit}/open: Link oder
     * Wissensartikel öffnen. Das Öffnen zählt als erledigt.
     */
    public function open(Request $request, string $course, string $unit): Response
    {
        $context = $this->learnContext($course, $unit);
        if ($context instanceof Response) {
            return $context;
        }
        [, $participation, , $unitModel] = $context;

        $target = match ($unitModel->type()) {
            UnitKind::Link      => self::safeUrl($unitModel->url),
            UnitKind::KnowledgeArticle => $unitModel->kb_entry_id !== null ? self::base() . 'lexicon/view?id=' . $unitModel->kb_entry_id : null,
            default             => null,
        };
        if ($target === null) {
            return self::notFound();
        }
        if ($participation !== null) {
            Curriculum::markDone($participation, $unitModel);
        }

        return Response::redirect($target);
    }

    /** GET /training/courses/{course}/files/{file} */
    public function file(Request $request, string $course, string $file): Response
    {
        $courseModel = self::findCourse($course);
        if ($courseModel === null) {
            return self::notFound();
        }
        $participation = $this->participation($courseModel);
        if (!$this->canLearn($courseModel, $participation) && !TrainingPolicy::instruct($courseModel)) {
            return self::notFound();
        }
        $curriculum = Curriculum::for($courseModel);
        $fileModel  = TrainingFile::query()->find((int) $file);
        if ($fileModel === null || !$curriculum->usesFile($fileModel->id)) {
            return self::notFound();
        }

        $instructor = TrainingPolicy::instruct($courseModel);
        // Folien einer Präsentation folgen deren Regel; eine Datei, die auch anderswo im Lehrplan steht, bleibt dort offen.
        if (!$instructor && !$this->slidesVisible($curriculum, $courseModel, $fileModel)) {
            return self::notFound();
        }

        $download = $request->queryString('download') === '1';
        if ($download && !$instructor && !$this->downloadAllowed($curriculum, $fileModel)) {
            return self::notFound();
        }

        // Office-Dateien zeigt der Browser nicht an, der Download ist das Lesen.
        if ($download && $participation !== null && $this->canLearn($courseModel, $participation) && !$fileModel->viewsAsPdf() && !$fileModel->isImage()) {
            foreach ($curriculum->units() as $unit) {
                if ($unit->file_id === $fileModel->id && $unit->type() !== UnitKind::Presentation) {
                    Curriculum::markDone($participation, $unit);
                }
            }
        }

        return self::deliver($fileModel, $download);
    }

    private function participation(Course $course): ?Participation
    {
        return self::ownParticipation($course);
    }

    private function canLearn(Course $course, ?Participation $participation): bool
    {
        return $participation !== null && $participation->canLearn() && $course->state()->materialsOpen();
    }

    /**
     * Lehrgang, eigene Teilnahme (null bei Ausbildern), Lehrplan, Einheit.
     *
     * @return array{0: Course, 1: ?Participation, 2: Curriculum, 3: Unit}|Response
     */
    private function learnContext(string $course, string $unit): array|Response
    {
        $courseModel = self::findCourse($course);
        if ($courseModel === null) {
            return self::notFound();
        }
        $participation = $this->participation($courseModel);
        if (!$this->canLearn($courseModel, $participation)) {
            if (!TrainingPolicy::instruct($courseModel)) {
                return self::notFound();
            }
            $participation = null;
        }
        $curriculum = Curriculum::for($courseModel);
        $unitModel  = $curriculum->unit((int) $unit);
        if ($unitModel === null) {
            return self::notFound();
        }

        return [$courseModel, $participation, $curriculum, $unitModel];
    }

    private function slidesVisible(Curriculum $curriculum, Course $course, TrainingFile $file): bool
    {
        $now = new \DateTimeImmutable();
        foreach ($curriculum->units() as $unit) {
            // Folien nur als PDF: unumgewandelt ginge sonst das Original als Anhang hinaus.
            if ($unit->file_id === $file->id && ($unit->type() !== UnitKind::Presentation || ($file->viewsAsPdf() && Presentations::slidesOpen($unit, $course, $now)))) {
                return true;
            }
        }

        return false;
    }

    private function downloadAllowed(Curriculum $curriculum, TrainingFile $file): bool
    {
        foreach ($curriculum->units() as $unit) {
            if ($unit->file_id === $file->id && $unit->type() !== UnitKind::Presentation && ($unit->allow_download || (!$file->isPdf() && !$file->isImage()))) {
                return true;
            }
        }

        return false;
    }

    private static function progressUnit(Unit $unit): ProgressUnit
    {
        return new ProgressUnit($unit->id, $unit->module_id, $unit->required);
    }

    public static function safeUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }
}
