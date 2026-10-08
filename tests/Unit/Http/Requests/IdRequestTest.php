<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\IdRequest;
use EmergencyForge\Http\Exceptions\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class IdRequestTest extends TestCase
{
    #[Test]
    public function liefert_die_id_als_zahl_und_laesst_andere_felder_liegen(): void
    {
        $this->assertSame(['id' => 42], IdRequest::validate(['id' => '42', 'fullname' => 'egal', 'csrf_token' => 'x']));
    }

    /** @return array<string,array{0:array<string,mixed>}> */
    public static function kaputt(): array
    {
        return [
            'fehlt'   => [[]],
            'leer'    => [['id' => '']],
            'null'    => [['id' => '0']],
            'negativ' => [['id' => '-3']],
            'text'    => [['id' => 'abc']],
            'liste'   => [['id' => ['7']]],
        ];
    }

    /** @param array<string,mixed> $input */
    #[Test]
    #[DataProvider('kaputt')]
    public function weist_ungueltige_ids_ab(array $input): void
    {
        try {
            IdRequest::validate($input);
            $this->fail('ValidationException erwartet');
        } catch (ValidationException $e) {
            $this->assertSame('Ungültige ID.', $e->firstError());
        }
    }
}
