<?php

declare(strict_types=1);

namespace Plugin\Training;

use App\Personnel\PersonalLogManager;
use DateTimeImmutable;
use DomainException;
use EmergencyForge\Training\Exam\AttemptRules;
use EmergencyForge\Training\Exam\ExamDrawer;
use EmergencyForge\Training\Exam\ExamResult;
use EmergencyForge\Training\Exam\GradeScale;
use EmergencyForge\Training\Exam\NotEnoughQuestions;
use EmergencyForge\Training\Exam\PracticalSheet;
use EmergencyForge\Training\ParticipationStatus;
use EmergencyForge\Training\Question\GradedAnswer;
use EmergencyForge\Training\Question\Grader;
use EmergencyForge\Training\Question\NumberSpec;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Carbon;
use Plugin\Training\Models\Attempt;
use Plugin\Training\Models\AttemptAnswer;
use Plugin\Training\Models\Course;
use Plugin\Training\Models\Exam;
use Plugin\Training\Models\ExamRelease;
use Plugin\Training\Models\ExtraAttempt;
use Plugin\Training\Models\Participation;

/**
 * Was mit Versuchen passiert: ziehen, speichern, abgeben, bewerten,
 * gegenzeichnen, und was das Ergebnis für die Teilnahme heißt.
 *
 * Die Frist prüft nur der Server. Wer nach Ablauf plus Kulanz noch
 * speichert, ändert nichts mehr; der Versuch gilt mit dem bis dahin
 * Gespeicherten als abgegeben.
 */
final class Exams
{
    public function __construct(
        private readonly Grader $grader = new Grader(),
        private readonly ExamDrawer $drawer = new ExamDrawer(),
    ) {
    }

    public function start(Course $course, Participation $participation, Exam $exam): Attempt
    {
        return Capsule::connection()->transaction(function () use ($course, $participation, $exam): Attempt {
            // Zwei Klicks auf „Prüfung beginnen“ dürfen nicht zwei Versuche ziehen.
            Participation::query()->whereKey($participation->id)->lockForUpdate()->first();
            $state = ExamBoard::for($course, [$participation])->state($participation, $exam);
            if (!$exam->isTheory() || !$state->canStart()) {
                throw new DomainException($state->blocker() ?? 'Diese Prüfung lässt sich nicht starten.');
            }
            $blueprint = $exam->examBlueprint();
            if ($blueprint === null) {
                throw new DomainException('Die Prüfung hat noch keine Fragen.');
            }
            $seed = random_int(1, PHP_INT_MAX);
            try {
                $drawn = $this->drawer->draw($blueprint, new Pool($exam->course_type_id), $seed);
            } catch (NotEnoughQuestions $e) {
                throw new DomainException($e->getMessage() . ' Bitte der Leitung Bescheid geben.');
            }

            $now      = Carbon::now();
            $deadline = $exam->examSettings()->attempts->deadline($now->toDateTimeImmutable());
            $attempt  = new Attempt();
            $attempt->participation_id = $participation->id;
            $attempt->exam_id          = $exam->id;
            $attempt->number           = count($state->attempts) + 1;
            $attempt->seed             = $seed;
            $attempt->drawn            = $drawn->toArray();
            $attempt->started_at       = $now;
            $attempt->deadline_at      = $deadline !== null ? Carbon::instance($deadline) : null;
            $attempt->status           = Attempt::OPEN;
            $attempt->save();

            foreach ($drawn->questions as $position => $question) {
                $row = new AttemptAnswer();
                $row->attempt_id       = $attempt->id;
                $row->position         = $position;
                $row->question_id      = $question->question->id;
                $row->question_version = $question->question->version;
                $row->save();
            }
            $this->enterExam($participation);

            return $attempt;
        });
    }

    public static function isOverdue(Attempt $attempt, ?DateTimeImmutable $now = null): bool
    {
        return (new AttemptRules())->isOverdue($attempt->deadline_at?->toDateTimeImmutable(), $now ?? new DateTimeImmutable());
    }

    /**
     * Speichert den ganzen Antwortstand. Fehlt eine Position, ist sie leer;
     * so lässt sich auch eine Mehrfachauswahl wieder ganz abwählen.
     *
     * @param array<mixed, mixed> $raw Position => Antwort aus dem Formular
     * @return bool false, wenn der Versuch nicht mehr offen ist
     */
    public function save(Attempt $attempt, array $raw): bool
    {
        if (!$attempt->isOpen()) {
            return false;
        }
        if (self::isOverdue($attempt)) {
            $this->submit($attempt, null);

            return false;
        }
        $drawn = $attempt->drawnExam();
        foreach ($attempt->answers()->get() as $row) {
            $question = $drawn->questions[$row->position] ?? null;
            if ($question === null) {
                continue;
            }
            $answer = Answers::fromForm($question, $raw[$row->position] ?? null);
            if ($row->answer !== $answer) {
                $row->answer = $answer;
                $row->save();
            }
        }

        return true;
    }

    /** @param array<mixed, mixed>|null $raw letzter Stand aus dem Formular, null beim Ablauf */
    public function submit(Attempt $attempt, ?array $raw): void
    {
        Capsule::connection()->transaction(function () use ($attempt, $raw): void {
            $locked = Attempt::query()->whereKey($attempt->id)->lockForUpdate()->first();
            if ($locked === null || !$locked->isOpen()) {
                return;
            }
            $overdue = self::isOverdue($locked);
            if ($raw !== null && !$overdue) {
                $this->save($locked, $raw);
            }
            $this->finish($locked, $overdue && $locked->deadline_at !== null ? $locked->deadline_at->copy() : Carbon::now());
            $attempt->setRawAttributes($locked->getAttributes(), true);
        });
        if ($attempt->status === Attempt::DONE) {
            $this->outcome($attempt);
        } elseif ($attempt->status === Attempt::REVIEW) {
            $this->askExaminers($attempt, ' wartet auf Bewertung');
        }
    }

    /** Bewertet automatisch, was geht, und setzt den Status. */
    private function finish(Attempt $attempt, Carbon $submittedAt): void
    {
        $drawn  = $attempt->drawnExam();
        $review = false;
        foreach ($attempt->answers()->get() as $row) {
            $question = $drawn->questions[$row->position] ?? null;
            if ($question === null) {
                continue;
            }
            $graded = $this->grader->grade($question->question, $row->answer);
            $row->auto_points  = $graded->needsReview ? null : $graded->points;
            $row->needs_review = $graded->needsReview;
            $row->save();
            $review = $review || $graded->needsReview;
        }
        $attempt->submitted_at = $submittedAt;
        $attempt->status       = $review ? Attempt::REVIEW : Attempt::DONE;
        if (!$review) {
            $this->score($attempt);
        }
        $attempt->save();
    }

    /** Punkte, Prozent und Bestehen aus den Antworten. */
    public function result(Attempt $attempt): ExamResult
    {
        $exam     = $attempt->exam;
        $settings = $exam->examSettings();
        if (!$exam->isTheory()) {
            return $this->sheet($attempt)->evaluate(self::ratings($attempt), $settings->passPercent);
        }
        $drawn   = $attempt->drawnExam();
        $answers = [];
        foreach ($attempt->answers()->get() as $row) {
            $max       = $drawn->questions[$row->position]->question->points ?? 0.0;
            $pending   = $row->needs_review && $row->manual_points === null;
            $answers[] = new GradedAnswer($row->question_id, $pending ? 0.0 : $row->points(), $max, $pending);
        }

        return ExamResult::from($answers, $settings->passPercent, $settings->knockouts, $settings->gradeScale);
    }

    private function score(Attempt $attempt): void
    {
        $result = $this->result($attempt);
        $attempt->points     = round($result->points, 2);
        $attempt->max_points = round($result->maxPoints, 2);
        $attempt->percent    = round($result->percent(), 2);
        $attempt->passed     = $result->passed();
    }

    /**
     * Bewertung von Hand. Punkte für Fragen, die ein Prüfer bewerten muss,
     * und Änderungen an automatischen Punkten, dann aber mit Begründung.
     *
     * @param array<mixed, mixed> $points   Position => Punkte als Text, leer = offen
     * @param array<mixed, mixed> $comments Position => Kommentar
     */
    public function review(Attempt $attempt, array $points, array $comments, bool $finish, int $userId, bool $mayOverride): void
    {
        if ($attempt->status !== Attempt::REVIEW) {
            throw new DomainException('Dieser Versuch wartet nicht auf eine Bewertung.');
        }
        $drawn = $attempt->drawnExam();
        $rows  = $attempt->answers()->get()->all();

        // Fehlerhafte Zeilen bleiben stehen, der Rest wird gespeichert: sonst
        // wären alle eingetippten Punkte nach einem Tippfehler weg.
        $errors = [];
        Capsule::connection()->transaction(function () use ($attempt, $drawn, $rows, $points, $comments, $finish, $userId, $mayOverride, &$errors): void {
            foreach ($rows as $row) {
                $max     = $drawn->questions[$row->position]->question->points ?? 0.0;
                $input   = trim((string) ($points[$row->position] ?? ''));
                $comment = trim((string) ($comments[$row->position] ?? ''));
                if (!$row->needs_review && !$mayOverride) {
                    continue;
                }
                $value = $input === '' ? null : NumberSpec::parse($input);
                if ($input !== '' && ($value === null || $value < 0 || $value > $max + 1e-9)) {
                    $errors[] = sprintf('Frage %d: Punkte zwischen 0 und %s.', $row->position + 1, self::number($max));
                    continue;
                }
                if (!$row->needs_review) {
                    $changes = $value !== null && abs($value - (float) $row->auto_points) > 1e-9;
                    if ($changes && $comment === '') {
                        $errors[] = sprintf('Frage %d: Wer automatische Punkte ändert, schreibt eine Begründung dazu.', $row->position + 1);
                        continue;
                    }
                    $value = $changes ? $value : null;
                }
                if ($row->manual_points !== $value || (string) $row->comment !== $comment) {
                    $row->manual_points = $value;
                    $row->comment       = $comment !== '' ? mb_substr($comment, 0, 2000) : null;
                    $row->graded_by     = $userId;
                    $row->save();
                }
            }

            if ($finish && $errors === []) {
                $open = count(array_filter($rows, static fn (AttemptAnswer $r): bool => $r->needs_review && $r->manual_points === null));
                if ($open > 0) {
                    $errors[] = $open === 1 ? 'Eine Antwort ist noch nicht bewertet.' : $open . ' Antworten sind noch nicht bewertet.';

                    return;
                }
                $attempt->graded_by = $userId;
                $attempt->status    = Attempt::DONE;
                $this->score($attempt);
                $attempt->save();
            }
        });

        if ($errors !== []) {
            throw new DomainException(implode(' ', $errors));
        }
        if ($attempt->status === Attempt::DONE) {
            $this->outcome($attempt);
        }
    }

    /** Praxisbogen anlegen oder den offenen zurückgeben. */
    public function openSheet(Course $course, Participation $participation, Exam $exam, ?string $case): Attempt
    {
        return Capsule::connection()->transaction(function () use ($course, $participation, $exam, $case): Attempt {
            Participation::query()->whereKey($participation->id)->lockForUpdate()->first();
            $state = ExamBoard::for($course, [$participation])->state($participation, $exam);
            if (($open = $state->open()) !== null) {
                return $open;
            }
            if ($exam->isTheory() || !$state->canStart()) {
                throw new DomainException($state->blocker() ?? 'Dieser Bogen lässt sich nicht öffnen.');
            }
            if ($exam->practicalSheet() === null) {
                throw new DomainException('Der Bewertungsbogen hat noch keine Kriterien.');
            }
            $cases     = $exam->cases();
            $seed      = random_int(1, PHP_INT_MAX);
            $drawnCase = false;
            if ($case === null || !in_array($case, $cases, true)) {
                $case      = $cases === [] ? null : $cases[$seed % count($cases)];
                $drawnCase = $case !== null;
            }

            $attempt = new Attempt();
            $attempt->participation_id = $participation->id;
            $attempt->exam_id          = $exam->id;
            $attempt->number           = count($state->attempts) + 1;
            $attempt->seed             = $seed;
            $attempt->drawn            = ['sheet' => $exam->sheet, 'case' => $case, 'case_drawn' => $drawnCase, 'ratings' => (object) [], 'comments' => (object) [], 'comment' => ''];
            $attempt->started_at       = Carbon::now();
            $attempt->status           = Attempt::OPEN;
            $attempt->save();
            $this->enterExam($participation);

            return $attempt;
        });
    }

    public function sheet(Attempt $attempt): PracticalSheet
    {
        return PracticalSheet::fromArray((array) ($attempt->drawn['sheet'] ?? []));
    }

    /** @return array<string, float|bool|null> */
    public static function ratings(Attempt $attempt): array
    {
        $ratings = $attempt->drawn['ratings'] ?? [];

        return is_array($ratings) ? $ratings : [];
    }

    /**
     * Praxisbogen zwischenspeichern oder abschließen.
     *
     * @param array<mixed, mixed> $raw      Kennung => Punkte, "yes" oder "no"
     * @param array<mixed, mixed> $comments Gruppenindex => Kommentar
     */
    public function rate(Attempt $attempt, array $raw, array $comments, string $comment, bool $finish, int $userId): void
    {
        if (!$attempt->isOpen() || $attempt->exam->isTheory()) {
            throw new DomainException('Dieser Bogen ist schon abgeschlossen.');
        }
        $sheet   = $this->sheet($attempt);
        $ratings = [];
        foreach ($sheet->groups as $group) {
            foreach ($group['criteria'] as $criterion) {
                $value = trim((string) ($raw[$criterion['key']] ?? ''));
                $ratings[$criterion['key']] = match (true) {
                    $value === ''                 => null,
                    $criterion['points'] === null => $value === 'yes',
                    default                       => max(0.0, min($criterion['points'], NumberSpec::parse($value) ?? 0.0)),
                };
            }
        }
        $drawn             = $attempt->drawn ?? [];
        $drawn['ratings']  = $ratings;
        $drawn['comments'] = array_map(static fn ($c): string => mb_substr(trim((string) $c), 0, 2000), array_slice($comments, 0, count($sheet->groups), true));
        $drawn['comment']  = mb_substr(trim($comment), 0, 4000);
        $attempt->drawn    = $drawn;

        if ($finish) {
            $result = $sheet->evaluate($ratings, $attempt->exam->examSettings()->passPercent);
            if (!$result->isFinal()) {
                $attempt->save();
                throw new DomainException($result->pending === 1 ? 'Ein Kriterium ist noch offen.' : $result->pending . ' Kriterien sind noch offen.');
            }
            $attempt->submitted_at = Carbon::now();
            $attempt->graded_by    = $userId;
            $attempt->status       = $attempt->exam->examSettings()->cosign ? Attempt::COSIGN : Attempt::DONE;
            $this->score($attempt);
        }
        $attempt->save();

        if ($attempt->status === Attempt::DONE) {
            $this->outcome($attempt);
        } elseif ($attempt->status === Attempt::COSIGN) {
            $this->askExaminers($attempt, ' wartet auf Gegenzeichnung', $userId);
        }
    }

    public function cosign(Attempt $attempt, int $userId): void
    {
        if ($attempt->status !== Attempt::COSIGN) {
            throw new DomainException('Dieser Bogen wartet nicht auf eine Gegenzeichnung.');
        }
        if ($attempt->graded_by === $userId) {
            throw new DomainException('Gegenzeichnen muss ein zweiter Prüfer.');
        }
        $attempt->cosigned_by = $userId;
        $attempt->status      = Attempt::DONE;
        $attempt->save();
        $this->outcome($attempt);
    }

    public function release(Course $course, Exam $exam, ?Participation $participation, int $userId): void
    {
        $exists = ExamRelease::query()->where('exam_id', $exam->id)->where('course_id', $course->id)
            ->where('participation_id', $participation?->id)->exists();
        if (!$exists) {
            ExamRelease::query()->create([
                'exam_id' => $exam->id, 'course_id' => $course->id, 'participation_id' => $participation?->id,
                'released_by' => $userId ?: null, 'released_at' => Carbon::now(),
            ]);
        }
        $ids = $participation !== null ? [$participation->personnel_id] : Participation::query()->where('course_id', $course->id)
            ->whereIn('status', ['in_training', 'in_exam'])->pluck('personnel_id')->all();
        Notify::personnel(array_values(array_filter(array_map(static fn ($id): ?int => $id === null ? null : (int) $id, $ids))), $exam->title . ' ist freigegeben', 'training/courses/' . $course->id . '/exams/' . $exam->id);
    }

    public function revoke(Course $course, Exam $exam, ?Participation $participation): void
    {
        ExamRelease::query()->where('exam_id', $exam->id)->where('course_id', $course->id)->where('participation_id', $participation?->id)->delete();
    }

    /** Zusatzversuch mit Begründung; eine nicht bestandene Teilnahme geht zurück in die Prüfung. */
    public function grantExtra(Participation $participation, Exam $exam, string $reason, int $userId, string $editor): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Bitte begründen, warum es einen Zusatzversuch gibt.');
        }
        if (!in_array($participation->status, ['in_training', 'in_exam', 'failed'], true)) {
            throw new DomainException('Diese Teilnahme ist nicht mehr in der Prüfung.');
        }
        ExtraAttempt::query()->create(['participation_id' => $participation->id, 'exam_id' => $exam->id, 'reason' => mb_substr($reason, 0, 500), 'granted_by' => $userId ?: null]);
        if ($participation->state() === ParticipationStatus::Failed) {
            $participation->status      = ParticipationStatus::InExam->value;
            $participation->finished_at = null;
            $participation->save();
        }
        $this->log($participation, sprintf('%s: Zusatzversuch für <strong>%s</strong>. Begründung: %s', htmlspecialchars($participation->course->title), htmlspecialchars($exam->title), htmlspecialchars($reason)), $editor, ['change_type' => 'training_extra_attempt', 'exam_id' => $exam->id]);
        Notify::personnel(array_filter([$participation->personnel_id]), 'Zusatzversuch: ' . $exam->title, 'training/courses/' . $participation->course_id . '/exams/' . $exam->id);
    }

    /** Liegengebliebene Versuche nach Ablauf abgeben. */
    public function closeOverdue(?DateTimeImmutable $now = null): int
    {
        $now   ??= new DateTimeImmutable();
        $limit   = Carbon::instance($now)->subSeconds(AttemptRules::GRACE_SECONDS);
        $count   = 0;
        /** @var list<Attempt> $overdue */
        $overdue = Attempt::query()->with('exam')->where('status', Attempt::OPEN)->whereNotNull('deadline_at')->where('deadline_at', '<', $limit)->get()->all();
        foreach ($overdue as $attempt) {
            $this->submit($attempt, null);
            $count++;
        }

        return $count;
    }

    private function enterExam(Participation $participation): void
    {
        if ($participation->state() === ParticipationStatus::InTraining) {
            $participation->status = ParticipationStatus::InExam->value;
            $participation->save();
        }
    }

    /**
     * Was ein fertig bewerteter Versuch für die Teilnahme heißt: alle
     * Pflicht-Teile bestanden, oder dieser Teil endgültig nicht.
     */
    private function outcome(Attempt $attempt): void
    {
        $participation = Participation::query()->with('course.type')->find($attempt->participation_id);
        if ($participation === null) {
            return;
        }
        $course = $participation->course;
        $exam   = $attempt->exam;
        $board  = ExamBoard::for($course, [$participation]);

        $this->log($participation, sprintf('%s: <strong>%s</strong>, Versuch %d %s, %s.', htmlspecialchars($course->title), htmlspecialchars($exam->title), $attempt->number, $attempt->passed ? 'bestanden' : 'nicht bestanden', Attempt::percentLabel((float) $attempt->percent)), 'System', ['change_type' => 'training_attempt', 'attempt_id' => $attempt->id]);

        if (in_array($participation->status, ['in_training', 'in_exam'], true)) {
            if ($board->allRequiredPassed($participation)) {
                $results = [];
                $grade   = null;
                foreach ($board->parts as $part) {
                    $result = $part->required ? $board->state($participation, $part)->result() : null;
                    if ($result === null) {
                        continue;
                    }
                    $results[] = (float) $result->percent;
                    $scale     = $part->examSettings()->gradeScale;
                    if ($grade === null && $scale instanceof GradeScale) {
                        $grade = GradeScale::label($scale->grade((float) $result->percent));
                    }
                }
                $participation->status         = ParticipationStatus::Passed->value;
                $participation->finished_at    = Carbon::now();
                $participation->result_percent = $results !== [] ? round(array_sum($results) / count($results), 2) : null;
                $participation->grade          = $grade;
                $participation->save();
            } elseif ($attempt->passed === false && $exam->required && $board->state($participation, $exam)->remaining === 0) {
                $participation->status      = ParticipationStatus::Failed->value;
                $participation->finished_at = Carbon::now();
                $participation->save();
            }
        }

        Notify::personnel(array_filter([$participation->personnel_id]), 'Ergebnis: ' . $exam->title, 'training/attempts/' . $attempt->id, $attempt->passed ? 'Bestanden.' : 'Nicht bestanden.');
    }

    private function askExaminers(Attempt $attempt, string $what, ?int $except = null): void
    {
        $participation = Participation::query()->with(['course', 'personnel'])->find($attempt->participation_id);
        if ($participation === null) {
            return;
        }
        $users = array_values(array_diff(Courses::examiners($participation->course), [$except ?? 0]));
        Notify::users($users, $attempt->exam->title . ' von ' . $participation->name() . $what, 'training/attempts/' . $attempt->id . ($attempt->exam->isTheory() ? '/review' : '/sheet'), $participation->course->title);
    }

    /** @param array<string, mixed> $meta */
    private function log(Participation $participation, string $html, string $editor, array $meta): void
    {
        if ($participation->personnel_id !== null) {
            (new PersonalLogManager())->addEntry($participation->personnel_id, PersonalLogManager::TYPE_TRAINING, $html, $editor, $meta + ['course_id' => $participation->course_id]);
        }
    }

    public static function number(float $value): string
    {
        return str_replace('.', ',', (string) round($value, 2));
    }
}
