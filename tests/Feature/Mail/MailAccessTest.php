<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Mail\AttachmentStorage;
use Plugin\Mail\Models\Attachment;
use Plugin\Mail\Models\Delivery;
use Plugin\Mail\Models\Message;
use Tests\FeatureTestCase;

/**
 * Wer an einer Mail nicht beteiligt ist, verschiebt, liest und ergänzt
 * nichts an ihr, antwortet nicht darauf und leitet sie nicht weiter. Ein
 * BCC-Empfänger ist beteiligt und kommt an die Anhänge. Schreibende
 * Mail-Routen brauchen den CSRF-Token.
 */
final class MailAccessTest extends FeatureTestCase
{
    use MailFixtures;

    /** @var list<string> */
    private array $tmpFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
    }

    protected function tearDown(): void
    {
        foreach (Attachment::query()->get() as $attachment) {
            $path = app(AttachmentStorage::class)->absolutePath($attachment);
            if ($path !== null) {
                @unlink($path);
            }
        }
        foreach ($this->tmpFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function attach(int $draftId, string $contents, string $name): \EmergencyForge\Http\Response
    {
        $tmp = (string) tempnam(sys_get_temp_dir(), 'mailtest');
        file_put_contents($tmp, $contents);
        $this->tmpFiles[] = $tmp;

        return $this->post('/mail/drafts/' . $draftId . '/attachments', [], ['files' => ['file' => [
            'name' => $name, 'type' => 'text/plain', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($contents),
        ]]]);
    }

    #[Test]
    public function wer_nicht_beteiligt_ist_kommt_an_nichts(): void
    {
        $alice = $this->member('Alice Absender');
        $bob   = $this->member('Bob Empfang');
        $eve   = $this->member('Eve Fremd');

        $this->loginAs($alice['user']);
        $draft = $this->draft(['to' => [$bob['mailbox']->address]]);
        $sent  = (int) $this->send(['subject' => 'Intern', 'to' => [$bob['mailbox']->address]])['messageId'];

        $this->loginAs($eve['user'], ['mail.use', 'full_admin']);
        $this->assertNotFound($this->post('/mail/messages/' . $sent . '/move', ['folder' => 'trash']));
        $this->assertNotFound($this->post('/mail/messages/' . $sent . '/read'));
        $this->assertNotFound($this->post('/mail/messages/' . $sent . '/read', ['read' => '0']));
        $this->assertNotFound($this->attach($draft, 'fremd', 'fremd.txt'));
        $this->assertNotFound($this->post('/mail/drafts', ['forward_from' => (string) $sent]));
        $this->assertNotFound($this->post('/mail/drafts', ['in_reply_to' => (string) $sent]));

        $this->assertSame('inbox', Delivery::query()->where('message_id', $sent)->where('mailbox_id', $bob['mailbox']->id)->value('folder'));
        $this->assertNull(Delivery::query()->where('message_id', $sent)->where('mailbox_id', $bob['mailbox']->id)->value('read_at'));
        $this->assertSame(0, Attachment::query()->where('message_id', $draft)->count());
        $this->assertSame(0, Message::query()->where('sender_mailbox_id', $eve['mailbox']->id)->count());
    }

    #[Test]
    public function ein_bcc_empfaenger_laedt_anhaenge_herunter(): void
    {
        $alice = $this->member('Alice Absender');
        $bob   = $this->member('Bob Empfang');
        $dora  = $this->member('Dora Blind');
        $this->loginAs($alice['user']);
        $id = $this->draft();
        $attachmentId = (int) $this->assertJsonResponse($this->attach($id, 'Nur für Dora sichtbar', 'blind.txt'))['attachmentId'];
        $this->assertTrue($this->assertJsonResponse($this->post('/mail/drafts/' . $id . '/send', ['to' => [$bob['mailbox']->address], 'bcc' => [$dora['mailbox']->address]]))['success']);

        $this->loginAs($dora['user']);
        $download = $this->get('/mail/attachments/' . $attachmentId);
        $this->assertOk($download);
        $this->assertSame('Nur für Dora sichtbar', $download->body);
    }

    #[Test]
    public function ohne_csrf_token_schreibt_keine_mail_route(): void
    {
        $alice = $this->member('Alice Absender');
        $bob   = $this->member('Bob Empfang');
        $this->loginAs($alice['user']);
        $sent   = (int) $this->send(['to' => [$bob['mailbox']->address]])['messageId'];
        $before = Message::query()->count();

        foreach ([
            ['/mail/drafts', ['subject' => 'Ohne Token']],
            ['/mail/messages/' . $sent . '/move', ['folder' => 'trash']],
            ['/mail/messages/' . $sent . '/delete', []],
        ] as [$path, $body]) {
            $response = $this->post($path, $body + ['csrf_token' => 'falsch']);
            $this->assertContains($response->status, [403, 419], $path);
        }

        $this->assertSame($before, Message::query()->count());
        $this->assertSame('sent', Delivery::query()->where('message_id', $sent)->where('mailbox_id', $alice['mailbox']->id)->value('folder'));
        $this->assertNull(Capsule::table('intra_mail_deliveries')->where('message_id', $sent)->whereNotNull('deleted_at')->value('id'));
    }
}
