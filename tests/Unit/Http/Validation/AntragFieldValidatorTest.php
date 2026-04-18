<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Validation;

use App\Exceptions\ValidationException;
use App\Http\Validation\AntragFieldValidator;
use App\Models\AntragField;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AntragFieldValidatorTest extends TestCase
{
    /**
     * Baut einen AntragField-Eloquent-Stub ohne DB-Persistenz.
     */
    private function field(array $attrs): AntragField
    {
        $f = new AntragField();
        foreach ($attrs as $k => $v) {
            $f->setAttribute($k, $v);
        }
        return $f;
    }

    #[Test]
    public function required_text_field_rejects_empty(): void
    {
        $felder = [$this->field([
            'feldname'    => 'grund',
            'feldtyp'     => 'text',
            'label'       => 'Grund',
            'pflichtfeld' => 1,
            'readonly'    => 0,
        ])];

        try {
            AntragFieldValidator::validate($felder, ['grund' => '']);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertArrayHasKey('grund', $errors);
            $this->assertStringContainsString('Pflichtfeld', $errors['grund']);
        }
    }

    #[Test]
    public function required_text_field_accepts_non_empty(): void
    {
        $felder = [$this->field([
            'feldname'    => 'grund',
            'feldtyp'     => 'text',
            'label'       => 'Grund',
            'pflichtfeld' => 1,
            'readonly'    => 0,
        ])];

        $result = AntragFieldValidator::validate($felder, ['grund' => '  Urlaub  ']);
        $this->assertSame('Urlaub', $result['grund']);
    }

    #[Test]
    public function optional_empty_field_returns_empty_string(): void
    {
        $felder = [$this->field([
            'feldname'    => 'notiz',
            'feldtyp'     => 'textarea',
            'pflichtfeld' => 0,
            'readonly'    => 0,
        ])];

        $result = AntragFieldValidator::validate($felder, []);
        $this->assertSame('', $result['notiz']);
    }

    #[Test]
    public function readonly_fields_are_skipped(): void
    {
        $felder = [$this->field([
            'feldname'    => 'fullname',
            'feldtyp'     => 'text',
            'pflichtfeld' => 1,
            'readonly'    => 1,
        ])];

        // Trotz leerer Eingabe KEIN Fehler, weil readonly=1 → Feld wird ignoriert.
        $result = AntragFieldValidator::validate($felder, []);
        $this->assertArrayNotHasKey('fullname', $result);
    }

    #[Test]
    public function email_field_validates_format(): void
    {
        $felder = [$this->field([
            'feldname'    => 'kontakt',
            'feldtyp'     => 'email',
            'pflichtfeld' => 1,
            'readonly'    => 0,
        ])];

        $this->expectException(ValidationException::class);
        AntragFieldValidator::validate($felder, ['kontakt' => 'nicht-eine-email']);
    }

    #[Test]
    public function email_field_accepts_valid_email(): void
    {
        $felder = [$this->field([
            'feldname'    => 'kontakt',
            'feldtyp'     => 'email',
            'pflichtfeld' => 1,
            'readonly'    => 0,
        ])];

        $result = AntragFieldValidator::validate($felder, ['kontakt' => 'test@example.com']);
        $this->assertSame('test@example.com', $result['kontakt']);
    }

    #[Test]
    public function date_field_enforces_iso_format(): void
    {
        $felder = [$this->field([
            'feldname'    => 'datum',
            'feldtyp'     => 'date',
            'pflichtfeld' => 1,
            'readonly'    => 0,
        ])];

        $this->expectException(ValidationException::class);
        AntragFieldValidator::validate($felder, ['datum' => '17.05.2024']);
    }

    #[Test]
    public function date_field_accepts_iso(): void
    {
        $felder = [$this->field([
            'feldname'    => 'datum',
            'feldtyp'     => 'date',
            'pflichtfeld' => 1,
            'readonly'    => 0,
        ])];

        $result = AntragFieldValidator::validate($felder, ['datum' => '2024-05-17']);
        $this->assertSame('2024-05-17', $result['datum']);
    }

    #[Test]
    public function time_field_validates_hhmm(): void
    {
        $felder = [$this->field([
            'feldname'    => 'zeit',
            'feldtyp'     => 'time',
            'pflichtfeld' => 1,
            'readonly'    => 0,
        ])];

        $result = AntragFieldValidator::validate($felder, ['zeit' => '14:30']);
        $this->assertSame('14:30', $result['zeit']);

        // Regex prüft nur das Format, keine Bereichsvalidierung der Zahlen —
        // '14h30' failt am Separator.
        $this->expectException(ValidationException::class);
        AntragFieldValidator::validate($felder, ['zeit' => '14h30']);
    }

    #[Test]
    public function number_field_requires_numeric(): void
    {
        $felder = [$this->field([
            'feldname'    => 'tage',
            'feldtyp'     => 'number',
            'pflichtfeld' => 1,
            'readonly'    => 0,
        ])];

        $this->expectException(ValidationException::class);
        AntragFieldValidator::validate($felder, ['tage' => 'abc']);
    }

    #[Test]
    public function select_field_enforces_whitelist(): void
    {
        $felder = [$this->field([
            'feldname'    => 'typ',
            'feldtyp'     => 'select',
            'optionen'    => "Urlaub\nDienstreise\nSonderurlaub",
            'pflichtfeld' => 1,
            'readonly'    => 0,
        ])];

        $result = AntragFieldValidator::validate($felder, ['typ' => 'Dienstreise']);
        $this->assertSame('Dienstreise', $result['typ']);

        $this->expectException(ValidationException::class);
        AntragFieldValidator::validate($felder, ['typ' => 'Irgendwas']);
    }

    #[Test]
    public function undeclared_fields_are_ignored(): void
    {
        $felder = [$this->field([
            'feldname'    => 'grund',
            'feldtyp'     => 'text',
            'pflichtfeld' => 1,
            'readonly'    => 0,
        ])];

        // Angreifer sendet Extra-Felder — die müssen rausgefiltert werden (Mass-Assignment-Schutz).
        $result = AntragFieldValidator::validate($felder, [
            'grund'       => 'Urlaub',
            'admin_flag'  => '1',
            'is_approved' => 'yes',
        ]);
        $this->assertArrayNotHasKey('admin_flag', $result);
        $this->assertArrayNotHasKey('is_approved', $result);
        $this->assertSame(['grund' => 'Urlaub'], $result);
    }

    #[Test]
    public function tel_field_allows_digits_and_common_separators(): void
    {
        $felder = [$this->field([
            'feldname'    => 'telefon',
            'feldtyp'     => 'tel',
            'pflichtfeld' => 1,
            'readonly'    => 0,
        ])];

        $result = AntragFieldValidator::validate($felder, ['telefon' => '+49 (0) 123-456 789']);
        $this->assertSame('+49 (0) 123-456 789', $result['telefon']);
    }

    #[Test]
    public function checkbox_accepts_standard_values(): void
    {
        $felder = [$this->field([
            'feldname'    => 'zustimmung',
            'feldtyp'     => 'checkbox',
            'pflichtfeld' => 0,
            'readonly'    => 0,
        ])];

        foreach (['0', '1', 'on', 'off'] as $val) {
            $result = AntragFieldValidator::validate($felder, ['zustimmung' => $val]);
            $this->assertSame($val, $result['zustimmung']);
        }
    }

    #[Test]
    public function validation_exception_carries_code_422(): void
    {
        $felder = [$this->field([
            'feldname'    => 'x',
            'feldtyp'     => 'text',
            'pflichtfeld' => 1,
            'readonly'    => 0,
        ])];

        try {
            AntragFieldValidator::validate($felder, []);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->getCode());
        }
    }
}
