<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Das Fehlerprotokoll löscht fehlgeschlagene Jobs über einen POST auf
 * dieselbe Adresse (logs-app.js). Die Route nahm vorher nur GET an.
 */
final class FailedJobsActionTest extends FeatureTestCase
{
    #[Test]
    public function ein_fehlgeschlagener_job_laesst_sich_loeschen(): void
    {
        $user = FixtureFactory::user(['full_admin' => true]);
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
        $id = (int) Capsule::table('intra_failed_jobs')->insertGetId([
            'uuid'      => bin2hex(random_bytes(8)),
            'queue'     => 'default',
            'payload'   => '{}',
            'exception' => 'Test',
            'failed_at' => date('Y-m-d H:i:s'),
        ]);

        $response = $this->post('/settings/system/logs', ['action' => 'delete', 'id' => (string) $id], [
            'server' => ['HTTP_X_CSRF_TOKEN' => $this->csrfToken()],
        ]);

        $this->assertNotSame(405, $response->status);
        $this->assertBodyContains('"success":true', $response);
        $this->assertFalse(Capsule::table('intra_failed_jobs')->where('id', $id)->exists());
    }
}
