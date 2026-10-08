<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Session\SessionManager;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;

/**
 * Eine Crew ohne ignis-Konto speichert im eNOTF, solange
 * ENOTF_REQUIRE_USER_AUTH aus ist, aber nur in Protokolle ihres Fahrzeugs.
 */
final class EnotfCrewSaveTest extends FeatureTestCase
{
    private string $enr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enr = 'T-' . uniqid();
        Capsule::table('intra_edivi')->insert([
            'enr'              => $this->enr,
            'fzg_transp'       => 'RTW-1',
            'protokoll_status' => 0,
            'hidden'           => 0,
            'freigegeben'      => 0,
            'created_at'       => date('Y-m-d H:i:s'),
        ]);
    }

    /** @param array<string, string> $fields */
    private function save(array $fields): Response
    {
        return $this->router->dispatch(new Request(
            'POST',
            '/api/enotf/save-fields',
            server: ['HTTP_X_CSRF_TOKEN' => $this->csrfToken(), 'CONTENT_TYPE' => 'application/json'],
            rawBody: json_encode(['enr' => $this->enr, 'fields' => $fields], JSON_THROW_ON_ERROR),
        ));
    }

    private function stored(string $field): mixed
    {
        return Capsule::table('intra_edivi')->where('enr', $this->enr)->value($field);
    }

    #[Test]
    public function crew_without_account_saves_new_fields(): void
    {
        SessionManager::loginEnotfCrew('fahrer', 'tok', ['fahrer' => ['name' => 'X', 'quali' => 'NotSan']], 'RTW-1');

        $this->assertStatus(200, $this->save(['rea_status' => '2', 'az_vor_ereignis' => '3']));

        $this->assertSame(2, (int) $this->stored('rea_status'));
        $this->assertSame(3, (int) $this->stored('az_vor_ereignis'));
        $this->assertArrayNotHasKey('userid', $_SESSION);
    }

    #[Test]
    public function crew_of_another_vehicle_is_rejected(): void
    {
        SessionManager::loginEnotfCrew('fahrer', 'tok', ['fahrer' => ['name' => 'X', 'quali' => 'NotSan']], 'RTW-2');

        $this->assertStatus(403, $this->save(['rea_status' => '2']));
        $this->assertNull($this->stored('rea_status'));
    }

    #[Test]
    public function request_without_login_is_rejected(): void
    {
        $this->assertStatus(401, $this->save(['rea_status' => '2']));
        $this->assertNull($this->stored('rea_status'));
    }
}
