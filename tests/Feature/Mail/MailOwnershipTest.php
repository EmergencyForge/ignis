<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Mail\Models\Mailbox;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Wem ein Postfach gehört: fest `user_id`, nicht die Discord-ID, die die
 * Personalverwaltung pflegt. Getauschte IDs öffnen kein fremdes Postfach,
 * ein freies Postfach bindet sich nur an ein eindeutiges Konto, der
 * Backfill hängt nie um, und „Konto zuordnen“ geht nur auf passende
 * Konten, nie auf das eigene, mit Audit und Benachrichtigung.
 */
final class MailOwnershipTest extends FeatureTestCase
{
    use MailFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
    }

    private function retag(\App\Models\Personnel $person, string $discordId): void
    {
        $person->discordtag = $discordId;
        $person->save();
    }

    private function unbind(Mailbox $mailbox): void
    {
        Mailbox::query()->whereKey($mailbox->id)->update(['user_id' => null]);
        $mailbox->refresh();
    }

    #[Test]
    public function getauschte_discord_ids_oeffnen_kein_fremdes_postfach(): void
    {
        $alice = $this->member('Alice Opfer');
        $bob   = $this->member('Bob Personal');
        $carla = $this->member('Carla Absender');
        $this->assertSame($alice['user']->id, $alice['mailbox']->refresh()->user_id, 'Beim Anlegen gebunden.');

        $this->loginAs($carla['user']);
        $this->send(['subject' => 'Nur für Alice', 'to' => [$alice['mailbox']->address]]);

        // Die Personalverwaltung tauscht die Discord-IDs der beiden Akten.
        $this->retag($alice['person'], (string) $bob['user']->discord_id);
        $this->retag($bob['person'], (string) $alice['user']->discord_id);

        $this->loginAs($bob['user']);
        $this->assertSame($bob['mailbox']->id, Mailbox::current()?->id);
        $this->assertBodyNotContains('Nur für Alice', $this->get('/mail/inbox'));

        // Ohne eigenes Postfach: die fremde ID in der Akte reicht nicht.
        $mallory = FixtureFactory::user();
        $this->retag($alice['person'], (string) $mallory->discord_id);
        $this->loginAs($mallory);
        $this->assertNull(Mailbox::current());
        $this->assertBodyNotContains('Nur für Alice', $this->get('/mail'));

        $this->loginAs($alice['user']);
        $this->assertBodyContains('Nur für Alice', $this->get('/mail/inbox'));

        // Die Glocke geht weiter an das Konto des Postfachs.
        $this->assertSame([$alice['user']->id], Mailbox::userIdsFor([$alice['mailbox']->id]));
    }

    #[Test]
    public function ein_freies_postfach_bindet_sich_beim_ersten_aufruf_nur_eindeutig(): void
    {
        $user    = FixtureFactory::user();
        $person  = $this->mitarbeiter('Frieda Frei', ['discordtag' => (string) $user->discord_id]);
        $mailbox = $this->provision($person);
        $this->unbind($mailbox);

        $this->loginAs($user);
        $this->assertSame($mailbox->id, Mailbox::current()?->id);
        $this->assertSame($user->id, $mailbox->refresh()->user_id);

        // Zwei passende Konten (Discord-ID und aktenid): keins bekommt es.
        $first   = FixtureFactory::user();
        $twice   = $this->mitarbeiter('Zora Zwei', ['discordtag' => (string) $first->discord_id]);
        FixtureFactory::user(['aktenid' => $twice->id]);
        $shared = $this->provision($twice);
        $this->assertNull($shared->refresh()->user_id);

        $this->loginAs($first);
        $this->assertNull(Mailbox::current());
        $this->assertNull($shared->refresh()->user_id);
        $this->assertBodyContains('Dein Konto ist keinem Postfach zugeordnet', $this->get('/mail'));
    }

    #[Test]
    public function backfill_bindet_freie_und_haengt_gebundene_nie_um(): void
    {
        $alice   = $this->member('Alice Bestand');
        $mallory = FixtureFactory::user();
        $this->retag($alice['person'], (string) $mallory->discord_id);

        $user   = FixtureFactory::user();
        $person = $this->mitarbeiter('Fritz Frei', ['discordtag' => (string) $user->discord_id]);
        $free   = $this->provision($person);
        $this->unbind($free);

        $this->assertSame(0, $this->commandTester('mail:backfill')->execute([]));

        $this->assertSame($alice['user']->id, $alice['mailbox']->refresh()->user_id);
        $this->assertSame($user->id, $free->refresh()->user_id);
    }

    #[Test]
    public function konto_zuordnen_nur_passend_nie_an_sich_selbst_und_protokolliert(): void
    {
        $admin   = FixtureFactory::user();
        $paul    = $this->member('Paul Umzug');
        $mailbox = $paul['mailbox'];
        $newUser = FixtureFactory::user();
        $assign  = fn (string $userId) => $this->post('/settings/mail/mailboxes/' . $mailbox->id . '/account', ['user_id' => $userId]);

        $this->loginAs($admin, ['mail.admin']);

        // Die Discord-ID der Akte passt nicht zum Ziel.
        $this->assertRedirect($assign((string) $newUser->id));
        $this->assertSame($paul['user']->id, $mailbox->refresh()->user_id);

        // Die Akte zeigt auf das Konto der Verwaltung: trotzdem nie an sich selbst.
        $this->retag($paul['person'], (string) $admin->discord_id);
        $this->assertRedirect($assign((string) $admin->id));
        $this->assertSame($paul['user']->id, $mailbox->refresh()->user_id);

        // Ein Konto, das schon ein Postfach hat, bekommt kein zweites.
        $olga = $this->member('Olga Hatschon');
        $this->retag($paul['person'], (string) $olga['user']->discord_id);
        $this->assertRedirect($assign((string) $olga['user']->id));
        $this->assertSame($paul['user']->id, $mailbox->refresh()->user_id);

        // Passend: umhängen, protokollieren (nur IDs), altes Konto benachrichtigen.
        $this->retag($paul['person'], (string) $newUser->discord_id);
        $this->assertBodyContains('>' . $newUser->username . '</option>', $this->get('/settings/mail/mailboxes/' . $mailbox->id . '/edit'));
        $this->assertRedirect($assign((string) $newUser->id));
        $this->assertSame($newUser->id, $mailbox->refresh()->user_id);

        $audit = Capsule::table('intra_audit_log')->where('module', 'Mail')->where('user', $admin->id)->orderBy('id')->get(['action', 'details', 'context'])->all();
        $this->assertCount(1, $audit);
        $this->assertSame('Postfach-Konto zugeordnet', $audit[0]->action);
        $this->assertStringNotContainsString($mailbox->address, (string) $audit[0]->details . (string) $audit[0]->context);
        $this->assertSame(['mailbox_id' => $mailbox->id, 'von' => $paul['user']->id, 'auf' => $newUser->id], json_decode((string) $audit[0]->context, true));
        $this->assertSame(1, Capsule::table('intra_notifications')->where('user_id', $paul['user']->id)->where('type', 'system')->count());

        $this->loginAs($newUser);
        $this->assertSame($mailbox->id, Mailbox::current()?->id);
        $this->loginAs($paul['user']);
        $this->assertNull(Mailbox::current());

        // Lösen geht, das eigene Postfach hängt niemand selbst um.
        $this->loginAs($admin, ['mail.admin']);
        $this->assertRedirect($assign(''));
        $this->assertNull($mailbox->refresh()->user_id);

        $this->loginAs($olga['user'], ['mail.use', 'mail.admin']);
        $this->assertRedirect($this->post('/settings/mail/mailboxes/' . $olga['mailbox']->id . '/account', ['user_id' => '']));
        $this->assertSame($olga['user']->id, $olga['mailbox']->refresh()->user_id);
    }
}
