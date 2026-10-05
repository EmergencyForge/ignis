<?php

declare(strict_types=1);

namespace Plugin\Enotf\Tests\Unit;

use App\Session\SessionManager;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Enotf\Http\CrewSessionMiddleware;
use Tests\TestCase;

class CrewSessionMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    private function responseStatus(): int
    {
        $response = (new CrewSessionMiddleware())->process(
            new Request('POST', '/api/enotf/save-fields'),
            fn () => Response::text('ok'),
        );
        return $response->status;
    }

    #[Test]
    public function rejects_request_without_any_login(): void
    {
        $this->assertSame(401, $this->responseStatus());
    }

    #[Test]
    public function accepts_crew_login_without_account(): void
    {
        SessionManager::loginEnotfCrew('fahrer', 'tok', ['fahrer' => ['name' => 'X', 'quali' => 'NotSan']], 'RTW-1');

        $this->assertSame(200, $this->responseStatus());
    }

    #[Test]
    public function accepts_account_login(): void
    {
        $_SESSION['userid'] = 1;

        $this->assertSame(200, $this->responseStatus());
    }
}
