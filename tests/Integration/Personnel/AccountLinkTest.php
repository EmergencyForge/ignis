<?php

declare(strict_types=1);

namespace Tests\Integration\Personnel;

use App\Events\EventDispatcher;
use App\Events\PersonnelSaved;
use App\Models\User;
use App\Personnel\AccountLink;
use DomainException;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\Test;
use Tests\FixtureFactory;
use Tests\IntegrationTestCase;

/**
 * ADR-0002: intra_users.aktenid ist die Verknüpfung Konto und Mitarbeiter.
 */
final class AccountLinkTest extends IntegrationTestCase
{
    /** @var array<string,mixed> */
    private array $sessionBefore = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->sessionBefore = $_SESSION ?? [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->sessionBefore;
        parent::tearDown();
    }

    private function auditCount(string $action): int
    {
        return Capsule::table('intra_audit_log')->where('action', 'LIKE', $action . '%')->count();
    }

    #[Test]
    public function verknuepfen_loesen_und_lesen_in_beide_richtungen(): void
    {
        $admin  = FixtureFactory::user();
        $user   = FixtureFactory::user();
        $person = FixtureFactory::personnel();
        $before = $this->auditCount('Mitarbeiter verknüpft');

        AccountLink::link((int) $user->id, (int) $person->id, (int) $admin->id);

        $this->assertSame((int) $person->id, AccountLink::personnelFor((int) $user->id)?->id);
        $this->assertSame((int) $user->id, AccountLink::userFor((int) $person->id)?->id);
        $this->assertSame($before + 1, $this->auditCount('Mitarbeiter verknüpft'));
        $entry = Capsule::table('intra_audit_log')->where('action', 'Mitarbeiter verknüpft [Benutzer-ID: ' . $user->id . ']')->first();
        $this->assertSame((int) $admin->id, (int) $entry->user);

        // Noch einmal dasselbe ändert nichts und schreibt nichts.
        AccountLink::link((int) $user->id, (int) $person->id, (int) $admin->id);
        $this->assertSame($before + 1, $this->auditCount('Mitarbeiter verknüpft'));

        AccountLink::unlink((int) $user->id, (int) $admin->id);
        $this->assertNull(AccountLink::personnelFor((int) $user->id));
        $this->assertNull(AccountLink::userFor((int) $person->id));
        $this->assertSame(1, $this->auditCount('Mitarbeiterverknüpfung gelöst [Benutzer-ID: ' . $user->id . ']'));
    }

    #[Test]
    public function ein_vergebener_mitarbeiter_wird_nie_umgehaengt(): void
    {
        $first  = FixtureFactory::user();
        $second = FixtureFactory::user();
        $person = FixtureFactory::personnel();
        $other  = FixtureFactory::personnel();
        AccountLink::link((int) $first->id, (int) $person->id, (int) $first->id);

        try {
            AccountLink::link((int) $second->id, (int) $person->id, (int) $first->id);
            $this->fail('Vergebener Mitarbeiter wurde umgehängt.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('schon mit einem anderen Konto', $e->getMessage());
        }

        // Ein verknüpftes Konto bekommt auch keinen zweiten Mitarbeiter.
        try {
            AccountLink::link((int) $first->id, (int) $other->id, (int) $first->id);
            $this->fail('Verknüpftes Konto wurde umgehängt.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('schon mit einem anderen Mitarbeiter', $e->getMessage());
        }

        $this->assertSame((int) $first->id, AccountLink::userFor((int) $person->id)?->id);
        $this->assertNull(User::query()->find($second->id)?->aktenid);
    }

    #[Test]
    public function der_eindeutige_index_haelt_auch_ohne_accountlink(): void
    {
        $person = FixtureFactory::personnel();
        FixtureFactory::user(['aktenid' => $person->id]);

        $this->expectException(QueryException::class);
        FixtureFactory::user(['aktenid' => $person->id]);
    }

    #[Test]
    public function current_liest_das_angemeldete_konto_aus_der_datenbank(): void
    {
        $user   = FixtureFactory::user(['discord_id' => '111111111111111111']);
        $person = FixtureFactory::personnel(['discordtag' => '222222222222222222']);
        $_SESSION['userid'] = $user->id;
        // Die Discord-ID der Sitzung spielt keine Rolle.
        $_SESSION['discordtag'] = '222222222222222222';

        $this->assertNull(AccountLink::current());

        AccountLink::link((int) $user->id, (int) $person->id, (int) $user->id);
        $this->assertSame((int) $person->id, AccountLink::currentId());

        unset($_SESSION['userid']);
        $this->assertNull(AccountLink::current());
    }

    #[Test]
    public function discord_verknuepft_nur_eindeutige_freie_paare(): void
    {
        // Eindeutig: genau ein aktives Konto, genau ein Mitarbeiter.
        $user   = FixtureFactory::user(['discord_id' => '300000000000000001']);
        $person = FixtureFactory::personnel(['discordtag' => '300000000000000001']);
        $this->assertSame((int) $user->id, AccountLink::autoLinkByDiscord('300000000000000001'));
        $this->assertSame((int) $person->id, AccountLink::personnelFor((int) $user->id)?->id);

        // Zwei Mitarbeiter mit derselben Discord-ID: nichts.
        $lonely = FixtureFactory::user(['discord_id' => '300000000000000002']);
        FixtureFactory::personnel(['discordtag' => '300000000000000002']);
        FixtureFactory::personnel(['discordtag' => '300000000000000002']);
        $this->assertNull(AccountLink::autoLinkByDiscord('300000000000000002'));
        $this->assertNull(AccountLink::personnelFor((int) $lonely->id));

        // Zwei aktive Konten mit derselben Discord-ID: nichts.
        FixtureFactory::user(['discord_id' => '300000000000000003']);
        FixtureFactory::user(['discord_id' => '300000000000000003']);
        $free = FixtureFactory::personnel(['discordtag' => '300000000000000003']);
        $this->assertNull(AccountLink::autoLinkByDiscord('300000000000000003'));
        $this->assertNull(AccountLink::userFor((int) $free->id));

        // Mitarbeiter schon an einem anderen Konto: nichts.
        $taken = FixtureFactory::personnel(['discordtag' => '300000000000000004']);
        $owner = FixtureFactory::user(['discord_id' => '300000000000000099']);
        AccountLink::link((int) $owner->id, (int) $taken->id, (int) $owner->id);
        $newcomer = FixtureFactory::user(['discord_id' => '300000000000000004']);
        $this->assertNull(AccountLink::autoLinkByDiscord('300000000000000004'));
        $this->assertNull(AccountLink::personnelFor((int) $newcomer->id));
        $this->assertSame((int) $owner->id, AccountLink::userFor((int) $taken->id)?->id);

        // Deaktiviertes Konto zählt nicht.
        FixtureFactory::user(['discord_id' => '300000000000000005', 'is_active' => false]);
        FixtureFactory::personnel(['discordtag' => '300000000000000005']);
        $this->assertNull(AccountLink::autoLinkByDiscord('300000000000000005'));

        $this->assertNull(AccountLink::autoLinkByDiscord(''));
        $this->assertNull(AccountLink::autoLinkByDiscord(null));
    }

    #[Test]
    public function speichern_eines_mitarbeiters_verknuepft_ueber_discord(): void
    {
        $user   = FixtureFactory::user(['discord_id' => '400000000000000001']);
        $person = FixtureFactory::personnel(['discordtag' => '400000000000000001']);
        $this->assertNull(AccountLink::userFor((int) $person->id));

        app(EventDispatcher::class)->fire(new PersonnelSaved((int) $person->id));

        $this->assertSame((int) $person->id, AccountLink::personnelFor((int) $user->id)?->id);
    }

    #[Test]
    public function einladung_fuer_einen_vergebenen_mitarbeiter_verknuepft_nicht_und_protokolliert(): void
    {
        $owner  = FixtureFactory::user();
        $person = FixtureFactory::personnel();
        AccountLink::link((int) $owner->id, (int) $person->id, (int) $owner->id);
        $invited = FixtureFactory::user();

        AccountLink::linkInvited((int) $invited->id, (int) $person->id);

        $this->assertNull(AccountLink::personnelFor((int) $invited->id));
        $this->assertSame(1, $this->auditCount('Einladung ohne Mitarbeiterverknüpfung eingelöst [Benutzer-ID: ' . $invited->id . ']'));
    }

    #[Test]
    public function geloeschter_mitarbeiter_loest_die_verknuepfung(): void
    {
        $user   = FixtureFactory::user();
        $person = FixtureFactory::personnel();
        AccountLink::link((int) $user->id, (int) $person->id, (int) $user->id);

        $person->delete();

        $this->assertNull(User::query()->find($user->id)?->aktenid);
    }
}
