<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\EventDispatcher;
use App\Events\PersonnelSaved;
use App\Models\Personnel;
use App\Models\PersonnelLog;
use App\Personnel\PersonalLogManager;
use App\Personnel\PersonnelQualifications as Q;
use Illuminate\Database\Capsule\Manager as Capsule;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Qualifikationen über PersonnelQualifications: Profilformular, API und
 * Fachdienste schreiben darüber, die Ausbildung trägt mit grant() nur
 * nach oben ein. Stufen aus den Seeds: RD 3 Keine, 4 RettSan, 6 NotSan.
 */
final class PersonnelQualificationsTest extends FeatureTestCase
{
    private const RD_NONE = 3;
    private const RD_RETTSAN = 4;
    private const RD_NOTSAN = 6;

    private Personnel $person;

    /** @var list<int> */
    private array $saved = [];

    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user(['full_admin' => true]);
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
        $this->person = FixtureFactory::personnel(['qualird' => self::RD_NONE]);

        app(EventDispatcher::class)->illuminate()->listen(PersonnelSaved::class, function (PersonnelSaved $event): void {
            $this->saved[] = $event->personnelId;
        });
    }

    private function rd(): int
    {
        return (int) Capsule::table('intra_mitarbeiter')->where('id', $this->person->id)->value('qualird');
    }

    /** @return list<PersonnelLog> */
    private function logs(int $type): array
    {
        return PersonnelLog::query()->where('profilid', $this->person->id)->where('type', $type)->get()->all();
    }

    #[Test]
    public function setzen_schreibt_den_verlauf_nur_bei_aenderung(): void
    {
        $service = new Q();

        $this->assertTrue($service->set((int) $this->person->id, Q::AREA_RD, self::RD_RETTSAN, 'Test'));
        $this->assertFalse($service->set((int) $this->person->id, Q::AREA_RD, self::RD_RETTSAN, 'Test'));

        $this->assertSame(self::RD_RETTSAN, $this->rd());
        $this->assertCount(1, $this->logs(PersonalLogManager::TYPE_MODIFICATION));
        $this->assertSame([], $this->saved, 'Das Ereignis feuert der Aufrufer.');
    }

    #[Test]
    public function profilformular_ueberschreibt_die_stufe_nicht_wieder(): void
    {
        $p    = $this->person;
        $body = [
            'new' => '1', 'id' => (string) $p->id, 'fullname' => 'Neuer Name', 'gebdatum' => '1990-01-01',
            'dienstgrad' => (string) $p->dienstgrad, 'discordtag' => '', 'telefonnr' => '', 'dienstnr' => (string) $p->dienstnr,
            'qualird' => (string) self::RD_NOTSAN, 'qualifw2' => (string) $p->qualifw2, 'geschlecht' => '0', 'zusatzqual' => '', 'pfp' => '',
        ];

        $this->post('/personnel/profile', $body, ['query' => ['id' => (string) $p->id]]);

        $this->assertSame(self::RD_NOTSAN, $this->rd());
        $this->assertSame('Neuer Name', Personnel::query()->findOrFail($p->id)->fullname);
        $rdLogs = array_filter(
            $this->logs(PersonalLogManager::TYPE_MODIFICATION),
            static fn (PersonnelLog $log): bool => str_contains((string) $log->content, 'Qualifikation (RD)'),
        );
        $this->assertCount(1, $rdLogs);
        $this->assertSame([(int) $p->id], $this->saved);
    }

    #[Test]
    public function fachdienste_speichern_feuert_das_ereignis(): void
    {
        $this->post('/personnel/profile', ['new' => '4', 'fachdienste' => ['7']], ['query' => ['id' => (string) $this->person->id]]);
        $this->post('/personnel/profile', ['new' => '4', 'fachdienste' => ['7']], ['query' => ['id' => (string) $this->person->id]]);

        $this->assertSame([(int) $this->person->id], $this->saved, 'Nur die echte Änderung zählt.');
    }

    #[Test]
    public function ausbildung_setzt_nur_nach_oben(): void
    {
        $service = new Q();
        $id      = (int) $this->person->id;

        $this->assertTrue($service->grant($id, Q::AREA_RD, self::RD_NOTSAN, 'NFS-Lehrgang 1/2026', 'System'));
        $this->assertFalse($service->grant($id, Q::AREA_RD, self::RD_RETTSAN, 'RS-Lehrgang 2/2026', 'System'));
        $this->assertFalse($service->grant($id, Q::AREA_RD, self::RD_NOTSAN, 'NFS-Lehrgang 1/2026', 'System'));

        $this->assertSame(self::RD_NOTSAN, $this->rd());
        $logs = $this->logs(PersonalLogManager::TYPE_TRAINING);
        $this->assertCount(1, $logs);
        $this->assertStringContainsString('NFS-Lehrgang 1/2026', (string) $logs[0]->content);
        $this->assertSame([$id], $this->saved);
    }

    #[Test]
    public function ausbildung_fuegt_einen_fachdienst_hinzu(): void
    {
        $id = (int) $this->person->id;
        Capsule::table('intra_mitarbeiter')->where('id', $id)->update(['fachdienste' => '["7"]']);

        $this->assertTrue((new Q())->grant($id, Q::AREA_DEPARTMENT, 12, 'Fachdienst-Lehrgang', 'System'));
        $this->assertFalse((new Q())->grant($id, Q::AREA_DEPARTMENT, '12', 'Fachdienst-Lehrgang', 'System'));

        $this->assertSame('["7","12"]', Capsule::table('intra_mitarbeiter')->where('id', $id)->value('fachdienste'));
    }

    #[Test]
    public function unbekannte_stufe_wird_abgelehnt(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Q())->grant((int) $this->person->id, Q::AREA_RD, 999999, 'Kaputt', 'System');
    }

    #[Test]
    public function ausbildung_steht_im_systemprotokoll(): void
    {
        (new Q())->grant((int) $this->person->id, Q::AREA_RD, self::RD_RETTSAN, 'RS-Lehrgang 1/2026', 'System');

        $entries = (new PersonalLogManager())->getSystemLogs((int) $this->person->id)['entries'];
        $this->assertSame('training', PersonalLogManager::getTypeName((int) $entries[0]['type']));
    }
}
