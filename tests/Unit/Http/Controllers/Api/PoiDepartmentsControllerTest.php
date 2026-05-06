<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api;

use App\Http\Controllers\Api\PoiDepartmentsController;
use App\Http\Request;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PoiDepartmentsControllerTest extends TestCase
{
    #[Test]
    public function controller_resolves_via_container(): void
    {
        $c = $this->resolve(PoiDepartmentsController::class);
        $this->assertInstanceOf(PoiDepartmentsController::class, $c);
    }

    #[Test]
    public function update_sort_rejects_missing_department_id(): void
    {
        $this->expectException(ValidationException::class);

        $controller = $this->resolve(PoiDepartmentsController::class);
        $req = new Request(
            method:  'POST',
            path:    '/api/pois/departments-sort',
            rawBody: json_encode(['sort_order' => 5]),
        );
        $controller->updateSort($req);
    }

    #[Test]
    public function update_sort_rejects_missing_sort_order(): void
    {
        $this->expectException(ValidationException::class);

        $controller = $this->resolve(PoiDepartmentsController::class);
        $req = new Request(
            method:  'POST',
            path:    '/api/pois/departments-sort',
            rawBody: json_encode(['department_id' => 7]),
        );
        $controller->updateSort($req);
    }

    #[Test]
    public function update_sort_rejects_non_positive_department_id(): void
    {
        $this->expectException(ValidationException::class);

        $controller = $this->resolve(PoiDepartmentsController::class);
        $req = new Request(
            method:  'POST',
            path:    '/api/pois/departments-sort',
            rawBody: json_encode(['department_id' => 0, 'sort_order' => 1]),
        );
        $controller->updateSort($req);
    }
}
