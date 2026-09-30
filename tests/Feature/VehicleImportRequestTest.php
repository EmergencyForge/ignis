<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Den EMD-Fahrzeugimport anfordern geht nur per POST mit CSRF-Token.
 *
 * Vorher setzte `?action=request` die Flag-Datei auch per GET, also an
 * CsrfMiddleware vorbei. Lesen (`list`, `status`) bleibt per GET.
 */
final class VehicleImportRequestTest extends FeatureTestCase
{
    private const FLAG = __DIR__ . '/../../storage/emd_vehicle_import_request.flag';

    private ?string $previousFlag = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousFlag = is_file(self::FLAG) ? (string) file_get_contents(self::FLAG) : null;
        @unlink(self::FLAG);

        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
    }

    protected function tearDown(): void
    {
        @unlink(self::FLAG);
        if ($this->previousFlag !== null) {
            file_put_contents(self::FLAG, $this->previousFlag);
        }
        parent::tearDown();
    }

    #[Test]
    public function get_fordert_keinen_import_an(): void
    {
        $this->get('/api/vehicles/import-handler', ['query' => ['action' => 'request']]);

        $this->assertFileDoesNotExist(self::FLAG);
    }

    #[Test]
    public function post_ohne_token_fordert_keinen_import_an(): void
    {
        // request() statt post(): post() legt den Token bei.
        $response = $this->request('POST', '/api/vehicles/import-handler', ['post' => ['action' => 'request']]);

        $this->assertStatus(403, $response);
        $this->assertFileDoesNotExist(self::FLAG);
    }

    #[Test]
    public function post_mit_token_fordert_den_import_an(): void
    {
        $response = $this->post('/api/vehicles/import-handler', ['action' => 'request']);

        $this->assertOk($response);
        $this->assertTrue(json_decode($response->body, true)['success']);
        $this->assertFileExists(self::FLAG);
    }

    #[Test]
    public function status_bleibt_per_get_lesbar(): void
    {
        $response = $this->get('/api/vehicles/import-handler', ['query' => ['action' => 'status']]);

        $this->assertOk($response);
        $this->assertFalse(json_decode($response->body, true)['request_pending']);
    }
}
