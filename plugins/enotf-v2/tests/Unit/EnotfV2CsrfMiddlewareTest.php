<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Tests\Unit;

use App\Http\Request;
use App\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Plugin\EnotfV2\Http\Csrf;
use Plugin\EnotfV2\Http\CsrfMiddleware;
use Tests\TestCase;

/**
 * Entscheidungslogik der CsrfMiddleware: gültiges Token (Feld oder
 * Header) lässt durch, sonst greift der Same-Origin-Fallback — fremder
 * Host 403, headerlose Requests (FiveM-CEF) und Reads passieren.
 */
class EnotfV2CsrfMiddlewareTest extends TestCase
{
    private CsrfMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new CsrfMiddleware();
        unset($_SESSION[Csrf::SESSION_KEY]);
    }

    /**
     * @param array<string,string> $server
     * @param array<string,mixed>  $post
     */
    private function dispatch(string $method, array $server, array $post = []): Response
    {
        $request = new Request(method: $method, path: '/enotf-v2/test', post: $post, server: $server);

        return $this->middleware->process($request, fn () => Response::text('durchgelassen'));
    }

    private function sessionToken(): string
    {
        $_SESSION[Csrf::SESSION_KEY] = str_repeat('ab', 32);

        return $_SESSION[Csrf::SESSION_KEY];
    }

    // ── Token-Pfade ──────────────────────────────────────────────────

    #[Test]
    public function gueltiges_formular_token_laesst_auch_fremden_origin_durch(): void
    {
        // Origin-Heuristik greift gar nicht mehr, wenn das Token stimmt
        $response = $this->dispatch(
            'POST',
            ['HTTP_HOST' => 'intra.example', 'HTTP_ORIGIN' => 'https://evil.example'],
            [Csrf::FIELD_NAME => $this->sessionToken()],
        );

        $this->assertSame(200, $response->status);
        $this->assertSame('durchgelassen', $response->body);
    }

    #[Test]
    public function gueltiges_header_token_laesst_durch(): void
    {
        $response = $this->dispatch('POST', [
            'HTTP_HOST'           => 'intra.example',
            'HTTP_ORIGIN'         => 'https://evil.example',
            'HTTP_X_CSRF_TOKEN'   => $this->sessionToken(),
        ]);

        $this->assertSame(200, $response->status);
    }

    #[Test]
    public function falsches_token_mit_fremdem_origin_wird_abgewiesen(): void
    {
        $this->sessionToken();

        $response = $this->dispatch(
            'POST',
            ['HTTP_HOST' => 'intra.example', 'HTTP_ORIGIN' => 'https://evil.example'],
            [Csrf::FIELD_NAME => 'komplett-falsch'],
        );

        $this->assertSame(403, $response->status);
    }

    #[Test]
    public function falsches_token_mit_gleichem_origin_passiert_den_fallback(): void
    {
        // Alt-Clients ohne (gültiges) Token bleiben über Same-Origin funktionsfähig
        $this->sessionToken();

        $response = $this->dispatch(
            'POST',
            ['HTTP_HOST' => 'intra.example', 'HTTP_ORIGIN' => 'https://intra.example'],
            [Csrf::FIELD_NAME => 'komplett-falsch'],
        );

        $this->assertSame(200, $response->status);
    }

    #[Test]
    public function token_ohne_session_gegenstueck_ist_nie_gueltig(): void
    {
        // Session trägt kein Token → eingereichtes Token zählt nicht,
        // der fremde Origin entscheidet
        $response = $this->dispatch(
            'POST',
            ['HTTP_HOST' => 'intra.example', 'HTTP_ORIGIN' => 'https://evil.example'],
            [Csrf::FIELD_NAME => 'irgendwas'],
        );

        $this->assertSame(403, $response->status);
    }

    // ── Csrf-Helper ──────────────────────────────────────────────────

    #[Test]
    public function is_valid_prueft_timing_sicher_gegen_das_session_token(): void
    {
        $token = $this->sessionToken();

        $this->assertTrue(Csrf::isValid($token));
        $this->assertFalse(Csrf::isValid('anderes-token'));
        $this->assertFalse(Csrf::isValid(''));
        $this->assertFalse(Csrf::isValid(null));
    }

    #[Test]
    public function leeres_session_token_bedeutet_niemals_gueltig(): void
    {
        $_SESSION[Csrf::SESSION_KEY] = '';

        $this->assertFalse(Csrf::isValid(''));
        $this->assertFalse(Csrf::isValid('x'));
    }

    #[Test]
    public function token_bleibt_innerhalb_der_session_stabil(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $this->markTestSkipped('Keine aktive Session im Test-Runner');
        }

        $first = Csrf::token();

        $this->assertNotSame('', $first);
        $this->assertSame($first, Csrf::token());
        $this->assertTrue(Csrf::isValid($first));
    }

    // ── Same-Origin-Fallback (Verhalten der frueheren SameOriginMiddleware) ──

    #[Test]
    public function get_requests_passieren_ohne_pruefung(): void
    {
        $response = $this->dispatch('GET', [
            'HTTP_HOST'   => 'intra.example',
            'HTTP_ORIGIN' => 'https://evil.example',
        ]);

        $this->assertSame(200, $response->status);
    }

    #[Test]
    public function post_mit_gleichem_origin_host_passiert(): void
    {
        $response = $this->dispatch('POST', [
            'HTTP_HOST'   => 'intra.example',
            'HTTP_ORIGIN' => 'https://intra.example',
        ]);

        $this->assertSame(200, $response->status);
    }

    #[Test]
    public function post_mit_fremdem_origin_wird_mit_403_abgewiesen(): void
    {
        $response = $this->dispatch('POST', [
            'HTTP_HOST'   => 'intra.example',
            'HTTP_ORIGIN' => 'https://evil.example',
        ]);

        $this->assertSame(403, $response->status);
    }

    #[Test]
    public function post_ohne_token_origin_und_referer_passiert(): void
    {
        // FiveM-CEF / ältere Clients senden keinen Origin — bewusst erlaubt
        $response = $this->dispatch('POST', ['HTTP_HOST' => 'intra.example']);

        $this->assertSame(200, $response->status);
    }

    #[Test]
    public function referer_dient_als_fallback_fuer_fehlenden_origin(): void
    {
        $same = $this->dispatch('POST', [
            'HTTP_HOST'    => 'intra.example',
            'HTTP_REFERER' => 'https://intra.example/enotf-v2/protokoll',
        ]);
        $this->assertSame(200, $same->status);

        $foreign = $this->dispatch('POST', [
            'HTTP_HOST'    => 'intra.example',
            'HTTP_REFERER' => 'https://evil.example/form.html',
        ]);
        $this->assertSame(403, $foreign->status);
    }

    #[Test]
    public function origin_null_aus_sandbox_iframes_wird_abgewiesen(): void
    {
        $response = $this->dispatch('POST', [
            'HTTP_HOST'   => 'intra.example',
            'HTTP_ORIGIN' => 'null',
        ]);

        $this->assertSame(403, $response->status);
    }

    #[Test]
    public function port_im_host_header_wird_beim_vergleich_ignoriert(): void
    {
        $response = $this->dispatch('POST', [
            'HTTP_HOST'   => 'intra.example:8443',
            'HTTP_ORIGIN' => 'https://intra.example',
        ]);

        $this->assertSame(200, $response->status);
    }

    #[Test]
    public function hostvergleich_ist_case_insensitiv(): void
    {
        $response = $this->dispatch('POST', [
            'HTTP_HOST'   => 'Intra.Example',
            'HTTP_ORIGIN' => 'https://intra.example',
        ]);

        $this->assertSame(200, $response->status);
    }

    #[Test]
    public function auch_put_patch_und_delete_werden_geprueft(): void
    {
        foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
            $response = $this->dispatch($method, [
                'HTTP_HOST'   => 'intra.example',
                'HTTP_ORIGIN' => 'https://evil.example',
            ]);

            $this->assertSame(403, $response->status, $method);
        }
    }
}
