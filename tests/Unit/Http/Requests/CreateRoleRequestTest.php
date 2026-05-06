<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Exceptions\ValidationException;
use App\Http\Requests\Roles\CreateRoleRequest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CreateRoleRequestTest extends TestCase
{
    #[Test]
    public function valid_input_passes_and_normalizes_types(): void
    {
        $data = CreateRoleRequest::validate([
            'name'        => '  Disponent  ',
            'priority'    => '50',
            'color'       => 'primary',
            'permissions' => ['users.view', 'edivi.view'],
        ]);

        $this->assertSame('Disponent', $data['name']);
        $this->assertSame(50, $data['priority']);
        $this->assertSame('primary', $data['color']);
        $this->assertSame(['users.view', 'edivi.view'], $data['permissions']);
    }

    #[Test]
    public function permissions_default_to_empty_array_when_missing(): void
    {
        $data = CreateRoleRequest::validate([
            'name'     => 'Praktikant',
            'priority' => '999',
            'color'    => 'secondary',
        ]);

        $this->assertSame([], $data['permissions']);
    }

    #[Test]
    public function blank_name_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        CreateRoleRequest::validate([
            'name'     => '   ',
            'priority' => '10',
            'color'    => 'success',
        ]);
    }

    #[Test]
    public function unknown_color_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        CreateRoleRequest::validate([
            'name'     => 'Test',
            'priority' => '10',
            'color'    => 'turquoise',
        ]);
    }

    #[Test]
    public function non_numeric_priority_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        CreateRoleRequest::validate([
            'name'     => 'Test',
            'priority' => 'abc',
            'color'    => 'primary',
        ]);
    }

    #[Test]
    public function priority_out_of_range_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        CreateRoleRequest::validate([
            'name'     => 'Test',
            'priority' => '99999',
            'color'    => 'primary',
        ]);
    }

    #[Test]
    public function non_string_permissions_are_filtered_out(): void
    {
        $data = CreateRoleRequest::validate([
            'name'        => 'Test',
            'priority'    => '10',
            'color'       => 'info',
            'permissions' => ['users.view', 42, null, 'edivi.view'],
        ]);
        $this->assertSame(['users.view', 'edivi.view'], $data['permissions']);
    }

    #[Test]
    public function validation_exception_carries_field_errors(): void
    {
        try {
            CreateRoleRequest::validate([
                'name'     => '',
                'priority' => 'abc',
                'color'    => 'rainbow',
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
            $this->assertNotNull($e->firstError());
            $this->assertSame(422, $e->getCode());
        }
    }
}
