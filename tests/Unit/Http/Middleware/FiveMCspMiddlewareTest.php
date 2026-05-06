<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\FiveMCspMiddleware;
use App\Http\Request;
use App\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FiveMCspMiddlewareTest extends TestCase
{
    private function req(string $userAgent = ''): Request
    {
        return new Request('GET', '/enotf/protokoll/1', server: [
            'HTTP_USER_AGENT' => $userAgent,
        ]);
    }

    #[Test]
    public function strips_csp_headers_for_citizen_fx_user_agent(): void
    {
        $mw = new FiveMCspMiddleware();

        $handler = fn () => (new Response(200, 'ok', [
            'Content-Security-Policy' => "default-src 'self'",
            'X-Frame-Options'         => 'DENY',
        ]));

        $res = $mw->process($this->req('Mozilla/5.0 CitizenFX/1 Chrome'), $handler);

        $this->assertArrayNotHasKey('Content-Security-Policy', $res->headers);
        $this->assertArrayNotHasKey('X-Frame-Options', $res->headers);
    }

    #[Test]
    public function sets_frame_ancestors_csp_for_normal_browser(): void
    {
        $mw = new FiveMCspMiddleware();

        $handler = fn () => new Response(200, 'ok');

        $res = $mw->process($this->req('Mozilla/5.0 Chrome/120'), $handler);

        $this->assertStringContainsString(
            "frame-ancestors 'self' https://*",
            $res->headers['Content-Security-Policy'] ?? ''
        );
    }
}
