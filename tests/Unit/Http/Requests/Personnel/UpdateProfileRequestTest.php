<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Personnel;

use App\Exceptions\ValidationException;
use App\Http\Requests\Personnel\UpdateProfileRequest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UpdateProfileRequestTest extends TestCase
{
    private function validBase(): array
    {
        return [
            'id'         => 42,
            'fullname'   => 'Max Mustermann',
            'gebdatum'   => '1990-05-17',
            'dienstgrad' => 3,
            'discordtag' => 'max#1234',
            'telefonnr'  => '0123456789',
            'dienstnr'   => 'ABC-1',
            'qualird'    => 2,
            'qualifw2'   => 1,
            'geschlecht' => 0,
            'zusatzqual' => 'Rettungsschwimmer',
            'pfp'        => '/storage/profile-pictures/42.png',
            'charakterid' => 'char-xyz',
        ];
    }

    #[Test]
    public function valid_input_is_normalized(): void
    {
        // Dienstnr wird VOR der Regex validiert, also schon ohne Whitespace erwartet —
        // die anderen String-Felder werden nach Validierung getrimmt.
        $data = UpdateProfileRequest::validate([
            'id'         => '42',
            'fullname'   => '  Max Mustermann  ',
            'gebdatum'   => '1990-05-17',
            'dienstgrad' => '3',
            'discordtag' => '  max#1234  ',
            'telefonnr'  => '0123456789',
            'dienstnr'   => 'ABC-1',
            'qualird'    => '2',
            'qualifw2'   => '1',
            'geschlecht' => '0',
            'zusatzqual' => '',
            'pfp'        => '',
            'charakterid' => '',
        ]);

        $this->assertSame(42, $data['id']);
        $this->assertSame('Max Mustermann', $data['fullname']);
        $this->assertSame(3, $data['dienstgrad']);
        $this->assertSame('max#1234', $data['discordtag']);
        $this->assertSame('ABC-1', $data['dienstnr']);
        $this->assertSame(0, $data['geschlecht']);
    }

    #[Test]
    public function optional_fields_default_to_empty_or_zero(): void
    {
        $data = UpdateProfileRequest::validate([
            'id'       => 7,
            'fullname' => 'Alex',
            'gebdatum' => '2000-01-01',
        ]);

        $this->assertSame(0, $data['dienstgrad']);
        $this->assertSame(0, $data['qualird']);
        $this->assertSame(0, $data['qualifw2']);
        $this->assertSame(0, $data['geschlecht']);
        $this->assertSame('', $data['dienstnr']);
        $this->assertSame('', $data['charakterid']);
    }

    #[Test]
    public function blank_fullname_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $payload = $this->validBase();
        $payload['fullname'] = '';
        UpdateProfileRequest::validate($payload);
    }

    #[Test]
    public function missing_id_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $payload = $this->validBase();
        unset($payload['id']);
        UpdateProfileRequest::validate($payload);
    }

    #[Test]
    public function zero_id_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $payload = $this->validBase();
        $payload['id'] = 0;
        UpdateProfileRequest::validate($payload);
    }

    #[Test]
    public function invalid_date_format_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $payload = $this->validBase();
        $payload['gebdatum'] = '17.05.1990';
        UpdateProfileRequest::validate($payload);
    }

    #[Test]
    public function dienstnr_without_digit_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $payload = $this->validBase();
        $payload['dienstnr'] = 'ABC-DEF';
        UpdateProfileRequest::validate($payload);
    }

    #[Test]
    public function dienstnr_with_invalid_chars_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $payload = $this->validBase();
        $payload['dienstnr'] = 'ABC 1';
        UpdateProfileRequest::validate($payload);
    }

    #[Test]
    public function empty_dienstnr_is_accepted(): void
    {
        $payload = $this->validBase();
        $payload['dienstnr'] = '';
        $data = UpdateProfileRequest::validate($payload);
        $this->assertSame('', $data['dienstnr']);
    }

    #[Test]
    public function invalid_geschlecht_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $payload = $this->validBase();
        $payload['geschlecht'] = 5;
        UpdateProfileRequest::validate($payload);
    }

    #[Test]
    public function valid_geschlecht_values(): void
    {
        foreach ([0, 1, 2] as $g) {
            $payload = $this->validBase();
            $payload['geschlecht'] = $g;
            $data = UpdateProfileRequest::validate($payload);
            $this->assertSame($g, $data['geschlecht']);
        }
    }

    #[Test]
    public function validation_exception_carries_field_errors(): void
    {
        try {
            UpdateProfileRequest::validate([
                'id'       => 'abc',
                'fullname' => '',
                'gebdatum' => 'not-a-date',
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
            $this->assertSame(422, $e->getCode());
        }
    }
}
