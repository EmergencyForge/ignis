<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Documents\Editor\SystemTemplates;
use App\Integrations\DiscordWebhook;
use App\Models\EditorDocument;
use App\Models\EditorTemplate;
use App\Models\PersonnelLog;
use App\Personnel\PersonalLogManager;
use EmergencyForge\Training\Completion\CompletionStep;
use EmergencyForge\Training\Completion\StepResult;
use EmergencyForge\Training\Exam\AttemptRules;
use EmergencyForge\Training\Exam\ExamSettings;
use EmergencyForge\Training\Exam\ReviewMode;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Training\Completion\CompletionTable;
use Plugin\Training\Completions;
use Plugin\Training\FollowUps;
use Plugin\Training\Models\Attempt;
use Plugin\Training\Models\Course;
use Plugin\Training\Models\CourseType;
use Plugin\Training\Models\Exam;
use Plugin\Training\Models\Participation;
use Plugin\Training\Models\Question;
use Plugin\Training\Models\Topic;
use Plugin\Training\Pool;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Lehrgangsabschluss: Bestehen startet die Kette (Ergebnis, Qualifikation,
 * Urkunde, Verlauf, Benachrichtigung), „Lehrgang abschließen“ setzt den
 * Rest auf nicht bestanden, gescheiterte Schritte laufen erneut.
 */
final class TrainingCompletionTest extends FeatureTestCase
{
    private CourseType $type;
    private Exam $exam;
    private Question $question;

    protected function setUp(): void
    {
        parent::setUp();
        $rettSan = (int) Capsule::table('intra_mitarbeiter_rdquali')->insertGetId(['priority' => 50, 'name' => 'Testsanitäter/-in', 'name_m' => 'Testsanitäter', 'name_w' => 'Testsanitäterin', 'none' => 0, 'trainable' => 1]);

        $this->type = new CourseType();
        $this->type->name               = 'Rettungssanitäter';
        $this->type->short              = 'RS';
        $this->type->enrollment_mode    = 'open';
        $this->type->pass_qualification = 'rd:' . $rettSan;
        $this->type->validity_months    = 24;
        $this->type->certificate_template_id = (int) EditorTemplate::query()->where('name', SystemTemplates::TRAINING_COURSE)->value('id');
        $this->type->save();

        $topic = new Topic();
        $topic->course_type_id = $this->type->id;
        $topic->name           = 'Anatomie';
        $topic->save();
        $this->question = new Question();
        $this->question->course_type_id = $this->type->id;
        Pool::save($this->question, ['type' => 'single_choice', 'body' => 'Welches Organ atmet?', 'points' => 1.0, 'spec' => ['options' => ['Herz', 'Lunge'], 'correct' => [1], 'partial' => false], 'explanation' => null, 'topic_id' => $topic->id, 'is_active' => true], null);

        $this->exam = new Exam();
        $this->exam->course_type_id = $this->type->id;
        $this->exam->kind           = 'theory';
        $this->exam->title          = 'Theorie';
        $this->exam->required       = true;
        $this->exam->blueprint      = ['topics' => [], 'fixed' => [$this->question->id], 'variants' => [], 'shuffle_questions' => false, 'shuffle_options' => false];
        $this->exam->settings       = (new ExamSettings(50.0, new AttemptRules(30, 2, 0), null, ReviewMode::Result, [], false, false))->toArray();
        $this->exam->save();
    }

    protected function tearDown(): void
    {
        DiscordWebhook::fake(null);
        parent::tearDown();
    }

    /** @return array{user: int, personnel: int} */
    private function account(): array
    {
        $personnel = FixtureFactory::personnel(['geschlecht' => 1]);
        $user      = FixtureFactory::user(['aktenid' => $personnel->id]);

        return ['user' => (int) $user->id, 'personnel' => (int) $personnel->id];
    }

    /** @param list<string> $permissions */
    private function as(int $userId, array $permissions): void
    {
        $_SESSION = [];
        $this->actingAs($userId, ['permissions' => $permissions, 'cirs_username' => 'test']);
    }

    private function course(int $lead): Course
    {
        $course = new Course();
        $course->course_type_id = $this->type->id;
        $course->title          = 'RS-Lehrgang 1/2026';
        $course->number         = 1;
        $course->year           = 2026;
        $course->status         = 'exam_phase';
        $course->starts_on      = Carbon::parse('2026-10-01');
        $course->ends_on        = Carbon::parse('2026-11-06');
        $course->lead_user_id   = $lead;
        $course->save();

        return $course;
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

    /** Theorie ablegen; `$right` entscheidet über Bestehen. */
    private function sit(Course $course, Participation $p, int $userId, bool $right): void
    {
        $this->as($userId, ['training.view']);
        $this->post('/training/courses/' . $course->id . '/exams/' . $this->exam->id . '/start');
        $attempt = Attempt::query()->where('participation_id', $p->id)->orderByDesc('id')->firstOrFail();
        $this->post('/training/attempts/' . $attempt->id . '/submit', ['answers' => ['0' => $right ? '1' : '0']]);
    }

    /** @phpstan-impure */
    private function step(Participation $p, CompletionStep $step): ?StepResult
    {
        return (new CompletionTable())->find($p->id)?->result($step);
    }

    #[Test]
    public function passing_the_last_part_runs_the_whole_chain(): void
    {
        $lead    = $this->account();
        $course  = $this->course($lead['user']);
        $learner = $this->account();
        $p       = $this->admit($course, $learner['personnel']);

        $this->sit($course, $p, $learner['user'], true);

        $p->refresh();
        $this->assertSame('passed', $p->status);
        $this->assertSame(100.0, (float) $p->result_percent);
        $this->assertNotNull($p->expires_on);
        $this->assertSame((int) explode(':', (string) $this->type->pass_qualification)[1], (int) Capsule::table('intra_mitarbeiter')->where('id', $learner['personnel'])->value('qualird'));

        $certificate = $this->step($p, CompletionStep::Certificate);
        $this->assertSame(StepResult::DONE, $certificate?->state, (string) $certificate?->message);
        $document = EditorDocument::query()->find((int) $certificate->data['document_id']);
        $this->assertNotNull($document);
        $this->assertTrue($document->isIssued());
        $this->assertSame('RS-Lehrgang 1/2026', $document->frozen_values['lehrgang.titel'] ?? null);
        $this->assertSame('Testsanitäterin', $document->frozen_values['qualifikation.name'] ?? null, 'Qualifikation in der weiblichen Form.');
        $this->assertSame('100 %', $document->frozen_values['pruefung.ergebnis'] ?? null);
        $this->assertSame('01.10.2026', $document->frozen_values['lehrgang.beginn'] ?? null);

        $this->assertNull($this->step($p, CompletionStep::Announce), 'Bekanntgabe ist ab Werk aus und läuft gar nicht.');
        $this->assertSame(1, PersonnelLog::query()->where('profilid', $learner['personnel'])->where('type', PersonalLogManager::TYPE_TRAINING)->where('content', 'like', '%bestanden (100 %%')->count());
        $this->assertTrue(Capsule::table('intra_notifications')->where('user_id', $learner['user'])->where('title', 'like', 'Glückwunsch%')->exists());
        $this->assertTrue((new CompletionTable())->find($p->id)?->isFinished());

        $this->as($learner['user'], ['training.view']);
        $this->assertSame(200, $this->get('/documents/' . $document->id)->status, 'Die eigene Urkunde sieht man ohne Dokumentenrecht.');
        $stranger = $this->account();
        $this->as($stranger['user'], ['training.view']);
        $this->assertSame(403, $this->get('/documents/' . $document->id)->status);
    }

    #[Test]
    public function a_higher_qualification_stays(): void
    {
        $lead    = $this->account();
        $course  = $this->course($lead['user']);
        $learner = $this->account();
        $notarzt = (int) Capsule::table('intra_mitarbeiter_rdquali')->insertGetId(['priority' => 90, 'name' => 'Testarzt', 'name_m' => 'Testarzt', 'name_w' => 'Testärztin', 'none' => 0, 'trainable' => 0]);
        Capsule::table('intra_mitarbeiter')->where('id', $learner['personnel'])->update(['qualird' => $notarzt]);
        $p = $this->admit($course, $learner['personnel']);

        $this->sit($course, $p, $learner['user'], true);

        $this->assertSame(StepResult::SKIPPED, $this->step($p, CompletionStep::Qualification)?->state);
        $this->assertSame($notarzt, (int) Capsule::table('intra_mitarbeiter')->where('id', $learner['personnel'])->value('qualird'));
    }

    #[Test]
    public function a_required_field_without_value_leaves_a_draft_and_tells_the_lead(): void
    {
        $template = EditorTemplate::query()->where('name', 'Lehrgangszertifikat')->firstOrFail();
        $this->type->certificate_template_id = $template->id;
        $this->type->save();
        $lead    = $this->account();
        $course  = $this->course($lead['user']);
        $learner = $this->account();
        $p       = $this->admit($course, $learner['personnel']);

        $this->sit($course, $p, $learner['user'], true);

        $certificate = $this->step($p, CompletionStep::Certificate);
        $this->assertSame(StepResult::DONE, $certificate?->state);
        $this->assertTrue((bool) $certificate->data['draft']);
        $this->assertStringStartsWith('Entwurf:', (string) $certificate->message);
        $this->assertFalse(EditorDocument::query()->findOrFail((int) $certificate->data['document_id'])->isIssued());
        $this->assertTrue(Capsule::table('intra_notifications')->where('user_id', $lead['user'])->where('title', 'like', 'Urkunde für%braucht noch Angaben')->exists());
        $this->assertSame(StepResult::DONE, $this->step($p, CompletionStep::Journal)?->state, 'Ein Entwurf hält den Rest nicht auf.');
    }

    #[Test]
    public function a_failed_step_waits_and_the_lead_can_force_a_retry(): void
    {
        $this->type->completion_plan     = ['announce' => true];
        $this->type->completion_settings = ['announce_channel' => 'discord', 'announce_webhook' => 'https://discord.com/api/webhooks/1/abc', 'announce_text' => '{person.name} hat den {lehrgang.titel} bestanden.'];
        $this->type->save();
        $sent = [];
        DiscordWebhook::fake(static function (string $url, string $body) use (&$sent): array {
            $sent[] = $body;

            return ['status' => count($sent) === 1 ? 500 : 204, 'body' => ''];
        });
        $lead    = $this->account();
        $course  = $this->course($lead['user']);
        $learner = $this->account();
        $p       = $this->admit($course, $learner['personnel']);

        $this->sit($course, $p, $learner['user'], true);
        $this->assertSame(StepResult::FAILED, $this->step($p, CompletionStep::Announce)?->state);
        $this->assertSame(0, (new Completions())->runPending(), 'Nach dem Fehlschlag wartet der Cron fünf Minuten.');

        $this->as($lead['user'], ['training.teach']);
        $this->post('/training/manage/' . $course->id . '/completions/' . $p->id . '/retry');
        $this->assertSame(StepResult::DONE, $this->step($p, CompletionStep::Announce)?->state);
        $this->assertCount(2, $sent);
        $this->assertStringContainsString('hat den RS-Lehrgang 1/2026 bestanden', $sent[1]);
        $this->assertSame(1, EditorDocument::query()->where('mitarbeiter_id', $learner['personnel'])->count(), 'Die Urkunde entsteht nur einmal.');
    }

    #[Test]
    public function closing_the_course_fails_everyone_who_has_not_passed(): void
    {
        $lead    = $this->account();
        $course  = $this->course($lead['user']);
        $winner  = $this->account();
        $loser   = $this->account();
        $passed  = $this->admit($course, $winner['personnel']);
        $failed  = $this->admit($course, $loser['personnel']);
        $this->sit($course, $passed, $winner['user'], true);
        $this->sit($course, $failed, $loser['user'], false);
        $this->sit($course, $failed, $loser['user'], false);
        $this->assertSame('in_exam', Participation::query()->whereKey($failed->id)->value('status'), 'Ohne Versuche bleibt die Teilnahme offen.');

        $this->as($lead['user'], ['training.teach']);
        $this->post('/training/manage/' . $course->id . '/status', ['status' => 'completed']);

        $this->assertSame('completed', Course::query()->whereKey($course->id)->value('status'));
        $this->assertSame('passed', Participation::query()->whereKey($passed->id)->value('status'));
        $this->assertSame('failed', Participation::query()->whereKey($failed->id)->value('status'));
        $completion = (new CompletionTable())->find($failed->id);
        $this->assertNotNull($completion);
        $this->assertFalse($completion->passed);
        $this->assertSame([CompletionStep::Result, CompletionStep::Journal, CompletionStep::Notify], $completion->steps);
        $this->assertTrue($completion->isFinished());
        $this->assertSame(0, EditorDocument::query()->where('mitarbeiter_id', $loser['personnel'])->count());
        $this->assertTrue(Capsule::table('intra_notifications')->where('user_id', $loser['user'])->where('title', 'like', '%nicht bestanden')->exists());
    }

    #[Test]
    public function closing_a_course_without_required_exam_passes_everyone(): void
    {
        $this->exam->required = false;
        $this->exam->save();
        $lead    = $this->account();
        $course  = $this->course($lead['user']);
        $learner = $this->account();
        $p       = $this->admit($course, $learner['personnel']);

        $course->ends_on = null;
        $course->save();

        $this->as($lead['user'], ['training.teach']);
        $this->post('/training/manage/' . $course->id . '/status', ['status' => 'completed']);

        $this->assertSame('passed', Participation::query()->whereKey($p->id)->value('status'));
        $this->assertTrue((new CompletionTable())->find($p->id)?->passed);
        $frozen = EditorDocument::query()->where('mitarbeiter_id', $learner['personnel'])->firstOrFail()->frozen_values;
        $today  = Carbon::now()->format('d.m.Y');
        $this->assertSame($today, $frozen['lehrgang.ende'] ?? null, 'Ohne Enddatum zählt der Abschluss der Teilnahme.');
        $this->assertSame($today, $frozen['pruefung.datum'] ?? null, 'Ohne Prüfung ebenso.');
    }

    #[Test]
    public function a_follow_up_for_a_role_shows_up_and_only_that_role_closes_it(): void
    {
        $role = FixtureFactory::role();
        $this->type->completion_plan     = ['follow_up' => true];
        $this->type->completion_settings = ['follow_up_target' => 'role:' . $role->id, 'follow_up_title' => 'Beförderung von {person.name} prüfen', 'follow_up_days' => 14];
        $this->type->save();
        $lead     = $this->account();
        $course   = $this->course($lead['user']);
        $learner  = $this->account();
        $assignee = (int) FixtureFactory::user(['role' => $role->id])->id;
        $stranger = $this->account();
        $p        = $this->admit($course, $learner['personnel']);

        $this->sit($course, $p, $learner['user'], true);

        $tasks = FollowUps::openFor($assignee);
        $this->assertCount(1, $tasks);
        $this->assertStringStartsWith('Beförderung von ', $tasks[0]->title);
        $this->assertSame([], FollowUps::openFor($stranger['user']));

        $this->as($stranger['user'], []);
        $this->post('/training/follow-ups/' . $tasks[0]->id . '/done', ['back' => 'personnel/profile?id=' . $learner['personnel']]);
        $this->assertCount(1, FollowUps::openFor($assignee), 'Fremde dürfen nicht abhaken.');

        $this->as($assignee, []);
        $this->post('/training/follow-ups/' . $tasks[0]->id . '/done', ['back' => 'personnel/profile?id=' . $learner['personnel']]);
        $this->assertSame([], FollowUps::openFor($assignee));
    }

    #[Test]
    public function the_type_tab_saves_steps_and_rejects_a_foreign_webhook(): void
    {
        $admin = $this->account();
        $this->as($admin['user'], ['training.manage']);
        $url = '/training/types/' . $this->type->id . '/completion';

        $this->post($url, ['steps' => ['qualification' => '1', 'certificate' => '1', 'journal' => '1', 'notify' => '1', 'announce' => '1'], 'signer_mode' => 'lead', 'certificate_template_id' => (string) $this->type->certificate_template_id, 'announce_channel' => 'discord', 'announce_webhook' => 'https://example.com/hook', 'announce_text' => 'x']);
        $this->assertNull($this->type->refresh()->completion_settings, 'Eine fremde URL wird nicht gespeichert.');

        $this->post($url, ['steps' => ['qualification' => '1', 'journal' => '1', 'notify' => '1', 'follow_up' => '1'], 'signer_mode' => 'grader', 'announce_channel' => 'dashboard', 'announce_text' => 'Neu: {person.name}', 'follow_up_target' => 'user:' . $admin['user'], 'follow_up_title' => 'Beförderung prüfen', 'follow_up_days' => '7']);
        $this->type->refresh();
        $this->assertFalse($this->type->completion_plan['certificate']);
        $this->assertTrue($this->type->completion_plan['follow_up']);
        $this->assertSame('grader', $this->type->signer_mode);
        $this->assertSame('user:' . $admin['user'], $this->type->completion_settings['follow_up_target'] ?? null);
    }
}
