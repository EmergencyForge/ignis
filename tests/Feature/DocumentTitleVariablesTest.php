<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Documents\Editor\VariableCatalog;
use App\Models\PersonnelTitle;
use App\Personnel\AccountLink;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/** Namens-Platzhalter in Dokumenten tragen den Titel, ausgestellte bleiben eingefroren. */
final class DocumentTitleVariablesTest extends FeatureTestCase
{
    #[Test]
    public function mitarbeiter_und_aussteller_mit_titel(): void
    {
        $dr   = (int) PersonnelTitle::query()->where('name', 'Dr.')->value('id');
        $prof = (int) PersonnelTitle::query()->where('name', 'Prof.')->value('id');
        $mitarbeiter = FixtureFactory::personnel(['fullname' => 'Max Muster', 'titel_id' => $dr]);
        $aussteller  = FixtureFactory::personnel(['fullname' => 'Erika Beispiel', 'geschlecht' => 1, 'titel_id' => $prof]);
        $user = FixtureFactory::user(['aktenid' => $aussteller->id]);
        $this->actingAs($user->id);
        AccountLink::forget();

        $values = VariableCatalog::resolve(['mitarbeiter' => $mitarbeiter->fresh()]);

        $this->assertSame('Dr. Max Muster', $values['mitarbeiter.name']);
        $this->assertSame('Dr.', $values['mitarbeiter.titel']);
        $this->assertSame('Prof. Erika Beispiel', $values['aussteller.name']);
        $this->assertSame('Prof.', $values['aussteller.titel']);
        $this->assertArrayHasKey('mitarbeiter.titel', VariableCatalog::catalog());
        $this->assertArrayHasKey('aussteller.titel', VariableCatalog::catalog());
    }

    #[Test]
    public function ohne_titel_bleibt_der_name_und_der_titel_fehlt(): void
    {
        $values = VariableCatalog::resolve(['mitarbeiter' => FixtureFactory::personnel(['fullname' => 'Max Muster'])]);

        $this->assertSame('Max Muster', $values['mitarbeiter.name']);
        $this->assertArrayNotHasKey('mitarbeiter.titel', $values);
    }
}
