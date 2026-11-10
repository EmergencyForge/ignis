<?php

declare(strict_types=1);

namespace Tests\Feature;

use DateTimeImmutable;
use EmergencyForge\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Training\Models\Course;
use Plugin\Training\Models\CourseStaff;
use Plugin\Training\Models\CourseType;
use Plugin\Training\Models\Module;
use Plugin\Training\Models\Participation;
use Plugin\Training\Models\Presentation;
use Plugin\Training\Models\PresentationEvent;
use Plugin\Training\Models\PresentationViewer;
use Plugin\Training\Models\Session;
use Plugin\Training\Models\TrainingFile;
use Plugin\Training\Models\Unit;
use Plugin\Training\Presentations;
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
     * Konto mit Mitarbeiter; $permissions landen in der Rolle, also in der DB.
     *
     * @param list<string> $permissions
     * @return array{user: int, personnel: int}
     */
    private function account(int $qualird = 3, array $permissions = []): array
    {
        $personnel = FixtureFactory::personnel(['qualird' => $qualird]);
        $user      = FixtureFactory::user(['aktenid' => $personnel->id, 'role' => FixtureFactory::role(['permissions' => $permissions])->id]);

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

    #[Test]
    public function slides_upload_error_names_the_failure(): void
    {
        $teacher = $this->account();
        $course  = $this->course($this->type(), 'running', lead: $teacher['user']);
        $session = $this->session($course);
        $this->as($teacher['user'], ['training.teach']);
        $url = '/training/manage/' . $course->id . '/sessions/' . $session->id . '/slides';

        $failed = static fn (int $error): array => ['files' => ['slides' => ['name' => 'Folien.pdf', 'tmp_name' => '', 'error' => $error, 'size' => 0]]];
        $this->assertRedirect($this->post($url, [], $failed(UPLOAD_ERR_INI_SIZE)), '/training/manage/' . $course->id . '#termine');
        $this->assertSame('Die Datei ist zu groß (höchstens 25 MB).', $_SESSION['flash']['text'] ?? null);
        $this->post($url, [], $failed(UPLOAD_ERR_PARTIAL));
        $this->assertSame('Der Upload ist fehlgeschlagen.', $_SESSION['flash']['text'] ?? null);
        $this->assertNull(Session::query()->find($session->id)->slides_file_id);
    }

    /**
     * Leitung (angemeldet, mit Lehrrecht in der DB), Lehrgang, Termin mit Folien, Pfad zum Vortrag.
     *
     * @return array{0: array{user: int, personnel: int}, 1: Course, 2: Session, 3: string}
     */
    private function talk(string $status = 'running'): array
    {
        $teacher = $this->account(permissions: ['training.teach']);
        $course  = $this->course($this->type(), $status, lead: $teacher['user']);
        $session = $this->session($course, $this->pdf()->id);
        $this->as($teacher['user'], ['training.teach']);

        return [$teacher, $course, $session, '/training/courses/' . $course->id . '/sessions/' . $session->id . '/presentation'];
    }

    /**
     * Ausbilder des Lehrgangs; $permissions gelten in der DB, nicht in der Sitzung.
     *
     * @param list<string> $permissions
     * @return array{user: int, personnel: int}
     */
    private function instructor(Course $course, array $permissions = ['training.teach']): array
    {
        $account = $this->account(permissions: $permissions);
        CourseStaff::query()->create(['course_id' => $course->id, 'user_id' => $account['user'], 'role' => 'instructor']);

        return $account;
    }

    /**
     * Teilnehmer in Ausbildung, angemeldet.
     *
     * @return array{user: int, personnel: int}
     */
    private function learnerIn(Course $course): array
    {
        $learner = $this->account();
        $this->admit($course, $learner['personnel']);
        $this->as($learner['user'], ['training.view']);

        return $learner;
    }

    private function live(Session $session): Presentation
    {
        return Presentation::query()->where('session_id', $session->id)->latest('id')->firstOrFail();
    }

    /** @param array<string, mixed> $body */
    private function postJson(string $path, array $body = []): Response
    {
        return $this->request('POST', $path, [
            'body'    => (string) json_encode($body),
            'headers' => ['X-CSRF-Token' => $this->csrfToken(), 'Content-Type' => 'application/json', 'Accept' => 'application/json'],
        ]);
    }

    /**
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private function stroke(int $id, array $override = []): array
    {
        return $override + ['kind' => 'begin', 'page' => 0, 'id' => $id, 'tool' => 'pen', 'color' => '#ef4444', 'width' => 2, 'p' => [0.1, 0.2], 't' => 0];
    }

    private function audits(string $action): int
    {
        return Capsule::table('intra_audit_log')->where('action', $action)->count();
    }

    #[Test]
    public function instructor_starts_and_reuses_a_talk(): void
    {
        [, $course, $session, $path] = $this->talk();
        $instructor = $this->instructor($course);
        $this->as($instructor['user'], ['training.teach']);
        $before = $this->audits('Vortrag gestartet');

        $this->assertRedirect($this->post($path . '/start'), $path);
        $this->assertRedirect($this->post($path . '/start'), $path);

        $this->assertSame(1, Presentation::query()->where('session_id', $session->id)->whereNull('ended_at')->count());
        $this->assertSame($instructor['user'], $this->live($session)->presenter_id);
        $this->assertSame($before + 1, $this->audits('Vortrag gestartet'));
        $this->assertBodyContains('data-role="present"', $this->get($path));

        $this->learnerIn($course);
        $this->assertBodyContains('data-role="follow"', $this->get($path));
    }

    #[Test]
    public function learner_cannot_start(): void
    {
        [, $course, $session, $path] = $this->talk();
        $this->learnerIn($course);

        $this->assertRedirect($this->post($path . '/start'), '/training/courses/' . $course->id);
        $this->assertSame('Einen Vortrag starten Leitung und Ausbilder des Lehrgangs.', $_SESSION['flash']['text'] ?? null);

        // Ohne Teilnahme gibt es den Lehrgang für ihn nicht.
        $outsider = $this->account();
        $this->as($outsider['user'], ['training.view']);
        $this->assertNotFound($this->post($path . '/start'));
        $this->assertSame(0, Presentation::query()->where('session_id', $session->id)->count());
    }

    #[Test]
    public function start_needs_slides(): void
    {
        [, $course, $session, $path] = $this->talk();
        $back = '/training/manage/' . $course->id . '#termine';
        $session->slides_file_id = null;
        $session->save();

        $this->assertRedirect($this->post($path . '/start'), $back);
        $this->assertSame('Dieser Termin hat keine Folien.', $_SESSION['flash']['text'] ?? null);

        // PowerPoint, die noch nicht umgewandelt ist, zählt nicht als Folien.
        $pptx = $this->pdf();
        $pptx->mime           = \Plugin\Training\Converter::PPTX;
        $pptx->convert_status = \Plugin\Training\Converter::PENDING;
        $pptx->save();
        $session->slides_file_id = $pptx->id;
        $session->save();
        $this->assertRedirect($this->post($path . '/start'), $back);

        $this->assertSame(0, Presentation::query()->where('session_id', $session->id)->count());
    }

    #[Test]
    public function state_returns_304_for_viewers_but_never_for_the_host(): void
    {
        [$teacher, $course, , $path] = $this->talk();
        $this->post($path . '/start');
        $this->learnerIn($course);

        $first = $this->get($path . '/state');
        $this->assertOk($first);
        $this->assertSame('private, no-cache', $first->headers['Cache-Control'] ?? null);
        $etag = $first->headers['ETag'] ?? '';

        $second = $this->get($path . '/state', ['headers' => ['If-None-Match' => $etag]]);
        $this->assertStatus(304, $second);
        $this->assertSame('', $second->body);
        $this->assertSame($etag, $second->headers['ETag'] ?? null);

        $this->as($teacher['user'], ['training.teach']);
        $host = $this->get($path . '/state', ['headers' => ['If-None-Match' => $etag]]);
        $this->assertOk($host);
        $state = $this->assertJsonResponse($host);
        $this->assertCount(1, $state['viewers']);
        $this->assertTrue($state['viewers'][0]['in_course']);
        $this->assertNotSame('', $state['viewers'][0]['name']);
        $this->assertSame($teacher['user'], $state['presenter']['id']);
        $this->assertNull($state['handover']);
    }

    #[Test]
    public function etag_contains_the_talk_id(): void
    {
        [$teacher, $course, $session, $path] = $this->talk();
        $this->post($path . '/start');
        $learner = $this->learnerIn($course);

        $first = $this->get($path . '/state');
        $id    = $this->live($session)->id;
        $etag  = $first->headers['ETag'] ?? '';
        $this->assertSame('"' . $id . '-0-0"', $etag);
        $this->assertSame($id, $this->assertJsonResponse($first)['id']);

        // Ein neuer Lauf desselben Termins beginnt wieder bei 0-0 und darf nicht mit 304 antworten.
        $this->as($teacher['user'], ['training.teach']);
        $this->post($path . '/end');
        $this->post($path . '/start');
        $this->as($learner['user'], ['training.view']);
        $third = $this->get($path . '/state', ['headers' => ['If-None-Match' => $etag]]);
        $this->assertOk($third);
        $this->assertNotSame($id, $this->assertJsonResponse($third)['id']);
    }

    #[Test]
    public function events_get_server_side_seq_without_gaps(): void
    {
        [, $course, , $path] = $this->talk();
        $this->post($path . '/start');

        $first = $this->assertJsonResponse($this->postJson($path . '/events', ['page_count' => 3, 'events' => [$this->stroke(1), $this->stroke(2)]]));
        $this->assertSame(2, $first['seq']);
        $laser  = ['kind' => 'laser', 'p' => [0.5, 0.5], 't' => 0];
        $second = $this->assertJsonResponse($this->postJson($path . '/events', ['page_count' => 3, 'events' => [$this->stroke(3), $laser]]));
        $this->assertSame(4, $second['seq']);

        $this->learnerIn($course);
        $all = $this->assertJsonResponse($this->get($path . '/state'));
        $this->assertSame('live', $all['status']);
        $this->assertSame(4, $all['seq']);
        // Wer neu dazukommt, bekommt keine Laser.
        $this->assertSame([1, 2, 3], array_column($all['events'], 'seq'));
        $this->assertArrayNotHasKey('viewers', $all);

        $later = $this->assertJsonResponse($this->get($path . '/state', ['query' => ['after' => '2']]));
        $this->assertSame([3, 4], array_column($later['events'], 'seq'));
        $this->assertSame('begin', $later['events'][0]['kind']);
        $this->assertSame(3, $later['events'][0]['id']);
        $this->assertSame('laser', $later['events'][1]['kind']);
    }

    #[Test]
    public function invalid_events_are_rejected(): void
    {
        [, , $session, $path] = $this->talk();
        $this->post($path . '/start');

        $cases = [
            'Farbe'      => ['page_count' => 3, 'events' => [$this->stroke(1), $this->stroke(2, ['color' => '#ff00ff'])]],
            'zu viele'   => ['page_count' => 3, 'events' => array_map(fn (int $i): array => $this->stroke($i), range(1, 201))],
            'Seite'      => ['page_count' => 3, 'page' => 3, 'events' => []],
            'Modus'      => ['page_count' => 3, 'mode' => 'screen', 'events' => []],
            'Seitenzahl' => ['page_count' => 501, 'events' => []],
        ];
        foreach ($cases as $label => $body) {
            $response = $this->postJson($path . '/events', $body);
            $this->assertStatus(422, $response);
            $this->assertSame(['error' => 'invalid'], $this->assertJsonResponse($response), $label);
        }

        $p = $this->live($session);
        $this->assertSame(0, $p->seq);
        $this->assertSame(0, $p->version);
        $this->assertSame(0, PresentationEvent::query()->where('presentation_id', $p->id)->count());
    }

    #[Test]
    public function learner_cannot_send_end_or_hand_over(): void
    {
        [, $course, $session, $path] = $this->talk();
        $this->post($path . '/start');
        $instructor = $this->instructor($course);
        $this->learnerIn($course);

        $this->assertForbidden($this->postJson($path . '/events', ['page_count' => 3, 'events' => [$this->stroke(1)]]));
        $this->assertForbidden($this->postJson($path . '/handover', ['user_id' => $instructor['user']]));
        $this->assertForbidden($this->postJson($path . '/handover/cancel'));
        $this->assertRedirect($this->post($path . '/end'), $path);
        $this->assertSame('Den Vortrag beendet der Host oder die Leitung.', $_SESSION['flash']['text'] ?? null);

        $p = $this->live($session);
        $this->assertTrue($p->isLive());
        $this->assertNull($p->handover_to);
        $this->assertSame(0, $p->seq);
    }

    #[Test]
    public function the_lead_can_end_a_talk_hosted_by_an_instructor(): void
    {
        [$teacher, $course, $session, $path] = $this->talk();
        $instructor = $this->instructor($course);
        $this->as($instructor['user'], ['training.teach']);
        $this->post($path . '/start');

        $this->as($teacher['user'], ['training.teach']);
        $this->assertRedirect($this->post($path . '/end'));
        $this->assertFalse($this->live($session)->isLive());
    }

    #[Test]
    public function orphaned_talk_ends_at_the_last_sign_of_life(): void
    {
        [$teacher, $course, $session, $path] = $this->talk();
        $this->post($path . '/start');
        $start = Carbon::now()->subHours(3)->startOfSecond();
        $seen  = $start->copy()->addMinutes(40);
        Presentation::query()->whereKey($this->live($session)->id)->update(['started_at' => $start, 'presenter_seen_at' => $seen]);

        $this->learnerIn($course);
        $this->assertSame(['status' => 'ended'], $this->assertJsonResponse($this->get($path . '/state')));
        $this->assertSame($seen->getTimestamp(), $this->live($session)->ended_at?->getTimestamp());

        $this->as($teacher['user'], ['training.teach']);
        $this->post($path . '/start');
        $this->assertSame(2, Presentation::query()->where('session_id', $session->id)->count());
        $this->assertSame(1, Presentation::query()->where('session_id', $session->id)->whereNull('ended_at')->count());
    }

    #[Test]
    public function viewer_gains_at_most_ten_seconds_per_poll(): void
    {
        [, $course, $session, $path] = $this->talk();
        $this->post($path . '/start');
        $learner = $this->learnerIn($course);
        $own     = Participation::query()->where('course_id', $course->id)->where('personnel_id', $learner['personnel'])->firstOrFail();
        $p       = $this->live($session);
        $now     = new DateTimeImmutable('2026-11-17 18:00:00');
        $watched = fn (): int => PresentationViewer::query()->where('presentation_id', $p->id)->where('user_id', $learner['user'])->firstOrFail()->seconds_watched;

        Presentations::heartbeat($p, $learner['user'], $own->id, $now);
        $this->assertSame(0, $watched());
        Presentations::heartbeat($p, $learner['user'], $own->id, $now->modify('+4 seconds'));
        $this->assertSame(4, $watched());
        // Ein liegengelassener Tab, der nach 20 Minuten wieder fragt.
        Presentations::heartbeat($p, $learner['user'], $own->id, $now->modify('+1204 seconds'));
        $this->assertSame(14, $watched());

        // Der Host zählt nicht als Zuschauer, er hinterlässt ein Lebenszeichen.
        Presentations::heartbeat($p, $p->presenter_id, null, $now);
        $this->assertSame(1, PresentationViewer::query()->where('presentation_id', $p->id)->count());
        $this->assertSame($now->getTimestamp(), Presentation::query()->findOrFail($p->id)->presenter_seen_at->getTimestamp());
    }

    #[Test]
    public function watched_time_is_capped_at_the_talk_length(): void
    {
        [, $course, $session, $path] = $this->talk();
        $this->post($path . '/start');
        $learner    = $this->learnerIn($course);
        $instructor = $this->instructor($course);
        $p          = $this->live($session);
        $start      = Carbon::now()->subHours(3)->startOfSecond();
        Presentation::query()->whereKey($p->id)->update(['started_at' => $start, 'presenter_seen_at' => $start->copy()->addMinutes(40)]);
        // Ein Tab, der nach dem letzten Lebenszeichen des Hosts weiterzählte, kommt über die Vortragsdauer.
        foreach ([[$learner['user'], 50 * 60], [$instructor['user'], 10 * 60]] as [$userId, $seconds]) {
            PresentationViewer::query()->create(['presentation_id' => $p->id, 'user_id' => $userId,
                'first_seen_at' => $start, 'last_seen_at' => $start, 'seconds_watched' => $seconds]);
        }

        $this->assertNull(Presentations::live($session, new DateTimeImmutable()));

        $watched = PresentationViewer::query()->where('presentation_id', $p->id)->pluck('seconds_watched', 'user_id')->all();
        $this->assertSame(40 * 60, (int) $watched[$learner['user']]);
        $this->assertSame(10 * 60, (int) $watched[$instructor['user']]);
    }

    #[Test]
    public function ended_talk_refuses_events(): void
    {
        [, , $session, $path] = $this->talk();
        $this->post($path . '/start');
        $stale = $this->live($session);
        Presentation::query()->whereKey($stale->id)->update(['ended_at' => Carbon::now()]);

        // Über die Route ist ein beendeter Vortrag schlicht nicht mehr da.
        $this->assertNotFound($this->postJson($path . '/events', ['page_count' => 3, 'events' => [$this->stroke(1)]]));
        // Endet er zwischen Lesen und Sperre, meldet apply() das Ende; der Controller macht daraus 409.
        try {
            Presentations::apply($stale, [$this->stroke(1)], 3, null, null);
            $this->fail('Ein beendeter Vortrag nimmt keine Ereignisse an.');
        } catch (\DomainException) {
        }
        $this->assertSame(0, PresentationEvent::query()->where('presentation_id', $stale->id)->count());
    }

    #[Test]
    public function end_is_idempotent(): void
    {
        [, $course, $session, $path] = $this->talk();
        $this->post($path . '/start');
        $this->postJson($path . '/events', ['page_count' => 3, 'events' => [$this->stroke(1)]]);
        $p      = $this->live($session);
        $back   = '/training/manage/' . $course->id . '?attendance=' . $session->id . '#termine';
        $before = $this->audits('Vortrag beendet');

        $this->assertRedirect($this->post($path . '/end'), $back);
        $ended = $this->live($session)->ended_at?->getTimestamp();
        $this->assertRedirect($this->post($path . '/end'), $back);
        $this->assertFalse(Presentations::end($p));

        $this->assertSame($before + 1, $this->audits('Vortrag beendet'));
        $this->assertSame($ended, $this->live($session)->ended_at?->getTimestamp());
        $this->assertSame(0, PresentationEvent::query()->where('presentation_id', $p->id)->count());
        $this->assertSame(['status' => 'ended'], $this->assertJsonResponse($this->get($path . '/state')));
        $this->assertRedirect($this->get($path), '/training/manage/' . $course->id);
    }

    #[Test]
    public function handover_needs_acceptance(): void
    {
        [$teacher, $course, $session, $path] = $this->talk();
        $this->post($path . '/start');
        $instructor = $this->instructor($course);
        $learner    = $this->learnerIn($course);

        $this->as($teacher['user'], ['training.teach']);
        foreach ([$learner['user'], $teacher['user'], 0] as $target) {
            $refused = $this->postJson($path . '/handover', ['user_id' => $target]);
            $this->assertStatus(422, $refused);
            $this->assertSame(['error' => 'target'], $this->assertJsonResponse($refused));
        }
        $this->assertNull($this->live($session)->handover_to);
        $this->assertOk($this->postJson($path . '/handover', ['user_id' => $instructor['user']]));
        $this->assertSame($instructor['user'], $this->live($session)->handover_to);
        $state = $this->assertJsonResponse($this->get($path . '/state'));
        $this->assertSame($instructor['user'], $state['handover']['to_id']);
        $this->assertNotSame('', $state['handover']['to_name']);

        $this->as($learner['user'], ['training.view']);
        $this->assertForbidden($this->postJson($path . '/handover/accept'));

        // Der alte Host war lange still; der neue darf nicht gleich als verwaist gelten.
        Presentation::query()->whereKey($this->live($session)->id)->update(['presenter_seen_at' => Carbon::now()->subMinutes(20)]);
        $this->as($instructor['user'], ['training.teach']);
        $this->assertOk($this->postJson($path . '/handover/accept'));
        $p = $this->live($session);
        $this->assertSame($instructor['user'], $p->presenter_id);
        $this->assertNull($p->handover_to);
        $this->assertGreaterThan(Carbon::now()->subMinute()->getTimestamp(), $p->presenter_seen_at->getTimestamp());
        $this->assertBodyContains('data-role="present"', $this->get($path));
    }

    #[Test]
    public function host_can_withdraw_or_redirect_a_handover(): void
    {
        [$teacher, $course, $session, $path] = $this->talk();
        $this->post($path . '/start');
        $first  = $this->instructor($course);
        $second = $this->instructor($course);
        $this->postJson($path . '/handover', ['user_id' => $first['user']]);

        // Die Zielperson darf die Anfrage nicht zurücknehmen.
        $this->as($first['user'], ['training.teach']);
        $this->assertForbidden($this->postJson($path . '/handover/cancel'));
        $this->assertSame($first['user'], $this->live($session)->handover_to);

        $this->as($teacher['user'], ['training.teach']);
        $version = $this->live($session)->version;
        $this->assertOk($this->postJson($path . '/handover/cancel'));
        $p = $this->live($session);
        $this->assertNull($p->handover_to);
        $this->assertSame($version + 1, $p->version);

        $this->as($first['user'], ['training.teach']);
        $this->assertForbidden($this->postJson($path . '/handover/accept'));
        $this->assertSame($teacher['user'], $this->live($session)->presenter_id);

        // Umleiten: die neue Anfrage ersetzt die alte.
        $this->as($teacher['user'], ['training.teach']);
        $this->postJson($path . '/handover', ['user_id' => $first['user']]);
        $this->assertOk($this->postJson($path . '/handover', ['user_id' => $second['user']]));
        $this->as($first['user'], ['training.teach']);
        $this->assertForbidden($this->postJson($path . '/handover/accept'));

        $this->as($second['user'], ['training.teach']);
        $version = $this->live($session)->version;
        $this->assertOk($this->postJson($path . '/handover/decline'));
        $p = $this->live($session);
        $this->assertNull($p->handover_to);
        $this->assertSame($teacher['user'], $p->presenter_id);
        $this->assertSame($version + 1, $p->version);
    }

    #[Test]
    public function handover_to_staff_without_teach_permission_is_refused(): void
    {
        [, $course, $session, $path] = $this->talk();
        $this->post($path . '/start');
        $lapsed = $this->instructor($course, ['training.view']);
        $active = $this->instructor($course);

        $refused = $this->postJson($path . '/handover', ['user_id' => $lapsed['user']]);
        $this->assertStatus(422, $refused);
        $this->assertSame(['error' => 'target'], $this->assertJsonResponse($refused));
        $this->assertNull($this->live($session)->handover_to);

        $this->assertSame([$active['user']], array_column(Presentations::targets($this->live($session)), 'id'));
    }

    #[Test]
    public function learner_without_open_materials_cannot_follow(): void
    {
        [, $course, , $path] = $this->talk('planned');
        $this->assertRedirect($this->post($path . '/start'), $path);
        $this->learnerIn($course);

        $this->assertNotFound($this->get($path . '/state'));
        $this->assertNotFound($this->get($path));

        $course->status = 'running';
        $course->save();
        $this->assertOk($this->get($path . '/state'));
    }
}
