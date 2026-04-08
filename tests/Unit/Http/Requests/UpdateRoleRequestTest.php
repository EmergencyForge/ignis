<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Exceptions\ValidationException;
use App\Http\Requests\Roles\UpdateRoleRequest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UpdateRoleRequestTest extends TestCase
{
    #[Test]
    public function valid_input_passes_and_casts_id_to_int(): void
    {
        $data = UpdateRoleRequest::validate([
            'id'          => '42',
            'name'        => 'Disponent',
            'priority'    => '50',
            'color'       => 'primary',
            'permissions' => ['users.view'],
        ]);

        $this->assertSame(42, $data['id']);
        $this->assertSame('Disponent', $data['name']);
    }

    #[Test]
    public function zero_id_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        UpdateRoleRequest::validate([
            'id'       => '0',
            'name'     => 'Disponent',
            'priority' => '50',
            'color'    => 'primary',
        ]);
    }

    #[Test]
    public function missing_id_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        UpdateRoleRequest::validate([
            'name'     => 'Disponent',
            'priority' => '50',
            'color'    => 'primary',
        ]);
    }
}
