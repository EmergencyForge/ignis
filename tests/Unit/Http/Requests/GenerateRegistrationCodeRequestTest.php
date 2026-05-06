<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Exceptions\ValidationException;
use App\Http\Requests\Users\GenerateRegistrationCodeRequest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GenerateRegistrationCodeRequestTest extends TestCase
{
    #[Test]
    public function empty_input_is_valid_and_returns_nulls(): void
    {
        $data = GenerateRegistrationCodeRequest::validate([
            'action' => 'generate',
        ]);

        $this->assertNull($data['label']);
        $this->assertNull($data['expires_at']);
    }

    #[Test]
    public function empty_string_label_becomes_null(): void
    {
        $data = GenerateRegistrationCodeRequest::validate([
            'label' => '   ',
        ]);
        $this->assertNull($data['label']);
    }

    #[Test]
    public function label_is_trimmed_and_kept(): void
    {
        $data = GenerateRegistrationCodeRequest::validate([
            'label' => '  Einladung Max  ',
        ]);
        $this->assertSame('Einladung Max', $data['label']);
    }

    #[Test]
    public function datetime_local_format_is_accepted(): void
    {
        $data = GenerateRegistrationCodeRequest::validate([
            'label'      => '',
            'expires_at' => '2026-12-31T23:59',
        ]);
        $this->assertSame('2026-12-31T23:59', $data['expires_at']);
    }

    #[Test]
    public function invalid_datetime_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        GenerateRegistrationCodeRequest::validate([
            'expires_at' => 'tomorrow at noon',
        ]);
    }

    #[Test]
    public function too_long_label_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        GenerateRegistrationCodeRequest::validate([
            'label' => str_repeat('x', 256),
        ]);
    }
}
