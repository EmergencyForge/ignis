<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Models\User;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Mail\MailboxProvisioner;
use Plugin\Mail\Models\Delivery;
use Plugin\Mail\Models\Mailbox;
use Plugin\Mail\Models\MailboxMember;
use Plugin\Mail\Models\MailList;
use Plugin\Mail\Models\Message;
use Plugin\Mail\Models\Signature;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Gruppenpostfächer: anlegen und Mitglieder pflegen in der
 * Postfachverwaltung (`mail.admin`), wechseln und senden in Mail. Anders
 * als ein Verteiler hat ein Gruppenpostfach eine eigene, geteilte Kopie.
 */
final class MailGroupMailboxTest extends FeatureTestCase
{
    use MailFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
    }

    /** @param list<User> $members */
    private function group(string $name, string $local, array $members = []): Mailbox
    {
        $mailbox = new Mailbox();
        $mailbox->kind         = Mailbox::KIND_GROUP;
        $mailbox->address      = $local . '@ignis.ef';
        $mailbox->display_name = $name;
        $mailbox->domain       = 'ignis.ef';
        $mailbox->active       = true;
        $mailbox->locked       = false;
        $mailbox->save();
        foreach ($members as $user) {
            MailboxMember::query()->create(['mailbox_id' => $mailbox->id, 'user_id' => $user->id]);
        }

        return $mailbox->refresh();
    }

    /** @return list<array{action:string, context:?string}> */
    private function audit(int $userId): array
    {
        return Capsule::table('intra_audit_log')->where('module', 'Mail')->where('user', $userId)->orderBy('id')
            ->get(['action', 'context'])->map(static fn ($r): array => (array) $r)->all();
    }

    #[Test]
    public function verwaltung_legt_ein_gruppenpostfach_ohne_mitarbeiter_an(): void
    {
        $admin = $this->member('Ada Admin');
        $this->loginAs($admin['user'], ['mail.use', 'mail.admin']);

        $this->assertOk($this->get('/settings/mail/mailboxes/groups/create'));
        $response = $this->post('/settings/mail/mailboxes/groups', ['name' => 'Leitstelle', 'local' => 'leitstelle', 'domain' => 'ignis.ef']);

        $mailbox = Mailbox::query()->where('address', 'leitstelle@ignis.ef')->firstOrFail();
        $this->assertRedirect($response, '/settings/mail/mailboxes/' . $mailbox->id . '/edit');
        $this->assertTrue($mailbox->isGroup());
        $this->assertNull($mailbox->mitarbeiter_id);
        $this->assertNull($mailbox->user_id);
        $this->assertSame('Gruppenpostfach angelegt', $this->audit($admin['user']->id)[0]['action']);

        $list = $this->get('/settings/mail/mailboxes', ['query' => ['kind' => 'group']]);
        $this->assertBodyContains('leitstelle@ignis.ef', $list);
        $this->assertBodyContains('0 Mitglieder', $list);
        $this->assertBodyNotContains($admin['mailbox']->address, $list);

        $edit = $this->get('/settings/mail/mailboxes/' . $mailbox->id . '/edit');
        $this->assertBodyContains('Mitglieder', $edit);
        $this->assertBodyNotContains('Konto zuordnen', $edit);

        // Die nächtliche Provisionierung legt es nicht still, obwohl kein Mitarbeiter dahinter steht.
        app(MailboxProvisioner::class)->deactivateOrphans();
        $this->assertTrue($mailbox->refresh()->active);

        // Dieselbe Adresse, ein Verteiler-Name, ein leerer Name: abgelehnt.
        MailList::query()->create(['address' => 'wache@ignis.ef', 'name' => 'Wache', 'kind' => 'static', 'senders' => 'all']);
        foreach ([['name' => 'Doppelt', 'local' => 'leitstelle'], ['name' => 'Wache', 'local' => 'wache'], ['name' => '', 'local' => 'ohne']] as $input) {
            $this->assertStatus(422, $this->post('/settings/mail/mailboxes/groups', $input + ['domain' => 'ignis.ef']));
        }
        $this->assertSame(1, Mailbox::query()->where('kind', 'group')->count());
    }

    #[Test]
    public function mitglieder_pflegt_die_verwaltung_aber_niemand_sich_selbst(): void
    {
        $admin  = $this->member('Ada Admin');
        $member = $this->member('Mia Mitglied');
        $group  = $this->group('Leitstelle', 'leitstelle');
        $this->loginAs($admin['user'], ['mail.use', 'mail.admin']);

        $this->assertRedirect($this->post('/settings/mail/mailboxes/' . $group->id . '/members', ['user_id' => (string) $member['user']->id]));
        $this->assertTrue($group->hasMember($member['user']->id));

        $this->post('/settings/mail/mailboxes/' . $group->id . '/members', ['user_id' => (string) $admin['user']->id]);
        $this->assertFalse($group->hasMember($admin['user']->id), 'Mitglied werden heißt mitlesen: das macht eine andere Person.');

        $this->post('/settings/mail/mailboxes/' . $group->id . '/name', ['name' => 'Leitstelle Nord']);
        $this->assertSame('Leitstelle Nord', $group->refresh()->display_name);
        $this->post('/settings/mail/mailboxes/' . $group->id . '/account', ['user_id' => (string) $member['user']->id]);
        $this->assertNull($group->refresh()->user_id, 'Ein Gruppenpostfach hat kein Konto.');

        $this->assertRedirect($this->post('/settings/mail/mailboxes/' . $group->id . '/members/' . $member['user']->id . '/delete'));
        $this->assertFalse($group->hasMember($member['user']->id));

        $actions = array_column($this->audit($admin['user']->id), 'action');
        $this->assertSame(['Gruppenpostfach: Mitglied aufgenommen', 'Gruppenpostfach umbenannt', 'Gruppenpostfach: Mitglied entfernt'], $actions);
        $removed = json_decode((string) $this->audit($admin['user']->id)[2]['context'], true);
        $this->assertEquals(['mailbox_id' => $group->id, 'user_id' => $member['user']->id], $removed);
    }

    #[Test]
    public function ohne_mail_admin_keine_gruppenverwaltung(): void
    {
        $user  = $this->member('Nur Nutzer');
        $group = $this->group('Leitstelle', 'leitstelle');
        $this->loginAs($user['user'], ['mail.use']);

        $this->assertContains($this->get('/settings/mail/mailboxes/groups/create')->status, [302, 403]);
        $this->assertContains($this->post('/settings/mail/mailboxes/' . $group->id . '/members', ['user_id' => (string) $user['user']->id])->status, [302, 403]);
        $this->assertFalse($group->hasMember($user['user']->id));
    }

    #[Test]
    public function mitglieder_wechseln_ins_gruppenpostfach_andere_nicht(): void
    {
        $member   = $this->member('Mia Mitglied');
        $outsider = $this->member('Otto Aussen');
        $group    = $this->group('Leitstelle', 'leitstelle', [$member['user']]);

        $this->loginAs($member['user']);
        $page = $this->get('/mail/inbox');
        $this->assertBodyContains('aria-label="Postfach wechseln"', $page);
        $this->assertBodyContains('href="/mail/inbox?postfach=' . $group->id . '"', $page);
        $this->assertBodyContains('Mein Postfach', $page);

        $groupPage = $this->get('/mail/inbox', ['query' => ['postfach' => (string) $group->id]]);
        $this->assertBodyContains('<h1>Leitstelle</h1>', $groupPage);
        $this->assertBodyContains('data-mail-mailbox="' . $group->id . '"', $groupPage);
        // Die Wahl gilt für die nächsten Seiten.
        $this->assertBodyContains('data-mail-mailbox="' . $group->id . '"', $this->get('/mail/sent'));

        // Wer nicht Mitglied ist, kommt nicht hinein, auch nicht mit mail.admin.
        $this->loginAs($outsider['user'], ['mail.use', 'mail.admin']);
        $this->assertBodyNotContains('postfach=' . $group->id, $this->get('/mail/inbox'));
        $this->assertBodyNotContains('data-mail-mailbox="' . $group->id . '"', $this->get('/mail/inbox', ['query' => ['postfach' => (string) $group->id]]));
        $this->assertStatus(403, $this->post('/mail/drafts', ['mailbox_id' => (string) $group->id, 'subject' => 'Fremd', 'body_json' => self::doc('x')]));
        $this->assertSame(0, Message::query()->where('sender_mailbox_id', $group->id)->count());
    }

    #[Test]
    public function post_an_die_gruppe_ist_eine_geteilte_kopie_mit_glocke_fuer_jedes_mitglied(): void
    {
        $mia    = $this->member('Mia Mitglied');
        $max    = $this->member('Max Mitglied');
        $sender = $this->member('Sven Sender');
        $group  = $this->group('Leitstelle', 'leitstelle', [$mia['user'], $max['user']]);

        $this->loginAs($sender['user']);
        $id = (int) $this->send(['subject' => 'Lage', 'to' => ['leitstelle@ignis.ef']])['messageId'];
        $this->assertEqualsCanonicalizing([['mailbox_id' => $group->id, 'role' => 'to', 'folder' => 'inbox'], ['mailbox_id' => $sender['mailbox']->id, 'role' => 'sender', 'folder' => 'sent']], $this->deliveries($id), 'Eine Kopie für die Gruppe, keine je Mitglied.');

        $type    = new \Plugin\Mail\Notifications\MailType();
        $counter = (require dirname(__DIR__, 3) . '/plugins/mail/counters.php')['mail'];
        foreach ([$mia, $max] as $member) {
            $this->assertSame(1, Capsule::table('intra_notifications')->where('user_id', $member['user']->id)->where('type', 'mail')->count());
            $this->loginAs($member['user']);
            $this->assertSame('/mail/inbox/' . $id . '?postfach=' . $group->id, $type->link(['link' => '/mail/inbox/' . $id]));
            $this->assertSame(1, $counter(), 'Der Zähler am Eintrag Mail zählt die Gruppe mit.');
        }

        // Gelesen ist gelesen für alle Mitglieder.
        $this->loginAs($mia['user']);
        $this->assertTrue($this->assertJsonResponse($this->post('/mail/messages/' . $id . '/read', ['mailbox_id' => (string) $group->id]))['success']);
        $this->loginAs($max['user']);
        $this->assertSame(0, $counter());
    }

    #[Test]
    public function mitglieder_senden_als_gruppe_und_die_anderen_sehen_wer_schrieb(): void
    {
        $mia   = $this->member('Mia Mitglied');
        $max   = $this->member('Max Mitglied');
        $to    = $this->member('Emil Empfang');
        $group = $this->group('Leitstelle', 'leitstelle', [$mia['user'], $max['user']]);

        $this->loginAs($mia['user']);
        $compose = $this->get('/mail/compose', ['query' => ['postfach' => (string) $group->id]]);
        $this->assertBodyContains('data-mailbox-id="' . $group->id . '"', $compose);
        $this->assertBodyContains('Du schreibst für das Gruppenpostfach', $compose);

        $draft = $this->draft(['mailbox_id' => (string) $group->id]);
        $sent  = $this->assertJsonResponse($this->post('/mail/drafts/' . $draft . '/send', ['mailbox_id' => (string) $group->id, 'subject' => 'Antwort der Leitstelle', 'body_json' => self::doc('Erledigt'), 'to' => [$to['mailbox']->address]]));
        $this->assertTrue($sent['success'], (string) ($sent['message'] ?? ''));
        $message = Message::query()->findOrFail($draft);
        $this->assertSame($group->id, $message->sender_mailbox_id);
        $this->assertSame($mia['user']->id, $message->sent_by_user_id);
        // Aus dem eigenen Postfach geht der Entwurf der Gruppe nicht zu senden.
        $this->assertNull(Delivery::query()->where('message_id', $draft)->where('mailbox_id', $mia['mailbox']->id)->first());

        $this->loginAs($to['user']);
        $received = $this->get('/mail/inbox/' . $draft);
        $this->assertBodyContains('Leitstelle <span class="ignis-preview__muted">&lt;leitstelle@ignis.ef&gt;', $received);
        $this->assertBodyNotContains('Geschrieben von', $received);

        $this->loginAs($max['user']);
        $sentPage = $this->get('/mail/sent/' . $draft, ['query' => ['postfach' => (string) $group->id]]);
        $this->assertBodyContains('Antwort der Leitstelle', $sentPage);
        $this->assertBodyContains('Geschrieben von', $sentPage);
        $this->assertBodyContains((string) ($mia['user']->fullname ?: $mia['user']->username), $sentPage);
    }

    #[Test]
    public function gesperrtes_gruppenpostfach_oeffnet_und_empfaengt_nichts(): void
    {
        $admin  = $this->member('Ada Admin');
        $mia    = $this->member('Mia Mitglied');
        $group  = $this->group('Leitstelle', 'leitstelle', [$mia['user']]);

        $this->loginAs($admin['user'], ['mail.use', 'mail.admin']);
        $this->post('/settings/mail/mailboxes/' . $group->id . '/lock');
        $this->assertTrue($group->refresh()->locked);

        $this->loginAs($mia['user']);
        $this->assertSame([$mia['mailbox']->id], Mailbox::accessibleIds());

        $this->loginAs($admin['user']);
        $draft = $this->draft();
        $this->assertStatus(422, $this->post('/mail/drafts/' . $draft . '/send', ['to' => ['leitstelle@ignis.ef']]));
    }

    #[Test]
    public function adressbuch_nennt_die_art_der_empfaenger(): void
    {
        $user = $this->member('Ada Adressbuch');
        $this->group('Leitstelle Adressbuch', 'leitstelle-ab');
        MailList::query()->create(['address' => 'alle-ab@ignis.ef', 'name' => 'Alle Adressbuch', 'kind' => 'static', 'senders' => 'all']);
        $this->loginAs($user['user']);

        $labels = array_column($this->assertJsonResponse($this->get('/mail/addressbook', ['query' => ['q' => 'Adressbuch']]))['results'], 'label', 'address');
        $this->assertSame('Leitstelle Adressbuch <leitstelle-ab@ignis.ef> · Gruppenpostfach', $labels['leitstelle-ab@ignis.ef']);
        $this->assertSame('Alle Adressbuch <alle-ab@ignis.ef> · Verteiler', $labels['alle-ab@ignis.ef']);
        $this->assertSame('Ada Adressbuch <' . $user['mailbox']->address . '>', $labels[$user['mailbox']->address]);
    }

    #[Test]
    public function signatur_der_gruppe_fuellt_der_schreibende_mit_der_gruppenadresse(): void
    {
        $mia   = $this->member('Mia Mitglied');
        $group = $this->group('Leitstelle', 'leitstelle', [$mia['user']]);
        Capsule::table('intra_config')->where('config_key', 'MAIL_DEFAULT_SIGNATURE')->update(['config_value' => json_encode(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'docVariable', 'attrs' => ['name' => 'absender.name']]]],
            ['type' => 'paragraph', 'content' => [['type' => 'docVariable', 'attrs' => ['name' => 'postfach.name']], ['type' => 'text', 'text' => ', '], ['type' => 'docVariable', 'attrs' => ['name' => 'absender.mailadresse']]]],
        ]])]);
        $this->loginAs($mia['user']);

        $compose = $this->get('/mail/compose', ['query' => ['postfach' => (string) $group->id]]);
        $this->assertBodyContains('&quot;text&quot;:&quot;Mia Mitglied&quot;', $compose);
        $this->assertBodyContains('&quot;text&quot;:&quot;Leitstelle&quot;', $compose);
        $this->assertBodyContains('&quot;text&quot;:&quot;leitstelle@ignis.ef&quot;', $compose);

        // Die eigene Signatur der Gruppe gehört der Gruppe und darf Platzhalter tragen.
        $form = $this->get('/mail/signature', ['query' => ['postfach' => (string) $group->id]]);
        $this->assertBodyContains('Signatur des Gruppenpostfachs', $form);
        $this->assertBodyContains('name="mailbox_id" value="' . $group->id . '"', $form);
        $this->assertBodyContains('data-mail-variable="postfach.name"', $form);

        $saved = $this->post('/mail/signature', ['mailbox_id' => (string) $group->id, 'body_json' => (string) json_encode(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Ihre '], ['type' => 'docVariable', 'attrs' => ['name' => 'postfach.name']]]],
        ]])]);
        $this->assertRedirect($saved, '/mail/signature');
        $this->assertNotNull(Signature::query()->where('mailbox_id', $group->id)->first());
        $this->assertNull(Signature::query()->where('mailbox_id', $mia['mailbox']->id)->first());
        $this->assertBodyContains('&quot;text&quot;:&quot;Leitstelle&quot;', $this->get('/mail/compose', ['query' => ['postfach' => (string) $group->id]]));
    }

    #[Test]
    public function konto_ohne_eigenes_postfach_bekommt_trotzdem_die_gruppe(): void
    {
        $user  = FixtureFactory::user();
        $group = $this->group('Leitstelle', 'leitstelle', [$user]);
        $this->loginAs($user);

        $page = $this->get('/mail');
        $this->assertOk($page);
        $this->assertBodyContains('data-mail-mailbox="' . $group->id . '"', $page);
        $this->assertBodyNotContains('Mein Postfach', $page);
    }
}
