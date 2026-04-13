<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api;

use App\Http\Controllers\Api\LegacyDispatcher;
use App\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Smoke-Tests für LegacyDispatcher.
 *
 * Der Dispatcher ist bewusst dünn — er hostet lediglich die alten Legacy-
 * API-Files aus `src/LegacyApi/` innerhalb der Router-Pipeline. Wir
 * verifizieren:
 *   - Container-Resolution
 *   - Path-Traversal-Schutz
 *   - 404-Verhalten bei unbekanntem Pfad
 *
 * Die eigentlichen Business-Logiken der Legacy-Files werden nicht via
 * Dispatcher-Tests abgedeckt — sie sind 1:1 Kopien der alten Files, und
 * werden in Zukunft einzeln refactored und dann mit echten Controller-
 * Tests versehen.
 */
class LegacyDispatcherTest extends TestCase
{
    #[Test]
    public function dispatcher_resolves_via_container(): void
    {
        $dispatcher = $this->resolve(LegacyDispatcher::class);
        $this->assertInstanceOf(LegacyDispatcher::class, $dispatcher);
    }

    #[Test]
    public function dispatcher_rejects_path_traversal_attempts(): void
    {
        $dispatcher = $this->resolve(LegacyDispatcher::class);
        $request = new Request('GET', '/api/anything');

        $res = $dispatcher->run($request, '../../etc/passwd');

        $this->assertSame(400, $res->status);
        $this->assertStringContainsString('Ungültiger Endpoint', $res->body);
    }

    #[Test]
    public function dispatcher_rejects_absolute_paths(): void
    {
        $dispatcher = $this->resolve(LegacyDispatcher::class);
        $request = new Request('GET', '/api/anything');

        $res = $dispatcher->run($request, '/etc/passwd');

        $this->assertSame(400, $res->status);
    }

    #[Test]
    public function dispatcher_returns_404_for_unknown_legacy_path(): void
    {
        $dispatcher = $this->resolve(LegacyDispatcher::class);
        $request = new Request('GET', '/api/anything');

        $res = $dispatcher->run($request, 'does/not/exist.php');

        $this->assertSame(404, $res->status);
        $this->assertStringContainsString('nicht verfügbar', $res->body);
    }
}
