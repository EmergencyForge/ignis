<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api;

use App\Http\Controllers\Api\EmdSyncController;
use App\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Smoke-Tests für EmdSyncController.
 *
 * Der Controller enthält ~1200 Zeilen komplexe DB-Logik (Fire Incidents,
 * eNOTF-Protokolle, Status-Queue, Sitreps). Vollständige Integration-Tests
 * brauchen ein Test-DB-Setup mit den relevanten Tabellen und ein
 * Fixtures-System — das ist eine eigene Session wert.
 *
 * Hier decken wir ab:
 *   - Container-Resolution (Controller ist korrekt verdrahtet)
 *   - Minimal-Request-Handling (ungültiges JSON, leerer Body)
 *   - Unbekannte Sync-Types werden sauber abgelehnt
 *
 * Regression-Tests gegen den FW-Fahrzeug-Bug benötigen echte DB-Fixtures
 * und werden in einer separaten Integration-Test-Runde nachgezogen.
 */
class EmdSyncControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $controller = $this->resolve(EmdSyncController::class);
        $this->assertInstanceOf(EmdSyncController::class, $controller);
    }

    #[Test]
    public function sync_rejects_invalid_json_body(): void
    {
        $controller = $this->resolve(EmdSyncController::class);

        // Kein rawBody → json() liefert null → Controller antwortet 400
        $request = new Request('POST', '/api/emd/sync');
        $response = $controller->sync($request);

        $this->assertSame(400, $response->status);
        $this->assertStringContainsString('Ungültiges JSON', $response->body);
    }

    #[Test]
    public function sync_returns_unknown_type_error_for_unrecognized_type(): void
    {
        $controller = $this->resolve(EmdSyncController::class);

        $request = new Request(
            method:  'POST',
            path:    '/api/emd/sync',
            rawBody: json_encode(['type' => 'nonsense_type']),
        );
        $response = $controller->sync($request);

        $this->assertSame(400, $response->status);
        $this->assertStringContainsString('Unbekannter Sync-Typ', $response->body);
    }

    #[Test]
    public function sync_handles_dispatch_logs_type_with_empty_missions(): void
    {
        $controller = $this->resolve(EmdSyncController::class);

        $request = new Request(
            method:  'POST',
            path:    '/api/emd/sync',
            rawBody: json_encode(['type' => 'dispatch_logs', 'missions' => []]),
        );
        $response = $controller->sync($request);

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('"processed":0', $response->body);
    }

    #[Test]
    public function controller_has_expected_public_method(): void
    {
        $reflection = new \ReflectionClass(EmdSyncController::class);
        $this->assertTrue($reflection->hasMethod('sync'));

        $syncMethod = $reflection->getMethod('sync');
        $this->assertTrue($syncMethod->isPublic());

        // Sollte einen Request nehmen und eine Response zurückgeben
        $params = $syncMethod->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame(Request::class, (string) $params[0]->getType());
        $this->assertSame(\App\Http\Response::class, (string) $syncMethod->getReturnType());
    }
}
