<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PersonnelLog;
use App\Personnel\PersonalLogManager;
use EmergencyForge\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Training\Models\Course;
use Plugin\Training\Models\CourseType;
use Plugin\Training\Models\Module;
use Plugin\Training\Models\Participation;
use Plugin\Training\Models\Prerequisite;
use Plugin\Training\Models\TrainingFile;
use Plugin\Training\Models\Unit;
use Plugin\Training\Models\UnitProgress;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Ausbildung von der Anmeldung bis zum Haken an der Einheit, über die
 * echten Routen. Die Konten wechseln je Schritt, wie zwischen zwei
 * Browsern.
 */
final class TrainingTest extends FeatureTestCase
{
    private const RD_NONE    = 3;
    private const RD_RETTSAN = 4;
    private const RD_NOTSAN  = 6;

    /** @var list<string> */
    private array $storedFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->storedFiles as $path) {
            @unlink($path);
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
    private function account(int $qualird = self::RD_NONE): array
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

    private function participation(Course $course, int $personnelId): ?Participation
    {
        return Participation::query()->where('course_id', $course->id)->where('personnel_id', $personnelId)->first();
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

    private function rd(int $personnelId): int
    {
        return (int) Capsule::table('intra_mitarbeiter')->where('id', $personnelId)->value('qualird');
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
    public function full_course_puts_the_next_on_the_waitlist_and_promotes_on_withdraw(): void
    {
        $course = $this->course($this->type(), 'enrollment_open', seats: 1);
        $first  = $this->account();
        $second = $this->account();

        $this->as($first['user'], ['training.view']);
        $this->post('/training/courses/' . $course->id . '/enroll');
        $this->as($second['user'], ['training.view']);
        $this->post('/training/courses/' . $course->id . '/enroll');

        $this->assertSame('admitted', $this->participation($course, $first['personnel'])?->status);
        $this->assertSame('waitlisted', $this->participation($course, $second['personnel'])?->status);

        $this->as($first['user'], ['training.view']);
        $this->post('/training/courses/' . $course->id . '/withdraw');

        $this->assertSame('aborted', $this->participation($course, $first['personnel'])?->status);
        $this->assertSame('admitted', $this->participation($course, $second['personnel'])?->status);
    }

    #[Test]
    public function confirm_mode_waits_for_the_lead(): void
    {
        $lead    = $this->account();
        $course  = $this->course($this->type(['enrollment_mode' => 'confirm']), 'enrollment_open', lead: $lead['user']);
        $learner = $this->account();

        $this->as($learner['user'], ['training.view']);
        $this->post('/training/courses/' . $course->id . '/enroll');
        $participation = $this->participation($course, $learner['personnel']);
        $this->assertNotNull($participation);
        $this->assertSame('enrolled', $participation->status);

        // Ein anderer Teilnehmer darf nicht entscheiden.
        $this->post('/training/manage/' . $course->id . '/participants/' . $participation->id . '/decide', ['decision' => 'accept']);
        $this->assertSame('enrolled', $this->participation($course, $learner['personnel'])?->status);

        $this->as($lead['user'], ['training.teach']);
        $this->post('/training/manage/' . $course->id . '/participants/' . $participation->id . '/decide', ['decision' => 'accept']);
        $this->assertSame('admitted', $this->participation($course, $learner['personnel'])?->status);
    }

    #[Test]
    public function missing_prerequisite_blocks_self_enrollment(): void
    {
        $type = $this->type();
        $pre  = new Prerequisite();
        $pre->course_type_id = $type->id;
        $pre->qualification  = 'rd:' . self::RD_NOTSAN;
        $pre->save();
        $course  = $this->course($type, 'enrollment_open');
        $learner = $this->account();

        $this->as($learner['user'], ['training.view']);
        $this->post('/training/courses/' . $course->id . '/enroll');

        $this->assertNull($this->participation($course, $learner['personnel']));
        $this->assertStringStartsWith('Setzt ', (string) ($_SESSION['flash']['text'] ?? ''));
    }

    #[Test]
    public function running_grants_the_start_qualification_and_cancel_can_reset_it(): void
    {
        $course  = $this->course($this->type(['start_qualification' => 'rd:' . self::RD_RETTSAN]), 'enrollment_open');
        $manager = $this->account();
        $learner = $this->account();
        $p       = $this->admit($course, $learner['personnel'], 'admitted');

        $this->as($manager['user'], ['training.manage']);
        $this->post('/training/manage/' . $course->id . '/status', ['status' => 'running']);

        $p = $p->fresh();
        $this->assertSame('in_training', $p?->status);
        $this->assertSame('rd:' . self::RD_NONE, $p->start_qualification_before);
        $this->assertSame(self::RD_RETTSAN, $this->rd($learner['personnel']));

        $this->post('/training/manage/' . $course->id . '/status', ['status' => 'cancelled', 'reset_qualification' => '1']);

        $this->assertSame('aborted', $p->fresh()?->status);
        $this->assertSame(self::RD_NONE, $this->rd($learner['personnel']));
    }

    #[Test]
    public function files_only_reach_participants_and_respect_the_download_switch(): void
    {
        $type    = $this->type();
        $course  = $this->course($type, 'running');
        $file    = $this->pdf();
        $this->unit($type, ['kind' => 'file', 'file_id' => $file->id, 'allow_download' => false]);
        $learner  = $this->account();
        $outsider = $this->account();
        $this->admit($course, $learner['personnel']);
        $url = '/training/courses/' . $course->id . '/files/' . $file->id;

        $this->as($learner['user'], ['training.view']);
        $response = $this->get($url);
        $this->assertOk($response);
        $this->assertSame('application/pdf', $response->headers['Content-Type'] ?? null);
        $this->assertStringStartsWith('inline;', $response->headers['Content-Disposition'] ?? '');
        $this->assertSame('private, no-store', $response->headers['Cache-Control'] ?? null);
        $this->assertNotFound($this->get($url, ['query' => ['download' => '1']]));
        // Unterlagen laufen nie über die Bild-Route ohne Teilnahmeprüfung.
        $this->assertNotFound($this->get('/training/images/' . $file->id));

        $this->as($outsider['user'], ['training.view']);
        $this->assertNotFound($this->get($url));
    }

    #[Test]
    public function viewer_pages_complete_a_pdf_unit(): void
    {
        $type    = $this->type();
        $course  = $this->course($type, 'running');
        $unit    = $this->unit($type, ['kind' => 'file', 'file_id' => $this->pdf()->id]);
        $learner = $this->account();
        $p       = $this->admit($course, $learner['personnel']);
        $this->as($learner['user'], ['training.view']);

        $first = $this->assertJsonResponse($this->postJson('/api/training/units/' . $unit->id . '/pages', ['course' => $course->id, 'pages' => [1, 2, 99], 'total' => 3]));
        $this->assertSame(['seen' => 2, 'total' => 3, 'done' => false], $first);

        $second = $this->assertJsonResponse($this->postJson('/api/training/units/' . $unit->id . '/pages', ['course' => $course->id, 'pages' => [3], 'total' => 3]));
        $this->assertTrue($second['done']);
        $this->assertSame('done', UnitProgress::query()->where('participation_id', $p->id)->where('unit_id', $unit->id)->value('status'));
    }

    #[Test]
    public function text_unit_can_be_marked_as_read(): void
    {
        $type    = $this->type();
        $course  = $this->course($type, 'running');
        $unit    = $this->unit($type, ['kind' => 'text', 'content' => ['doc' => ['type' => 'doc', 'content' => []]]]);
        $learner = $this->account();
        $p       = $this->admit($course, $learner['personnel']);

        $this->as($learner['user'], ['training.view']);
        $this->assertOk($this->get('/training/courses/' . $course->id . '/units/' . $unit->id));
        $this->post('/training/courses/' . $course->id . '/units/' . $unit->id . '/done');

        $row = UnitProgress::query()->where('participation_id', $p->id)->where('unit_id', $unit->id)->first();
        $this->assertSame('done', $row?->status);
        $this->assertNull($row->completed_by);
    }

    #[Test]
    public function instructor_marks_by_hand_with_a_log_entry(): void
    {
        $lead    = $this->account();
        $type    = $this->type();
        $course  = $this->course($type, 'running', lead: $lead['user']);
        $unit    = $this->unit($type, ['kind' => 'text']);
        $learner = $this->account();
        $p       = $this->admit($course, $learner['personnel']);
        $body    = ['participation' => (string) $p->id, 'unit' => (string) $unit->id, 'done' => '1'];

        $this->as($learner['user'], ['training.view']);
        $this->assertNotSame(200, $this->post('/training/manage/' . $course->id . '/progress', $body, ['headers' => ['Accept' => 'application/json']])->status);
        $this->assertNull(UnitProgress::query()->where('participation_id', $p->id)->first());

        $this->as($lead['user'], ['training.teach']);
        $this->assertSame(['done' => true], $this->assertJsonResponse($this->post('/training/manage/' . $course->id . '/progress', $body)));

        $row = UnitProgress::query()->where('participation_id', $p->id)->where('unit_id', $unit->id)->first();
        $this->assertSame('done', $row?->status);
        $this->assertSame($lead['user'], $row->completed_by);
        $this->assertSame(1, PersonnelLog::query()->where('profilid', $learner['personnel'])->where('type', PersonalLogManager::TYPE_TRAINING)->count());
    }

    #[Test]
    public function outsiders_do_not_see_a_running_course(): void
    {
        $course   = $this->course($this->type(), 'running');
        $outsider = $this->account();

        $this->as($outsider['user'], ['training.view']);
        $this->assertOk($this->get('/training'));
        $this->assertNotFound($this->get('/training/courses/' . $course->id));
    }
}
