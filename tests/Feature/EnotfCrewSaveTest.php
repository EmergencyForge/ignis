<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Session\SessionManager;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;

/**
 * Eine Crew ohne ignis-Konto speichert im eNOTF v1, solange
 * ENOTF_REQUIRE_USER_AUTH aus ist. Früher verlangte die API immer ein Konto.
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
            'protokoll_status' => 0,
            'hidden'           => 0,
            'freigegeben'      => 0,
            'created_at'       => date('Y-m-d H:i:s'),
        ]);
    }

    private function save(string $field, string $value): \EmergencyForge\Http\Response
    {
        return $this->post('/api/enotf/save-fields', ['enr' => $this->enr, 'field' => $field, 'value' => $value]);
    }

    private function stored(string $field): mixed
    {
        return Capsule::table('intra_edivi')->where('enr', $this->enr)->value($field);
    }

    #[Test]
    public function crew_without_account_saves_new_fields(): void
    {
        SessionManager::loginEnotfCrew('fahrer', 'tok', ['fahrer' => ['name' => 'X', 'quali' => 'NotSan']], 'RTW-1');

        $this->assertStatus(200, $this->save('rea_status', '2'));
        $this->assertStatus(200, $this->save('az_vor_ereignis', '3'));

        $this->assertSame(2, (int) $this->stored('rea_status'));
        $this->assertSame(3, (int) $this->stored('az_vor_ereignis'));
        $this->assertArrayNotHasKey('userid', $_SESSION);
    }

    #[Test]
    public function request_without_login_is_rejected(): void
    {
        $this->assertStatus(401, $this->save('rea_status', '2'));
        $this->assertNull($this->stored('rea_status'));
    }
}
