<?php

declare(strict_types=1);

namespace Tests\Integration\Federation;

use App\Exceptions\FederationAuthException;
use App\Federation\FederationMiddleware;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

/**
 * Coverage für die fünfte Auth-Variante (Federation, Server-to-Server).
 *
 * Federation-Auth läuft NICHT über die Router-Middleware-Pipeline,
 * sondern wird in den Federation-Controllern via
 * `FederationMiddleware::authenticate()` aufgerufen. Die `try*()`-API
 * der Middleware (siehe FederationMiddleware) wirft typisierte
 * Exceptions — diese Tests prüfen den vollständigen Auth-Flow gegen
 * eine echte Test-DB.
 */
final class FederationMiddlewareTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('FEDERATION_ENABLED')) {
            define('FEDERATION_ENABLED', true);
        }

        unset($_SERVER['HTTP_X_FEDERATION_KEY']);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_FEDERATION_KEY']);
        parent::tearDown();
    }

    private function insertLink(array $overrides = []): string
    {
        $key = $overrides['api_key_incoming'] ?? bin2hex(random_bytes(16));
        $row = array_merge([
            'instance_id'        => 'inst-' . substr($key, 0, 8),
            'instance_name'      => 'Test Instance',
            'instance_url'       => 'https://test.invalid',
            'api_key_outgoing'   => 'outgoing-' . substr($key, 0, 8),
            'api_key_incoming'   => $key,
            'provide_personnel'  => 1,
            'provide_enotf'      => 0,
            'provide_fire'       => 1,
            'is_active'          => 1,
        ], $overrides);

        $cols = implode(',', array_map(fn ($c) => "`$c`", array_keys($row)));
        $ph   = implode(',', array_fill(0, count($row), '?'));
        $stmt = $this->pdo->prepare("INSERT INTO intra_federation_links ($cols) VALUES ($ph)");
        $stmt->execute(array_values($row));

        return $row['api_key_incoming'];
    }

    // ─── Pure Helpers ────────────────────────────────────────────────

    #[Test]
    public function is_enabled_reads_runtime_constant(): void
    {
        $this->assertTrue(FederationMiddleware::isEnabled());
    }

    #[Test]
    public function config_returns_default_for_undefined_constant(): void
    {
        $this->assertSame('fallback', FederationMiddleware::config('NOT_DEFINED_CONST', 'fallback'));
    }

    // ─── tryRequireEnabled ───────────────────────────────────────────

    #[Test]
    public function try_require_enabled_passes_when_constant_true(): void
    {
        FederationMiddleware::tryRequireEnabled();
        $this->expectNotToPerformAssertions();
    }

    // ─── tryAuthenticate Happy Path ──────────────────────────────────

    #[Test]
    public function try_authenticate_returns_link_for_valid_key(): void
    {
        $key = $this->insertLink(['instance_id' => 'matching-inst']);
        $_SERVER['HTTP_X_FEDERATION_KEY'] = $key;

        $link = FederationMiddleware::tryAuthenticate($this->pdo);

        $this->assertSame('matching-inst', $link['instance_id']);
        $this->assertSame(1, (int) $link['provide_personnel']);
        $this->assertSame(0, (int) $link['provide_enotf']);
    }

    // ─── tryAuthenticate Rejection Paths ─────────────────────────────

    #[Test]
    public function try_authenticate_rejects_missing_header(): void
    {
        $this->expectException(FederationAuthException::class);
        $this->expectExceptionCode(401);
        $this->expectExceptionMessage('Federation-Key fehlt');

        FederationMiddleware::tryAuthenticate($this->pdo);
    }

    #[Test]
    public function try_authenticate_rejects_unknown_key(): void
    {
        $_SERVER['HTTP_X_FEDERATION_KEY'] = 'totally-bogus-key-' . bin2hex(random_bytes(8));

        $this->expectException(FederationAuthException::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage('Ungültiger Federation-Key');

        FederationMiddleware::tryAuthenticate($this->pdo);
    }

    #[Test]
    public function try_authenticate_rejects_inactive_link(): void
    {
        $key = $this->insertLink(['is_active' => 0]);
        $_SERVER['HTTP_X_FEDERATION_KEY'] = $key;

        $this->expectException(FederationAuthException::class);
        $this->expectExceptionCode(403);

        FederationMiddleware::tryAuthenticate($this->pdo);
    }

    #[Test]
    public function try_authenticate_rejects_when_other_link_uses_same_key_pattern(): void
    {
        // Sicherstellen, dass tryAuthenticate STRICT auf api_key_incoming
        // matched (nicht z.B. via LIKE oder Substring).
        $a = $this->insertLink(['instance_id' => 'a']);
        $_SERVER['HTTP_X_FEDERATION_KEY'] = substr($a, 0, 5); // Prefix des echten Keys

        $this->expectException(FederationAuthException::class);
        $this->expectExceptionCode(403);

        FederationMiddleware::tryAuthenticate($this->pdo);
    }

    // ─── tryRequireProvidePermission ─────────────────────────────────

    #[Test]
    public function try_require_provide_permission_passes_when_capability_set(): void
    {
        $link = ['provide_personnel' => 1, 'provide_enotf' => 0, 'provide_fire' => 1];

        FederationMiddleware::tryRequireProvidePermission($link, 'personnel');
        FederationMiddleware::tryRequireProvidePermission($link, 'fire');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function try_require_provide_permission_rejects_missing_capability(): void
    {
        $link = ['provide_personnel' => 1, 'provide_enotf' => 0];

        $this->expectException(FederationAuthException::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessageMatches("/'enotf'/");

        FederationMiddleware::tryRequireProvidePermission($link, 'enotf');
    }

    #[Test]
    public function try_require_provide_permission_rejects_when_column_missing(): void
    {
        // Defensives Verhalten: wenn die Spalte gar nicht im Link-Array ist
        // (z.B. weil die DB älter ist und das Feld noch nicht existiert),
        // wird das als „nicht freigegeben" gewertet.
        $link = ['provide_personnel' => 1];

        $this->expectException(FederationAuthException::class);
        $this->expectExceptionCode(403);

        FederationMiddleware::tryRequireProvidePermission($link, 'fire');
    }
}
