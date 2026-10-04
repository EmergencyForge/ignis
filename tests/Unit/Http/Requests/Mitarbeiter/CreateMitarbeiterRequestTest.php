<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Mitarbeiter;

use App\Http\Requests\Mitarbeiter\CreateMitarbeiterRequest;
use EmergencyForge\Http\Exceptions\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Die Discord-ID ist beim Anlegen optional (ADR-0002, Punkt 7): auf
 * Instanzen mit zentraler Anmeldung gibt es sie oft nicht.
 */
final class CreateMitarbeiterRequestTest extends TestCase
{
    /** @return array<string,string> */
    private function input(): array
    {
        return [
            'fullname'   => 'Max Muster',
            'gebdatum'   => '1990-01-01',
            'dienstgrad' => '3',
            'geschlecht' => '0',
            'dienstnr'   => 'RD-001',
            'einstdatum' => '2024-01-01',
        ];
    }

    #[Test]
    public function ohne_discord_id_wird_sie_null(): void
    {
        $this->assertNull(CreateMitarbeiterRequest::validate($this->input())['discordtag']);
        $this->assertNull(CreateMitarbeiterRequest::validate($this->input() + ['discordtag' => ''])['discordtag']);
    }

    #[Test]
    public function eine_angegebene_discord_id_wird_geprueft(): void
    {
        $this->assertSame('123456789012345678', CreateMitarbeiterRequest::validate($this->input() + ['discordtag' => '123456789012345678'])['discordtag']);

        $this->expectException(ValidationException::class);
        CreateMitarbeiterRequest::validate($this->input() + ['discordtag' => 'abc']);
    }
}
