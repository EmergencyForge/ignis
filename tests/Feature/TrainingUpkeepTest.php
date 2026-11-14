<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Notification;
use EmergencyForge\Training\Exam\AttemptRules;
use EmergencyForge\Training\Exam\ExamSettings;
use EmergencyForge\Training\Exam\ReviewMode;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Training\Converter;
use Plugin\Training\Expiry;
use Plugin\Training\Models\Attempt;
use Plugin\Training\Models\Course;
use Plugin\Training\Models\CourseType;
use Plugin\Training\Models\Exam;
use Plugin\Training\Models\Participation;
use Plugin\Training\Models\Question;
use Plugin\Training\Models\TrainingFile;
use Plugin\Training\Pool;
use Plugin\Training\QuestionAnalysis;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Ablauf-Erinnerung, Fragenauswertung und die Umwandlung von PowerPoint.
 */
final class TrainingUpkeepTest extends FeatureTestCase
{
    private CourseType $type;

    private int $courseNumber = 0;

    /** @var list<string> */
    private array $paths = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->type = new CourseType();
        $this->type->name            = 'Truppführer';
        $this->type->short           = 'TF';
        $this->type->enrollment_mode = 'open';
        $this->type->validity_months = 24;
        $this->type->save();
    }

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    /** @return array{user: int, personnel: int} */
    private function account(): array
    {
        $personnel = FixtureFactory::personnel();
        $user      = FixtureFactory::user(['aktenid' => $personnel->id]);

        return ['user' => (int) $user->id, 'personnel' => (int) $personnel->id];
    }

    private function course(string $status, ?int $lead = null, ?string $startsOn = null): Course
    {
        $course = new Course();
        $course->course_type_id = $this->type->id;
        $course->title          = 'TF ' . $status;
        // Nummer ist je Kursart und Jahr eindeutig, eine Zufallszahl kollidiert bei mehreren Kursen pro Test.
        $course->number         = ++$this->courseNumber;
        $course->year           = 2026;
        $course->status         = $status;
        $course->starts_on      = $startsOn !== null ? Carbon::parse($startsOn) : null;
        $course->lead_user_id   = $lead;
        $course->save();

        return $course;
    }

    private function participation(Course $course, int $personnelId, string $status, ?string $expiresOn = null): Participation
    {
        $p = new Participation();
        $p->course_id    = $course->id;
        $p->personnel_id = $personnelId;
        $p->status       = $status;
        $p->enrolled_at  = Carbon::now();
        $p->expires_on   = $expiresOn !== null ? Carbon::parse($expiresOn) : null;
        $p->save();

        return $p;
    }

    /** @return list<Notification> */
    private function notifications(int $userId): array
    {
        return Notification::query()->where('user_id', $userId)->where('type', 'training')->get()->all();
    }

    #[Test]
    public function expiry_reminds_once_and_points_to_the_next_open_course(): void
    {
        $lead    = (int) FixtureFactory::user()->id;
        $learner = $this->account();
        $done    = $this->course('closed', $lead);
        $next    = $this->course('enrollment_open', null, '2026-12-01');
        $p       = $this->participation($done, $learner['personnel'], 'passed', '2026-12-01');
        $this->participation($done, $this->account()['personnel'], 'passed', '2027-06-01');

        $this->assertSame(1, Expiry::remind(Carbon::parse('2026-11-10')));
        $this->assertSame(0, Expiry::remind(Carbon::parse('2026-11-11')));

        $this->assertNotNull($p->fresh()?->expiry_notified_at);
        $mine = $this->notifications($learner['user']);
        $this->assertCount(1, $mine);
        $this->assertSame('Truppführer läuft am 01.12.2026 ab', $mine[0]->title);
        $this->assertStringEndsWith('training/courses/' . $next->id, (string) $mine[0]->link);
        $this->assertStringContainsString('Nächster Lehrgang: TF enrollment_open ab 01.12.2026.', (string) $mine[0]->message);
        $this->assertCount(1, $this->notifications($lead));
    }

    #[Test]
    public function question_analysis_counts_answers_and_choices(): void
    {
        $question = new Question();
        $question->course_type_id = $this->type->id;
        Pool::save($question, ['type' => 'single_choice', 'body' => 'Wie viele Atemschutzgeräteträger hat ein Trupp?', 'points' => 1.0, 'spec' => ['options' => ['Einen', 'Zwei'], 'correct' => [1], 'partial' => false], 'explanation' => null, 'topic_id' => null, 'is_active' => true], null);

        $exam = new Exam();
        $exam->course_type_id = $this->type->id;
        $exam->kind           = 'theory';
        $exam->title          = 'Theorie';
        $exam->required       = true;
        $exam->blueprint      = ['topics' => [], 'fixed' => [$question->id], 'variants' => [], 'shuffle_questions' => false, 'shuffle_options' => false];
        $exam->settings       = (new ExamSettings(50.0, new AttemptRules(30, 2, 0), null, ReviewMode::Result, [], false, false))->toArray();
        $exam->save();

        $course = $this->course('exam_phase');
        foreach (['1', '0', '1'] as $choice) {
            $account = $this->account();
            $p       = $this->participation($course, $account['personnel'], 'in_training');
            $_SESSION = [];
            $this->actingAs($account['user'], ['permissions' => ['training.view'], 'cirs_username' => 'test']);
            $this->post('/training/courses/' . $course->id . '/exams/' . $exam->id . '/start');
            $attempt = Attempt::query()->where('participation_id', $p->id)->firstOrFail();
            $this->post('/training/attempts/' . $attempt->id . '/submit', ['answers' => ['0' => $choice]]);
        }

        $stats = QuestionAnalysis::of([$question->fresh()])[$question->id] ?? null;

        $this->assertNotNull($stats);
        $this->assertSame(3, $stats->asked());
        $this->assertEqualsWithDelta(2 / 3, $stats->correctShare(), 0.001);
        $this->assertEquals([0 => 1, 1 => 2], $stats->choiceCounts());
        $this->assertNull($stats->verdict(), 'Unter zehn Antworten gibt es keine Markierung.');
    }

    #[Test]
    public function converter_attaches_the_pdf_and_marks_failures(): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/private/training';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $ok   = $this->pptx($dir);
        $fake = sys_get_temp_dir() . '/fake-soffice-' . bin2hex(random_bytes(4));
        // Schreibt eine PDF nach --outdir, benannt wie die Quelle, so wie LibreOffice.
        file_put_contents($fake, "#!/bin/sh\nwhile [ \$# -gt 1 ]; do [ \"\$1\" = --outdir ] && out=\$2; shift; done\nname=\$(basename \"\$1\")\nprintf '%%PDF-1.4 fake' > \"\$out/\${name%.*}.pdf\"\n");
        chmod($fake, 0755);
        $this->paths[] = $fake;

        $this->assertSame(['done' => 1, 'failed' => 0], (new Converter($fake))->runPending());

        $ok = $ok->fresh();
        $this->assertNotNull($ok);
        $this->assertSame(Converter::DONE, $ok->convert_status);
        $this->assertTrue($ok->viewsAsPdf());
        $this->paths[] = $dir . '/' . $ok->pdf_storage_name;
        $this->assertStringEqualsFile($dir . '/' . $ok->pdf_storage_name, '%PDF-1.4 fake');

        $broken = $this->pptx($dir);
        $this->assertSame(['done' => 0, 'failed' => 1], (new Converter('/bin/false'))->runPending());
        $this->assertSame(Converter::FAILED, $broken->fresh()?->convert_status);
        $this->assertFalse($broken->fresh()->viewsAsPdf());
    }

    private function pptx(string $dir): TrainingFile
    {
        $name = 'test-' . bin2hex(random_bytes(8)) . '.pptx';
        file_put_contents($dir . '/' . $name, 'PK fake');
        $this->paths[] = $dir . '/' . $name;

        $file = new TrainingFile();
        $file->storage_name   = $name;
        $file->original_name  = 'Folien.pptx';
        $file->mime           = Converter::PPTX;
        $file->size           = 7;
        $file->convert_status = Converter::PENDING;
        $file->save();

        return $file;
    }
}
