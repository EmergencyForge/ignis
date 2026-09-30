<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Mail\Models\Message;
use Tests\FeatureTestCase;

/**
 * Sendepause je Postfach (`MAIL_SEND_COOLDOWN`): kein allgemeines
 * Rate-Limit, aber höchstens eine Mail alle X Sekunden aus demselben
 * Postfach. Entwürfe speichern zählt nicht.
 */
final class MailSendCooldownTest extends FeatureTestCase
{
    use MailFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
    }

    private function setCooldown(int $seconds): void
    {
        Capsule::table('intra_config')->where('config_key', 'MAIL_SEND_COOLDOWN')->update(['config_value' => (string) $seconds]);
    }

    /** @return array<string,mixed> */
    private function mailTo(string $address): array
    {
        return ['subject' => 'Lagemeldung', 'body_json' => self::doc('Text'), 'to' => [$address]];
    }

    #[Test]
    public function eine_zweite_mail_in_der_pause_bekommt_429(): void
    {
        $this->setCooldown(10);
        $sender = $this->member('Schnell Schreiber');
        $to     = $this->member('Geduldig Leser');
        $this->loginAs($sender['user']);

        $this->assertTrue($this->send($this->mailTo($to['mailbox']->address))['success']);

        $draftId = $this->draft($this->mailTo($to['mailbox']->address));
        // Entwürfe speichern bleibt frei.
        $this->assertOk($this->post('/mail/drafts/' . $draftId, $this->mailTo($to['mailbox']->address)));

        $again = $this->post('/mail/drafts/' . $draftId . '/send', $this->mailTo($to['mailbox']->address));
        $this->assertStatus(429, $again);
        $body = $this->assertJsonResponse($again);
        $this->assertFalse($body['success']);
        $this->assertGreaterThanOrEqual(1, $body['retry_after']);
        $this->assertLessThanOrEqual(10, $body['retry_after']);
        $this->assertSame((string) $body['retry_after'], $again->headers['Retry-After'] ?? null);
        $this->assertStringStartsWith('Bitte warte noch ' . $body['retry_after'] . ' Sekunde', $body['message']);
        $this->assertStringEndsWith('bis zur nächsten Mail.', $body['message']);
        $this->assertSame('draft', Message::query()->findOrFail($draftId)->status);
        $this->assertSame(1, Message::query()->where('sender_mailbox_id', $sender['mailbox']->id)->where('status', 'sent')->count());
    }

    #[Test]
    public function nach_der_pause_geht_das_senden_wieder(): void
    {
        $this->setCooldown(10);
        $sender = $this->member('Spaeter Schreiber');
        $to     = $this->member('Spaeter Leser');
        $this->loginAs($sender['user']);

        $first = (int) $this->send($this->mailTo($to['mailbox']->address))['messageId'];
        Message::query()->whereKey($first)->update(['sent_at' => date('Y-m-d H:i:s', time() - 11)]);

        $this->assertTrue($this->send($this->mailTo($to['mailbox']->address))['success']);
    }

    #[Test]
    public function null_schaltet_die_pause_ab(): void
    {
        $this->setCooldown(0);
        $sender = $this->member('Ohne Pause');
        $to     = $this->member('Viel Post');
        $this->loginAs($sender['user']);

        foreach ([1, 2, 3] as $_) {
            $this->assertTrue($this->send($this->mailTo($to['mailbox']->address))['success']);
        }
    }

    #[Test]
    public function die_pause_gilt_je_postfach(): void
    {
        $this->setCooldown(10);
        $first  = $this->member('Erstes Postfach');
        $second = $this->member('Zweites Postfach');

        $this->loginAs($first['user']);
        $this->assertTrue($this->send($this->mailTo($second['mailbox']->address))['success']);

        $this->loginAs($second['user']);
        $this->assertTrue($this->send($this->mailTo($first['mailbox']->address))['success']);
    }
}
