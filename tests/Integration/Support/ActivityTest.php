<?php

declare(strict_types=1);

namespace Tests\Integration\Support;

use App\Support\Activity;
use App\Utils\AuditLogger;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FixtureFactory;
use Tests\IntegrationTestCase;

/**
 * Die Aktivitätsliste eines Fahrzeugs liest das Prüfprotokoll.
 *
 * Neue Einträge tragen die Kennung als JSON in `context`; Zeilen von vor
 * dieser Spalte haben sie nur als Text. Beide müssen gefunden werden, und
 * Fahrzeug 12 darf dabei nicht die Einträge von 123 einsammeln — genau
 * daran krankte die Textsuche.
 */
final class ActivityTest extends IntegrationTestCase
{
    private int $userId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // intra_audit_log.user haengt per Fremdschluessel an
        // intra_users; ohne echten Benutzer verschluckt der Logger
        // den Insert und der Test prueft nichts.
        $this->userId = FixtureFactory::user()->id;
    }

    /** @param array<string,scalar|null> $context */
    private function log(string $action, ?string $details, array $context = []): void
    {
        (new AuditLogger())->log($this->userId, $action, $details, 'Fahrzeuge', 1, $context);
    }

    #[Test]
    public function der_kontext_findet_den_eintrag_auch_ohne_kennung_im_text(): void
    {
        // Bewusst ohne „[ID: 12]" in der Meldung: was hier gefunden
        // wird, kann nur ueber den Kontext gefunden worden sein.
        $this->log('Fahrzeug aktualisiert', null, ['id' => 12]);

        $entries = Activity::vehicle(12);

        $this->assertCount(1, $entries);
        $this->assertSame('Stammdaten geändert', $entries[0]['label']);
    }

    #[Test]
    public function alte_zeilen_ohne_kontext_werden_weiter_gefunden(): void
    {
        // So schrieb ignis vor der Kontextspalte.
        Capsule::table('intra_audit_log')->insert([
            'user'    => $this->userId,
            'module'  => 'Fahrzeuge',
            'action'  => 'Fahrzeug gelöscht [ID: 12]',
            'details' => null,
            'global'  => 1,
        ]);

        $entries = Activity::vehicle(12);

        $this->assertCount(1, $entries);
        $this->assertSame('Fahrzeug gelöscht', $entries[0]['label']);
    }

    #[Test]
    public function eine_andere_kennung_faellt_nicht_mit_hinein(): void
    {
        $this->log('Fahrzeug aktualisiert [ID: 123]', null, ['id' => 123]);
        $this->log('Fahrzeug aktualisiert [ID: 12]', null, ['id' => 12]);

        $this->assertCount(1, Activity::vehicle(12));
        $this->assertCount(1, Activity::vehicle(123));
    }

    #[Test]
    public function ein_mangel_erscheint_in_der_liste_seines_fahrzeugs(): void
    {
        // Die Kennung in der Aktion ist die des Mangels, der Kontext zeigt
        // auf das Fahrzeug — sonst stuende der Mangel bei Fahrzeug 7.
        $this->log('Defekt gemeldet [ID: 7]', 'Fahrzeug-ID: 12 | Bremsen', ['id' => 12, 'defect_id' => 7]);

        $entries = Activity::vehicle(12);

        $this->assertCount(1, $entries);
        $this->assertSame('Mangel gemeldet: Bremsen', $entries[0]['label']);
        $this->assertSame([], Activity::vehicle(7));
    }
}
