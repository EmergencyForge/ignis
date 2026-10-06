<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PersonnelTitle;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Titel stehen in einer eigenen Liste und nie im Namen: fullname ist
 * Schlüssel für Protokollanten, Mail-Adressen und Federation.
 */
final class PersonnelTitleTest extends FeatureTestCase
{
    #[Test]
    public function formeller_name_setzt_den_titel_vor_den_namen(): void
    {
        $titel = PersonnelTitle::query()->create(['name' => 'Dr. rer. nat.', 'priority' => 99]);
        $mit   = FixtureFactory::personnel(['fullname' => 'Max Muster', 'titel_id' => $titel->id]);
        $ohne  = FixtureFactory::personnel(['fullname' => 'Erika Muster']);

        $this->assertSame('Dr. rer. nat. Max Muster', $mit->refresh()->formalName());
        $this->assertSame('Erika Muster', $ohne->refresh()->formalName());
        $this->assertSame('Max Muster', $mit->refresh()->fullname);
    }

    #[Test]
    public function startbelegung_optionen_und_pruefung_der_kennung(): void
    {
        $this->assertSame(['Dr.', 'Dr. med.', 'Prof.', 'Prof. Dr.'], PersonnelTitle::query()->orderBy('priority')->pluck('name')->all());

        $dr = (int) PersonnelTitle::query()->where('name', 'Dr.')->value('id');
        $this->assertSame($dr, PersonnelTitle::existingId((string) $dr));
        $this->assertNull(PersonnelTitle::existingId(''));
        $this->assertNull(PersonnelTitle::existingId(null));
        $this->assertNull(PersonnelTitle::existingId('999999'));

        $options = PersonnelTitle::options();
        $this->assertSame(['', 'Kein Titel'], $options[0]);
        $this->assertSame([(string) $dr, 'Dr.'], $options[1]);
    }
}
