<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Mitarbeiter;

use App\Exceptions\ValidationException;
use App\Http\Requests\Mitarbeiter\CreateDocumentRequest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CreateDocumentRequestTest extends TestCase
{
    #[Test]
    public function valid_minimal_input_passes(): void
    {
        $data = CreateDocumentRequest::validate([
            'profileid' => '42',
            'docType'   => '1',
        ]);

        $this->assertSame(42, $data['profileid']);
        $this->assertSame('1', $data['docType']);
        $this->assertNull($data['inhalt']);
        $this->assertSame(date('Y-m-d'), $data['ausstellungsdatum']);
    }

    #[Test]
    public function missing_profileid_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        CreateDocumentRequest::validate(['docType' => '1']);
    }

    #[Test]
    public function zero_profileid_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        CreateDocumentRequest::validate([
            'profileid' => '0',
            'docType'   => '1',
        ]);
    }

    #[Test]
    public function unknown_doctype_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        CreateDocumentRequest::validate([
            'profileid' => '42',
            'docType'   => '99',
        ]);
    }

    #[Test]
    public function ausstellungsdatum_follows_doctype_mapping_for_9(): void
    {
        // docType < 10 → Suffix = docType
        $data = CreateDocumentRequest::validate([
            'profileid'             => '42',
            'docType'               => '5',
            'ausstellungsdatum_5'   => '2024-06-15',
            'ausstellungsdatum_10'  => '2099-12-31',
        ]);
        $this->assertSame('2024-06-15', $data['ausstellungsdatum']);
    }

    #[Test]
    public function ausstellungsdatum_collapses_10_11_12_13_to_suffix_10(): void
    {
        // docType 10/11/12/13 → gemeinsamer Suffix '_10'
        foreach (['10', '11', '12', '13'] as $type) {
            $data = CreateDocumentRequest::validate([
                'profileid'             => '42',
                'docType'               => $type,
                'ausstellungsdatum_10'  => '2024-07-01',
                'ausstellungsdatum_0'   => '2020-01-01',
            ]);
            $this->assertSame('2024-07-01', $data['ausstellungsdatum'], "docType $type should use _10 suffix");
        }
    }

    #[Test]
    public function ausstellungsdatum_falls_back_to_0_suffix_when_specific_missing(): void
    {
        $data = CreateDocumentRequest::validate([
            'profileid'             => '42',
            'docType'               => '5',
            'ausstellungsdatum_0'   => '2020-01-01',
        ]);
        $this->assertSame('2020-01-01', $data['ausstellungsdatum']);
    }

    #[Test]
    public function empty_ausstellungsdatum_defaults_to_today(): void
    {
        $data = CreateDocumentRequest::validate([
            'profileid' => '42',
            'docType'   => '2',
        ]);
        $this->assertSame(date('Y-m-d'), $data['ausstellungsdatum']);
    }

    #[Test]
    public function german_date_format_is_accepted_and_normalized(): void
    {
        $data = CreateDocumentRequest::validate([
            'profileid'           => '42',
            'docType'             => '5',
            'ausstellungsdatum_5' => '15.06.2024',
        ]);
        $this->assertSame('2024-06-15', $data['ausstellungsdatum']);
    }

    #[Test]
    public function optional_strings_trim_and_nullify_empty(): void
    {
        $data = CreateDocumentRequest::validate([
            'profileid'      => '42',
            'docType'        => '1',
            'inhalt'         => '  hello  ',
            'erhalter'       => '',
            'aussteller_name' => 'Dr. Mueller',
        ]);

        $this->assertSame('hello', $data['inhalt']);
        $this->assertNull($data['erhalter']);
        $this->assertSame('Dr. Mueller', $data['aussteller_name']);
    }

    #[Test]
    public function inhalt_over_10k_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        CreateDocumentRequest::validate([
            'profileid' => '42',
            'docType'   => '1',
            'inhalt'    => str_repeat('x', 10_001),
        ]);
    }
}
