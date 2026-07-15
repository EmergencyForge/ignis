<?php

declare(strict_types=1);

namespace Plugin\Enotf\Tests\Unit;

use Plugin\Enotf\Controllers\Api\KlinikCodeController;
use App\Http\Request;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit-Smoke-Tests für den KlinikCodeController. Echte DB-Szenarien
 * (existierendes Protokoll, gültiger Code-Cache, Kollisions-Check)
 * gehören in einen Integration-Test mit Test-DB — hier decken wir nur
 * Container-Resolution und Input-Validation ab.
 */
class KlinikCodeControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $c = $this->resolve(KlinikCodeController::class);
        $this->assertInstanceOf(KlinikCodeController::class, $c);
    }

    #[Test]
    public function generate_throws_validation_error_without_enr(): void
    {
        $this->expectException(ValidationException::class);

        $controller = $this->resolve(KlinikCodeController::class);
        $controller->generate(new Request('POST', '/api/klinik/generate-code'));
    }

    #[Test]
    public function generate_throws_validation_error_for_empty_enr(): void
    {
        $this->expectException(ValidationException::class);

        $controller = $this->resolve(KlinikCodeController::class);
        $req = new Request(
            method:  'POST',
            path:    '/api/klinik/generate-code',
            post:    ['enr' => ''],
        );
        $controller->generate($req);
    }
}
