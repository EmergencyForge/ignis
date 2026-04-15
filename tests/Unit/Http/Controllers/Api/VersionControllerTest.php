<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api;

use App\Http\Controllers\Api\VersionController;
use App\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VersionControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $c = $this->resolve(VersionController::class);
        $this->assertInstanceOf(VersionController::class, $c);
    }

    #[Test]
    public function index_returns_version_json_with_system_field(): void
    {
        $controller = $this->resolve(VersionController::class);
        $response   = $controller->index(new Request('GET', '/api/version'));

        // Entweder 200 mit valider Version oder 404 (falls version.json fehlt).
        // Auf dem Dev-System existiert das File, also erwarten wir 200.
        if ($response->status === 404) {
            $this->markTestSkipped('system/updates/version.json nicht vorhanden');
        }

        $this->assertSame(200, $response->status);
        $this->assertSame('application/json; charset=utf-8', $response->headers['Content-Type']);

        $data = json_decode($response->body, true);
        $this->assertIsArray($data);
        $this->assertSame('intraRP', $data['system']);
        $this->assertArrayHasKey('version', $data);
    }
}
