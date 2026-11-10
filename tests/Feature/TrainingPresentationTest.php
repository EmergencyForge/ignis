<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Training\Models\Course;
use Plugin\Training\Models\CourseType;
use Plugin\Training\Models\Module;
use Plugin\Training\Models\Participation;
use Plugin\Training\Models\Presentation;
use Plugin\Training\Models\Session;
use Plugin\Training\Models\TrainingFile;
use Plugin\Training\Models\Unit;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;
use ZipArchive;

/**
 * Live-Vortrag an Terminen: Tabellen, Folien am Termin und was Lernende
 * davon sehen, über die echten Routen.
 */
final class TrainingPresentationTest extends FeatureTestCase
{
    /** @var list<string> */
    private array $storedFiles = [];

    /** @var list<string> */
    private array $tempFiles = [];

    private ?string $converterBefore = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converterBefore = $_ENV['TRAINING_CONVERTER'] ?? null;
        // Ohne Umwandler ist der Normalfall; die Testumgebung soll ihn nicht vorgeben.
        $_ENV['TRAINING_CONVERTER'] = '';
    }

    protected function tearDown(): void
    {
        foreach ([...$this->storedFiles, ...$this->tempFiles] as $path) {
            @unlink($path);
        }
        if ($this->converterBefore === null) {
            unset($_ENV['TRAINING_CONVERTER']);
        } else {
            $_ENV['TRAINING_CONVERTER'] = $this->converterBefore;
        }
        parent::tearDown();
    }

    /** @param array<string, mixed> $attributes */
    private function type(array $attributes = []): CourseType
    {
        $type = new CourseType();
        $type->name            = 'Rettungssanitäter';
        $type->short           = 'RS';
        $type->enrollment_mode = 'open';
        foreach ($attributes as $key => $value) {
            $type->{$key} = $value;
        }
        $type->save();

        return $type;
    }

    private function course(CourseType $type, string $status, ?int $seats = null, ?int $lead = null): Course
    {
        $course = new Course();
        $course->course_type_id = $type->id;
        $course->title          = $type->short . ' 1/2026';
        $course->number         = 1;
        $course->year           = 2026;
        $course->status         = $status;
        $course->seats          = $seats;
        $course->lead_user_id   = $lead;
        $course->save();

        return $course;
    }

    /** @param array<string, mixed> $attributes */
    private function unit(CourseType $type, array $attributes): Unit
    {
        $module = Module::query()->where('course_type_id', $type->id)->first();
        if ($module === null) {
            $module = new Module();
            $module->course_type_id = $type->id;
            $module->title          = 'Grundlagen';
            $module->save();
        }
        $unit = new Unit();
        $unit->module_id = $module->id;
        $unit->title     = 'Einheit ' . uniqid();
        $unit->kind      = 'text';
        foreach ($attributes as $key => $value) {
            $unit->{$key} = $value;
        }
        $unit->save();

        return $unit;
    }

    private function pdf(): TrainingFile
    {
        $dir = dirname(__DIR__, 2) . '/storage/private/training';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = 'test-' . bin2hex(random_bytes(8)) . '.pdf';
        file_put_contents($dir . '/' . $name, '%PDF-1.4 test');
        $this->storedFiles[] = $dir . '/' . $name;

        $file = new TrainingFile();
        $file->storage_name  = $name;
        $file->original_name = 'Skript.pdf';
        $file->mime          = 'application/pdf';
        $file->size          = 13;
        $file->save();

        return $file;
    }

    /**
     * Konto mit Mitarbeiter.
     *
     * @return array{user: int, personnel: int}
     */
    private function account(int $qualird = 3): array
    {
        $personnel = FixtureFactory::personnel(['qualird' => $qualird]);
        $user      = FixtureFactory::user(['aktenid' => $personnel->id]);

        return ['user' => (int) $user->id, 'personnel' => (int) $personnel->id];
    }

    /** @param list<string> $permissions */
    private function as(int $userId, array $permissions): void
    {
        $_SESSION = [];
        $this->actingAs($userId, ['permissions' => $permissions, 'cirs_username' => 'test']);
    }

    private function admit(Course $course, int $personnelId, string $status = 'in_training'): Participation
    {
        $p = new Participation();
        $p->course_id    = $course->id;
        $p->personnel_id = $personnelId;
        $p->status       = $status;
        $p->enrolled_at  = Carbon::now();
        $p->save();

        return $p;
    }

    private function session(Course $course, ?int $slides = null): Session
    {
        $session = new Session();
        $session->course_id      = $course->id;
        $session->title          = 'Theorie';
        $session->starts_at      = Carbon::now()->addDay();
        $session->ends_at        = Carbon::now()->addDay()->addHours(2);
        $session->required       = true;
        $session->slides_file_id = $slides;
        $session->save();

        return $session;
    }

    /**
     * Eine Upload-Datei wie aus $_FILES.
     *
     * @return array{name: string, tmp_name: string, error: int, size: int}
     */
    private function upload(string $content, string $name): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'slides');
        file_put_contents($tmp, $content);
        $this->tempFiles[] = $tmp;

        return ['name' => $name, 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($content)];
    }

    private function pptx(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pptx');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<Types><Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/></Types>');
        $zip->close();
        $content = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $content;
    }

    private function forgetStored(?int $fileId): void
    {
        $file = $fileId !== null ? TrainingFile::query()->find($fileId) : null;
        if ($file !== null) {
            $this->storedFiles[] = dirname(__DIR__, 2) . '/storage/private/training/' . $file->storage_name;
        }
    }

    #[Test]
    public function migration_adds_slides_and_presentation_tables(): void
    {
        $course  = $this->course($this->type(), 'running');
        $teacher = $this->account();
        $session = $this->session($course, $this->pdf()->id);

        $presentation = new Presentation();
        $presentation->session_id        = $session->id;
        $presentation->presenter_id      = $teacher['user'];
        $presentation->started_at        = Carbon::now();
        $presentation->presenter_seen_at = Carbon::now();
        $presentation->save();

        $session = Session::query()->find($session->id);
        $this->assertSame('Skript.pdf', $session->slides?->original_name);
        $this->assertSame(1, $session->presentations()->whereNull('ended_at')->count());
        $fresh = Presentation::query()->find($presentation->id);
        $this->assertSame(0, $fresh->page);
        $this->assertSame('slides', $fresh->mode);
        $this->assertTrue($fresh->isLive());
        $this->assertSame($teacher['user'], $fresh->presenter->id);
    }

    #[Test]
    public function instructor_uploads_slides_for_a_session(): void
    {
        $teacher = $this->account();
        $course  = $this->course($this->type(), 'running', lead: $teacher['user']);
        $session = $this->session($course);
        $this->as($teacher['user'], ['training.teach']);

        $response = $this->post('/training/manage/' . $course->id . '/sessions/' . $session->id . '/slides', [], [
            'files' => ['slides' => $this->upload("%PDF-1.4\n%%EOF\n", 'Folien.pdf')],
        ]);

        $fileId = Session::query()->find($session->id)->slides_file_id;
        $this->forgetStored($fileId);
        $this->assertRedirect($response, '/training/manage/' . $course->id . '#termine');
        $this->assertNotNull($fileId);
        $this->assertSame('Folien.pdf', TrainingFile::query()->find($fileId)->original_name);
    }

    #[Test]
    public function instructor_picks_slides_from_the_plan(): void
    {
        $teacher = $this->account();
        $type    = $this->type();
        $course  = $this->course($type, 'running', lead: $teacher['user']);
        $session = $this->session($course);
        $own     = $this->pdf();
        $foreign = $this->pdf();
        $this->unit($type, ['kind' => 'file', 'file_id' => $own->id]);
        $url = '/training/manage/' . $course->id . '/sessions/' . $session->id . '/slides';
        $this->as($teacher['user'], ['training.teach']);

        $this->post($url, ['slides_file_id' => (string) $foreign->id]);
        $this->assertNull(Session::query()->find($session->id)->slides_file_id);

        $this->assertRedirect($this->post($url, ['slides_file_id' => (string) $own->id]), '/training/manage/' . $course->id . '#termine');
        $this->assertSame($own->id, Session::query()->find($session->id)->slides_file_id);

        $this->post($url, ['remove' => '1']);
        $this->assertNull(Session::query()->find($session->id)->slides_file_id);
    }

    #[Test]
    public function an_upload_beats_the_pick(): void
    {
        $teacher = $this->account();
        $type    = $this->type();
        $course  = $this->course($type, 'running', lead: $teacher['user']);
        $session = $this->session($course);
        $own     = $this->pdf();
        $this->unit($type, ['kind' => 'file', 'file_id' => $own->id]);
        $this->as($teacher['user'], ['training.teach']);

        $this->post('/training/manage/' . $course->id . '/sessions/' . $session->id . '/slides', ['slides_file_id' => (string) $own->id], [
            'files' => ['slides' => $this->upload("%PDF-1.4\n%%EOF\n", 'Neu.pdf')],
        ]);

        $fileId = Session::query()->find($session->id)->slides_file_id;
        $this->forgetStored($fileId);
        $this->assertNotSame($own->id, $fileId);
    }

    #[Test]
    public function powerpoint_slides_need_the_converter(): void
    {
        $teacher = $this->account();
        $course  = $this->course($this->type(), 'running', lead: $teacher['user']);
        $session = $this->session($course);
        $this->as($teacher['user'], ['training.teach']);
        $url = '/training/manage/' . $course->id . '/sessions/' . $session->id . '/slides';

        $this->post($url, [], ['files' => ['slides' => $this->upload($this->pptx(), 'Folien.pptx')]]);
        $this->assertSame('PowerPoint braucht hier den Umwandler. Bitte als PDF hochladen.', $_SESSION['flash']['text'] ?? null);
        $this->assertNull(Session::query()->find($session->id)->slides_file_id);

        $this->post($url, [], ['files' => ['slides' => $this->upload('nur Text', 'Folien.txt')]]);
        $this->assertSame('Folien müssen ein PDF oder eine PowerPoint-Datei sein.', $_SESSION['flash']['text'] ?? null);
        $this->assertNull(Session::query()->find($session->id)->slides_file_id);
    }

    #[Test]
    public function learner_cannot_set_slides(): void
    {
        $teacher = $this->account();
        $course  = $this->course($this->type(), 'running', lead: $teacher['user']);
        $session = $this->session($course);
        $url     = '/training/manage/' . $course->id . '/sessions/' . $session->id . '/slides';
        $files   = ['files' => ['slides' => $this->upload("%PDF-1.4\n%%EOF\n", 'Folien.pdf')]];

        // Mit Lehrrecht, aber nicht in diesem Lehrgang: 404.
        $other = $this->account();
        $this->as($other['user'], ['training.teach']);
        $this->assertNotFound($this->post($url, [], $files));

        // Lernende haben das Recht gar nicht; die Middleware schickt sie weg wie bei jeder Verwaltungsroute.
        $learner = $this->account();
        $this->admit($course, $learner['personnel']);
        $this->as($learner['user'], ['training.view']);
        $this->assertRedirect($this->post($url, [], $files));

        $this->assertNull(Session::query()->find($session->id)->slides_file_id);
    }

    #[Test]
    public function slides_are_delivered_to_learners_with_open_materials(): void
    {
        $teacher  = $this->account();
        $course   = $this->course($this->type(), 'running', lead: $teacher['user']);
        $session  = $this->session($course, $this->pdf()->id);
        $learner  = $this->account();
        $outsider = $this->account();
        $this->admit($course, $learner['personnel']);
        $url = '/training/courses/' . $course->id . '/sessions/' . $session->id . '/slides';

        $this->as($learner['user'], ['training.view']);
        $response = $this->get($url);
        $this->assertOk($response);
        $this->assertSame('application/pdf', $response->headers['Content-Type'] ?? null);
        $this->assertSame('private, no-store', $response->headers['Cache-Control'] ?? null);

        $this->as($outsider['user'], ['training.view']);
        $this->assertNotFound($this->get($url));

        // Teilnahme besteht, der Lehrgang gibt die Unterlagen aber noch nicht frei.
        $closed        = $this->course($this->type(), 'planned');
        $closedSession = $this->session($closed, $this->pdf()->id);
        $this->admit($closed, $learner['personnel']);
        $this->as($learner['user'], ['training.view']);
        $this->assertNotFound($this->get('/training/courses/' . $closed->id . '/sessions/' . $closedSession->id . '/slides'));

        $this->as($teacher['user'], ['training.teach']);
        $this->assertOk($this->get($url));
    }

    #[Test]
    public function session_card_shows_the_slides_form_and_a_pending_conversion(): void
    {
        $teacher = $this->account();
        $course  = $this->course($this->type(), 'running', lead: $teacher['user']);
        $pptx    = $this->pdf();
        $pptx->mime            = \Plugin\Training\Converter::PPTX;
        $pptx->original_name    = 'Folien.pptx';
        $pptx->convert_status   = \Plugin\Training\Converter::PENDING;
        $pptx->save();
        $session = $this->session($course, $pptx->id);
        $this->as($teacher['user'], ['training.teach']);

        $response = $this->get('/training/manage/' . $course->id);

        $this->assertOk($response);
        $this->assertBodyContains('id="slides-' . $session->id . '"', $response);
        $this->assertBodyContains('Ohne Umwandler gehen hier nur PDF-Folien.', $response);
        $this->assertBodyContains('wird umgewandelt', $response);
        $this->assertBodyContains('Folien.pptx', $response);
    }
}