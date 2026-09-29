<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Mail\Models\Delivery;
use Plugin\Mail\Models\Message;
use Plugin\Mail\Models\Signature;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die Seiten des Mailmoduls: Arbeitsbereich mit Ordnern, Lesebereich
 * (BCC-Sichtregel), Verfassen ohne Schreiben auf GET, Antworten und
 * Weiterleiten, Signatur, Leerzustände und die Seite ohne Postfach.
 */
final class MailPagesTest extends FeatureTestCase
{
    use MailFixtures;

    /** @var array{user: \App\Models\User, person: \App\Models\Personnel, mailbox: \Plugin\Mail\Models\Mailbox} */
    private array $alice;
    /** @var array{user: \App\Models\User, person: \App\Models\Personnel, mailbox: \Plugin\Mail\Models\Mailbox} */
    private array $bob;
    /** @var array{user: \App\Models\User, person: \App\Models\Personnel, mailbox: \Plugin\Mail\Models\Mailbox} */
    private array $carla;
    /** @var array{user: \App\Models\User, person: \App\Models\Personnel, mailbox: \Plugin\Mail\Models\Mailbox} */
    private array $dora;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
        $this->alice = $this->member('Alice Absender');
        $this->bob   = $this->member('Bob Empfang');
        $this->carla = $this->member('Carla Kopie');
        $this->dora  = $this->member('Dora Blind');
    }

    private function sendRound(): int
    {
        $this->loginAs($this->alice['user']);
        $data = $this->send([
            'subject'   => 'Wachplan KW 40',
            'body_json' => self::doc('Bitte bis Freitag bestätigen.'),
            'to'        => [$this->bob['mailbox']->address],
            'cc'        => [$this->carla['mailbox']->address],
            'bcc'       => [$this->dora['mailbox']->address],
        ]);
        $this->assertTrue($data['success']);

        return (int) $data['messageId'];
    }

    #[Test]
    public function posteingang_listet_ungelesen_und_schreibt_nichts_auf_get(): void
    {
        $id = $this->sendRound();
        $this->loginAs($this->bob['user']);

        $page = $this->get('/mail');
        $this->assertOk($page);
        $this->assertSame('private, no-store', $page->headers['Cache-Control'] ?? null);
        $this->assertBodyContains('data-ignis-workbench', $page);
        $this->assertBodyContains('data-ignis-preview-url="/mail/inbox/{id}/preview"', $page);
        $this->assertBodyContains('data-ignis-row="' . $id . '"', $page);
        $this->assertBodyContains('class="is-unread"', $page);
        $this->assertBodyContains('Wachplan KW 40', $page);
        $this->assertBodyContains('Bitte bis Freitag bestätigen.', $page);
        $this->assertBodyContains('Alice Absender', $page);
        $this->assertMatchesRegularExpression('~Posteingang</span>\s*<span class="ignis-chip ignis-chip--count">1<~', $page->body);

        $single = $this->get('/mail/inbox/' . $id);
        $this->assertOk($single);
        $this->assertBodyContains('data-mail-mark-read="' . $id . '"', $single);
        $this->assertOk($this->get('/mail/inbox/' . $id . '/preview'));
        $this->assertNull(Delivery::query()->where('message_id', $id)->where('mailbox_id', $this->bob['mailbox']->id)->value('read_at'), 'GET markiert nichts.');

        $this->assertTrue($this->assertJsonResponse($this->post('/mail/messages/' . $id . '/read'))['success']);
        $this->assertBodyNotContains('class="is-unread"', $this->get('/mail'));
        $this->assertBodyNotContains('data-mail-mark-read', $this->get('/mail/inbox/' . $id . '/preview'));
    }

    #[Test]
    public function bcc_sieht_nur_der_absender_und_der_bcc_empfaenger_sich_selbst(): void
    {
        $id = $this->sendRound();

        $sender = $this->get('/mail/sent/' . $id . '/preview');
        $this->assertOk($sender);
        $this->assertBodyContains('<dt>BCC</dt>', $sender);
        $this->assertBodyContains($this->dora['mailbox']->address, $sender);

        foreach ([$this->bob, $this->carla] as $who) {
            $this->loginAs($who['user']);
            $view = $this->get('/mail/inbox/' . $id . '/preview');
            $this->assertOk($view);
            $this->assertBodyNotContains('<dt>BCC</dt>', $view);
            $this->assertBodyNotContains($this->dora['mailbox']->address, $view);
            $this->assertBodyContains('Allen antworten', $view);
        }

        $this->loginAs($this->dora['user']);
        $blind = $this->get('/mail/inbox/' . $id . '/preview');
        $this->assertBodyContains('<dt>BCC</dt>', $blind);
        $this->assertBodyContains($this->dora['mailbox']->address, $blind);

        // Allen antworten: ohne BCC und ohne mich.
        $replyAll = $this->get('/mail/compose/reply-all/' . $id);
        $this->assertOk($replyAll);
        $this->assertBodyContains('value="Re: Wachplan KW 40"', $replyAll);
        $this->assertBodyContains($this->alice['mailbox']->address, $replyAll);
        $this->assertBodyContains($this->bob['mailbox']->address, $replyAll);
        $this->assertBodyNotContains('data-value="' . $this->dora['mailbox']->address, $replyAll);
    }

    #[Test]
    public function fremde_und_falsche_ordner_gibt_es_nicht_auch_nicht_fuer_admins(): void
    {
        $id  = $this->sendRound();
        $eve = $this->member('Eve Admin');
        $this->loginAs($eve['user'], ['full_admin']);

        $this->assertNotFound($this->get('/mail/inbox/' . $id));
        $this->assertNotFound($this->get('/mail/sent/' . $id . '/preview'));
        $this->assertNotFound($this->get('/mail/compose/reply/' . $id));
        $this->assertNotFound($this->get('/mail/compose/forward/' . $id));
        $this->assertBodyNotContains('Wachplan', $this->get('/mail/inbox'));

        $this->loginAs($this->bob['user']);
        $this->assertNotFound($this->get('/mail/sent/' . $id . '/preview'));
        $this->assertNotFound($this->get('/mail/compose/draft/' . $id));
    }

    #[Test]
    public function verfassen_legt_nichts_an_und_haengt_die_signatur_an(): void
    {
        $this->loginAs($this->alice['user']);
        $before = Message::query()->count();

        Capsule::table('intra_config')->where('config_key', 'MAIL_DEFAULT_SIGNATURE')->update(['config_value' => \Plugin\Mail\SignatureText::toJson("Wache 1\nLeitstelle")]);
        $compose = $this->get('/mail/compose', ['query' => ['to' => $this->bob['mailbox']->address]]);
        $this->assertOk($compose);
        $this->assertSame($before, Message::query()->count(), 'GET legt keinen Entwurf an.');
        $this->assertBodyContains('data-ignis-drawer-native', $compose);
        $this->assertBodyContains('data-draft-id=""', $compose);
        $this->assertBodyContains('Leitstelle', $compose);
        $this->assertBodyContains('&quot;-- &quot;', $compose);
        $this->assertBodyContains('Bob Empfang &lt;' . $this->bob['mailbox']->address . '&gt;', $compose);
        $this->assertBodyContains('assets/js/ui/multi-select.js', $compose);
        $this->assertBodyContains('plugins/mail/assets/mail-compose.js', $compose);

        // Eine leere eigene Signatur heißt: keine, auch kein Trenner.
        Signature::query()->create(['mailbox_id' => $this->alice['mailbox']->id, 'body_json' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]]]);
        $plain = $this->get('/mail/compose');
        $this->assertBodyNotContains('Leitstelle', $plain);
        $this->assertBodyNotContains('&quot;-- &quot;', $plain);
    }

    #[Test]
    public function antworten_und_weiterleiten_sind_vorbefuellt(): void
    {
        $id = $this->sendRound();
        Capsule::table('intra_mail_attachments')->insert(['message_id' => $id, 'path' => 'storage/private/mail-attachments/x.txt', 'original_name' => 'dienstplan.pdf', 'mime' => 'application/pdf', 'size' => 10]);
        $this->loginAs($this->bob['user']);

        $reply = $this->get('/mail/compose/reply/' . $id);
        $this->assertOk($reply);
        $this->assertBodyContains('value="Re: Wachplan KW 40"', $reply);
        $this->assertBodyContains('data-in-reply-to="' . $id . '"', $reply);
        $this->assertBodyContains('data-value="' . $this->alice['mailbox']->address . '"', $reply);
        $this->assertBodyContains('schrieb Alice Absender', $reply);

        $forward = $this->get('/mail/compose/forward/' . $id);
        $this->assertBodyContains('value="Fwd: Wachplan KW 40"', $forward);
        $this->assertBodyContains('data-forward-from="' . $id . '"', $forward);
        $this->assertBodyContains('dienstplan.pdf', $forward);
    }

    #[Test]
    public function entwurf_im_ordner_und_weiter_bearbeiten(): void
    {
        $this->loginAs($this->alice['user']);
        $id = $this->draft(['subject' => 'Halb fertig', 'to' => [$this->bob['mailbox']->address]]);

        $drafts = $this->get('/mail/drafts');
        $this->assertBodyContains('Halb fertig', $drafts);
        $this->assertBodyContains('<th scope="col">An</th>', $drafts);

        $pane = $this->get('/mail/drafts/' . $id . '/preview');
        $this->assertBodyContains('Weiter bearbeiten', $pane);
        $this->assertBodyContains('data-mail-action="discard"', $pane);

        $edit = $this->get('/mail/compose/draft/' . $id);
        $this->assertOk($edit);
        $this->assertBodyContains('data-draft-id="' . $id . '"', $edit);
        $this->assertBodyContains('value="Halb fertig"', $edit);

        $this->loginAs($this->bob['user']);
        $this->assertNotFound($this->get('/mail/compose/draft/' . $id));
    }

    #[Test]
    public function leerzustaende_je_ordner_und_papierkorb(): void
    {
        $this->loginAs($this->alice['user']);
        $this->assertBodyContains('Noch keine Mails', $this->get('/mail/inbox'));
        $this->assertBodyContains('Noch nichts gesendet', $this->get('/mail/sent'));
        $this->assertBodyContains('Keine Entwürfe', $this->get('/mail/drafts'));
        $this->assertBodyContains('Das Archiv ist leer', $this->get('/mail/archive'));
        $this->assertBodyContains('Der Papierkorb ist leer', $this->get('/mail/trash'));
        $this->assertNotFound($this->get('/mail/spam'));

        $id = $this->sendRound();
        $this->loginAs($this->bob['user']);
        $this->assertTrue($this->assertJsonResponse($this->post('/mail/messages/' . $id . '/move', ['folder' => 'trash']))['success']);
        $trash = $this->get('/mail/trash/' . $id . '/preview');
        $this->assertBodyContains('data-mail-target="restore"', $trash);
        $this->assertBodyContains('Endgültig löschen', $trash);
        $this->assertTrue($this->assertJsonResponse($this->post('/mail/messages/' . $id . '/move', ['folder' => 'restore']))['success']);
        $this->assertSame('inbox', Delivery::query()->where('message_id', $id)->where('mailbox_id', $this->bob['mailbox']->id)->value('folder'));
    }

    #[Test]
    public function ohne_postfach_eine_erklaerung(): void
    {
        $user = FixtureFactory::user();
        $this->loginAs($user);
        $page = $this->get('/mail');
        $this->assertOk($page);
        $this->assertBodyContains('Noch kein Postfach', $page);

        $this->alice['mailbox']->update(['locked' => 1]);
        $this->loginAs($this->alice['user']);
        $this->assertBodyContains('Dein Postfach ist nicht aktiv', $this->get('/mail'));
    }

    #[Test]
    public function kein_altes_vokabular_in_den_mailseiten(): void
    {
        $id = $this->sendRound();
        foreach (['/mail/sent', '/mail/sent/' . $id, '/mail/trash', '/mail/compose', '/mail/compose/forward/' . $id, '/mail/signature'] as $route) {
            $response = $this->get($route);
            $this->assertOk($response);
            $this->assertDoesNotMatchRegularExpression(
                '~class="[^"]*ignis-(btn--(accent|soft-[a-z]+|outline-[a-z]+|success|info|warning)|chip--(success|warning|accent)|alert--(success|warning|error))(?![a-zA-Z0-9_-])~',
                $response->body,
                $route,
            );
        }
    }

    #[Test]
    public function signatur_speichern_und_kaputten_text_ablehnen(): void
    {
        $this->loginAs($this->alice['user']);
        $this->assertOk($this->get('/mail/signature'));

        $this->assertRedirect($this->post('/mail/signature', ['body_json' => '{kaputt']), '/mail/signature');
        $this->assertNull(Signature::query()->where('mailbox_id', $this->alice['mailbox']->id)->first());

        $this->assertRedirect($this->post('/mail/signature', ['body_json' => self::doc('Gruß, Alice')]), '/mail/signature');
        $this->assertSame('Gruß, Alice', Signature::query()->where('mailbox_id', $this->alice['mailbox']->id)->firstOrFail()->body_json['content'][0]['content'][0]['text']);
    }
}
