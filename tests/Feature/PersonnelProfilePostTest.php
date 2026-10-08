<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Personnel;
use App\Models\PersonnelLog;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die Posts aus der Personalakte: Fachdienste (new=4), Notiz (new=5), das
 * alte Bearbeitungsformular (new=1), Dokument löschen und Kommentar löschen.
 * Die Dialoge posten auf die Seite selbst, die Akte steht deshalb in `?id=`.
 */
final class PersonnelProfilePostTest extends FeatureTestCase
{
    private Personnel $person;

    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user(['full_admin' => true]);
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
        $this->person = FixtureFactory::personnel(['fullname' => 'Petra Post']);
    }

    /**
     * @param array<string,mixed> $body
     */
    private function profilePost(array $body): \EmergencyForge\Http\Response
    {
        return $this->post('/personnel/profile', $body, ['query' => ['id' => (string) $this->person->id]]);
    }

    /** @return array<string,string> */
    private function stammdaten(): array
    {
        $p = $this->person;

        return [
            'new'        => '1',
            'id'         => (string) $p->id,
            'fullname'   => 'Petra Neu',
            'gebdatum'   => '1990-01-01',
            'dienstgrad' => (string) $p->dienstgrad,
            'discordtag' => '',
            'telefonnr'  => '',
            'dienstnr'   => (string) $p->dienstnr,
            'qualird'    => (string) $p->qualird,
            'qualifw2'   => (string) $p->qualifw2,
            'geschlecht' => '0',
            'zusatzqual' => '',
            'pfp'        => '',
        ];
    }

    #[Test]
    public function fachdienste_speichern(): void
    {
        $this->assertRedirect($this->profilePost(['new' => '4', 'fachdienste' => ['7', '12']]), '/personnel/profile?id=' . $this->person->id);

        $this->assertSame('["7","12"]', Personnel::query()->findOrFail($this->person->id)->fachdienste);
    }

    #[Test]
    public function fachdienste_ohne_haekchen_leeren_die_liste(): void
    {
        $this->profilePost(['new' => '4', 'fachdienste' => ['7']]);
        $this->profilePost(['new' => '4']);

        $this->assertSame('[]', Personnel::query()->findOrFail($this->person->id)->fachdienste);
    }

    #[Test]
    public function fachdienste_als_text_aendern_nichts(): void
    {
        $this->profilePost(['new' => '4', 'fachdienste' => ['7']]);
        $this->profilePost(['new' => '4', 'fachdienste' => '9']);

        $this->assertSame('Die Fachdienste müssen als Liste kommen.', $_SESSION['flash']['text'] ?? null);
        $this->assertSame('["7"]', Personnel::query()->findOrFail($this->person->id)->fachdienste);
    }

    #[Test]
    public function notiz_anlegen(): void
    {
        $this->assertRedirect($this->profilePost(['new' => '5', 'noteType' => '0', 'content' => '  Allgemeine Notiz  ']));

        $this->assertSame(
            'Allgemeine Notiz',
            PersonnelLog::query()->where('profilid', $this->person->id)->where('type', 0)->value('content'),
        );
    }

    #[Test]
    public function notiz_mit_unbekanntem_typ_legt_nichts_an(): void
    {
        $this->profilePost(['new' => '5', 'noteType' => 'gut', 'content' => 'Text']);

        $this->assertSame('Unbekannter Notiztyp.', $_SESSION['flash']['text'] ?? null);
        $this->assertFalse(PersonnelLog::query()->where('profilid', $this->person->id)->where('content', 'Text')->exists());
    }

    #[Test]
    public function altes_formular_speichert_die_stammdaten(): void
    {
        $this->assertRedirect($this->profilePost($this->stammdaten()), '/personnel/profile?id=' . $this->person->id);

        $this->assertSame('Petra Neu', Personnel::query()->findOrFail($this->person->id)->fullname);
    }

    #[Test]
    public function altes_formular_mit_leerem_namen_springt_zur_akte_zurueck(): void
    {
        $response = $this->profilePost(['fullname' => ''] + $this->stammdaten());

        $this->assertRedirect($response, '/personnel/profile?id=' . $this->person->id);
        $this->assertSame('Der Name darf nicht leer sein und höchstens 255 Zeichen haben.', $_SESSION['flash']['text'] ?? null);
        $this->assertSame('Petra Post', Personnel::query()->findOrFail($this->person->id)->fullname);
    }

    #[Test]
    public function dokument_loeschen(): void
    {
        $docid = random_int(10000000, 99999999);
        Capsule::table('intra_mitarbeiter_dokumente')->insert([
            'docid'             => $docid,
            'profileid'         => $this->person->id,
            'ausstellerid'      => '1',
            'aussteller_name'   => 'Ausstellerin',
            'ausstellungsdatum' => '2026-01-15',
            'type'              => 1,
        ]);

        $this->assertRedirect($this->post('/personnel/document-delete', ['docid' => (string) $docid, 'pid' => (string) $this->person->id]));

        $this->assertFalse(Capsule::table('intra_mitarbeiter_dokumente')->where('docid', $docid)->exists());
    }

    #[Test]
    public function kommentar_loeschen_ohne_gueltige_id(): void
    {
        $this->assertRedirect($this->post('/personnel/comment-delete', ['id' => 'abc']));

        $this->assertSame('Ungültige/Keine ID angegeben.', $_SESSION['flash']['text'] ?? null);
    }
}
