<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PersonnelLog;
use App\Personnel\PersonalLogManager;
use DomainException;
use EmergencyForge\Http\Response;
use EmergencyForge\Training\Exam\AttemptRules;
use EmergencyForge\Training\Exam\ExamSettings;
use EmergencyForge\Training\Exam\ReviewMode;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Training\Exams;
use Plugin\Training\Exchange;
use Plugin\Training\Models\Attempt;
use Plugin\Training\Models\AttemptAnswer;
use Plugin\Training\Models\Course;
use Plugin\Training\Models\CourseStaff;
use Plugin\Training\Models\CourseType;
use Plugin\Training\Models\Exam;
use Plugin\Training\Models\ExtraAttempt;
use Plugin\Training\Models\Participation;
use Plugin\Training\Models\Question;
use Plugin\Training\Models\Topic;
use Plugin\Training\Pool;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Prüfungen über die echten Routen: Fragenpool, Theorie mit Frist und
 * Bewertung, Praxisbogen mit Gegenzeichnung, Zusatzversuch.
 */
final class TrainingExamTest extends FeatureTestCase
{
    private CourseType $type;
    private Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->type = new CourseType();
        $this->type->name            = 'Rettungssanitäter';
        $this->type->short           = 'RS';
        $this->type->enrollment_mode = 'open';
        $this->type->save();
        $this->topic = new Topic();
        $this->topic->course_type_id = $this->type->id;
        $this->topic->name           = 'Anatomie';
        $this->topic->save();
    }

    /** @param array<string, mixed>|null $spec */
    private function question(string $type, ?array $spec, float $points = 1.0): Question
    {
        $question = new Question();
        $question->course_type_id = $this->type->id;
        Pool::save($question, ['type' => $type, 'body' => 'Frage ' . uniqid(), 'points' => $points, 'spec' => $spec, 'explanation' => null, 'topic_id' => $this->topic->id, 'is_active' => true], null);

        return $question;
    }

    private function choice(): Question
    {
        return $this->question('single_choice', ['options' => ['Herz', 'Lunge', 'Leber'], 'correct' => [1], 'partial' => false]);
    }

    /** @param list<int> $fixed */
    private function theory(array $fixed, int $maxAttempts = 2): Exam
    {
        $exam = new Exam();
        $exam->course_type_id = $this->type->id;
        $exam->kind           = 'theory';
        $exam->title          = 'Theorie';
        $exam->required       = true;
        $exam->blueprint      = ['topics' => [], 'fixed' => $fixed, 'variants' => [], 'shuffle_questions' => false, 'shuffle_options' => false];
        $exam->settings       = (new ExamSettings(50.0, new AttemptRules(30, $maxAttempts, 0), null, ReviewMode::Solutions, [], false, false))->toArray();
        $exam->save();

        return $exam;
    }

    private function practical(): Exam
    {
        $exam = new Exam();
        $exam->course_type_id = $this->type->id;
        $exam->kind           = 'practical';
        $exam->title          = 'Praxis';
        $exam->required       = true;
        $exam->sheet          = [['title' => 'Eigenschutz', 'criteria' => [
            ['key' => 'k1', 'label' => 'Handschuhe', 'points' => null, 'knockout' => true],
            ['key' => 'k2', 'label' => 'Lagerung', 'points' => 4.0, 'knockout' => false],
        ]]];
        $exam->settings       = (new ExamSettings(50.0, new AttemptRules(null, 2, 0), null, ReviewMode::Result, [], false, true))->toArray();
        $exam->save();

        return $exam;
    }

    private function course(?int $lead = null): Course
    {
        $course = new Course();
        $course->course_type_id = $this->type->id;
        $course->title          = 'RS 1/2026';
        $course->number         = 1;
        $course->year           = 2026;
        $course->status         = 'exam_phase';
        $course->lead_user_id   = $lead;
        $course->save();

        return $course;
    }

    /** @return array{user: int, personnel: int} */
    private function account(): array
    {
        $personnel = FixtureFactory::personnel();
        $user      = FixtureFactory::user(['aktenid' => $personnel->id]);

        return ['user' => (int) $user->id, 'personnel' => (int) $personnel->id];
    }

    private function examiner(Course $course, int $userId): void
    {
        $staff = new CourseStaff();
        $staff->course_id = $course->id;
        $staff->user_id   = $userId;
        $staff->role      = 'examiner';
        $staff->save();
    }

    /** @param list<string> $permissions */
    private function as(int $userId, array $permissions): void
    {
        $_SESSION = [];
        $this->actingAs($userId, ['permissions' => $permissions, 'cirs_username' => 'test']);
    }

    private function admit(Course $course, int $personnelId): Participation
    {
        $p = new Participation();
        $p->course_id    = $course->id;
        $p->personnel_id = $personnelId;
        $p->status       = 'in_training';
        $p->enrolled_at  = Carbon::now();
        $p->save();

        return $p;
    }

    private function begin(Course $course, Exam $exam, Participation $p): Attempt
    {
        $this->post('/training/courses/' . $course->id . '/exams/' . $exam->id . '/start');
        $attempt = Attempt::query()->where('participation_id', $p->id)->where('exam_id', $exam->id)->orderByDesc('id')->first();
        $this->assertNotNull($attempt, (string) ($_SESSION['flash']['text'] ?? 'kein Versuch'));

        return $attempt;
    }

    /** @phpstan-impure */
    private function statusOf(Attempt|Participation $model): ?string
    {
        $status = $model::query()->whereKey($model->id)->value('status');

        return $status === null ? null : (string) $status;
    }

    /** @param array<string, mixed> $body */
    private function postJson(string $path, array $body): Response
    {
        return $this->request('POST', $path, [
            'body'    => (string) json_encode($body),
            'headers' => ['X-CSRF-Token' => $this->csrfToken(), 'Content-Type' => 'application/json', 'Accept' => 'application/json'],
        ]);
    }

    #[Test]
    public function a_question_used_in_an_attempt_gets_a_new_version_on_save(): void
    {
        $question = $this->choice();
        $admin    = $this->account();
        $this->as($admin['user'], ['training.exams']);
        $form = ['type' => 'single_choice', 'body' => 'Welches Organ atmet?', 'points' => '1', 'topic_id' => (string) $this->topic->id, 'options' => ['Herz', 'Lunge'], 'correct' => '1', 'is_active' => '1'];

        $this->post('/training/questions/' . $question->id, $form);
        $this->assertSame('Welches Organ atmet?', $question->refresh()->body);
        $this->assertSame(1, $question->refresh()->version, 'Ohne Versuch bleibt die Version.');

        $exam    = $this->theory([$question->id]);
        $course  = $this->course();
        $learner = $this->account();
        $p       = $this->admit($course, $learner['personnel']);
        $this->as($learner['user'], ['training.view']);
        $this->begin($course, $exam, $p);

        $this->as($admin['user'], ['training.exams']);
        $this->post('/training/questions/' . $question->id, ['body' => 'Welches Organ tauscht Gas?'] + $form);

        $fresh = $question->refresh();
        $this->assertSame(2, $fresh->version);
        $old = Question::query()->find($fresh->previous_id);
        $this->assertSame('Welches Organ atmet?', $old?->body);
        $this->assertNotNull($old->deleted_at);
    }

    #[Test]
    public function the_pool_needs_the_exams_permission(): void
    {
        $manager = $this->account();
        $this->as($manager['user'], ['training.manage']);
        $page = $this->get('/training/types/' . $this->type->id);
        $this->assertOk($page);
        $this->assertStringNotContainsString('Fragenpool', $page->body);
        $this->post('/training/types/' . $this->type->id . '/topics', ['name' => 'Pharmakologie']);
        $this->assertSame(1, Topic::query()->where('course_type_id', $this->type->id)->count());

        $this->as($manager['user'], ['training.exams']);
        $this->assertStringContainsString('Fragenpool', $this->get('/training/types/' . $this->type->id)->body);
    }

    #[Test]
    public function csv_import_is_all_or_nothing_and_names_the_line(): void
    {
        $csv = "Thema;Art;Frage;Antworten;Punkte;Erklärung\n"
            . "Kreislauf;Einfachauswahl;Normaler Puls?;*60-80|120-140;1;\n"
            . "Kreislauf;Zahl;Atemfrequenz?;;1;\n";
        try {
            Exchange::importCsv($this->type, $csv, null);
            $this->fail('Import ohne Wert hätte scheitern müssen.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Zeile 3', $e->getMessage());
        }
        $this->assertSame(0, Question::query()->where('course_type_id', $this->type->id)->count());

        Exchange::importCsv($this->type, str_replace('Zahl;Atemfrequenz?;;', 'Zahl;Atemfrequenz?;14|2|/min;', $csv), null);
        $this->assertSame(2, Question::query()->where('course_type_id', $this->type->id)->count());
        $this->assertSame(1, Topic::query()->where('course_type_id', $this->type->id)->where('name', 'Kreislauf')->count());
    }

    #[Test]
    public function theory_attempt_saves_submits_and_waits_for_a_second_person(): void
    {
        $choice = $this->choice();
        $free   = $this->question('free_text', null, 2.0);
        $exam   = $this->theory([$choice->id, $free->id]);
        $lead    = $this->account();
        $course  = $this->course($lead['user']);
        $learner = $this->account();
        $p       = $this->admit($course, $learner['personnel']);

        $this->as($learner['user'], ['training.view']);
        $attempt = $this->begin($course, $exam, $p);
        $this->assertSame(Attempt::OPEN, $attempt->status);
        $this->assertSame('in_exam', $this->statusOf($p));

        $saved = $this->assertJsonResponse($this->postJson('/api/training/attempts/' . $attempt->id . '/answers', ['answers' => ['0' => '1', '1' => 'Erst Eigenschutz']]));
        $this->assertSame(['saved' => true], $saved);
        $this->assertSame(1, AttemptAnswer::query()->where('attempt_id', $attempt->id)->where('position', 0)->first()?->answer);

        $this->post('/training/attempts/' . $attempt->id . '/submit', ['answers' => ['0' => '1', '1' => 'Erst Eigenschutz, dann Patient']]);
        $this->assertSame(Attempt::REVIEW, $this->statusOf($attempt));

        // Teilnehmer kommen an die Bewertung nicht heran.
        $this->post('/training/attempts/' . $attempt->id . '/review', ['points' => ['1' => '2'], 'finish' => '1']);
        $this->assertSame(Attempt::REVIEW, $attempt->refresh()->status);

        // Automatische Punkte nur mit Begründung ändern.
        $this->as($lead['user'], ['training.teach']);
        $this->post('/training/attempts/' . $attempt->id . '/review', ['points' => ['0' => '0', '1' => '2'], 'finish' => '1']);
        $this->assertStringContainsString('Begründung', (string) ($_SESSION['flash']['text'] ?? ''));
        $this->assertSame(Attempt::REVIEW, $this->statusOf($attempt));

        $this->post('/training/attempts/' . $attempt->id . '/review', ['points' => ['1' => '1.5'], 'comments' => ['1' => 'Patient fehlt'], 'finish' => '1']);
        $attempt->refresh();
        $this->assertSame(Attempt::DONE, $attempt->status);
        $this->assertEqualsWithDelta(2.5, (float) $attempt->points, 0.001);
        $this->assertTrue($attempt->passed);
        $this->assertSame('passed', $this->statusOf($p));
    }

    #[Test]
    public function nobody_grades_their_own_attempt(): void
    {
        $exam    = $this->theory([$this->question('free_text', null)->id]);
        $learner = $this->account();
        $course  = $this->course($learner['user']);
        $p       = $this->admit($course, $learner['personnel']);
        $this->as($learner['user'], ['training.view', 'training.teach']);
        $attempt = $this->begin($course, $exam, $p);
        $this->post('/training/attempts/' . $attempt->id . '/submit', ['answers' => ['0' => 'Antwort']]);

        $this->assertNotFound($this->get('/training/attempts/' . $attempt->id . '/review'));
        $this->post('/training/attempts/' . $attempt->id . '/review', ['points' => ['0' => '1'], 'finish' => '1']);
        $this->assertSame(Attempt::REVIEW, $this->statusOf($attempt));
    }

    #[Test]
    public function only_the_server_deadline_counts_with_grace(): void
    {
        $exam    = $this->theory([$this->choice()->id]);
        $course  = $this->course();
        $learner = $this->account();
        $p       = $this->admit($course, $learner['personnel']);
        $this->as($learner['user'], ['training.view']);
        $attempt = $this->begin($course, $exam, $p);
        $url     = '/api/training/attempts/' . $attempt->id . '/answers';

        $attempt->deadline_at = Carbon::now()->subSeconds(10);
        $attempt->save();
        $this->assertSame(['saved' => true], $this->assertJsonResponse($this->postJson($url, ['answers' => ['0' => '1']])));

        $attempt->deadline_at = Carbon::now()->subSeconds(AttemptRules::GRACE_SECONDS + 5);
        $attempt->save();
        $this->assertSame(['saved' => false], $this->assertJsonResponse($this->postJson($url, ['answers' => ['0' => '0']])));

        $attempt->refresh();
        $this->assertSame(Attempt::DONE, $attempt->status);
        $this->assertSame(1, AttemptAnswer::query()->where('attempt_id', $attempt->id)->value('answer'), 'Nach Ablauf zählt der letzte gespeicherte Stand.');
        $this->assertTrue($attempt->passed);
    }

    #[Test]
    public function the_cron_closes_forgotten_attempts(): void
    {
        $exam    = $this->theory([$this->choice()->id]);
        $course  = $this->course();
        $learner = $this->account();
        $p       = $this->admit($course, $learner['personnel']);
        $this->as($learner['user'], ['training.view']);
        $attempt = $this->begin($course, $exam, $p);
        $attempt->deadline_at = Carbon::now()->subHour();
        $attempt->save();

        $this->assertSame(1, (new Exams())->closeOverdue());
        $attempt->refresh();
        $this->assertSame(Attempt::DONE, $attempt->status);
        $this->assertFalse($attempt->passed);
        $this->assertSame(0, (new Exams())->closeOverdue());
    }

    #[Test]
    public function practical_sheet_needs_a_second_examiner_to_cosign(): void
    {
        $exam    = $this->practical();
        $course  = $this->course();
        $first   = $this->account();
        $second  = $this->account();
        $this->examiner($course, $first['user']);
        $this->examiner($course, $second['user']);
        $learner = $this->account();
        $p       = $this->admit($course, $learner['personnel']);

        $this->as($first['user'], ['training.teach']);
        $this->post('/training/manage/' . $course->id . '/exams/' . $exam->id . '/sheet', ['participation' => (string) $p->id]);
        $attempt = Attempt::query()->where('participation_id', $p->id)->first();
        $this->assertNotNull($attempt);

        $this->post('/training/attempts/' . $attempt->id . '/sheet', ['ratings' => ['k1' => 'yes'], 'finish' => '1']);
        $this->assertSame(Attempt::OPEN, $this->statusOf($attempt), 'Ein offenes Kriterium hält den Abschluss auf.');

        $this->post('/training/attempts/' . $attempt->id . '/sheet', ['ratings' => ['k1' => 'yes', 'k2' => '3'], 'finish' => '1']);
        $this->assertSame(Attempt::COSIGN, $this->statusOf($attempt));

        $this->post('/training/attempts/' . $attempt->id . '/cosign');
        $this->assertSame(Attempt::COSIGN, $attempt->refresh()->status, 'Wer bewertet hat, zeichnet nicht gegen.');

        $this->as($second['user'], ['training.teach']);
        $this->post('/training/attempts/' . $attempt->id . '/cosign');
        $attempt->refresh();
        $this->assertSame(Attempt::DONE, $attempt->status);
        $this->assertSame($second['user'], $attempt->cosigned_by);
        $this->assertTrue($attempt->passed);
    }

    #[Test]
    public function an_extra_attempt_needs_a_reason_and_lands_in_the_log(): void
    {
        $exam    = $this->theory([$this->choice()->id], maxAttempts: 1);
        $lead    = $this->account();
        $course  = $this->course($lead['user']);
        $learner = $this->account();
        $p       = $this->admit($course, $learner['personnel']);
        $this->as($learner['user'], ['training.view']);
        $attempt = $this->begin($course, $exam, $p);
        $this->post('/training/attempts/' . $attempt->id . '/submit', ['answers' => ['0' => '0']]);
        $this->assertSame('failed', $this->statusOf($p));

        $url = '/training/manage/' . $course->id . '/exams/' . $exam->id . '/extra';
        $this->as($lead['user'], ['training.teach']);
        $this->post($url, ['participation' => (string) $p->id, 'reason' => ' ']);
        $this->assertSame(0, ExtraAttempt::query()->where('participation_id', $p->id)->count());

        $this->post($url, ['participation' => (string) $p->id, 'reason' => 'Krank während der Prüfung']);
        $this->assertSame(1, ExtraAttempt::query()->where('participation_id', $p->id)->count());
        $this->assertSame('in_exam', $this->statusOf($p));
        $this->assertSame(1, PersonnelLog::query()->where('profilid', $learner['personnel'])->where('type', PersonalLogManager::TYPE_TRAINING)->where('content', 'like', '%Zusatzversuch%')->count());

        $this->as($learner['user'], ['training.view']);
        $second = $this->begin($course, $exam, $p);
        $this->assertSame(2, $second->number);
    }
}
