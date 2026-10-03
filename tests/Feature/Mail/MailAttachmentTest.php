<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use PHPUnit\Framework\Attributes\Test;
use Plugin\Mail\AttachmentStorage;
use Plugin\Mail\Controllers\MailController;
use Plugin\Mail\Models\Attachment;
use Tests\FeatureTestCase;

/**
 * Anhänge: nur an den eigenen Entwurf, Typ aus den Magic Bytes, 10 MB je
 * Mail, Auslieferung nur an Beteiligte als Download (nosniff, private,
 * no-store, RFC-5987-Dateiname), Weiterleiten kopiert, Verwerfen und
 * mail:cleanup räumen die Dateien weg.
 */
final class MailAttachmentTest extends FeatureTestCase
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

    /** @return array<string,mixed> */
    private function upload(string $contents, string $name): array
    {
        $tmp = (string) tempnam(sys_get_temp_dir(), 'mailtest');
        file_put_contents($tmp, $contents);
        $this->tmpFiles[] = $tmp;

        return ['name' => $name, 'type' => 'application/octet-stream', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($contents)];
    }

    private function attach(int $draftId, string $contents, string $name): \EmergencyForge\Http\Response
    {
        return $this->post('/mail/drafts/' . $draftId . '/attachments', [], ['files' => ['file' => $this->upload($contents, $name)]]);
    }

    #[Test]
    public function hochladen_ausliefern_und_nur_fuer_beteiligte(): void
    {
        $alice = $this->member('Alice Absender');
        $bob   = $this->member('Bob Empfang');
        $eve   = $this->member('Eve Fremd');
        $this->loginAs($alice['user']);

        $id   = $this->draft();
        $data = $this->assertJsonResponse($this->attach($id, "Dienstplan\nMontag", "Plan \"März\"\r\n.txt"));
        $this->assertTrue($data['success']);
        $attachmentId = (int) $data['attachmentId'];
        $this->assertSame('Plan "März".txt', Attachment::query()->findOrFail($attachmentId)->original_name, 'Steuerzeichen fliegen raus.');

        $this->assertTrue($this->assertJsonResponse($this->post('/mail/drafts/' . $id . '/send', ['to' => [$bob['mailbox']->address]]))['success']);

        $this->loginAs($bob['user']);
        $download = $this->get('/mail/attachments/' . $attachmentId);
        $this->assertOk($download);
        $this->assertSame("Dienstplan\nMontag", $download->body);
        $this->assertSame('text/plain', $download->headers['Content-Type']);
        $this->assertSame('nosniff', $download->headers['X-Content-Type-Options']);
        $this->assertSame('private, no-store', $download->headers['Cache-Control']);
        $this->assertSame('attachment; filename="Plan _M_rz_.txt"; filename*=UTF-8\'\'Plan%20%22M%C3%A4rz%22.txt', $download->headers['Content-Disposition']);

        // Wer nicht beteiligt ist, bekommt nichts, auch mit allen Rechten.
        $this->loginAs($eve['user'], ['full_admin']);
        $this->assertNotFound($this->get('/mail/attachments/' . $attachmentId));

        // Nach dem Löschen der eigenen Kopie ist auch für Bob Schluss.
        $this->loginAs($bob['user']);
        $this->assertTrue($this->assertJsonResponse($this->post('/mail/messages/' . $id . '/delete'))['success']);
        $this->assertNotFound($this->get('/mail/attachments/' . $attachmentId));

        // Am gesendeten Anhang ändert niemand mehr etwas.
        $this->loginAs($alice['user']);
        $this->assertNotFound($this->post('/mail/attachments/' . $attachmentId . '/delete'));
    }

    #[Test]
    public function typen_aus_den_magic_bytes_und_die_grenze_je_mail(): void
    {
        $alice = $this->member('Alice Absender');
        $this->loginAs($alice['user']);
        $id = $this->draft();

        $svg = $this->attach($id, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'bild.png');
        $this->assertStatus(422, $svg);
        $html = $this->attach($id, '<!DOCTYPE html><html><body>x</body></html>', 'seite.txt');
        $this->assertStatus(422, $html);

        $chunk = str_repeat("Zeile mit Text\n", (int) (4 * 1024 * 1024 / 15));
        $this->assertOk($this->attach($id, $chunk, 'a.txt'));
        $this->assertOk($this->attach($id, $chunk, 'b.txt'));
        $third = $this->attach($id, $chunk, 'c.txt');
        $this->assertStatus(422, $third);
        $this->assertStringContainsString('10 MB', (string) ($this->assertJsonResponse($third)['message'] ?? ''));
        $this->assertSame(2, Attachment::query()->where('message_id', $id)->count());
    }

    #[Test]
    public function weiterleiten_kopiert_und_verwerfen_raeumt_dateien_weg(): void
    {
        $alice = $this->member('Alice Absender');
        $bob   = $this->member('Bob Empfang');
        $this->loginAs($alice['user']);
        $id = $this->draft();
        $this->assertOk($this->attach($id, 'Anlage', 'anlage.txt'));
        $this->post('/mail/drafts/' . $id . '/send', ['to' => [$bob['mailbox']->address]]);

        $this->loginAs($bob['user']);
        $forward = $this->draft(['forward_from' => (string) $id, 'subject' => 'Fwd: Betreff']);
        $copy = Attachment::query()->where('message_id', $forward)->firstOrFail();
        $original = Attachment::query()->where('message_id', $id)->firstOrFail();
        $this->assertSame('anlage.txt', $copy->original_name);
        $this->assertNotSame($original->path, $copy->path, 'Eigene Datei je Kopie.');

        $copyPath = app(AttachmentStorage::class)->absolutePath($copy);
        $this->assertNotNull($copyPath);
        $this->assertTrue($this->assertJsonResponse($this->post('/mail/messages/' . $forward . '/delete'))['success']);
        $this->assertFileDoesNotExist($copyPath);
        $this->assertNotNull(app(AttachmentStorage::class)->absolutePath($original), 'Das Original bleibt.');
        // Ein Entwurf hat nur die eigene Kopie: Zeile und Text gehen sofort, nicht erst mit mail:cleanup.
        $this->assertNull(\Plugin\Mail\Models\Message::query()->find($forward));
        $this->assertSame([], $this->deliveries($forward));

        // Fremde Mails lassen sich nicht weiterleiten.
        $eve = $this->member('Eve Fremd');
        $this->loginAs($eve['user']);
        $this->assertNotFound($this->post('/mail/drafts', ['forward_from' => (string) $id]));
    }

    #[Test]
    public function cleanup_entfernt_alte_entwuerfe_und_verwaiste_anhaenge(): void
    {
        $alice = $this->member('Alice Absender');
        $bob   = $this->member('Bob Empfang');
        $this->loginAs($alice['user']);

        $stale = $this->draft();
        $this->assertOk($this->attach($stale, 'alt', 'alt.txt'));
        $stalePath = app(AttachmentStorage::class)->absolutePath(Attachment::query()->where('message_id', $stale)->firstOrFail());
        \Plugin\Mail\Models\Message::query()->whereKey($stale)->update(['updated_at' => date('Y-m-d H:i:s', time() - 8 * 86400)]);

        $fresh = $this->draft();

        $sent = $this->draft();
        $this->assertOk($this->attach($sent, 'weg', 'weg.txt'));
        $this->post('/mail/drafts/' . $sent . '/send', ['to' => [$bob['mailbox']->address]]);
        $this->post('/mail/messages/' . $sent . '/delete');
        $this->loginAs($bob['user']);
        $this->post('/mail/messages/' . $sent . '/delete');

        $this->assertSame(0, $this->commandTester('mail:cleanup')->execute([]));

        $this->assertNull(\Plugin\Mail\Models\Message::query()->find($stale));
        $this->assertNotNull($stalePath);
        $this->assertFileDoesNotExist($stalePath);
        $this->assertNotNull(\Plugin\Mail\Models\Message::query()->find($fresh));
        $this->assertSame(0, Attachment::query()->where('message_id', $sent)->count());
        $this->assertNotNull(\Plugin\Mail\Models\Message::query()->find($sent), 'Die Nachricht bleibt für den Verlauf.');
    }

    #[Test]
    public function an_eine_gesendete_mail_kommt_kein_anhang_mehr(): void
    {
        $alice = $this->member('Alice Absender');
        $bob   = $this->member('Bob Empfang');
        $this->loginAs($alice['user']);
        $id    = $this->draft();
        $stale = \Plugin\Mail\Models\Message::query()->findOrFail($id);
        $this->assertTrue($this->assertJsonResponse($this->post('/mail/drafts/' . $id . '/send', ['to' => [$bob['mailbox']->address]]))['success']);

        $this->assertNotFound($this->attach($id, 'zu spät', 'nachtrag.txt'));

        // Auch wer den Entwurf vor dem Senden geladen hat, kommt nicht mehr durch.
        try {
            app(AttachmentStorage::class)->store($stale, $this->upload('zu spät', 'nachtrag.txt'));
            $this->fail('Anhang an gesendete Mail angenommen.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('gesendet', $e->getMessage());
        }
        $this->assertSame(0, Attachment::query()->where('message_id', $id)->count());
    }

    #[Test]
    public function die_endung_folgt_dem_erkannten_typ(): void
    {
        $alice = $this->member('Alice Absender');
        $bob   = $this->member('Bob Empfang');
        $this->loginAs($alice['user']);
        $id  = $this->draft();
        $png = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $cases = [
            ["start calc\r\nexit\r\n", 'Wachplan.bat', 'Wachplan.txt'],
            ['Nur Text', 'LIESMICH', 'LIESMICH.txt'],
            ['Nur Text', 'notiz.TXT', 'notiz.TXT'],
            [$png, 'bild.txt', 'bild.png'],
            [$png, '.exe', 'anhang.png'],
        ];
        $ids = [];
        foreach ($cases as [$contents, $name, $expected]) {
            $data = $this->assertJsonResponse($this->attach($id, $contents, $name));
            $this->assertTrue($data['success'], $name);
            $this->assertSame($expected, $data['name'], $name);
            $ids[] = (int) $data['attachmentId'];
        }

        $this->post('/mail/drafts/' . $id . '/send', ['to' => [$bob['mailbox']->address]]);
        $this->loginAs($bob['user']);
        $download = $this->get('/mail/attachments/' . $ids[0]);
        $this->assertSame('text/plain', $download->headers['Content-Type']);
        $this->assertStringStartsWith('attachment; filename="Wachplan.txt"', $download->headers['Content-Disposition']);

        // Auch ein älterer Datensatz geht nur mit der passenden Endung raus.
        Attachment::query()->whereKey($ids[0])->update(['original_name' => 'Wachplan.bat']);
        $this->assertStringStartsWith('attachment; filename="Wachplan.txt"', $this->get('/mail/attachments/' . $ids[0])->headers['Content-Disposition']);
    }

    #[Test]
    public function dateiname_nach_rfc_5987(): void
    {
        $this->assertSame('attachment; filename="a_b.pdf"; filename*=UTF-8\'\'a%C3%9Fb.pdf', MailController::contentDisposition('aßb.pdf'));
    }
}
