<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Mail\Models\Message;
use Tests\FeatureTestCase;

/**
 * Entwurf und Senden über die JSON-Routen: Zustellungen je Rolle,
 * Absenderkopie, an sich selbst, unbekannte Adressen, Glocke, die
 * Grenzen (Betreff, Text, Empfänger roh und aufgelöst) und die
 * `is_string`-Riegel.
 */
final class MailSendTest extends FeatureTestCase
{
    use MailFixtures;

    /** @var array{user: \App\Models\User, person: \App\Models\Personnel, mailbox: \Plugin\Mail\Models\Mailbox} */
    private array $alice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
        $this->alice = $this->member('Alice Absender');
        $this->loginAs($this->alice['user']);
    }

    #[Test]
    public function senden_stellt_je_rolle_zu_und_legt_die_absenderkopie_ab(): void
    {
        $bob   = $this->member('Bob Empfang');
        $carla = $this->member('Carla Kopie');
        $dora  = $this->member('Dora Blind');

        $id = $this->draft(['to' => [$bob['mailbox']->address]]);
        $this->assertSame([['mailbox_id' => $this->alice['mailbox']->id, 'role' => 'sender', 'folder' => 'drafts']], $this->deliveries($id));

        $data = $this->assertJsonResponse($this->post('/mail/drafts/' . $id . '/send', [
            'subject'   => 'Dienstplan',
            'body_json' => self::doc('Bitte lesen'),
            'to'        => [$bob['mailbox']->address, 'niemand@ignis.ef'],
            'cc'        => [$carla['mailbox']->address],
            'bcc'       => [$dora['mailbox']->address],
        ]));

        $this->assertTrue($data['success']);
        $this->assertSame($id, $data['messageId'], 'Der Entwurf wird gesendet, keine zweite Zeile.');
        $this->assertSame(['niemand@ignis.ef'], $data['unresolvedAddresses']);
        $this->assertIsString($data['csrf_token']);

        $expected = [
            ['mailbox_id' => $this->alice['mailbox']->id, 'role' => 'sender', 'folder' => 'sent'],
            ['mailbox_id' => $bob['mailbox']->id, 'role' => 'to', 'folder' => 'inbox'],
            ['mailbox_id' => $carla['mailbox']->id, 'role' => 'cc', 'folder' => 'inbox'],
            ['mailbox_id' => $dora['mailbox']->id, 'role' => 'bcc', 'folder' => 'inbox'],
        ];
        usort($expected, static fn (array $a, array $b): int => [$a['mailbox_id'], $a['role']] <=> [$b['mailbox_id'], $b['role']]);
        $this->assertSame($expected, $this->deliveries($id));

        $message = Message::query()->findOrFail($id);
        $this->assertSame('sent', $message->status);
        $this->assertNotNull($message->sent_at);
        $this->assertStringContainsString('Bitte lesen', (string) $message->body_html);

        // Glocke: je Empfänger ein Eintrag, der Absender bekommt keinen.
        $notified = Capsule::table('intra_notifications')->where('type', 'mail')->where('link', '/mail/inbox/' . $id)->pluck('user_id')->map('intval')->sort()->values()->all();
        $recipients = [$bob['user']->id, $carla['user']->id, $dora['user']->id];
        sort($recipients);
        $this->assertSame($recipients, $notified);

        // Zweimal senden geht nicht, der Entwurf ist keiner mehr.
        $this->assertStatus(404, $this->post('/mail/drafts/' . $id . '/send', []));
    }

    #[Test]
    public function an_sich_selbst_landet_im_posteingang_und_in_gesendet(): void
    {
        $data = $this->send(['to' => [$this->alice['mailbox']->address]]);

        $this->assertSame([
            ['mailbox_id' => $this->alice['mailbox']->id, 'role' => 'sender', 'folder' => 'sent'],
            ['mailbox_id' => $this->alice['mailbox']->id, 'role' => 'to', 'folder' => 'inbox'],
        ], $this->deliveries((int) $data['messageId']));
    }

    #[Test]
    public function senden_ohne_felder_nimmt_den_gespeicherten_entwurf(): void
    {
        $bob = $this->member('Bob Empfang');
        $id  = $this->draft(['subject' => 'Gespeichert', 'to' => [$bob['mailbox']->address]]);

        $data = $this->assertJsonResponse($this->post('/mail/drafts/' . $id . '/send', []));

        $this->assertTrue($data['success']);
        $this->assertSame('Gespeichert', Message::query()->findOrFail($id)->subject);
        $this->assertContains(['mailbox_id' => $bob['mailbox']->id, 'role' => 'to', 'folder' => 'inbox'], $this->deliveries($id));
    }

    #[Test]
    public function entwurf_wird_an_ort_und_stelle_gespeichert_und_nur_vom_besitzer(): void
    {
        $id = $this->draft();
        $update = $this->assertJsonResponse($this->post('/mail/drafts/' . $id, ['subject' => 'Neu', 'body_json' => self::doc('Zwei')]));
        $this->assertSame($id, $update['messageId']);
        $this->assertSame('Neu', Message::query()->findOrFail($id)->subject);
        $this->assertSame(1, Message::query()->where('sender_mailbox_id', $this->alice['mailbox']->id)->count());

        $mallory = $this->member('Mallory Fremd');
        $this->loginAs($mallory['user']);
        $this->assertStatus(404, $this->post('/mail/drafts/' . $id, ['subject' => 'Gekapert']));
        $this->assertStatus(404, $this->post('/mail/drafts/' . $id . '/send', ['to' => [$mallory['mailbox']->address]]));
        $this->assertStatus(404, $this->post('/mail/messages/' . $id . '/delete'));
        $this->assertSame('Neu', Message::query()->findOrFail($id)->subject);
    }

    #[Test]
    public function grenzen_fuer_betreff_text_und_empfaenger(): void
    {
        $id = $this->draft();

        $long = $this->post('/mail/drafts/' . $id, ['subject' => str_repeat('ä', 256)]);
        $this->assertStatus(422, $long);

        // Die Größe zählt vor dem Dekodieren: 200 KB + 1 Byte, auch kein JSON.
        $big = $this->post('/mail/drafts/' . $id, ['body_json' => str_repeat('x', 200_001)]);
        $this->assertStatus(422, $big);
        $this->assertStringContainsString('200 KB', (string) ($this->assertJsonResponse($big)['message'] ?? ''));

        $this->assertStatus(422, $this->post('/mail/drafts/' . $id, ['body_json' => '{"type":"doc"']));
        $this->assertStatus(422, $this->post('/mail/drafts/' . $id, ['body_json' => '{"type":"script"}']));

        $many = array_map(static fn (int $i): string => 'x' . $i . '@ignis.ef', range(1, 101));
        $this->assertStatus(422, $this->post('/mail/drafts/' . $id . '/send', ['to' => $many]));

        // Leer bleibt leer: ohne Betreff sendet das Paket nicht.
        $this->assertStatus(422, $this->post('/mail/drafts/' . $id . '/send', ['subject' => '', 'to' => [$this->alice['mailbox']->address]]));
    }

    #[Test]
    public function arrays_statt_texten_sind_eine_ablehnung(): void
    {
        $id = $this->draft();

        $this->assertStatus(422, $this->post('/mail/drafts/' . $id, ['subject' => ['x']]));
        $this->assertStatus(422, $this->post('/mail/drafts/' . $id, ['body_json' => ['type' => 'doc']]));
        $this->assertStatus(422, $this->post('/mail/drafts/' . $id, ['to' => 'a@ignis.ef']));
        $this->assertStatus(422, $this->post('/mail/drafts/' . $id, ['to' => [['a@ignis.ef']]]));
        $this->assertStatus(422, $this->post('/mail/messages/' . $id . '/move', ['folder' => ['trash']]));
    }

    #[Test]
    public function aufgeloeste_zustellungen_sind_begrenzt(): void
    {
        $listId = (int) Capsule::table('intra_mail_lists')->insertGetId(['address' => 'alle@ignis.ef', 'name' => 'Alle', 'kind' => 'static']);
        $rows = [];
        for ($i = 0; $i < 501; $i++) {
            $rows[] = ['address' => 'viele' . $i . '@ignis.ef', 'display_name' => 'Viele ' . $i, 'domain' => 'ignis.ef', 'active' => 1, 'locked' => 0];
        }
        Capsule::table('intra_mail_mailboxes')->insert($rows);
        Capsule::connection()->statement(
            'INSERT INTO intra_mail_list_members (list_id, mailbox_id) SELECT ?, id FROM intra_mail_mailboxes WHERE address LIKE ?',
            [$listId, 'viele%@ignis.ef'],
        );

        $response = $this->post('/mail/drafts/' . $this->draft() . '/send', ['subject' => 'Rundmail', 'to' => ['alle@ignis.ef']]);

        $this->assertStatus(422, $response);
        $this->assertStringContainsString('500', (string) ($this->assertJsonResponse($response)['message'] ?? ''));
    }

    #[Test]
    public function ohne_postfach_und_ohne_recht_geht_nichts(): void
    {
        $user = \Tests\FixtureFactory::user();
        $this->loginAs($user);
        $this->assertStatus(403, $this->post('/mail/drafts', ['subject' => 'x']));

        $this->loginAs($this->alice['user'], []);
        $response = $this->post('/mail/drafts', ['subject' => 'x'], ['headers' => ['Accept' => 'application/json']]);
        $this->assertStatus(403, $response);

        $this->loginAs($this->alice['user']);
        $this->alice['mailbox']->locked = true;
        $this->alice['mailbox']->save();
        \Plugin\Mail\Models\Mailbox::forget();
        $this->assertStatus(403, $this->post('/mail/drafts', ['subject' => 'x']));
    }
}
