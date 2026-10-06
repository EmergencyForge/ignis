<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Personnel;
use App\Models\PersonnelTitle;
use EmergencyForge\Http\Request;
use Illuminate\Database\Capsule\Manager as Capsule;
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

    #[Test]
    public function anlegen_mit_titel_und_ohne(): void
    {
        $user = FixtureFactory::user(['full_admin' => true]);
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
        $dr   = (int) PersonnelTitle::query()->where('name', 'Dr.')->value('id');
        $rank = (int) Capsule::table('intra_mitarbeiter_dienstgrade')->where('archive', 0)->min('id');

        foreach (['Titel Mit' => (string) $dr, 'Titel Ohne' => ''] as $name => $titelId) {
            $response = $this->post('/personnel/create', [
                'fullname'    => $name,
                'titel_id'    => $titelId,
                'gebdatum'    => '1990-01-01',
                'dienstgrad'  => (string) $rank,
                'geschlecht'  => '0',
                'discordtag'  => '',
                'telefonnr'   => '',
                'dienstnr'    => 'TT-' . uniqid(),
                'einstdatum'  => '2024-01-01',
                'charakterid' => 'ABC12345',
            ]);
            $this->assertRedirect($response, '/personnel/profile');
        }

        $this->assertSame($dr, (int) Personnel::query()->where('fullname', 'Titel Mit')->value('titel_id'));
        $this->assertNull(Personnel::query()->where('fullname', 'Titel Ohne')->value('titel_id'));
    }

    /** @param array<string,mixed> $extra */
    private function saveProfile(Personnel $p, array $extra): \EmergencyForge\Http\Response
    {
        $payload = $extra + [
            'id' => $p->id, 'fullname' => $p->fullname, 'gebdatum' => '1990-01-01',
            'dienstgrad' => (string) $p->dienstgrad, 'discordtag' => '', 'telefonnr' => '',
            'dienstnr' => $p->dienstnr, 'qualird' => (string) $p->qualird, 'qualifw2' => (string) $p->qualifw2,
            'geschlecht' => '0', 'zusatzqual' => '', 'pfp' => '', 'charakterid' => '',
        ];

        return $this->router->dispatch(new Request(
            'POST',
            '/api/personnel/update-profile',
            server: ['HTTP_X_CSRF_TOKEN' => $this->csrfToken(), 'CONTENT_TYPE' => 'application/json'],
            rawBody: json_encode($payload, JSON_THROW_ON_ERROR),
        ));
    }

    #[Test]
    public function profil_setzt_titel_zeigt_ihn_im_kopf_und_behaelt_ihn_ohne_feld(): void
    {
        $user = FixtureFactory::user(['full_admin' => true]);
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
        $dr     = (int) PersonnelTitle::query()->where('name', 'Dr.')->value('id');
        $person = FixtureFactory::personnel(['fullname' => 'Max Muster']);

        // Vorab speichern, damit Telefon und Profilbild normalisiert sind und
        // danach allein der Titel einen Unterschied machen kann.
        $this->assertStatus(200, $this->saveProfile($person, []));

        $response = $this->saveProfile($person, ['titel_id' => (string) $dr]);
        $this->assertStatus(200, $response);
        $json = $this->assertJsonResponse($response);
        $this->assertSame(['basedata'], $json['changes']);
        $this->assertSame('Herr Dr. Max Muster', $json['display']['profileName']);
        $this->assertSame($dr, (int) Personnel::query()->whereKey($person->id)->value('titel_id'));

        // Ein Client ohne das Feld darf den Titel nicht löschen.
        $this->assertStatus(200, $this->saveProfile($person, ['telefonnr' => '555']));
        $this->assertSame($dr, (int) Personnel::query()->whereKey($person->id)->value('titel_id'));

        $page = $this->get('/personnel/profile', ['query' => ['id' => (string) $person->id]]);
        $this->assertBodyContains('Herr Dr. Max Muster', $page);
        $this->assertBodyContains('data-field="titel_id"', $page);

        // Telefon bleibt gleich, nur der Titel fällt weg.
        $cleared = $this->saveProfile($person, ['telefonnr' => '555', 'titel_id' => '']);
        $this->assertStatus(200, $cleared);
        $this->assertSame(['basedata'], $this->assertJsonResponse($cleared)['changes']);
        $this->assertNull(Personnel::query()->whereKey($person->id)->value('titel_id'));
    }
}
