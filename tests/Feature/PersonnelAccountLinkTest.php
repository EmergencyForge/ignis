<?php

declare(strict_types=1);

namespace Tests\Feature;

use Plugin\Calendar\Models\CalendarEvent;
use Plugin\Forms\Models\Form;
use Plugin\Forms\Models\FormType;
use App\Models\Personnel;
use App\Models\RegistrationCode;
use App\Models\User;
use App\Personnel\AccountLink;
use EmergencyForge\Http\Request;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Mail\Models\Mailbox;
use Tests\Feature\Mail\MailFixtures;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * ADR-0002 in den Seiten: Verknüpfen und Lösen in der Benutzerbearbeitung
 * und im Mitarbeiterprofil, Einladungen mit Mitarbeiter, und ein Konto aus
 * der zentralen Anmeldung (ohne Discord-ID) sieht nach der Verknüpfung
 * seine eigenen Daten, vorher keine fremden.
 */
final class PersonnelAccountLinkTest extends FeatureTestCase
{
    use MailFixtures;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
    }

    /** @param list<string> $permissions */
    private function loginAdmin(array $permissions = ['full_admin']): void
    {
        $this->admin = FixtureFactory::user();
        $this->actingAs($this->admin->id, ['permissions' => $permissions, 'cirs_username' => $this->admin->username, 'discordtag' => (string) $this->admin->discord_id]);
    }

    /** Konto wie aus der zentralen Anmeldung: ohne Discord-ID. */
    private function centralAccount(): User
    {
        $user = FixtureFactory::user();
        User::query()->whereKey($user->id)->update(['discord_id' => null]);

        return $user->refresh();
    }

    /** @param list<string> $permissions */
    private function loginCentral(User $user, array $permissions = ['full_admin']): void
    {
        $_SESSION = [];
        $this->actingAs($user->id, ['permissions' => $permissions, 'cirs_username' => $user->username, 'discordtag' => null]);
    }

    /** @param array<string,string> $body */
    private function linkRequest(array $body): \EmergencyForge\Http\Response
    {
        return $this->post('/users/personnel-link', $body);
    }

    #[Test]
    public function benutzerbearbeitung_verknuepft_und_loest_mit_audit(): void
    {
        $this->loginAdmin();
        $target = FixtureFactory::user();
        $person = FixtureFactory::personnel(['fullname' => 'Vera Verknuepft']);

        $page = $this->get('/users/edit', ['query' => ['id' => (string) $target->id]]);
        $this->assertOk($page);
        $this->assertBodyContains('<h2 class="twplus-section-card__title mb-2">Mitarbeiter</h2>', $page);
        $this->assertBodyContains('<option value="' . $person->id . '">Vera Verknuepft', $page);

        $this->assertRedirect($this->linkRequest(['id' => (string) $target->id, 'action' => 'link', 'mitarbeiter_id' => (string) $person->id]), '/users/edit?id=' . $target->id);
        $this->assertSame((int) $person->id, AccountLink::personnelFor((int) $target->id)?->id);
        $this->assertSame(1, Capsule::table('intra_audit_log')->where('user', $this->admin->id)->where('action', 'Mitarbeiter verknüpft [Benutzer-ID: ' . $target->id . ']')->count());

        $page = $this->get('/users/edit', ['query' => ['id' => (string) $target->id]]);
        $this->assertBodyContains('Vera Verknuepft', $page);
        $this->assertBodyContains('Verknüpfung lösen', $page);

        $this->assertRedirect($this->linkRequest(['id' => (string) $target->id, 'action' => 'unlink']));
        $this->assertNull(AccountLink::personnelFor((int) $target->id));
        $this->assertSame(1, Capsule::table('intra_audit_log')->where('user', $this->admin->id)->where('action', 'Mitarbeiterverknüpfung gelöst [Benutzer-ID: ' . $target->id . ']')->count());
    }

    #[Test]
    public function das_eigene_konto_laesst_sich_nicht_umhaengen(): void
    {
        $this->loginAdmin();
        $person = FixtureFactory::personnel();

        $this->assertRedirect($this->linkRequest(['id' => (string) $this->admin->id, 'action' => 'link', 'mitarbeiter_id' => (string) $person->id]));
        $this->assertNull(AccountLink::personnelFor((int) $this->admin->id));

        // Im Profil steht das eigene Konto nicht zur Wahl, ein anderes schon.
        $other = FixtureFactory::user();
        $page = $this->get('/personnel/profile', ['query' => ['id' => (string) $person->id]]);
        $this->assertBodyContains('<option value="' . $other->id . '">', $page);
        $this->assertBodyContains('id="linkAccountForm"', $page);
        $this->assertBodyNotContains('<option value="' . $this->admin->id . '">', $page);

        // Verknüpft lässt es sich auch nicht lösen.
        AccountLink::link((int) $this->admin->id, (int) $person->id, (int) $this->admin->id);
        $this->assertRedirect($this->linkRequest(['id' => (string) $this->admin->id, 'action' => 'unlink']));
        $this->assertSame((int) $person->id, AccountLink::personnelFor((int) $this->admin->id)?->id);
        $this->assertBodyNotContains('id="unlinkAccountForm"', $this->get('/personnel/profile', ['query' => ['id' => (string) $person->id]]));
    }

    #[Test]
    public function ohne_recht_und_bei_hoeherer_rolle_bleibt_alles_wie_es_ist(): void
    {
        $this->loginAdmin(['users.view', 'personnel.view']);
        $target = FixtureFactory::user();
        $person = FixtureFactory::personnel();

        $this->assertStatus(403, $this->linkRequest(['id' => (string) $target->id, 'action' => 'link', 'mitarbeiter_id' => (string) $person->id]));
        $this->assertNull(AccountLink::personnelFor((int) $target->id));
        $this->assertBodyNotContains('id="linkAccountForm"', $this->get('/personnel/profile', ['query' => ['id' => (string) $person->id]]));

        // Mit dem Recht, aber das Ziel steht in der Rolle höher.
        $this->loginAdmin(['users.edit']);
        $_SESSION['role_priority'] = 500;
        $this->assertRedirect($this->linkRequest(['id' => (string) $target->id, 'action' => 'link', 'mitarbeiter_id' => (string) $person->id]));
        $this->assertNull(AccountLink::personnelFor((int) $target->id));
    }

    #[Test]
    public function ein_vergebener_mitarbeiter_wird_nicht_umgehaengt(): void
    {
        $this->loginAdmin();
        $owner  = FixtureFactory::user();
        $other  = FixtureFactory::user();
        $person = FixtureFactory::personnel();
        AccountLink::link((int) $owner->id, (int) $person->id, (int) $this->admin->id);

        $this->assertRedirect($this->linkRequest(['id' => (string) $other->id, 'action' => 'link', 'mitarbeiter_id' => (string) $person->id]));

        $this->assertSame((int) $owner->id, AccountLink::userFor((int) $person->id)?->id);
        $this->assertNull(AccountLink::personnelFor((int) $other->id));
    }

    #[Test]
    public function profil_zeigt_den_kontostatus_und_verknuepft_mit_ruecksprung(): void
    {
        $this->loginAdmin();
        $target = FixtureFactory::user(['username' => 'konto_' . uniqid()]);
        $person = FixtureFactory::personnel();

        $page = $this->get('/personnel/profile', ['query' => ['id' => (string) $person->id]]);
        $this->assertBodyContains('Kein Konto', $page);
        $this->assertBodyContains('<option value="' . $target->id . '">' . $target->username, $page);

        $response = $this->linkRequest(['id' => (string) $target->id, 'action' => 'link', 'mitarbeiter_id' => (string) $person->id, 'back' => 'profile']);
        $this->assertRedirect($response, '/personnel/profile?id=' . $person->id);

        $page = $this->get('/personnel/profile', ['query' => ['id' => (string) $person->id]]);
        $this->assertBodyContains('Konto aktiv', $page);
        $this->assertBodyContains('(' . $target->username . ')', $page);
        $this->assertBodyContains('id="unlinkAccountForm"', $page);
    }

    #[Test]
    public function einladung_mit_mitarbeiter_von_der_einladungsseite_und_aus_dem_profil(): void
    {
        $this->loginAdmin();
        $person = FixtureFactory::personnel(['fullname' => 'Ines Eingeladen']);
        $taken  = FixtureFactory::personnel(['fullname' => 'Tom Vergeben']);
        AccountLink::link((int) FixtureFactory::user()->id, (int) $taken->id, (int) $this->admin->id);

        $page = $this->get('/users/registration-codes');
        $this->assertBodyContains('<option value="' . $person->id . '">Ines Eingeladen', $page);
        $this->assertBodyNotContains('<option value="' . $taken->id . '">', $page);

        $this->assertRedirect($this->post('/users/registration-codes', ['action' => 'generate', 'label' => '', 'expires_at' => '', 'mitarbeiter_id' => (string) $person->id]));
        $code = RegistrationCode::query()->where('mitarbeiter_id', $person->id)->firstOrFail();
        $this->assertSame('Ines Eingeladen', $code->label);
        $this->assertBodyContains('>Ines Eingeladen</a>', $this->get('/users/registration-codes'));

        // Der Kontostatus findet die Einladung über den Mitarbeiter, nicht über den Namen.
        $this->assertBodyContains('Einladung ausstehend', $this->get('/personnel/profile', ['query' => ['id' => (string) $person->id]]));
        $code->label = 'Ganz anders';
        $code->save();
        $this->assertBodyContains('Einladung ausstehend', $this->get('/personnel/profile', ['query' => ['id' => (string) $person->id]]));

        // Ein Mitarbeiter mit Konto bekommt keine Einladung.
        $this->post('/users/registration-codes', ['action' => 'generate', 'label' => '', 'expires_at' => '', 'mitarbeiter_id' => (string) $taken->id]);
        $this->assertFalse(RegistrationCode::query()->where('mitarbeiter_id', $taken->id)->exists());

        // Aus dem Profil (JSON-API).
        $api = new \App\Http\Controllers\Api\PersonnelController();
        $other = FixtureFactory::personnel();
        $response = $api->generateInvite(new Request('POST', '/api/personnel/generate-invite', rawBody: json_encode(['label' => 'Profil', 'mitarbeiter_id' => $other->id], JSON_THROW_ON_ERROR)));
        $this->assertSame(200, $response->status);
        $this->assertTrue(RegistrationCode::query()->where('mitarbeiter_id', $other->id)->exists());
        $refused = $api->generateInvite(new Request('POST', '/api/personnel/generate-invite', rawBody: json_encode(['label' => 'Profil', 'mitarbeiter_id' => $taken->id], JSON_THROW_ON_ERROR)));
        $this->assertSame(422, $refused->status);
    }

    /** Ein Klick im Profil: Knopf ohne Konto, Link kopieren bei offener Einladung, kein Duplikat. */
    #[Test]
    public function einladung_mit_einem_klick_im_profil(): void
    {
        $this->loginAdmin();
        $person = FixtureFactory::personnel(['fullname' => 'Olga Offen']);

        // Ohne Konto und ohne Einladung: der Knopf (Registrierung im Test: open).
        $this->assertBodyContains('id="generateInviteBtn"', $this->get('/personnel/profile', ['query' => ['id' => (string) $person->id]]));

        $api = new \App\Http\Controllers\Api\PersonnelController();
        $request = fn (): Request => new Request('POST', '/api/personnel/generate-invite', rawBody: json_encode(['label' => 'Olga Offen', 'mitarbeiter_id' => $person->id], JSON_THROW_ON_ERROR));
        $first = json_decode($api->generateInvite($request())->body, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($first['success']);
        $this->assertFalse($first['existing']);
        $this->assertStringEndsWith('invite?code=' . $first['code'], $first['inviteUrl']);

        // Ein zweiter Klick liefert dieselbe Einladung statt einer neuen.
        $second = json_decode($api->generateInvite($request())->body, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($second['existing']);
        $this->assertSame($first['code'], $second['code']);
        $this->assertSame(1, RegistrationCode::query()->where('mitarbeiter_id', $person->id)->count());
        $this->assertSame(1, Capsule::table('intra_audit_log')->where('action', 'Einladung erstellt')->where('details', 'Olga Offen')->count());

        // Nach dem Neuladen: offene Einladung mit „Link kopieren“, kein Knopf mehr.
        $profile = $this->get('/personnel/profile', ['query' => ['id' => (string) $person->id]]);
        $this->assertBodyContains('Einladung ausstehend', $profile);
        $this->assertBodyContains('data-invite-copy="' . htmlspecialchars(RegistrationCode::inviteUrl($first['code']), ENT_QUOTES) . '"', $profile);
        $this->assertBodyNotContains('id="generateInviteBtn"', $profile);
    }

    #[Test]
    public function neuer_mitarbeiter_ohne_discord_id_und_mit_passender_discord_id(): void
    {
        $this->loginAdmin();
        $account = FixtureFactory::user(['discord_id' => '600000000000000001']);
        $base = [
            'gebdatum'   => '1990-01-01',
            'dienstgrad' => (string) Capsule::table('intra_mitarbeiter_dienstgrade')->where('archive', 0)->min('id'),
            'geschlecht' => '0',
            'telefonnr'  => '',
            'einstdatum' => '2024-01-01',
            'charakterid' => 'ABC12345',
        ];

        $this->assertRedirect($this->post('/personnel/create', $base + ['fullname' => 'Ohne Discord', 'dienstnr' => 'OD-' . random_int(100, 999), 'discordtag' => '']));
        $without = Personnel::query()->where('fullname', 'Ohne Discord')->firstOrFail();
        $this->assertNull($without->discordtag);

        $this->assertRedirect($this->post('/personnel/create', $base + ['fullname' => 'Mit Discord', 'dienstnr' => 'MD-' . random_int(100, 999), 'discordtag' => '600000000000000001']));
        $with = Personnel::query()->where('fullname', 'Mit Discord')->firstOrFail();
        $this->assertSame((int) $account->id, AccountLink::userFor((int) $with->id)?->id);
    }

    #[Test]
    public function zentrales_konto_sieht_nach_der_verknuepfung_seine_daten(): void
    {
        $user   = $this->centralAccount();
        $person = $this->mitarbeiter('Zoe Zentral');
        $mailbox = $this->provision($person);
        $this->assertNull($mailbox->refresh()->user_id, 'Ohne Verknüpfung bindet sich kein Postfach.');

        $typ = new FormType();
        $typ->name  = 'Urlaub_' . uniqid();
        $typ->aktiv = true;
        $typ->save();
        $antrag = new Form();
        $antrag->uniqueid       = 'Z' . random_int(100000, 999999);
        $antrag->antragstyp_id  = $typ->id;
        $antrag->mitarbeiter_id = $person->id;
        $antrag->name_dn        = 'Zoe Zentral (Z-1)';
        $antrag->cirs_status    = Form::STATUS_IN_PROGRESS;
        $antrag->save();

        $docId = random_int(1000000, 9999999);
        Capsule::table('intra_mitarbeiter_dokumente')->insert(['docid' => $docId, 'profileid' => $person->id, 'type' => 1, 'ausstellungsdatum' => '2026-01-15', 'aussteller_name' => 'Ausstellerin']);

        $creator = FixtureFactory::user();
        $event = new CalendarEvent();
        $event->title      = 'Dienstbesprechung Zentral';
        $event->starts_at  = date('Y-m-d 10:00:00', strtotime('+1 day'));
        $event->ends_at    = date('Y-m-d 11:00:00', strtotime('+1 day'));
        $event->visibility = CalendarEvent::VISIBILITY_ATTENDEES;
        $event->created_by = $creator->id;
        $event->save();
        Capsule::table('intra_calendar_attendees')->insert(['event_id' => $event->id, 'mitarbeiter_id' => $person->id]);

        // Vorher: nichts davon.
        $this->loginCentral($user, ['calendar.view', 'mail.use']);
        $page = $this->get('/index');
        $this->assertBodyNotContains((string) $antrag->uniqueid, $page);
        $this->assertBodyNotContains((string) $docId, $page);
        $this->assertFalse(\Plugin\Forms\Policies\FormsPolicy::view($antrag));
        $this->assertStringNotContainsString('Dienstbesprechung Zentral', \Plugin\Calendar\IcalExporter::export((int) $user->id));
        Mailbox::forget();
        $this->assertNull(Mailbox::current());

        AccountLink::link((int) $user->id, (int) $person->id, (int) $creator->id);

        $page = $this->get('/index');
        $this->assertBodyContains('href="/forms/view?antrag=' . $antrag->uniqueid . '"', $page);
        $this->assertBodyContains((string) $docId, $page);
        $this->assertBodyNotContains('Kein Mitarbeiterprofil verknüpft', $page);
        AccountLink::forget();
        $this->assertTrue(\Plugin\Forms\Policies\FormsPolicy::view($antrag));
        $this->assertStringContainsString('Dienstbesprechung Zentral', \Plugin\Calendar\IcalExporter::export((int) $user->id));
        $this->assertOk($this->get('/calendar/view', ['query' => ['id' => (string) $event->id]]));
        $this->assertSame($mailbox->id, Mailbox::ownedBy((int) $user->id)?->id);
        $this->assertSame((int) $user->id, $mailbox->refresh()->user_id);
    }

    #[Test]
    public function ein_unverknuepftes_konto_sieht_auf_dem_dashboard_nichts_fremdes(): void
    {
        $user = $this->centralAccount();

        // Früher wurde aus where(discordtag, null) ein IS NULL: dieser Mitarbeiter
        // ohne Discord-ID und dieser Antrag ohne Discord-ID wären aufgetaucht.
        $stranger = FixtureFactory::personnel(['discordtag' => null]);
        $docId = random_int(1000000, 9999999);
        Capsule::table('intra_mitarbeiter_dokumente')->insert(['docid' => $docId, 'profileid' => $stranger->id, 'type' => 1, 'ausstellungsdatum' => '2026-01-15', 'aussteller_name' => 'Fremd']);
        $typ = new FormType();
        $typ->name  = 'Fremd_' . uniqid();
        $typ->aktiv = true;
        $typ->save();
        $antrag = new Form();
        $antrag->uniqueid      = 'X' . random_int(100000, 999999);
        $antrag->antragstyp_id = $typ->id;
        $antrag->discordid     = null;
        $antrag->name_dn       = 'Fremd';
        $antrag->cirs_status   = Form::STATUS_IN_PROGRESS;
        $antrag->save();

        $this->loginCentral($user);
        $page = $this->get('/index');

        $this->assertOk($page);
        $this->assertBodyContains('Kein Mitarbeiterprofil verknüpft', $page);
        $this->assertBodyNotContains((string) $docId, $page);
        $this->assertBodyNotContains((string) $antrag->uniqueid, $page);
        $this->assertBodyContains('Noch keine Anträge', $page);
    }
}
