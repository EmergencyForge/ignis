<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Auth\FabricaClient;
use App\Models\User;
use DateTimeImmutable;
use EmergencyForge\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as HttpResponse;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Training\AiDrafts;
use Plugin\Training\Models\Course;
use Plugin\Training\Models\CourseStaff;
use Plugin\Training\Models\CourseType;
use Plugin\Training\Models\Module;
use Plugin\Training\Models\Participation;
use Plugin\Training\Models\Presentation;
use Plugin\Training\Models\PresentationEvent;
use Plugin\Training\Models\PresentationQuestion;
use Plugin\Training\Models\PresentationViewer;
use Plugin\Training\Models\PresentationVisit;
use Plugin\Training\Models\Session;
use Plugin\Training\Models\TrainingFile;
use Plugin\Training\Models\Unit;
use Plugin\Training\Models\UnitProgress;
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
        AiDrafts::useClient(null);
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

    private function course(CourseType $type, string $status, ?int $seats = null, ?int $lead = null, int $number = 1): Course
    {
        $course = new Course();
        $course->course_type_id = $type->id;
        $course->title          = $type->short . ' 1/2026';
        $course->number         = $number;
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

    /** Präsentation im Lehrplan der Lehrgangsart; mit Folien, wenn eine Datei da ist. */
    private function presentation(Course $course, ?TrainingFile $file = null, bool $afterTalk = true): Unit
    {
        return $this->unit($course->type, ['kind' => 'presentation', 'title' => 'Vortrag', 'file_id' => $file?->id, 'slides_after_talk' => $afterTalk]);
    }

    private function session(Course $course, ?Unit $unit = null): Session
    {
        $session = new Session();
        $session->course_id      = $course->id;
        $session->title          = 'Theorie';
        $session->starts_at      = Carbon::now()->addDay();
        $session->ends_at        = Carbon::now()->addDay()->addHours(2);
        $session->required       = true;
        $session->unit_id        = $unit?->id;
        $session->module_id      = $unit?->module_id;
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
    public function migration_detaches_slides_and_ends_running_talks(): void
    {
        // Die Migration ändert Schema (DDL beendet die Test-Transaktion); ab hier läuft alles ohne.
        Capsule::connection()->rollBack();
        $migration = $this->migration();
        $teacher   = $this->account();
        $type      = $this->type();
        $course    = $this->course($type, 'running');
        $file      = $this->pdf();
        $unit      = $this->unit($type, ['kind' => 'session']);
        $roleId    = (int) Capsule::table('intra_users')->where('id', $teacher['user'])->value('role');
        $room = $this->roomMigration();
        try {
            // Die Migration kennt noch `screen_room`; den späteren Stand der Spalten also zuerst zurücknehmen.
            $room->down();
            $migration->down();
            $this->assertTrue(Capsule::schema()->hasColumn('intra_training_sessions', 'slides_file_id'));
            $this->assertFalse(Capsule::schema()->hasColumn('intra_training_units', 'slides_after_talk'));

            // Zustand vor der Migration: Termin mit Folien, ein laufender und ein beendeter Vortrag.
            $sessionId = (int) Capsule::table('intra_training_sessions')->insertGetId([
                'course_id' => $course->id, 'unit_id' => $unit->id, 'slides_file_id' => $file->id, 'title' => 'Theorie',
                'starts_at' => '2026-11-12 18:00:00', 'ends_at' => '2026-11-12 20:00:00', 'required' => 1,
            ]);
            $talk = static fn (?string $endedAt): int => (int) Capsule::table('intra_training_presentations')->insertGetId([
                'session_id' => $sessionId, 'presenter_id' => $teacher['user'], 'started_at' => '2026-11-12 18:00:00', 'ended_at' => $endedAt,
                'mode' => 'screen', 'screen_via' => 'direct', 'screen_room' => 'raum', 'seq' => 2, 'presenter_seen_at' => '2026-11-12 18:30:00', 'handover_to' => $teacher['user'],
            ]);
            $running = $talk(null);
            $ended   = $talk('2026-11-12 19:00:00');
            foreach ([$running, $ended] as $id) {
                Capsule::table('intra_training_presentation_events')->insert([
                    ['presentation_id' => $id, 'seq' => 1, 'kind' => 'laser', 'data' => '{}', 'created_at' => '2026-11-12 18:10:00'],
                    ['presentation_id' => $id, 'seq' => 2, 'kind' => 'begin', 'data' => '{}', 'created_at' => '2026-11-12 18:10:01'],
                ]);
            }
            // Ein Zuschauer des laufenden Vortrags, der nach dem letzten Lebenszeichen noch gezählt hat.
            Capsule::table('intra_training_presentation_viewers')->insert([
                'presentation_id' => $running, 'user_id' => $teacher['user'], 'first_seen_at' => '2026-11-12 18:00:00', 'last_seen_at' => '2026-11-12 18:40:00', 'seconds_watched' => 3000,
            ]);

            $migration->up();

            $this->assertFalse(Capsule::schema()->hasColumn('intra_training_sessions', 'slides_file_id'));
            $this->assertTrue(Capsule::schema()->hasColumn('intra_training_units', 'slides_after_talk'));
            $this->assertSame(1, (int) Capsule::table('intra_training_units')->where('id', $unit->id)->value('slides_after_talk'));
            $this->assertNotNull(TrainingFile::query()->find($file->id));

            $ran = Presentation::query()->find($running);
            $this->assertSame('2026-11-12 18:30:00', $ran->ended_at?->format('Y-m-d H:i:s'));
            $this->assertSame(['slides', null, null, null], [$ran->mode, $ran->screen_via, $ran->getAttribute('screen_room'), $ran->handover_to]);
            $this->assertSame(0, PresentationEvent::query()->where('presentation_id', $running)->count());
            $this->assertSame(1800, (int) Capsule::table('intra_training_presentation_viewers')->where('presentation_id', $running)->value('seconds_watched'));
            // Ein bereits beendeter Vortrag bleibt, wie er war.
            $this->assertSame('2026-11-12 19:00:00', Presentation::query()->find($ended)->ended_at?->format('Y-m-d H:i:s'));
            $this->assertSame(2, PresentationEvent::query()->where('presentation_id', $ended)->count());
        } finally {
            // Auch nach einem halb gelaufenen down() das Schema des Zielstands herstellen.
            $hasSlides = Capsule::schema()->hasColumn('intra_training_sessions', 'slides_file_id');
            $hasSwitch = Capsule::schema()->hasColumn('intra_training_units', 'slides_after_talk');
            if ($hasSlides && $hasSwitch) {
                Capsule::schema()->table('intra_training_units', static fn ($table) => $table->dropColumn('slides_after_talk'));
            }
            if ($hasSlides) {
                $migration->up();
            } elseif (!$hasSwitch) {
                Capsule::schema()->table('intra_training_units', static fn ($table) => $table->boolean('slides_after_talk')->default(true));
            }
            if (Capsule::schema()->hasColumn('intra_training_presentations', 'screen_room')) {
                $room->up();
            }
            Capsule::table('intra_training_courses')->where('id', $course->id)->delete();
            Capsule::table('intra_training_modules')->where('course_type_id', $type->id)->delete();
            Capsule::table('intra_training_course_types')->where('id', $type->id)->delete();
            Capsule::table('intra_training_files')->where('id', $file->id)->delete();
            Capsule::table('intra_users')->where('id', $teacher['user'])->delete();
            Capsule::table('intra_mitarbeiter')->where('id', $teacher['personnel'])->delete();
            Capsule::table('intra_users_roles')->where('id', $roleId)->delete();
        }
    }

    private function roomMigration(): \AddIntraTrainingPresentationQuestions
    {
        require_once dirname(__DIR__, 2) . '/plugins/training/migrations/20261111000003_add_intra_training_presentation_questions.php';
        $adapter   = new \Phinx\Db\Adapter\MysqlAdapter(['name' => (string) $_ENV['DB_NAME']]);
        $adapter->setConnection(Capsule::connection()->getPdo());
        $migration = new \AddIntraTrainingPresentationQuestions('testing', 20261111000003);
        $migration->setAdapter($adapter);

        return $migration;
    }

    private function migration(): \AddIntraTrainingPresentationUnits
    {
        require_once dirname(__DIR__, 2) . '/plugins/training/migrations/20261111000002_add_intra_training_presentation_units.php';
        $adapter   = new \Phinx\Db\Adapter\MysqlAdapter(['name' => (string) $_ENV['DB_NAME']]);
        $adapter->setConnection(Capsule::connection()->getPdo());
        $migration = new \AddIntraTrainingPresentationUnits('testing', 20261111000002);
        $migration->setAdapter($adapter);

        return $migration;
    }

    #[Test]
    public function presentation_unit_takes_slides_and_the_switch(): void
    {
        $type   = $this->type();
        $module = new Module();
        $module->course_type_id = $type->id;
        $module->title          = 'Grundlagen';
        $module->save();
        $this->as($this->account()['user'], ['training.manage']);
        $url = '/training/modules/' . $module->id . '/units';

        // Hochgeladene PDF, Schalter aus: der Haken fehlt im Formular.
        $this->assertRedirect($this->post($url, ['kind' => 'presentation', 'title' => 'Eigensicherung'], [
            'files' => ['file' => $this->upload("%PDF-1.4\n%%EOF\n", 'Folien.pdf')],
        ]));
        $unit = Unit::query()->where('module_id', $module->id)->where('title', 'Eigensicherung')->firstOrFail();
        $this->forgetStored($unit->file_id);
        $this->assertSame('presentation', $unit->kind);
        $this->assertFalse($unit->slides_after_talk);
        $this->assertFalse($unit->allow_download);
        $this->assertSame('Folien.pdf', $unit->file?->original_name);

        // Ohne Folien ist die Präsentation gültig; der Schalter wird gespeichert.
        $this->assertRedirect($this->post($url, ['kind' => 'presentation', 'title' => 'Ohne Folien', 'slides_after_talk' => '1']));
        $bare = Unit::query()->where('module_id', $module->id)->where('title', 'Ohne Folien')->firstOrFail();
        $this->assertNull($bare->file_id);
        $this->assertTrue($bare->slides_after_talk);

        // Beim Bearbeiten ohne neuen Upload bleiben die Folien; ein Wechsel der Art löst sie.
        $this->post('/training/units/' . $unit->id, ['kind' => 'presentation', 'title' => 'Eigensicherung', 'slides_after_talk' => '1']);
        $this->assertSame($unit->file_id, Unit::query()->find($unit->id)->file_id);
        $this->assertTrue(Unit::query()->find($unit->id)->slides_after_talk);
        $this->post('/training/units/' . $unit->id, ['kind' => 'text', 'title' => 'Eigensicherung', 'doc' => (string) json_encode(['type' => 'doc', 'content' => []])]);
        $this->assertNull(Unit::query()->find($unit->id)->file_id);
    }

    #[Test]
    public function powerpoint_slides_need_the_converter(): void
    {
        $type   = $this->type();
        $module = new Module();
        $module->course_type_id = $type->id;
        $module->title          = 'Grundlagen';
        $module->save();
        $this->as($this->account()['user'], ['training.manage']);
        $url = '/training/modules/' . $module->id . '/units';

        // Die Meldung steht auf dem neu gezeigten Formular.
        $pptx = $this->post($url, ['kind' => 'presentation', 'title' => 'Folien'], ['files' => ['file' => $this->upload($this->pptx(), 'Folien.pptx')]]);
        $this->assertStatus(422, $pptx);
        $this->assertBodyContains('PowerPoint braucht hier den Umwandler. Bitte als PDF hochladen.', $pptx);

        $text = $this->post($url, ['kind' => 'presentation', 'title' => 'Folien'], ['files' => ['file' => $this->upload('nur Text', 'Folien.txt')]]);
        $this->assertStatus(422, $text);
        $this->assertBodyContains('Folien müssen ein PDF oder eine PowerPoint-Datei sein.', $text);
        $this->assertSame(0, Unit::query()->where('module_id', $module->id)->count());
    }

    #[Test]
    public function session_links_a_presentation_unit_and_shows_start(): void
    {
        $teacher = $this->account();
        $type    = $this->type();
        $course  = $this->course($type, 'running', lead: $teacher['user']);
        $slides  = $this->presentation($course, $this->pdf());
        $plain   = $this->unit($type, ['kind' => 'session', 'title' => 'Praxistag']);
        $text    = $this->unit($type, ['kind' => 'text']);
        $this->as($teacher['user'], ['training.teach']);
        $url  = '/training/manage/' . $course->id . '/sessions';
        $form = ['date' => '2026-12-01', 'from' => '18:00', 'until' => '20:00'];

        // Offen ist die Präsentation im Auswahlfeld, ein Artikel nicht.
        $page = $this->get('/training/manage/' . $course->id);
        $this->assertBodyContains('<option value="' . $slides->id . '">Vortrag · Präsentation</option>', $page);
        $this->assertBodyNotContains('<option value="' . $text->id . '">', $page);

        $this->post($url, $form + ['unit_id' => (string) $text->id]);
        $this->assertSame(0, Session::query()->where('course_id', $course->id)->count());

        $this->assertRedirect($this->post($url, $form + ['unit_id' => (string) $slides->id]));
        $session = Session::query()->where('course_id', $course->id)->firstOrFail();
        $this->assertSame($slides->id, $session->unit_id);
        $this->assertSame($slides->module_id, $session->module_id);
        $this->assertSame('Vortrag', $session->title);

        // Eine Einheit hat höchstens einen Termin, auch eine Präsentation.
        $this->post($url, $form + ['unit_id' => (string) $slides->id]);
        $this->assertSame('Für diese Einheit gibt es schon einen Termin.', $_SESSION['flash']['text'] ?? null);
        $this->assertSame(1, Session::query()->where('course_id', $course->id)->count());

        $this->post($url, $form + ['unit_id' => (string) $plain->id]);
        $both = $this->get('/training/manage/' . $course->id);
        $this->assertOk($both);
        // Der Start-Knopf gehört nur zum Termin der Präsentation.
        $this->assertSame(1, substr_count($both->body, 'Vortrag starten'));
        $this->assertBodyContains('<span class="ignis-chip ignis-chip--sm">Präsentation</span>', $both);
        $this->assertBodyNotContains('Folien speichern', $both);
    }

    #[Test]
    public function session_card_flags_slides_that_are_still_converting(): void
    {
        $teacher = $this->account();
        $course  = $this->course($this->type(), 'running', lead: $teacher['user']);
        $pptx    = $this->pdf();
        $pptx->mime           = \Plugin\Training\Converter::PPTX;
        $pptx->original_name  = 'Folien.pptx';
        $pptx->convert_status = \Plugin\Training\Converter::PENDING;
        $pptx->save();
        $this->session($course, $this->presentation($course, $pptx));
        $this->as($teacher['user'], ['training.teach']);

        $page = $this->get('/training/manage/' . $course->id);

        $this->assertOk($page);
        $this->assertBodyContains('PowerPoint wird umgewandelt', $page);
        $this->assertBodyNotContains('Vortrag starten', $page);
    }

    #[Test]
    public function start_needs_a_presentation_unit_not_slides(): void
    {
        [, , , $path] = $this->talk(slides: false);

        // Eine Präsentation ohne Folien startet, der Vortrag läuft dann ohne PDF.
        $this->assertRedirect($this->post($path . '/start'), $path);
        $this->assertSame(1, Presentation::query()->whereNull('ended_at')->count());

        // Ein Termin ohne Präsentation hat nichts zu zeigen.
        $type  = $this->type();
        $other = $this->course($type, 'running', lead: $this->account(permissions: ['training.teach'])['user']);
        foreach ([null, $this->unit($type, ['kind' => 'session'])] as $unit) {
            $session = $this->session($other, $unit);
            $this->as((int) $other->lead_user_id, ['training.teach']);
            $this->assertRedirect($this->post('/training/courses/' . $other->id . '/sessions/' . $session->id . '/presentation/start'), '/training/manage/' . $other->id . '#termine');
            $this->assertSame('Dieser Termin hat keine Präsentation.', $_SESSION['flash']['text'] ?? null);
            $this->assertSame(0, Presentation::query()->where('session_id', $session->id)->count());
        }

        // PowerPoint, die noch nicht umgewandelt ist, lässt sich nicht zeigen.
        $pptx = $this->pdf();
        $pptx->mime           = \Plugin\Training\Converter::PPTX;
        $pptx->convert_status = \Plugin\Training\Converter::PENDING;
        $pptx->save();
        $waiting = $this->session($other, $this->presentation($other, $pptx));
        $this->assertRedirect($this->post('/training/courses/' . $other->id . '/sessions/' . $waiting->id . '/presentation/start'), '/training/manage/' . $other->id . '#termine');
        $this->assertSame('Die Folien werden noch umgewandelt.', $_SESSION['flash']['text'] ?? null);
        $this->assertSame(0, Presentation::query()->where('session_id', $waiting->id)->count());
        $pptx->convert_status = \Plugin\Training\Converter::FAILED;
        $pptx->save();
        $this->post('/training/courses/' . $other->id . '/sessions/' . $waiting->id . '/presentation/start');
        $this->assertSame('Die Folien ließen sich nicht umwandeln.', $_SESSION['flash']['text'] ?? null);
        $this->assertSame(0, Presentation::query()->where('session_id', $waiting->id)->count());

        // Lernende starten nichts.
        [, $course2, $session2, $path2] = $this->talk();
        $this->learnerIn($course2);
        $this->assertRedirect($this->post($path2 . '/start'), '/training/courses/' . $course2->id);
        $this->assertSame(0, Presentation::query()->where('session_id', $session2->id)->count());
    }

    #[Test]
    public function slides_only_during_the_talk_stay_hidden_otherwise(): void
    {
        [$teacher, $course, $session, $path] = $this->talk(afterTalk: false);
        $unit  = $session->unit;
        $file  = $unit->file;
        $slides = '/training/courses/' . $course->id . '/sessions/' . $session->id . '/slides';
        $files  = '/training/courses/' . $course->id . '/files/' . $file->id;
        $unitUrl = '/training/courses/' . $course->id . '/units/' . $unit->id;
        $pages   = ['course' => $course->id, 'pages' => [1], 'total' => 1];
        $learner = $this->learnerIn($course);

        // Ohne laufenden Vortrag sieht ein Teilnehmer nichts davon.
        $this->assertNotFound($this->get($slides));
        $this->assertNotFound($this->get($files));
        $this->assertNotFound($this->postJson('/api/training/units/' . $unit->id . '/pages', $pages));
        $this->assertBodyContains('Die Folien gibt es nur im Vortrag.', $this->get($unitUrl));
        $this->assertNull(UnitProgress::query()->where('unit_id', $unit->id)->first());

        // Ausbilder immer.
        $this->as($teacher['user'], ['training.teach']);
        $this->assertOk($this->get($slides));
        $this->assertOk($this->get($files));
        $this->assertBodyContains('data-ignis-document-viewer', $this->get($unitUrl));

        // Mit laufendem Vortrag auch der Teilnehmer, mit Hinweis zum Mitverfolgen.
        $this->post($path . '/start');
        $this->as($learner['user'], ['training.view']);
        $this->assertOk($this->get($slides));
        $this->assertOk($this->get($files));
        $viewer = $this->get($unitUrl);
        $this->assertBodyContains('data-ignis-document-viewer', $viewer);
        $this->assertBodyContains('Live mitverfolgen', $viewer);
        // Ansehen ja, zählen nein: nur Folien, die nach dem Vortrag offen bleiben, haken ab.
        $this->assertBodyNotContains('data-pages-url', $viewer);
        $this->assertNotFound($this->postJson('/api/training/units/' . $unit->id . '/pages', $pages));

        // Nach dem Ende ist wieder zu.
        $this->as($teacher['user'], ['training.teach']);
        $this->post($path . '/end');
        $this->as($learner['user'], ['training.view']);
        $this->assertNotFound($this->get($slides));
        $this->assertNotFound($this->get($files));
        $this->assertNotFound($this->get($files, ['query' => ['download' => '1']]));

        // Mit dem Schalter an bleibt das PDF im Lehrgang.
        $unit->slides_after_talk = true;
        $unit->save();
        $this->assertOk($this->get($slides));
        $this->assertOk($this->get($files));
        $this->assertNotFound($this->get($files, ['query' => ['download' => '1']]));
    }

    #[Test]
    public function slides_stay_closed_for_another_course_of_the_same_type(): void
    {
        $teacher = $this->account(permissions: ['training.teach']);
        $type    = $this->type();
        $courseA = $this->course($type, 'running', lead: $teacher['user']);
        $courseB = $this->course($type, 'running', lead: $teacher['user'], number: 2);
        $unit    = $this->presentation($courseA, $this->pdf(), afterTalk: false);
        $sessionA = $this->session($courseA, $unit);
        $sessionB = $this->session($courseB, $unit);
        $learnerA = $this->account();
        $learnerB = $this->account();
        $this->admit($courseA, $learnerA['personnel']);
        $this->admit($courseB, $learnerB['personnel']);
        $this->as($teacher['user'], ['training.teach']);
        $this->post('/training/courses/' . $courseA->id . '/sessions/' . $sessionA->id . '/presentation/start');

        $filePath = static fn (Course $c): string => '/training/courses/' . $c->id . '/files/' . $unit->file_id;
        $pages    = static fn (Course $c): array => ['course' => $c->id, 'pages' => [1], 'total' => 1];

        $this->as($learnerB['user'], ['training.view']);
        $this->assertNotFound($this->get($filePath($courseB)));
        $this->assertNotFound($this->get('/training/courses/' . $courseB->id . '/sessions/' . $sessionB->id . '/slides'));
        $this->assertNotFound($this->postJson('/api/training/units/' . $unit->id . '/pages', $pages($courseB)));
        $own = $this->get('/training/courses/' . $courseB->id . '/units/' . $unit->id);
        $this->assertBodyContains('Die Folien gibt es nur im Vortrag.', $own);
        $this->assertBodyNotContains('Live mitverfolgen', $own);

        $this->as($learnerA['user'], ['training.view']);
        $this->assertOk($this->get($filePath($courseA)));
        $this->assertOk($this->get('/training/courses/' . $courseA->id . '/sessions/' . $sessionA->id . '/slides'));
        $this->assertNotFound($this->postJson('/api/training/units/' . $unit->id . '/pages', $pages($courseA)));
        $this->assertBodyContains('Live mitverfolgen', $this->get('/training/courses/' . $courseA->id . '/units/' . $unit->id));
    }

    #[Test]
    public function a_kept_file_must_still_be_valid_slides_when_the_kind_becomes_presentation(): void
    {
        $type = $this->type();
        $docx = $this->pdf();
        $docx->mime          = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        $docx->original_name = 'Skript.docx';
        $docx->save();
        $pptx = $this->pdf();
        $pptx->mime           = \Plugin\Training\Converter::PPTX;
        $pptx->original_name  = 'Folien.pptx';
        $pptx->convert_status = \Plugin\Training\Converter::PENDING;
        $pptx->save();
        $word  = $this->unit($type, ['kind' => 'file', 'file_id' => $docx->id]);
        $power = $this->unit($type, ['kind' => 'file', 'file_id' => $pptx->id]);
        $good  = $this->unit($type, ['kind' => 'file', 'file_id' => $this->pdf()->id]);
        $this->as($this->account()['user'], ['training.manage']);

        $refused = $this->post('/training/units/' . $word->id, ['kind' => 'presentation', 'title' => 'Skript']);
        $this->assertStatus(422, $refused);
        $this->assertBodyContains('Folien müssen ein PDF oder eine PowerPoint-Datei sein.', $refused);
        $this->assertSame('file', Unit::query()->find($word->id)->kind);

        $refused = $this->post('/training/units/' . $power->id, ['kind' => 'presentation', 'title' => 'Folien']);
        $this->assertStatus(422, $refused);
        $this->assertBodyContains('PowerPoint braucht hier den Umwandler. Bitte als PDF hochladen.', $refused);
        $this->assertSame('file', Unit::query()->find($power->id)->kind);

        $this->assertRedirect($this->post('/training/units/' . $good->id, ['kind' => 'presentation', 'title' => 'PDF']));
        $this->assertSame('presentation', Unit::query()->find($good->id)->kind);
    }

    #[Test]
    public function slides_cannot_change_while_a_talk_runs(): void
    {
        [, , $session, $path] = $this->talk(afterTalk: false);
        $unit = $session->unit;
        $file = $unit->file_id;
        $this->post($path . '/start');
        $this->as($this->account()['user'], ['training.manage']);
        $message = 'Während des Vortrags lassen sich die Folien nicht ändern.';

        $upload = $this->post('/training/units/' . $unit->id, ['kind' => 'presentation', 'title' => 'Neu'], ['files' => ['file' => $this->upload("%PDF-1.4\n%%EOF\n", 'Neu.pdf')]]);
        $this->assertStatus(422, $upload);
        $this->assertBodyContains($message, $upload);
        $this->assertSame($file, Unit::query()->find($unit->id)->file_id);

        $kind = $this->post('/training/units/' . $unit->id, ['kind' => 'text', 'title' => 'Neu', 'doc' => (string) json_encode(['type' => 'doc', 'content' => []])]);
        $this->assertStatus(422, $kind);
        $this->assertSame(['presentation', $file], [Unit::query()->find($unit->id)->kind, Unit::query()->find($unit->id)->file_id]);

        // Ohne neue Folien lässt sich die Einheit weiter bearbeiten.
        $this->assertRedirect($this->post('/training/units/' . $unit->id, ['kind' => 'presentation', 'title' => 'Umbenannt']));
        $this->assertSame('Umbenannt', Unit::query()->find($unit->id)->title);
    }

    #[Test]
    public function the_new_unit_form_defaults_the_switch_to_on(): void
    {
        $type   = $this->type();
        $module = new Module();
        $module->course_type_id = $type->id;
        $module->title          = 'Grundlagen';
        $module->save();
        $off = $this->unit($type, ['kind' => 'presentation', 'slides_after_talk' => false]);
        $this->as($this->account()['user'], ['training.manage']);
        $checked = 'name="slides_after_talk" value="1" checked';

        $this->assertBodyContains($checked, $this->get('/training/modules/' . $module->id . '/units/create', ['query' => ['kind' => 'presentation']]));
        $edit = $this->get('/training/units/' . $off->id . '/edit');
        $this->assertBodyNotContains($checked, $edit);
        $this->assertBodyContains('Abgehakt wird dann über die Anwesenheit am Termin. Wer dort keinen Eintrag hat, lässt sich von Hand abhaken.', $edit);
        $this->assertBodyNotContains('Wer fehlt', $edit);
    }

    #[Test]
    public function slides_are_delivered_to_learners_with_open_materials(): void
    {
        $teacher  = $this->account();
        $type     = $this->type();
        $course   = $this->course($type, 'running', lead: $teacher['user']);
        $session  = $this->session($course, $this->presentation($course, $this->pdf()));
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
        $closedSession = $this->session($closed, $this->presentation($closed, $this->pdf()));
        $this->admit($closed, $learner['personnel']);
        $this->as($learner['user'], ['training.view']);
        $this->assertNotFound($this->get('/training/courses/' . $closed->id . '/sessions/' . $closedSession->id . '/slides'));

        // Ein Termin ohne Präsentation oder ohne Folien liefert nichts.
        $this->as($teacher['user'], ['training.teach']);
        $this->assertOk($this->get($url));
        $this->assertNotFound($this->get('/training/courses/' . $course->id . '/sessions/' . $this->session($course)->id . '/slides'));
        $this->assertNotFound($this->get('/training/courses/' . $course->id . '/sessions/' . $this->session($course, $this->presentation($course))->id . '/slides'));
    }

    #[Test]
    public function events_without_slides_only_take_screen_strokes(): void
    {
        [, , $session, $path] = $this->talk(slides: false);
        $this->post($path . '/start');

        // Ohne Folien zählt die Seite -1 (geteilter Bildschirm), eine Folie gibt es nicht.
        $this->assertOk($this->postJson($path . '/events', ['page_count' => 0, 'events' => [$this->stroke(1, ['page' => -1])]]));
        $this->assertSame(1, PresentationEvent::query()->where('presentation_id', $this->live($session)->id)->count());
        $this->assertOk($this->postJson($path . '/events', ['page_count' => 0, 'events' => [['kind' => 'laser', 'p' => [0.5, 0.5], 't' => 0]]]));
        $this->assertStatus(422, $this->postJson($path . '/events', ['page_count' => 0, 'events' => [$this->stroke(2, ['page' => 0])]]));
        $this->assertStatus(422, $this->postJson($path . '/events', ['page_count' => 0, 'page' => 0, 'events' => []]));
        $this->assertStatus(422, $this->postJson($path . '/events', ['page_count' => -1, 'events' => []]));
        $this->assertStatus(422, $this->postJson($path . '/events', ['page_count' => 501, 'events' => []]));
        $this->assertSame(2, PresentationEvent::query()->where('presentation_id', $this->live($session)->id)->count());
    }

    #[Test]
    public function only_the_latest_view_per_page_is_kept(): void
    {
        [, , , $path] = $this->talk();
        $this->post($path . '/start');
        $view = static fn (int $page, float $z): array => ['kind' => 'view', 'page' => $page, 'z' => $z, 'x' => 0.5, 'y' => 0.5, 'r' => 0, 't' => 0];
        $this->assertOk($this->postJson($path . '/events', ['page_count' => 3, 'events' => [$view(0, 1.25), $this->stroke(1), $view(0, 1.5)]]));
        $this->assertOk($this->postJson($path . '/events', ['page_count' => 3, 'events' => [$view(1, 2.0), $view(0, 1.75)]]));
        $this->assertOk($this->postJson($path . '/events', ['page_count' => 3, 'events' => [$view(0, 2.5), $this->stroke(2)]]));

        $events = $this->assertJsonResponse($this->get($path . '/state', ['query' => ['after' => '0']]))['events'];
        $views  = array_values(array_filter($events, static fn (array $e): bool => $e['kind'] === 'view'));
        usort($views, static fn (array $a, array $b): int => $a['page'] <=> $b['page']);
        $this->assertSame([[0, 2.5], [1, 2.0]], array_map(static fn (array $e): array => [$e['page'], (float) $e['z']], $views));
        $this->assertSame([1, 2], array_values(array_map(static fn (array $e): int => $e['id'], array_filter($events, static fn (array $e): bool => $e['kind'] === 'begin'))));
    }

    /**
     * Leitung (angemeldet, mit Lehrrecht in der DB), Lehrgang, Termin an einer Präsentation (mit Folien, wenn $slides), Pfad zum Vortrag.
     *
     * @return array{0: array{user: int, personnel: int}, 1: Course, 2: Session, 3: string}
     */
    private function talk(string $status = 'running', bool $slides = true, bool $afterTalk = true): array
    {
        $teacher = $this->account(permissions: ['training.teach']);
        $course  = $this->course($this->type(), $status, lead: $teacher['user']);
        $session = $this->session($course, $this->presentation($course, $slides ? $this->pdf() : null, $afterTalk));
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
        $this->assertSame('"' . $id . '-0-0-0"', $etag);
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

    private function notifications(int $userId): int
    {
        return Capsule::table('intra_notifications')->where('user_id', $userId)->where('type', 'training')->where('title', 'like', 'Vortrag läuft%')->count();
    }

    /**
     * Ein beendeter Vortrag, der $talkSeconds lief; $watched je Teilnahme-ID in Sekunden.
     *
     * @param array<int, int> $watched
     */
    private function endedTalk(Session $session, int $hostId, int $talkSeconds, array $watched): Presentation
    {
        $end = Carbon::now();
        $p   = Presentation::query()->create(['session_id' => $session->id, 'presenter_id' => $hostId, 'started_at' => $end->copy()->subSeconds($talkSeconds), 'ended_at' => $end, 'presenter_seen_at' => $end]);
        foreach ($watched as $participationId => $seconds) {
            $this->viewer($p, $participationId, $seconds);
        }

        return $p;
    }

    private function viewer(Presentation $p, int $participationId, int $seconds): void
    {
        $user = $this->account();
        PresentationViewer::query()->create(['presentation_id' => $p->id, 'user_id' => $user['user'], 'participation_id' => $participationId, 'first_seen_at' => Carbon::now(), 'last_seen_at' => Carbon::now(), 'seconds_watched' => $seconds]);
    }

    #[Test]
    public function start_notifies_only_learners_with_open_materials(): void
    {
        [$teacher, $course, , $path] = $this->talk();
        $open    = $this->account();
        $blocked = $this->account();
        $this->admit($course, $open['personnel']);
        $this->admit($course, $blocked['personnel'], 'enrolled');

        $this->post($path . '/start');

        $this->assertSame(1, $this->notifications($open['user']));
        $this->assertSame(0, $this->notifications($blocked['user']));
        $this->assertSame(0, $this->notifications($teacher['user']));
        $row = Capsule::table('intra_notifications')->where('user_id', $open['user'])->where('title', 'like', 'Vortrag läuft%')->first();
        $this->assertSame('Vortrag läuft: Theorie', $row->title);
        $this->assertStringEndsWith($path, (string) $row->link);
        $this->assertSame($this->nameOf($teacher['user']) . ' hält gerade einen Vortrag.', $row->message);

        $this->post($path . '/start');
        $this->assertSame(1, $this->notifications($open['user']));
    }

    #[Test]
    public function notification_failure_does_not_block_the_start(): void
    {
        [, $course, $session, $path] = $this->talk();
        $learner = $this->account();
        $this->admit($course, $learner['personnel']);
        $container = $this->container;
        if (!$container instanceof \DI\Container) {
            $this->markTestSkipped('Braucht den PHP-DI-Container.');
        }
        $original = $container->get(\App\Notifications\NotificationManager::class);
        $container->set(\App\Notifications\NotificationManager::class, new class extends \App\Notifications\NotificationManager {
            public function notify(string $type, array $userIds, array $payload): int
            {
                throw new \RuntimeException('kaputt');
            }
        });
        $previous = ini_set('error_log', sys_get_temp_dir() . '/presentation-notify-test.log');
        try {
            $this->assertRedirect($this->post($path . '/start'), $path);
        } finally {
            ini_set('error_log', (string) $previous);
            $container->set(\App\Notifications\NotificationManager::class, $original);
        }

        $this->assertSame(1, Presentation::query()->where('session_id', $session->id)->whereNull('ended_at')->count());
    }

    #[Test]
    public function course_page_shows_the_live_hint(): void
    {
        [, $course, $session, $path] = $this->talk();
        $this->post($path . '/start');
        $this->learnerIn($course);

        $response = $this->get('/training/courses/' . $course->id);

        $this->assertOk($response);
        $this->assertBodyContains('Vortrag läuft: Theorie', $response);
        $this->assertBodyContains('hält gerade einen Vortrag. Wer mitverfolgt, wird für die Anwesenheit vorgeschlagen.', $response);
        $this->assertBodyContains('/sessions/' . $session->id . '/presentation"', $response);

        // Ohne offene Unterlagen kein Hinweis.
        $course->status = 'planned';
        $course->save();
        $this->assertBodyNotContains('Vortrag läuft', $this->get('/training/courses/' . $course->id));
    }

    #[Test]
    public function course_page_hides_the_live_hint_from_the_host(): void
    {
        [$teacher, $course, , $path] = $this->talk();
        $other = $this->instructor($course);
        $this->post($path . '/start');

        $this->as($teacher['user'], ['training.teach']);
        $this->assertBodyNotContains('Vortrag läuft', $this->get('/training/courses/' . $course->id));

        $this->as($other['user'], ['training.teach']);
        $this->assertBodyContains('Vortrag läuft: Theorie', $this->get('/training/courses/' . $course->id));
    }

    #[Test]
    public function both_views_render(): void
    {
        [, $course, , $path] = $this->talk();
        $other = $this->instructor($course);
        $this->post($path . '/start');

        $host = $this->get($path);
        $this->assertOk($host);
        $this->assertBodyContains('data-role="present"', $host);
        $this->assertBodyContains('data-tr-live', $host);
        $this->assertBodyContains('data-pdfjs="', $host);
        $this->assertBodyContains('data-elapsed="', $host);
        $this->assertBodyContains('plugins/training/assets/css/presentation.css', $host);
        $this->assertBodyContains('plugins/training/assets/js/presentation.js', $host);
        $this->assertBodyContains('data-handover-to="' . $other['user'] . '"', $host);
        $this->assertBodyContains('An wen soll der Vortrag gehen? Die Person muss annehmen.', $host);
        $this->assertBodyContains('data-tool="ellipse"', $host);
        $this->assertBodyContains('style="background:#ef4444"', $host);
        $this->assertBodyContains($path . '/end"', $host);
        $this->assertBodyContains('Vortrag beendet', $host);

        $this->learnerIn($course);
        $follow = $this->get($path);
        $this->assertOk($follow);
        $this->assertBodyContains('data-role="follow"', $follow);
        $this->assertBodyContains('Kurze Pause', $follow);
        $this->assertBodyContains('Verbunden', $follow);
        $this->assertBodyContains('Folien öffnen', $follow);
        $this->assertBodyNotContains('data-handover-to', $follow);
        $this->assertBodyNotContains('data-tool=', $follow);
        $this->assertBodyNotContains($path . '/end"', $follow);
    }

    #[Test]
    public function pointer_is_the_default_tool(): void
    {
        [, , , $path] = $this->talk();
        $this->post($path . '/start');
        $host = $this->get($path);
        $this->assertBodyContains('data-tool="pointer" aria-pressed="true"', $host);
        $this->assertBodyContains('data-tool="laser" aria-pressed="false"', $host);

        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/plugins/training/assets/js/presentation.js');
        $this->assertStringContainsString("pick('pointer')", $js);
        $this->assertStringNotContainsString("pick('laser')", $js);
    }

    #[Test]
    public function both_views_carry_the_waiting_screen(): void
    {
        [, $course, , $path] = $this->talk(slides: false);
        $this->post($path . '/start');

        $host = $this->get($path);
        $this->assertBodyContains('data-role="present"', $host);
        $this->assertBodyContains('Die Übertragung startet in Kürze', $host);
        $this->assertBodyContains('data-slides=""', $host);
        $this->assertBodyContains('data-live-wait-share', $host);
        $this->assertBodyContains('<button type="button" data-live-mode="slides" aria-pressed="true" hidden>', $host);

        $this->learnerIn($course);
        $follow = $this->get($path);
        $this->assertBodyContains('data-role="follow"', $follow);
        $this->assertBodyContains('Die Übertragung startet in Kürze', $follow);
        $this->assertBodyContains('data-slides=""', $follow);
        $this->assertBodyNotContains('data-live-wait-share', $follow);
    }

    #[Test]
    public function dock_has_zoom_and_rotate(): void
    {
        [, , , $path] = $this->talk();
        $this->post($path . '/start');
        $host = $this->get($path);
        foreach (['data-live-zoom-in', 'data-live-zoom-out', 'data-live-fit', 'data-live-rotate', 'data-live-screen-ink'] as $hook) {
            $this->assertBodyContains($hook, $host);
        }
        // Mit Folien bleibt das Segment sichtbar.
        $this->assertBodyContains('<button type="button" data-live-mode="slides" aria-pressed="true">', $host);
    }

    #[Test]
    public function sharing_asks_for_tab_audio_only(): void
    {
        // Nur Tab-Ton: Discord und Spielton dürfen nie bei den Zuschauern landen.
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/plugins/training/assets/js/presentation.js');
        $this->assertStringContainsString("systemAudio: 'exclude'", $js);
        $this->assertStringContainsString('audio: true', $js);
        $this->assertStringNotContainsString('audio: false', $js);
    }

    #[Test]
    public function follower_view_has_sound_controls(): void
    {
        [, $course, , $path] = $this->talk();
        $this->post($path . '/start');
        $this->learnerIn($course);
        $page = $this->get($path);
        $this->assertBodyContains('data-role="follow"', $page);
        foreach (['data-live-audio', 'data-live-mute', 'data-live-volume', 'data-live-unmute'] as $hook) {
            $this->assertBodyContains($hook, $page);
        }
        preg_match_all('~<video\b[^>]*>~', $page->body, $videos);
        $this->assertNotEmpty($videos[0]);
        foreach ($videos[0] as $tag) {
            $this->assertStringContainsString(' muted', $tag);
        }
    }

    #[Test]
    public function ink_travels_over_the_room(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/plugins/training/assets/js/presentation.js');
        foreach (['publishData', 'RoomEvent.DataReceived', 'ScreenShareAudio'] as $needle) {
            $this->assertStringContainsString($needle, $js);
        }
    }

    #[Test]
    public function host_without_other_teachers_sees_the_empty_handover_list(): void
    {
        [, , , $path] = $this->talk();
        $this->post($path . '/start');

        $this->assertBodyContains('Im Lehrgang lehrt sonst niemand.', $this->get($path));
    }

    #[Test]
    public function staff_page_offers_follow_to_non_hosts(): void
    {
        [, $course, , $path] = $this->talk();
        $other = $this->instructor($course);
        $this->assertBodyContains('Vortrag starten', $this->get('/training/manage/' . $course->id));

        $this->post($path . '/start');
        $host = $this->get('/training/manage/' . $course->id);
        $this->assertBodyContains('Vortrag fortsetzen', $host);
        $this->assertBodyNotContains('Mitverfolgen', $host);

        $this->as($other['user'], ['training.teach']);
        $follower = $this->get('/training/manage/' . $course->id);
        $this->assertBodyContains('Mitverfolgen', $follower);
        $this->assertBodyNotContains('Vortrag fortsetzen', $follower);
        $this->assertBodyContains('presentation"', $follower);
    }

    #[Test]
    public function handover_target_sees_the_request_on_the_staff_page(): void
    {
        [, $course, , $path] = $this->talk();
        $other = $this->instructor($course);
        $this->post($path . '/start');
        $this->assertOk($this->postJson($path . '/handover', ['user_id' => $other['user']]));

        $this->as($other['user'], ['training.teach']);
        $response = $this->get('/training/manage/' . $course->id);

        $this->assertBodyContains('möchte dir den Vortrag übergeben.', $response);
        $this->assertBodyContains('Zum Vortrag', $response);
    }

    #[Test]
    public function attendance_suggests_half_the_talk(): void
    {
        [$teacher, $course, $session] = $this->talk();
        $yes = $this->admit($course, $this->account()['personnel']);
        $no  = $this->admit($course, $this->account()['personnel']);
        $this->endedTalk($session, $teacher['user'], 42 * 60, [$yes->id => 21 * 60, $no->id => 20 * 60]);

        $response = $this->get('/training/manage/' . $course->id);

        $this->assertBodyContains('Vortrag lief 42 Minuten. Vorgeschlagen wird, wer mindestens 21 Minuten dabei war.', $response);
        $this->assertBodyContains('21 von 42 Min.', $response);
        $this->assertBodyContains('20 von 42 Min.', $response);
        $this->assertSame(1, substr_count($response->body, '</i> anwesend</span>'));
        $this->assertSame(1, substr_count($response->body, 'kein Vorschlag'));
        // Keine Vorbelegung: kein Eintrag ist ausgewählt.
        $this->assertBodyNotContains(' selected', $response);
    }

    #[Test]
    public function attendance_adds_up_all_runs_of_a_session(): void
    {
        [$teacher, $course, $session] = $this->talk();
        $own = $this->admit($course, $this->account()['personnel']);
        // Abgebrochener erster Lauf und Neustart: einzeln reicht keiner, zusammen schon.
        $this->endedTalk($session, $teacher['user'], 10 * 60, [$own->id => 6 * 60]);
        $this->endedTalk($session, $teacher['user'], 20 * 60, [$own->id => 9 * 60]);

        $response = $this->get('/training/manage/' . $course->id);

        $this->assertBodyContains('Vortrag lief 30 Minuten. Vorgeschlagen wird, wer mindestens 15 Minuten dabei war.', $response);
        $this->assertBodyContains('15 von 30 Min.', $response);
        $this->assertSame(1, substr_count($response->body, '</i> anwesend</span>'));
    }

    #[Test]
    public function short_talk_shows_under_one_minute(): void
    {
        [$teacher, $course, $session] = $this->talk();
        $in  = $this->admit($course, $this->account()['personnel']);
        $out = $this->admit($course, $this->account()['personnel']);
        // Der zweite Zuschauer hat länger gezählt, als der Vortrag lief (später Abruf nach dem Ende).
        $this->endedTalk($session, $teacher['user'], 20, [$in->id => 20, $out->id => 600]);

        $response = $this->get('/training/manage/' . $course->id);

        $this->assertBodyContains('Vortrag lief 1 Minute. Vorgeschlagen wird, wer mindestens 1 Minute dabei war.', $response);
        $this->assertSame(2, substr_count($response->body, 'unter 1 Min.'));
        $this->assertSame(0, substr_count($response->body, '</i> anwesend</span>'));
    }

    #[Test]
    public function suggestion_never_sets_attendance(): void
    {
        [, $course, $session, $path] = $this->talk();
        $own = $this->admit($course, $this->account()['personnel']);
        $this->post($path . '/start');
        $p = $this->live($session);
        Presentation::query()->whereKey($p->id)->update(['started_at' => Carbon::now()->subMinutes(10)]);
        $this->viewer($p, $own->id, 540);
        $this->assertRedirect($this->post($path . '/end'));

        $this->assertSame(0, Capsule::table('intra_training_attendance')->count());
        $this->assertTrue(Presentations::suggestions([$this->live($session)])['viewers'][$own->id]['suggested']);
    }

    #[Test]
    public function hint_text_names_minutes_and_need(): void
    {
        [$teacher, $course, $session] = $this->talk();
        $own = $this->admit($course, $this->account()['personnel']);
        $this->endedTalk($session, $teacher['user'], 180, [$own->id => 120]);

        $response = $this->get('/training/manage/' . $course->id);

        $this->assertBodyContains('Vortrag lief 3 Minuten. Vorgeschlagen wird, wer mindestens 2 Minuten dabei war. Erst Speichern trägt die Anwesenheit ein.', $response);
    }

    /** @var list<array<string, mixed>> */
    private array $sent = [];

    /**
     * EF Sync mit gemockter Konsole; die Antworten in dieser Reihenfolge.
     *
     * @param list<HttpResponse> $answers
     */
    private function console(array $answers): void
    {
        $this->sent = [];
        $stack = HandlerStack::create(new MockHandler($answers));
        $stack->push(Middleware::history($this->sent));
        $instance = 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa';
        AiDrafts::useClient(new FabricaClient('https://console.example.test', $instance, $instance . '.' . str_repeat('s', 43), new Client(['handler' => $stack])));
    }

    private function liveKey(string $token = 'jwt'): HttpResponse
    {
        return new HttpResponse(200, [], (string) json_encode(['url' => 'wss://live.example.test', 'token' => $token, 'room' => 'x.y']));
    }

    /** @return array<string, mixed> Body der n-ten Anfrage an die Konsole */
    private function sentBody(int $i): array
    {
        return (array) json_decode((string) $this->sent[$i]['request']->getBody(), true);
    }

    /** Zuschauerzeilen ohne Anmeldung, zuletzt vor $ago Sekunden gesehen. */
    private function viewers(Presentation $p, int $count, int $ago = 0): void
    {
        for ($i = 0; $i < $count; $i++) {
            PresentationViewer::query()->create(['presentation_id' => $p->id, 'user_id' => FixtureFactory::user()->id, 'first_seen_at' => Carbon::now()->subSeconds($ago),
                'last_seen_at' => Carbon::now()->subSeconds($ago), 'seconds_watched' => 0]);
        }
    }

    /**
     * Leitung (angemeldet), Lehrgang, Termin, Pfad, Zuschauer A und B (haben schon gefragt); direkt geteilt.
     *
     * @return array{0: array{user: int, personnel: int}, 1: Course, 2: Session, 3: string, 4: array{user: int, personnel: int}, 5: array{user: int, personnel: int}}
     */
    private function directShare(): array
    {
        [$teacher, $course, $session, $path] = $this->talk();
        $this->post($path . '/start');
        $a = $this->learnerIn($course);
        $this->get($path . '/state');
        $b = $this->learnerIn($course);
        $this->get($path . '/state');
        $this->as($teacher['user'], ['training.teach']);
        $this->assertSame('direct', $this->assertJsonResponse($this->postJson($path . '/screen'))['via']);

        return [$teacher, $course, $session, $path, $a, $b];
    }

    #[Test]
    public function host_shares_over_livekit_with_ef_sync(): void
    {
        [$teacher, , $session, $path] = $this->talk();
        $this->console([$this->liveKey('host-jwt')]);
        $this->post($path . '/start');

        $res = $this->assertJsonResponse($this->postJson($path . '/screen'));
        $this->assertSame(['livekit', 'wss://live.example.test', 'host-jwt'], [$res['via'], $res['url'], $res['token']]);
        $p = $this->live($session);
        $this->assertSame('screen', $p->mode);
        $this->assertSame('livekit', $p->screen_via);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', (string) $p->room);
        $this->assertSame(1, $p->version);
        $this->assertSame('https://console.example.test/api/instance/livekit/token', (string) $this->sent[0]['request']->getUri());
        $sent = $this->sentBody(0);
        $this->assertSame([$p->room, 'u' . $teacher['user'], 'host'], [$sent['room'], $sent['identity'], $sent['role']]);
    }

    #[Test]
    public function screen_falls_back_to_direct_when_fabrica_fails(): void
    {
        [, , $session, $path] = $this->talk();
        $this->console([new HttpResponse(503, [], (string) json_encode(['error' => 'Nicht eingerichtet']))]);
        $this->post($path . '/start');
        $this->assertNotNull($this->live($session)->room);

        $res = $this->assertJsonResponse($this->postJson($path . '/screen'));
        $this->assertSame('direct', $res['via']);
        $this->assertArrayNotHasKey('token', $res);
        $p = $this->live($session);
        $this->assertSame(['screen', 'direct', null], [$p->mode, $p->screen_via, $p->room]);
    }

    #[Test]
    public function screen_is_direct_without_ef_sync(): void
    {
        [, , , $path] = $this->talk();
        $this->post($path . '/start');
        $this->assertNull(AiDrafts::client());

        $this->assertSame('direct', $this->assertJsonResponse($this->postJson($path . '/screen'))['via']);
        $this->assertBodyContains('data-screen-via="direct"', $this->get($path));
    }

    #[Test]
    public function direct_screen_needs_fifteen_viewers_or_fewer(): void
    {
        [, , $session, $path] = $this->talk();
        $this->post($path . '/start');
        $p = $this->live($session);
        $this->viewers($p, Presentations::SCREEN_LIMIT);
        $this->viewers($p, 5, 11);
        $this->assertSame('direct', $this->assertJsonResponse($this->postJson($path . '/screen'))['via']);

        $this->postJson($path . '/events', ['page_count' => 3, 'mode' => 'slides', 'events' => []]);
        $this->viewers($p, 1);
        $full = $this->postJson($path . '/screen');
        $this->assertStatus(409, $full);
        $this->assertSame('too_many', $this->assertJsonResponse($full)['error']);
        $this->assertSame('slides', $this->live($session)->mode);
    }

    #[Test]
    public function screen_goes_direct_on_request_without_asking_the_console(): void
    {
        [, , $session, $path] = $this->talk();
        $this->console([$this->liveKey('host-jwt')]);
        $this->post($path . '/start');
        $this->assertSame('livekit', $this->assertJsonResponse($this->postJson($path . '/screen'))['via']);

        // LiveKit kam nicht zustande; der Host weicht mit demselben Bild aus, der Raum bleibt (nur ein Ausfall der Konsole nimmt ihn).
        $room = $this->live($session)->room;
        $res  = $this->assertJsonResponse($this->postJson($path . '/screen', ['via' => 'direct']));
        $this->assertSame('direct', $res['via']);
        $this->assertCount(1, $this->sent);
        $p = $this->live($session);
        $this->assertNotNull($room);
        $this->assertSame(['screen', 'direct', $room], [$p->mode, $p->screen_via, $p->room]);

        $this->assertStatus(422, $this->postJson($path . '/screen', ['via' => 'livekit']));

        $this->viewers($p, Presentations::SCREEN_LIMIT + 1);
        $full = $this->postJson($path . '/screen', ['via' => 'direct']);
        $this->assertStatus(409, $full);
        $this->assertSame('too_many', $this->assertJsonResponse($full)['error']);
        $this->assertCount(1, $this->sent);
    }

    #[Test]
    public function viewer_tokens_stop_at_ten_a_minute(): void
    {
        [$teacher, $course, , $path] = $this->talk();
        // Jede Antwort für sich, ein Körper lässt sich nur einmal lesen.
        $this->console(array_map(fn (): HttpResponse => $this->liveKey(), range(1, 12)));
        $this->post($path . '/start');
        $viewer = $this->learnerIn($course);
        $this->as($teacher['user'], ['training.teach']);
        $this->postJson($path . '/screen');

        $this->as($viewer['user'], ['training.view']);
        for ($i = 0; $i < 10; $i++) {
            $this->assertJsonResponse($this->postJson($path . '/room/token'));
        }
        $refused = $this->postJson($path . '/room/token');
        $this->assertStatus(429, $refused);
        $this->assertSame('too_many_tokens', $this->assertJsonResponse($refused)['error']);
        $this->assertCount(11, $this->sent);

        Carbon::setTestNow(Carbon::now()->addSeconds(61));
        try {
            $this->assertJsonResponse($this->postJson($path . '/room/token'));
        } finally {
            Carbon::setTestNow();
        }
    }

    #[Test]
    public function only_the_host_starts_screen(): void
    {
        [, $course, $session, $path] = $this->talk();
        $this->post($path . '/start');
        $this->learnerIn($course);

        $this->assertForbidden($this->postJson($path . '/screen'));
        $this->assertSame('slides', $this->live($session)->mode);
    }

    #[Test]
    public function viewer_token_only_for_a_talk_they_may_see(): void
    {
        [$teacher, $course, $session, $path] = $this->talk();
        $this->console([$this->liveKey('viewer-jwt'), $this->liveKey('viewer-jwt-2')]);
        $this->post($path . '/start');
        $viewer = $this->learnerIn($course);
        $room   = $this->live($session)->room;

        $res = $this->assertJsonResponse($this->postJson($path . '/room/token'));
        $this->assertSame(['wss://live.example.test', 'viewer-jwt'], [$res['url'], $res['token']]);
        $sent = $this->sentBody(0);
        $this->assertSame([$room, 'viewer'], [$sent['room'], $sent['role']]);
        // Je Tab eine eigene Identität, sonst wirft LiveKit den älteren Tab hinaus.
        $this->assertMatchesRegularExpression('/^u' . $viewer['user'] . '-[0-9a-f]{8}$/', (string) $sent['identity']);

        // Wer den Vortrag nicht sehen darf, bekommt keinen Schlüssel.
        $this->as($this->account()['user'], ['training.view']);
        $this->assertNotFound($this->postJson($path . '/room/token'));
        $this->assertCount(1, $this->sent);

        // Der Host bekommt den Schlüssel mit seiner Identität und der Host-Rolle.
        $this->as($teacher['user'], ['training.teach']);
        $this->assertOk($this->postJson($path . '/room/token'));
        $this->assertSame([$room, 'u' . $teacher['user'], 'host'], [$this->sentBody(1)['room'], $this->sentBody(1)['identity'], $this->sentBody(1)['role']]);

        // Der alte Weg ist weg.
        $this->assertStatus(404, $this->postJson($path . '/screen/token'));
    }

    #[Test]
    public function signals_reach_only_their_viewer(): void
    {
        [$teacher, , $session, $path, $a, $b] = $this->directShare();
        $before = $this->live($session);
        $this->assertOk($this->postJson($path . '/signal', ['to' => $a['user'], 'type' => 'offer', 'sdp' => 'v=0 fuer A', 'x' => 1]));
        $this->assertSame($before->version, $this->live($session)->version);
        $after = (string) $before->seq;

        $this->as($a['user'], ['training.view']);
        $seen = $this->assertJsonResponse($this->get($path . '/state', ['query' => ['after' => $after]]))['events'];
        $this->assertCount(1, $seen);
        $this->assertSame(['kind' => 'signal', 'from' => $teacher['user'], 'type' => 'offer', 'sdp' => 'v=0 fuer A'], array_diff_key($seen[0], ['seq' => 1]));
        $this->assertSame([], $this->assertJsonResponse($this->get($path . '/state', ['query' => ['after' => '0']]))['events']);

        $this->as($b['user'], ['training.view']);
        $this->assertSame([], $this->assertJsonResponse($this->get($path . '/state', ['query' => ['after' => $after]]))['events']);
        $this->assertSame([], $this->assertJsonResponse($this->get($path . '/state', ['query' => ['after' => '0']]))['events']);
    }

    #[Test]
    public function viewer_signals_always_go_to_the_host(): void
    {
        [$teacher, , , $path, $a, $b] = $this->directShare();
        $this->as($a['user'], ['training.view']);
        $this->assertOk($this->postJson($path . '/signal', ['to' => $b['user'], 'type' => 'answer', 'sdp' => 'v=0 von A']));

        $event = PresentationEvent::query()->where('kind', 'signal')->firstOrFail();
        $this->assertSame($teacher['user'], $event->to_user_id);
        $this->assertSame(['from' => $a['user'], 'type' => 'answer', 'sdp' => 'v=0 von A'], json_decode($event->data, true));
    }

    #[Test]
    public function host_cannot_signal_strangers(): void
    {
        [, , , $path] = $this->directShare();
        $stranger = FixtureFactory::user();

        $this->assertStatus(422, $this->postJson($path . '/signal', ['to' => $stranger->id, 'type' => 'offer', 'sdp' => 'v=0']));
        $this->assertStatus(422, $this->postJson($path . '/signal', ['type' => 'offer', 'sdp' => 'v=0']));
        $this->assertSame(0, PresentationEvent::query()->count());
    }

    #[Test]
    public function viewer_cannot_send_offers(): void
    {
        [$teacher, , , $path, $a] = $this->directShare();
        $this->as($a['user'], ['training.view']);

        $this->assertStatus(422, $this->postJson($path . '/signal', ['type' => 'offer', 'sdp' => 'v=0']));
        $this->assertStatus(422, $this->postJson($path . '/signal', ['type' => 'full']));
        $this->assertStatus(422, $this->postJson($path . '/signal', ['type' => 'script']));
        $this->assertOk($this->postJson($path . '/signal', ['type' => 'ready']));
        $this->as($teacher['user'], ['training.teach']);
        $this->assertStatus(422, $this->postJson($path . '/signal', ['to' => $a['user'], 'type' => 'answer', 'sdp' => 'v=0']));
        $this->assertSame(1, PresentationEvent::query()->count());
    }

    #[Test]
    public function signals_need_direct_screen(): void
    {
        [$teacher, $course, , $path] = $this->talk();
        $this->console([$this->liveKey()]);
        $this->post($path . '/start');
        $a = $this->learnerIn($course);
        $this->get($path . '/state');
        $this->assertStatus(409, $this->postJson($path . '/signal', ['type' => 'ready']));

        $this->as($teacher['user'], ['training.teach']);
        $this->postJson($path . '/screen');
        $this->assertStatus(409, $this->postJson($path . '/signal', ['to' => $a['user'], 'type' => 'offer', 'sdp' => 'v=0']));
        $this->assertSame(0, PresentationEvent::query()->count());
    }

    #[Test]
    public function leaving_screen_keeps_the_room(): void
    {
        [, , $session, $path] = $this->talk();
        $this->console([$this->liveKey(), $this->liveKey()]);
        $this->post($path . '/start');
        $room = $this->live($session)->room;
        $this->postJson($path . '/screen');
        $this->assertNotNull($room);

        // Der Raum gehört dem Vortrag; wer das Teilen beendet, lässt ihn stehen.
        $this->assertOk($this->postJson($path . '/events', ['page_count' => 3, 'mode' => 'pause', 'events' => []]));
        $p = $this->live($session);
        $this->assertSame(['pause', null, $room], [$p->mode, $p->screen_via, $p->room]);
        $this->assertSame(2, $p->version);
        $this->assertNull($this->assertJsonResponse($this->get($path . '/state'))['screen']);

        $this->postJson($path . '/screen');
        $this->assertSame($room, $this->live($session)->room);
        $this->assertOk($this->postJson($path . '/events', ['page_count' => 3, 'mode' => 'slides', 'events' => []]));
        $p = $this->live($session);
        $this->assertSame(['slides', null], [$p->mode, $p->screen_via]);
    }

    #[Test]
    public function handover_and_end_leave_screen_mode(): void
    {
        [$teacher, $course, $session, $path] = $this->talk();
        $this->post($path . '/start');
        $instructor = $this->instructor($course);
        $this->postJson($path . '/screen');
        $this->postJson($path . '/handover', ['user_id' => $instructor['user']]);

        $this->as($instructor['user'], ['training.teach']);
        $this->assertOk($this->postJson($path . '/handover/accept'));
        $p = $this->live($session);
        $this->assertSame(['slides', null], [$p->mode, $p->screen_via]);

        $this->postJson($path . '/screen');
        $this->assertSame('screen', $this->live($session)->mode);
        $this->post($path . '/end');
        $p = Presentation::query()->where('session_id', $session->id)->latest('id')->firstOrFail();
        $this->assertNotNull($p->ended_at);
        $this->assertSame(['slides', null], [$p->mode, $p->screen_via]);
        $this->assertSame($instructor['user'], $p->presenter_id);
        $this->assertNotSame($teacher['user'], $p->presenter_id);
    }

    #[Test]
    public function signals_expire_after_a_minute(): void
    {
        [, , , $path, $a] = $this->directShare();
        $this->postJson($path . '/signal', ['to' => $a['user'], 'type' => 'offer', 'sdp' => 'v=0']);
        PresentationEvent::query()->where('kind', 'signal')->update(['created_at' => Carbon::now()->subSeconds(61)]);

        $this->postJson($path . '/signal', ['to' => $a['user'], 'type' => 'bye']);
        $types = PresentationEvent::query()->pluck('data')->map(static fn ($d) => json_decode((string) $d, true)['type'])->all();
        $this->assertSame(['bye'], $types);
        PresentationEvent::query()->update(['created_at' => Carbon::now()->subSeconds(61)]);
        $this->postJson($path . '/events', ['page_count' => 3, 'events' => [$this->stroke(1)]]);
        $this->assertSame(['begin'], PresentationEvent::query()->pluck('kind')->all());
    }

    #[Test]
    public function state_tells_the_screen_path(): void
    {
        [, , , $path, $a, $b] = $this->directShare();
        $host = $this->assertJsonResponse($this->get($path . '/state'));
        $this->assertSame(['via' => 'direct'], $host['screen']);
        $this->assertSame('screen', $host['mode']);
        $this->assertEqualsCanonicalizing([$a['user'], $b['user']], array_column($host['viewers'], 'id'));

        $this->as($a['user'], ['training.view']);
        $follow = $this->assertJsonResponse($this->get($path . '/state'));
        $this->assertSame(['via' => 'direct'], $follow['screen']);
        $this->assertArrayNotHasKey('viewers', $follow);
    }

    #[Test]
    public function signals_stop_at_twenty_a_minute(): void
    {
        [$teacher, , , $path, $a, $b] = $this->directShare();
        $this->as($a['user'], ['training.view']);
        for ($i = 0; $i < 20; $i++) {
            $this->assertOk($this->postJson($path . '/signal', ['type' => 'ready']));
        }
        $refused = $this->postJson($path . '/signal', ['type' => 'ready']);
        $this->assertStatus(429, $refused);
        $this->assertSame('too_many_signals', $this->assertJsonResponse($refused)['error']);
        $this->assertSame(20, PresentationEvent::query()->count());

        // Andere Absender und andere Empfänger zählen für sich.
        $this->as($b['user'], ['training.view']);
        $this->assertOk($this->postJson($path . '/signal', ['type' => 'ready']));
        $this->as($teacher['user'], ['training.teach']);
        for ($i = 0; $i < 20; $i++) {
            $this->assertOk($this->postJson($path . '/signal', ['to' => $a['user'], 'type' => 'full']));
        }
        $this->assertStatus(429, $this->postJson($path . '/signal', ['to' => $a['user'], 'type' => 'full']));
        $this->assertOk($this->postJson($path . '/signal', ['to' => $b['user'], 'type' => 'full']));

        // Nach einer Minute geht es wieder.
        PresentationEvent::query()->update(['created_at' => Carbon::now()->subSeconds(61)]);
        $this->as($a['user'], ['training.view']);
        $this->assertOk($this->postJson($path . '/signal', ['type' => 'ready']));
    }

    #[Test]
    public function livekit_name_stays_within_fifty(): void
    {
        [$teacher, , , $path] = $this->talk();
        $this->console([$this->liveKey()]);
        $this->post($path . '/start');
        User::query()->whereKey($teacher['user'])->update(['aktenid' => null, 'username' => str_repeat('Ä', 60) . "\u{1F600}"]);

        $this->postJson($path . '/screen');
        $this->assertSame(str_repeat('Ä', 50), $this->sentBody(0)['name']);
    }

    #[Test]
    public function both_views_carry_the_screen_path(): void
    {
        [$teacher, $course, , $path] = $this->talk();
        $this->post($path . '/start');
        $viewer = $this->learnerIn($course);

        foreach ([[$teacher['user'], 'training.teach', 'data-role="present"'], [$viewer['user'], 'training.view', 'data-role="follow"']] as [$user, $permission, $role]) {
            $this->as($user, [$permission]);
            AiDrafts::useClient(null);
            $page = $this->get($path);
            $this->assertBodyContains($role, $page);
            $this->assertBodyContains('data-screen-via="direct"', $page);
            $this->assertBodyContains('data-livekit="', $page);
            $this->assertBodyContains('assets/dist/livekit/livekit-client.esm.mjs', $page);
            $this->assertBodyContains('<video data-live-video playsinline autoplay muted>', $page);
            $this->console([]);
            $page = $this->get($path);
            $this->assertBodyContains('data-screen-via="livekit"', $page);
        }
        $this->as($teacher['user'], ['training.teach']);
        $this->assertBodyContains('data-live-mode="screen"', $this->get($path));
    }

    /** Sekundengenau, denn die Datenbank rundet Bruchteile. */
    private function clock(): DateTimeImmutable
    {
        return (new DateTimeImmutable())->setTimestamp(time());
    }

    /** @return array<string, mixed> */
    private function stateOf(string $path): array
    {
        return $this->assertJsonResponse($this->get($path . '/state'));
    }

    private function nameOf(int $userId): string
    {
        return Presentations::identity(User::query()->with('mitarbeiter')->find($userId))['name'];
    }

    #[Test]
    public function room_is_made_at_start_with_ef_sync(): void
    {
        [$teacher, $course, $session, $path] = $this->talk();
        $this->console([$this->liveKey('viewer-jwt'), $this->liveKey('host-jwt')]);
        $this->post($path . '/start');
        $room = $this->live($session)->room;
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', (string) $room);
        // Würfeln braucht keine Anfrage an die Konsole.
        $this->assertCount(0, $this->sent);

        $viewer = $this->learnerIn($course);
        $res    = $this->assertJsonResponse($this->postJson($path . '/room/token'));
        $this->assertSame(['wss://live.example.test', 'viewer-jwt'], [$res['url'], $res['token']]);
        $this->assertSame([$room, 'viewer'], [$this->sentBody(0)['room'], $this->sentBody(0)['role']]);
        $this->assertTrue($this->stateOf($path)['room']);
        $this->assertTrue($this->stateOf($path)['slides']);

        $this->as($teacher['user'], ['training.teach']);
        $this->assertOk($this->postJson($path . '/room/token'));
        $this->assertSame([$room, 'u' . $teacher['user'], 'host'], [$this->sentBody(1)['room'], $this->sentBody(1)['identity'], $this->sentBody(1)['role']]);

        // Ohne EF Sync gibt es keinen Raum und keinen Schlüssel.
        $this->post($path . '/end');
        AiDrafts::useClient(null);
        $this->post($path . '/start');
        $second = $this->live($session);
        $this->assertNotSame($room, $second->room);
        $this->assertNull($second->room);
        $this->assertFalse($this->stateOf($path)['room']);
        $this->as($viewer['user'], ['training.view']);
        $none = $this->postJson($path . '/room/token');
        $this->assertStatus(409, $none);
        $this->assertSame('no_room', $this->assertJsonResponse($none)['error']);
    }

    #[Test]
    public function a_failing_console_drops_the_room(): void
    {
        [$teacher, $course, $session, $path] = $this->talk();
        $this->console([new HttpResponse(503, [], (string) json_encode(['error' => 'Nicht eingerichtet']))]);
        $this->post($path . '/start');
        $this->assertNotNull($this->live($session)->room);
        $before = $this->live($session)->version;

        $failed = $this->postJson($path . '/room/token');
        $this->assertStatus(409, $failed);
        $this->assertSame('no_room', $this->assertJsonResponse($failed)['error']);
        $p = $this->live($session);
        $this->assertNull($p->room);
        // Die Version steigt, damit alle Fenster den Raum fallen lassen.
        $this->assertSame($before + 1, $p->version);
        $this->assertFalse($this->stateOf($path)['room']);

        // Weitere Schlüssel gibt es nicht, ohne dass die Konsole noch einmal gefragt wird.
        $this->learnerIn($course);
        $this->assertStatus(409, $this->postJson($path . '/room/token'));
        $this->assertCount(1, $this->sent);

        $this->as($teacher['user'], ['training.teach']);
        $res = $this->assertJsonResponse($this->postJson($path . '/screen'));
        $this->assertSame('direct', $res['via']);
        $this->assertCount(1, $this->sent);
        $this->assertSame('direct', $this->live($session)->screen_via);
    }

    /** @return array{0: array{user: int, personnel: int}, 1: array{user: int, personnel: int}, 2: Session, 3: string} Host, Zuschauer, Termin, Pfad; LiveKit-Teilen läuft */
    private function livekitShare(): array
    {
        [$teacher, $course, $session, $path] = $this->talk();
        $this->console([$this->liveKey('host-jwt')]);
        $this->post($path . '/start');
        $viewer = $this->learnerIn($course);
        $this->as($teacher['user'], ['training.teach']);
        $this->assertSame('livekit', $this->assertJsonResponse($this->postJson($path . '/screen'))['via']);

        return [$teacher, $viewer, $session, $path];
    }

    #[Test]
    public function a_viewers_failed_key_keeps_the_room(): void
    {
        [, $viewer, $session, $path] = $this->livekitShare();
        $room    = $this->live($session)->room;
        $version = $this->live($session)->version;
        $this->console([new HttpResponse(503), $this->liveKey('viewer-jwt')]);
        $this->as($viewer['user'], ['training.view']);

        $down = $this->postJson($path . '/room/token');
        $this->assertStatus(503, $down);
        $this->assertSame('unavailable', $this->assertJsonResponse($down)['error']);
        $p = $this->live($session);
        $this->assertSame([$room, $version, 'screen', 'livekit'], [$p->room, $p->version, $p->mode, $p->screen_via]);
        $state = $this->stateOf($path);
        $this->assertSame(['via' => 'livekit'], $state['screen']);
        $this->assertTrue($state['room']);

        // Ist die Konsole wieder da, bekommt der Zuschauer seinen Schlüssel.
        $res = $this->assertJsonResponse($this->postJson($path . '/room/token'));
        $this->assertSame('viewer-jwt', $res['token']);
    }

    #[Test]
    public function a_hosts_failed_key_ends_a_livekit_share(): void
    {
        [$teacher, , $session, $path] = $this->livekitShare();
        $this->console([new HttpResponse(503)]);

        $failed = $this->postJson($path . '/room/token');
        $this->assertStatus(409, $failed);
        $this->assertSame('no_room', $this->assertJsonResponse($failed)['error']);
        $p = $this->live($session);
        $this->assertSame(['slides', null, null], [$p->mode, $p->screen_via, $p->room]);
        $state = $this->stateOf($path);
        $this->assertNull($state['screen']);
        $this->assertFalse($state['room']);

        $this->as($teacher['user'], ['training.teach']);
        $this->assertSame('direct', $this->assertJsonResponse($this->postJson($path . '/screen'))['via']);
    }

    #[Test]
    public function questions_reach_only_instructors(): void
    {
        [$teacher, $course, $session, $path] = $this->talk();
        $this->post($path . '/start');
        $second = $this->instructor($course);
        $this->assertSame(0, $this->live($session)->q_version);
        $a      = $this->learnerIn($course);
        $b      = $this->learnerIn($course);

        $this->as($a['user'], ['training.view']);
        $asked = $this->assertJsonResponse($this->postJson($path . '/questions', ['body' => 'Was heißt SAMPLER?']));
        $this->assertTrue($asked['ok']);
        $id = $asked['id'];

        $this->as($b['user'], ['training.view']);
        $seenByB = $this->get($path . '/state');
        $state   = $this->assertJsonResponse($seenByB);
        $this->assertSame([], $state['questions']);
        $this->assertArrayNotHasKey('visits', $state);
        $this->assertStringNotContainsString('SAMPLER', $seenByB->body);

        $this->as($a['user'], ['training.view']);
        $own = $this->stateOf($path);
        $this->assertCount(1, $own['questions']);
        $this->assertSame(['Was heißt SAMPLER?', $this->nameOf($a['user']), null, false], [$own['questions'][0]['body'], $own['questions'][0]['name'], $own['questions'][0]['answer'], $own['questions'][0]['done']]);
        $this->assertArrayNotHasKey('visits', $own);

        foreach ([[$teacher['user'], 'training.teach'], [$second['user'], 'training.teach']] as [$user, $permission]) {
            $this->as($user, [$permission]);
            $all = $this->stateOf($path);
            $this->assertCount(1, $all['questions']);
            $this->assertSame([$id, $a['user'], $this->nameOf($a['user'])], [$all['questions'][0]['id'], $all['questions'][0]['user_id'], $all['questions'][0]['name']]);
            $this->assertArrayHasKey('visits', $all);
        }
        // Ausbilder bekommen nie ein 304, denn Fragen und Besuche ändern sich ohne neue Version.
        $etag = (string) ($this->get($path . '/state')->headers['ETag'] ?? '');
        $this->assertOk($this->get($path . '/state', ['headers' => ['If-None-Match' => $etag]]));

        // Beantworten und abhaken darf nur, wer am Lehrgang Rechte hat.
        $this->as($b['user'], ['training.view']);
        $beforeB = (string) ($this->get($path . '/state')->headers['ETag'] ?? '');
        $this->assertForbidden($this->postJson($path . '/questions/' . $id . '/answer', ['answer' => 'Ich weiß es.']));
        $this->assertForbidden($this->postJson($path . '/questions/' . $id . '/done'));
        $this->as($a['user'], ['training.view']);
        $this->assertForbidden($this->postJson($path . '/questions/' . $id . '/answer', ['answer' => 'Selbst beantwortet.']));
        $this->assertNull(PresentationQuestion::query()->findOrFail($id)->answer);

        // Der Host fragt nicht.
        $this->as($teacher['user'], ['training.teach']);
        $this->assertForbidden($this->postJson($path . '/questions', ['body' => 'Frage vom Host']));

        $this->assertOk($this->postJson($path . '/questions/' . $id . '/answer', ['answer' => 'Das kommt gleich.']));
        $row = PresentationQuestion::query()->findOrFail($id);
        $this->assertSame(['Das kommt gleich.', $teacher['user']], [$row->answer, $row->answered_by]);
        $this->assertNotNull($row->answered_at);

        $this->as($a['user'], ['training.view']);
        $this->assertSame('Das kommt gleich.', $this->stateOf($path)['questions'][0]['answer']);
        $this->as($b['user'], ['training.view']);
        // Auch mit dem alten Stand im Zwischenspeicher: kein 304 über den Antwortstand hinweg, und nichts Fremdes im Körper.
        $after = $this->get($path . '/state', ['headers' => ['If-None-Match' => $beforeB]]);
        $this->assertOk($after);
        $this->assertSame([], $this->assertJsonResponse($after)['questions']);
        $this->assertStringNotContainsString('Das kommt gleich.', $after->body);
        $this->assertStringContainsString('Cookie', (string) ($after->headers['Vary'] ?? ''));

        // Ohne Text ist die Frage nur abgehakt.
        $this->as($second['user'], ['training.teach']);
        $this->assertOk($this->postJson($path . '/questions/' . $id . '/done'));
        $this->assertNotNull(PresentationQuestion::query()->findOrFail($id)->done_at);
        $this->assertTrue($this->stateOf($path)['questions'][0]['done']);
        $this->assertSame(3, $this->live($session)->q_version);
    }

    #[Test]
    public function questions_and_log_render_only_for_instructors(): void
    {
        [$teacher, $course, , $path] = $this->talk();
        $this->post($path . '/start');
        $second = $this->instructor($course);
        $a      = $this->learnerIn($course);

        $this->as($teacher['user'], ['training.teach']);
        $host = $this->get($path);
        $this->assertBodyContains('data-live-tab="questions"', $host);
        $this->assertBodyContains('data-live-tab="log"', $host);
        $this->assertBodyContains('data-live-question-count', $host);
        $this->assertStringNotContainsString('data-live-ask', $host->body);

        $this->as($a['user'], ['training.view']);
        $follow = $this->get($path);
        $this->assertBodyContains('data-live-ask', $follow);
        $this->assertStringNotContainsString('data-live-tab="log"', $follow->body);
        $this->assertStringNotContainsString('data-live-tab="questions"', $follow->body);

        $this->as($second['user'], ['training.teach']);
        $staff = $this->get($path);
        $this->assertBodyContains('data-role="follow"', $staff);
        $this->assertBodyContains('data-live-tab="questions"', $staff);
        $this->assertStringNotContainsString('data-live-ask', $staff->body);
    }

    #[Test]
    public function questions_are_limited_and_validated(): void
    {
        [$teacher, $course, , $path] = $this->talk();
        $this->post($path . '/start');
        $this->learnerIn($course);

        foreach ([['body' => ''], ['body' => " \n "], ['body' => str_repeat('ä', 501)], ['body' => 5], []] as $bad) {
            $res = $this->postJson($path . '/questions', $bad);
            $this->assertStatus(422, $res);
            $this->assertSame(['error' => 'invalid'], $this->assertJsonResponse($res));
        }
        $this->assertSame(0, PresentationQuestion::query()->count());

        $this->assertOk($this->postJson($path . '/questions', ['body' => str_repeat('ä', 500)]));
        for ($i = 2; $i <= 5; $i++) {
            $this->assertOk($this->postJson($path . '/questions', ['body' => 'Frage ' . $i]));
        }
        $sixth = $this->postJson($path . '/questions', ['body' => 'Frage 6']);
        $this->assertStatus(429, $sixth);
        $this->assertSame('too_many', $this->assertJsonResponse($sixth)['error']);
        $this->assertSame(5, PresentationQuestion::query()->count());

        Carbon::setTestNow(Carbon::now()->addSeconds(61));
        try {
            $this->assertOk($this->postJson($path . '/questions', ['body' => 'Frage 6']));
        } finally {
            Carbon::setTestNow();
        }

        // Antworten sind genauso begrenzt, und eine fremde Frage gibt es nicht.
        $id = (int) PresentationQuestion::query()->value('id');
        $this->as($teacher['user'], ['training.teach']);
        $long = $this->postJson($path . '/questions/' . $id . '/answer', ['answer' => str_repeat('x', 501)]);
        $this->assertStatus(422, $long);
        $this->assertSame(['error' => 'invalid'], $this->assertJsonResponse($long));
        $this->assertNull(PresentationQuestion::query()->findOrFail($id)->answer);
        $this->assertNotFound($this->postJson($path . '/questions/999999/answer', ['answer' => 'Hallo']));
        $this->assertNotFound($this->postJson($path . '/questions/999999/done'));
    }

    #[Test]
    public function ending_drops_questions_but_keeps_visits(): void
    {
        [$teacher, $course, $session, $path] = $this->talk();
        $this->post($path . '/start');
        $a = $this->learnerIn($course);
        $this->stateOf($path);
        $this->postJson($path . '/questions', ['body' => 'Noch da?']);
        $this->as($teacher['user'], ['training.teach']);
        $this->stateOf($path);
        $p = $this->live($session);
        $this->assertSame(1, PresentationQuestion::query()->where('presentation_id', $p->id)->count());

        $this->assertRedirect($this->post($path . '/end'));
        $this->assertSame(0, PresentationQuestion::query()->where('presentation_id', $p->id)->count());
        $visits = PresentationVisit::query()->where('presentation_id', $p->id)->get();
        $this->assertEqualsCanonicalizing([$teacher['user'], $a['user']], $visits->pluck('user_id')->all());
        foreach ($visits as $visit) {
            $this->assertNotNull($visit->left_at);
            $this->assertSame($visit->last_seen_at->getTimestamp(), $visit->left_at->getTimestamp());
        }
    }

    #[Test]
    public function an_orphaned_talk_drops_questions_and_closes_visits(): void
    {
        [, $course, $session, $path] = $this->talk();
        $this->post($path . '/start');
        $this->learnerIn($course);
        $this->stateOf($path);
        $this->postJson($path . '/questions', ['body' => 'Hallo?']);
        $p = $this->live($session);
        Presentation::query()->whereKey($p->id)->update(['started_at' => Carbon::now()->subHours(3), 'presenter_seen_at' => Carbon::now()->subHours(2)]);

        $this->assertSame(['status' => 'ended'], $this->stateOf($path));
        $this->assertSame(0, PresentationQuestion::query()->where('presentation_id', $p->id)->count());
        $visit = PresentationVisit::query()->where('presentation_id', $p->id)->firstOrFail();
        $this->assertNotNull($visit->left_at);
        $this->assertSame($visit->last_seen_at->getTimestamp(), $visit->left_at->getTimestamp());
    }

    #[Test]
    public function visits_split_only_after_a_gap(): void
    {
        [$teacher, $course, $session, $path] = $this->talk();
        $this->post($path . '/start');
        $a = $this->learnerIn($course);
        $b = $this->learnerIn($course);
        $p = $this->live($session);
        $t = $this->clock();
        $visitsOf = static fn (int $userId) => PresentationVisit::query()->where('presentation_id', $p->id)->where('user_id', $userId)->orderBy('id')->get();

        foreach ([0, 10, 25] as $seconds) {
            Presentations::heartbeat($p, $a['user'], null, $t->modify('+' . $seconds . ' seconds'));
        }
        $one = $visitsOf($a['user']);
        $this->assertCount(1, $one);
        $this->assertNull($one[0]->left_at);
        $this->assertSame([$t->getTimestamp(), $t->getTimestamp() + 25], [$one[0]->joined_at->getTimestamp(), $one[0]->last_seen_at->getTimestamp()]);

        // Eine ältere, langsamere Anfrage zieht das Lebenszeichen nicht zurück.
        Presentations::heartbeat($p, $a['user'], null, $t->modify('+5 seconds'));
        $this->assertSame($t->getTimestamp() + 25, $visitsOf($a['user'])[0]->last_seen_at->getTimestamp());
        $this->assertCount(1, $visitsOf($a['user']));

        Presentations::heartbeat($p, $a['user'], null, $t->modify('+70 seconds'));
        $two = $visitsOf($a['user']);
        $this->assertCount(2, $two);
        $this->assertSame($t->getTimestamp() + 25, $two[0]->left_at?->getTimestamp());
        $this->assertNull($two[1]->left_at);
        $this->assertSame($t->getTimestamp() + 70, $two[1]->joined_at->getTimestamp());

        // Zwei Tabs im selben Augenblick: ein offener Besuch.
        Presentations::heartbeat($p, $b['user'], null, $t);
        Presentations::heartbeat($p, $b['user'], null, $t);
        $this->assertCount(1, $visitsOf($b['user']));
        $this->assertNull($visitsOf($b['user'])[0]->left_at);

        // Der Host zählt ebenfalls.
        Presentations::heartbeat($p, $teacher['user'], null, $t);
        $this->assertCount(1, $visitsOf($teacher['user']));

        // Wer nach dem Ende noch fragt, eröffnet keinen Besuch.
        Presentations::end($p, $t->modify('+80 seconds'));
        Presentations::heartbeat($p, $b['user'], null, $t->modify('+90 seconds'));
        $this->assertCount(1, $visitsOf($b['user']));
        $this->assertNotNull($visitsOf($b['user'])[0]->left_at);
    }

    #[Test]
    public function staff_sees_the_log_after_the_talk(): void
    {
        [$teacher, $course, , $path] = $this->talk();
        $this->post($path . '/start');
        $a = $this->learnerIn($course);
        $this->stateOf($path);
        $this->as($teacher['user'], ['training.teach']);
        $live = $this->stateOf($path);
        $this->assertCount(2, $live['visits']);
        $this->assertEqualsCanonicalizing([$a['user'], $teacher['user']], array_column($live['visits'], 'user_id'));
        $this->assertNotSame('', $live['visits'][0]['name']);
        $this->post($path . '/end');
        PresentationVisit::query()->where('user_id', $a['user'])->update(['joined_at' => '2026-11-11 10:05:00', 'last_seen_at' => '2026-11-11 10:42:00', 'left_at' => '2026-11-11 10:42:00']);

        $page = $this->get('/training/manage/' . $course->id);
        $this->assertBodyContains('Protokoll', $page);
        $this->assertBodyContains($this->nameOf($a['user']), $page);
        $this->assertBodyContains('10:05', $page);
        $this->assertBodyContains('10:42', $page);

        // Ein Besuch ohne Ende steht als „noch da“.
        PresentationVisit::query()->where('user_id', $a['user'])->update(['left_at' => null, 'last_seen_at' => Carbon::now()]);
        $this->assertBodyContains('noch da', $this->get('/training/manage/' . $course->id));
    }

    #[Test]
    public function participants_never_get_powerpoint_slides_as_a_file(): void
    {
        [$teacher, $course, $session] = $this->talk();
        $file = $session->unit->file;
        $file->mime           = \Plugin\Training\Converter::PPTX;
        $file->original_name  = 'Folien.pptx';
        $file->convert_status = \Plugin\Training\Converter::PENDING;
        $file->save();
        $url = '/training/courses/' . $course->id . '/files/' . $file->id;
        $this->learnerIn($course);

        // Noch nicht oder gar nicht umgewandelt: kein Original, nichts abgehakt.
        $this->assertNotFound($this->get($url));
        $file->convert_status = \Plugin\Training\Converter::FAILED;
        $file->save();
        $this->assertNotFound($this->get($url));
        $this->assertNull(UnitProgress::query()->where('unit_id', $session->unit_id)->first());

        // Umgewandelt gibt es die PDF zum Ansehen, aber keinen Download.
        $file->pdf_storage_name = $this->pdf()->storage_name;
        $file->convert_status   = \Plugin\Training\Converter::DONE;
        $file->save();
        $shown = $this->get($url);
        $this->assertOk($shown);
        $this->assertSame('application/pdf', $shown->headers['Content-Type'] ?? null);
        $this->assertStringStartsWith('inline;', (string) ($shown->headers['Content-Disposition'] ?? ''));
        $this->assertNotFound($this->get($url, ['query' => ['download' => '1']]));
        $this->assertNull(UnitProgress::query()->where('unit_id', $session->unit_id)->first());

        // Ausbilder bekommen die Datei wie bisher, auch unumgewandelt.
        $file->pdf_storage_name = null;
        $file->convert_status   = \Plugin\Training\Converter::PENDING;
        $file->save();
        $this->as($teacher['user'], ['training.teach']);
        $original = $this->get($url);
        $this->assertOk($original);
        $this->assertStringStartsWith('attachment;', (string) ($original->headers['Content-Disposition'] ?? ''));
    }

    #[Test]
    public function pages_do_not_count_during_the_talk_when_slides_are_talk_only(): void
    {
        [, $course, $session, $path] = $this->talk(afterTalk: false);
        $this->post($path . '/start');
        $unit  = $session->unit;
        $pages = ['course' => $course->id, 'pages' => [1], 'total' => 1];
        $this->learnerIn($course);

        $page = $this->get('/training/courses/' . $course->id . '/units/' . $unit->id);
        $this->assertBodyContains('data-ignis-document-viewer', $page);
        $this->assertBodyNotContains('data-pages-url', $page);
        $this->assertNotFound($this->postJson('/api/training/units/' . $unit->id . '/pages', $pages));
        $this->assertNull(UnitProgress::query()->where('unit_id', $unit->id)->first());

        // Bleiben die Folien nach dem Vortrag offen, zählt das Durchgehen.
        $unit->slides_after_talk = true;
        $unit->save();
        $this->assertBodyContains('data-pages-url', $this->get('/training/courses/' . $course->id . '/units/' . $unit->id));
        $this->assertTrue($this->assertJsonResponse($this->postJson('/api/training/units/' . $unit->id . '/pages', $pages))['done']);
    }

    #[Test]
    public function nothing_under_a_live_talk_can_be_deleted(): void
    {
        [$teacher, $course, $session, $path] = $this->talk();
        $this->post($path . '/start');
        $unit    = $session->unit;
        $module  = $unit->module_id;
        $manager = $this->account()['user'];
        $message = 'Während eines Vortrags lässt sich das nicht löschen.';
        $deleteSession = '/training/manage/' . $course->id . '/sessions/' . $session->id . '/delete';

        $this->assertRedirect($this->post($deleteSession));
        $this->assertSame($message, $_SESSION['flash']['text'] ?? null);
        $this->as($manager, ['training.manage']);
        $this->assertRedirect($this->post('/training/units/' . $unit->id . '/delete'));
        $this->assertSame($message, $_SESSION['flash']['text'] ?? null);
        $this->assertRedirect($this->post('/training/modules/' . $module . '/delete'));
        $this->assertSame($message, $_SESSION['flash']['text'] ?? null);
        $this->assertNotNull(Session::query()->find($session->id));
        $this->assertNotNull(Unit::query()->find($unit->id));
        $this->assertNotNull(Module::query()->find($module));
        $this->assertNull($this->live($session)->ended_at);

        $this->as($teacher['user'], ['training.teach']);
        $this->post($path . '/end');
        $this->post($deleteSession);
        $this->assertNull(Session::query()->find($session->id));
        $this->as($manager, ['training.manage']);
        $this->post('/training/units/' . $unit->id . '/delete');
        $this->assertNull(Unit::query()->find($unit->id));
        $this->post('/training/modules/' . $module . '/delete');
        $this->assertNull(Module::query()->find($module));
    }

    #[Test]
    public function a_session_unit_can_become_a_presentation(): void
    {
        $teacher = $this->account(permissions: ['training.teach']);
        $type    = $this->type();
        $course  = $this->course($type, 'running', lead: $teacher['user']);
        $unit    = $this->unit($type, ['kind' => 'session', 'title' => 'Praxistag']);
        $session = $this->session($course, $unit);
        $learner = $this->account();
        $participation = $this->admit($course, $learner['personnel']);
        \Plugin\Training\Models\Attendance::query()->create(['session_id' => $session->id, 'participation_id' => $participation->id, 'state' => 'present', 'recorded_by' => $teacher['user']]);
        $hint = 'Für einen Live-Vortrag die Einheit dieses Termins im Lehrplan auf Präsentation stellen.';

        $this->as($teacher['user'], ['training.teach']);
        $before = $this->get('/training/manage/' . $course->id);
        $this->assertBodyContains($hint, $before);
        $this->assertBodyContains('Ein Termin zu einer Präsentation lässt sich als Live-Vortrag halten.', $before);
        $this->assertBodyNotContains('Vortrag starten', $before);

        $this->as($this->account()['user'], ['training.manage']);
        $this->assertRedirect($this->post('/training/units/' . $unit->id, ['kind' => 'presentation', 'title' => 'Praxistag']));
        $this->assertSame('presentation', Unit::query()->find($unit->id)->kind);

        $this->as($teacher['user'], ['training.teach']);
        $after = $this->get('/training/manage/' . $course->id);
        $this->assertBodyContains('Vortrag starten', $after);
        $this->assertBodyNotContains($hint, $after);
        $this->assertSame($unit->id, Session::query()->findOrFail($session->id)->unit_id);
        $this->assertSame(1, \Plugin\Training\Models\Attendance::query()->where('session_id', $session->id)->count());
    }

    #[Test]
    public function presentation_unit_pages_say_how_they_are_settled(): void
    {
        $teacher = $this->account(permissions: ['training.teach']);
        $course  = $this->course($this->type(), 'running', lead: $teacher['user']);
        $unit    = $this->presentation($course, $this->pdf());
        $url     = '/training/courses/' . $course->id . '/units/' . $unit->id;
        $settled = 'Wird abgehakt, sobald die Anwesenheit am Termin eingetragen ist';
        $teach   = 'Noch kein Termin. Den Vortrag startest du am Termin dieser Einheit.';

        $this->as($teacher['user'], ['training.teach']);
        $this->assertBodyContains($teach, $this->get($url));

        $this->learnerIn($course);
        $page = $this->get($url);
        $this->assertBodyContains('Noch kein Termin.', $page);
        $this->assertBodyNotContains($teach, $page);
        $this->assertBodyContains($settled . ' oder wenn du die Folien danach durchgehst.', $page);

        $this->session($course, $unit);
        $page = $this->get($url);
        $this->assertBodyNotContains('Noch kein Termin', $page);
        $this->assertBodyContains($settled . ' oder wenn du die Folien danach durchgehst.', $page);

        $unit->slides_after_talk = false;
        $unit->save();
        $this->assertBodyContains($settled . '.', $this->get($url));

        $this->as($teacher['user'], ['training.teach']);
        $this->assertBodyNotContains('Noch kein Termin', $this->get($url));
    }
}
