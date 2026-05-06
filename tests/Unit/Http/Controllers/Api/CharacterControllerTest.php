<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api;

use App\Http\Controllers\Api\CharacterController;
use App\Http\Request;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterControllerTest extends TestCase
{
    private CharacterController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = $this->resolve(CharacterController::class);
    }

    private function jsonRequest(string $method, string $path, array $body): Request
    {
        // Request mit eingebettetem JSON-Body — der Constructor akzeptiert
        // rawBody als named-Parameter, damit wir keinen echten `php://input`
        // Stream brauchen.
        return new Request(
            method:  $method,
            path:    $path,
            server:  ['CONTENT_TYPE' => 'application/json'],
            rawBody: json_encode($body),
        );
    }

    #[Test]
    public function session_id_returns_current_session(): void
    {
        // Sicherstellen, dass eine Session existiert
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $expected = session_id();

        $res = $this->controller->sessionId(new Request('GET', '/api/character/get-session-id'));

        $this->assertSame(200, $res->status);
        $this->assertSame('application/json; charset=utf-8', $res->headers['Content-Type']);
        $this->assertStringContainsString($expected, $res->body);
    }

    #[Test]
    public function identify_throws_validation_error_for_empty_body(): void
    {
        $this->expectException(ValidationException::class);

        // Kein Body → kein JSON → FormRequest liefert leeren Input → alle Felder fehlen
        $req = new Request('POST', '/api/character/identify');
        $this->controller->identify($req);
    }

    #[Test]
    public function identify_throws_validation_error_for_missing_fields(): void
    {
        try {
            $req = $this->jsonRequest('POST', '/api/character/identify', [
                'session_id' => 'deadbeef1234',
                // char_name + char_job fehlen
            ]);
            $this->controller->identify($req);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('char_name', $e->errors());
            $this->assertArrayHasKey('char_job', $e->errors());
            $this->assertArrayNotHasKey('session_id', $e->errors());
        }
    }

    #[Test]
    public function identify_throws_validation_error_for_empty_strings(): void
    {
        $this->expectException(ValidationException::class);

        $req = $this->jsonRequest('POST', '/api/character/identify', [
            'session_id' => '',
            'char_name'  => '',
            'char_job'   => '',
        ]);
        $this->controller->identify($req);
    }

    #[Test]
    public function identify_throws_validation_error_for_too_long_char_name(): void
    {
        try {
            $req = $this->jsonRequest('POST', '/api/character/identify', [
                'session_id' => 'deadbeef1234',
                'char_name'  => str_repeat('A', 150), // max ist 100
                'char_job'   => 'BF',
            ]);
            $this->controller->identify($req);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('char_name', $e->errors());
        }
    }
}
