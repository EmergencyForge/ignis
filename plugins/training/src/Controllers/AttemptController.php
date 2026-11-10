<?php

declare(strict_types=1);

namespace Plugin\Training\Controllers;

use DomainException;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use Plugin\Training\ExamBoard;
use Plugin\Training\Exams;
use Plugin\Training\Models\Attempt;
use Plugin\Training\Models\Exam;
use Plugin\Training\Models\Participation;
use Plugin\Training\Models\TrainingFile;
use Plugin\Training\Policies\TrainingPolicy;

/**
 * Prüfung aus Sicht der Teilnehmer (Start, Bearbeiten, Ergebnis) und der
 * Prüfer (Bewerten, Praxisbogen, Gegenzeichnen).
 */
final class AttemptController extends BaseController
{
    public function __construct(private readonly Exams $exams = new Exams())
    {
    }

    /** GET /training/courses/{course}/exams/{exam}: Startseite. */
    public function start(Request $request, string $course, string $exam): Response
    {
        [$model, $participation, $part] = $this->own($course, $exam);
        if ($participation === null || $part === null) {
            return self::notFound();
        }
        $state = ExamBoard::for($model, [$participation])->state($participation, $part);
        if ($state->open() !== null && $part->isTheory()) {
            return self::back('training/attempts/' . $state->open()->id);
        }

        return $this->page('training/exam-start', ['course' => $model, 'participation' => $participation, 'exam' => $part, 'state' => $state]);
    }

    /** POST /training/courses/{course}/exams/{exam}/start */
    public function begin(Request $request, string $course, string $exam): Response
    {
        [$model, $participation, $part] = $this->own($course, $exam);
        if ($participation === null || $part === null) {
            return self::notFound();
        }
        try {
            $attempt = $this->exams->start($model, $participation, $part);
        } catch (DomainException $e) {
            return self::back('training/courses/' . $model->id . '/exams/' . $part->id, $e->getMessage());
        }

        return self::back('training/attempts/' . $attempt->id);
    }

    /** GET /training/attempts/{id}: laufende Prüfung oder Ergebnis. */
    public function show(Request $request, string $id): Response
    {
        $attempt = self::attempt($id);
        if ($attempt === null || !TrainingPolicy::viewAttempt($attempt)) {
            return self::notFound();
        }
        $own = $attempt->participation->personnel_id === self::personnelId();
        if (!$own) {
            return self::back('training/attempts/' . $attempt->id . ($attempt->exam->isTheory() ? '/review' : '/sheet'));
        }
        if ($attempt->isOpen() && $attempt->exam->isTheory() && Exams::isOverdue($attempt)) {
            $this->exams->submit($attempt, null);
        }
        if ($attempt->isOpen() && $attempt->exam->isTheory()) {
            return $this->page('training/exam', ['attempt' => $attempt, 'course' => $attempt->participation->course, 'exam' => $attempt->exam]);
        }

        return $this->page('training/exam-result', [
            'attempt' => $attempt,
            'course'  => $attempt->participation->course,
            'exam'    => $attempt->exam,
            'result'  => $attempt->status === Attempt::DONE ? $this->exams->result($attempt) : null,
        ]);
    }

    /** POST /training/attempts/{id}/submit */
    public function submit(Request $request, string $id): Response
    {
        $attempt = self::attempt($id);
        if ($attempt === null || $attempt->participation->personnel_id !== self::personnelId() || !$attempt->exam->isTheory()) {
            return self::notFound();
        }
        $this->exams->submit($attempt, is_array($request->post['answers'] ?? null) ? $request->post['answers'] : []);

        return self::back('training/attempts/' . $attempt->id);
    }

    /** POST /api/training/attempts/{id}/answers: Zwischenstand bei jedem Schritt. */
    public function answers(Request $request, string $id): Response
    {
        $attempt = self::attempt($id);
        if ($attempt === null || $attempt->participation->personnel_id !== self::personnelId()) {
            return self::json(['error' => 'Nicht gefunden.'], 404);
        }
        $body  = $request->json() ?? [];
        $saved = $this->exams->save($attempt, is_array($body['answers'] ?? null) ? $body['answers'] : []);

        return self::json(['saved' => $saved]);
    }

    /** POST /api/training/attempts/{id}/focus: nur gezählt, nichts gesperrt. */
    public function focus(Request $request, string $id): Response
    {
        $attempt = self::attempt($id);
        if ($attempt === null || $attempt->participation->personnel_id !== self::personnelId()) {
            return self::json(['error' => 'Nicht gefunden.'], 404);
        }
        if ($attempt->isOpen() && $attempt->exam->setting('count_focus', false)) {
            Attempt::query()->whereKey($attempt->id)->increment('focus_lost');
        }

        return self::json(['ok' => true]);
    }

    /** GET /training/attempts/{id}/images/{position}: Bild einer gezogenen Frage. */
    public function image(Request $request, string $id, string $position): Response
    {
        $attempt = self::attempt($id);
        if ($attempt === null || !TrainingPolicy::viewAttempt($attempt)) {
            return self::notFound();
        }
        $image = $attempt->drawnExam()->questions[(int) $position]->question->image ?? null;
        $file  = $image !== null ? TrainingFile::query()->find((int) $image) : null;

        return $file === null ? self::notFound() : self::deliver($file, false);
    }

    /** GET /training/attempts/{id}/review */
    public function review(Request $request, string $id): Response
    {
        $attempt = self::attempt($id);
        if ($attempt === null || !$attempt->exam->isTheory() || !TrainingPolicy::viewAttempt($attempt) || $attempt->participation->personnel_id === self::personnelId()) {
            return self::notFound();
        }

        return $this->page('training/review', [
            'attempt'  => $attempt,
            'course'   => $attempt->participation->course,
            'exam'     => $attempt->exam,
            'result'   => $attempt->isOpen() ? null : $this->exams->result($attempt),
            'mayGrade' => $attempt->status === Attempt::REVIEW && TrainingPolicy::grade($attempt),
        ]);
    }

    /** POST /training/attempts/{id}/review */
    public function grade(Request $request, string $id): Response
    {
        $attempt = self::attempt($id);
        if ($attempt === null || !$attempt->exam->isTheory() || !TrainingPolicy::grade($attempt)) {
            return self::notFound();
        }
        $finish = ($request->post['finish'] ?? '') === '1';
        try {
            $this->exams->review(
                $attempt,
                is_array($request->post['points'] ?? null) ? $request->post['points'] : [],
                is_array($request->post['comments'] ?? null) ? $request->post['comments'] : [],
                $finish,
                self::userId(),
                true,
            );
        } catch (DomainException $e) {
            return self::back('training/attempts/' . $attempt->id . '/review', $e->getMessage());
        }

        return $finish
            ? self::back('training/manage/' . $attempt->participation->course_id . '#bewertungen', null, 'Bewertung abgeschlossen.')
            : self::back('training/attempts/' . $attempt->id . '/review', null, 'Zwischenstand gespeichert.');
    }

    /** GET /training/attempts/{id}/sheet: Praxisbogen. */
    public function sheet(Request $request, string $id): Response
    {
        $attempt = self::attempt($id);
        if ($attempt === null || $attempt->exam->isTheory() || !TrainingPolicy::viewAttempt($attempt) || $attempt->participation->personnel_id === self::personnelId()) {
            return self::notFound();
        }

        return $this->page('training/sheet', [
            'attempt'   => $attempt,
            'course'    => $attempt->participation->course,
            'exam'      => $attempt->exam,
            'sheet'     => $this->exams->sheet($attempt),
            'ratings'   => Exams::ratings($attempt),
            'result'    => $this->exams->result($attempt),
            'mayRate'   => $attempt->isOpen() && TrainingPolicy::grade($attempt),
            'mayCosign' => $attempt->status === Attempt::COSIGN && TrainingPolicy::grade($attempt),
        ]);
    }

    /** POST /training/attempts/{id}/sheet */
    public function rate(Request $request, string $id): Response
    {
        $attempt = self::attempt($id);
        if ($attempt === null || $attempt->exam->isTheory() || !TrainingPolicy::grade($attempt)) {
            return self::notFound();
        }
        $finish = ($request->post['finish'] ?? '') === '1';
        try {
            $this->exams->rate(
                $attempt,
                is_array($request->post['ratings'] ?? null) ? $request->post['ratings'] : [],
                is_array($request->post['comments'] ?? null) ? $request->post['comments'] : [],
                trim((string) ($request->post['comment'] ?? '')),
                $finish,
                self::userId(),
            );
        } catch (DomainException $e) {
            return self::back('training/attempts/' . $attempt->id . '/sheet', $e->getMessage());
        }
        if (!$finish) {
            return self::back('training/attempts/' . $attempt->id . '/sheet', null, 'Zwischenstand gespeichert.');
        }

        return self::back('training/manage/' . $attempt->participation->course_id . '#pruefungen', null, $attempt->status === Attempt::COSIGN ? 'Bewertet. Jetzt zeichnet ein zweiter Prüfer gegen.' : 'Bewertung abgeschlossen.');
    }

    /** POST /training/attempts/{id}/cosign */
    public function cosign(Request $request, string $id): Response
    {
        $attempt = self::attempt($id);
        if ($attempt === null || !TrainingPolicy::grade($attempt)) {
            return self::notFound();
        }
        try {
            $this->exams->cosign($attempt, self::userId());
        } catch (DomainException $e) {
            return self::back('training/attempts/' . $attempt->id . '/sheet', $e->getMessage());
        }

        return self::back('training/manage/' . $attempt->participation->course_id . '#bewertungen', null, 'Gegengezeichnet.');
    }

    /** @return array{0: \Plugin\Training\Models\Course, 1: ?Participation, 2: ?\Plugin\Training\Models\Exam}|array{0: null, 1: null, 2: null} */
    private function own(string $courseId, string $examId): array
    {
        $course      = self::findCourse($courseId);
        $personnelId = self::personnelId();
        if ($course === null || $personnelId === null) {
            return [null, null, null];
        }
        $participation = Participation::query()->where('course_id', $course->id)->where('personnel_id', $personnelId)->first();
        $participation?->setRelation('course', $course);
        $exam = Exam::query()->find((int) $examId);
        if ($exam === null || $exam->course_type_id !== $course->course_type_id) {
            return [$course, null, null];
        }

        return [$course, $participation, $exam];
    }

    private static function attempt(string $id): ?Attempt
    {
        return Attempt::query()->with(['exam', 'participation.course.type', 'participation.personnel'])->find((int) $id);
    }
}
