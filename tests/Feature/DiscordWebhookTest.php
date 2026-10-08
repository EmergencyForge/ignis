<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\EventDispatcher;
use App\Integrations\DiscordWebhook;
use App\Jobs\SendDiscordWebhookJob;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Queue\QueueManager;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Enotf\Events\EnotfProtocolReleased;
use Tests\FeatureTestCase;

/**
 * Die Discord-Webhooks zu freigegebenen Protokollen und Voranmeldungen.
 * Sie lagen monatelang ungesendet in der Queue; was dabei nie auffiel:
 * ohne eingetragene URL ging jeder Job in drei Fehlversuche, leere Felder
 * und ungültige Links lassen Discord die ganze Nachricht ablehnen. Discord
 * selbst ersetzt DiscordWebhook::fake().
 */
final class DiscordWebhookTest extends FeatureTestCase
{
    private const URL = 'https://discord.com/api/webhooks/123456789012345678/abc-DEF_123';

    /** @var list<array{url:string, body:array<string,mixed>}> */
    private array $posts = [];

    /** @var array{status:int, body:string}|null */
    private ?array $answer = ['status' => 204, 'body' => ''];

    protected function setUp(): void
    {
        parent::setUp();
        DiscordWebhook::clearCache();
        DiscordWebhook::fake(function (string $url, string $body): ?array {
            $this->posts[] = ['url' => $url, 'body' => json_decode($body, true, 512, JSON_THROW_ON_ERROR)];

            return $this->answer;
        });
    }

    protected function tearDown(): void
    {
        DiscordWebhook::fake(null);
        DiscordWebhook::clearCache();
        parent::tearDown();
    }

    private function configure(string $key, string $url = self::URL): void
    {
        Capsule::table('intra_config')->updateOrInsert(['config_key' => $key], ['config_value' => $url, 'config_type' => 'url', 'category' => 'webhooks']);
        DiscordWebhook::clearCache();
    }

    /** @return array<string, mixed> */
    private function embed(): array
    {
        $this->assertCount(1, $this->posts);

        return $this->posts[0]['body']['embeds'][0];
    }

    #[Test]
    public function ohne_eingetragene_url_ist_nichts_zu_tun(): void
    {
        $this->configure('DISCORD_WEBHOOK_ENOTF_PROTOCOL', '');

        (new SendDiscordWebhookJob('enotf_released', ['enr' => '4711']))->handle();

        $this->assertSame([], $this->posts);
    }

    #[Test]
    public function freigegebenes_enotf_protokoll(): void
    {
        $this->configure('DISCORD_WEBHOOK_ENOTF_PROTOCOL');

        (new SendDiscordWebhookJob('enotf_released', ['enr' => '4711', 'last_edit' => '2026-10-08 12:00:00']))->handle();

        $this->assertSame(self::URL, $this->posts[0]['url']);
        $this->assertSame(['parse' => []], $this->posts[0]['body']['allowed_mentions']);
        $embed = $this->embed();
        $this->assertSame('📋 eNOTF-Protokoll freigegeben', $embed['title']);
        $this->assertSame(['Einsatznummer', '**#4711**'], [$embed['fields'][0]['name'], $embed['fields'][0]['value']]);
        $this->assertValidEmbed($embed);
    }

    #[Test]
    public function fehlende_und_lange_werte_lehnt_discord_nicht_ab(): void
    {
        $this->configure('DISCORD_WEBHOOK_FIRE_PROTOCOL');
        $this->configure('DISCORD_WEBHOOK_ENOTF_PREREG');

        (new SendDiscordWebhookJob('fire_released', ['id' => 3, 'incident_number' => 'E-1', 'location' => '', 'keyword' => null]))->handle();
        $fire = $this->posts[0]['body']['embeds'][0];
        $this->assertSame('Unbekannt', $fire['fields'][1]['value']);
        $this->assertSame('Unbekannt', $fire['fields'][2]['value']);
        $this->assertValidEmbed($fire);

        (new SendDiscordWebhookJob('enotf_preregistration', [
            'priority'  => '2',
            'arrival'   => '2026-10-08 12:30:00',
            'fahrzeug'  => 42,
            'diagnose'  => str_repeat('x', 3000),
            'ziel'      => 'Klinikum',
            'intubiert' => '0',
            'kreislauf' => '',
        ]))->handle();
        $prereg = $this->posts[1]['body']['embeds'][0];
        $values = array_column($prereg['fields'], 'value', 'name');
        $this->assertSame('🔴 **Sofort**', $values['Priorität']);
        $this->assertSame('08.10.2026 12:30', $values['Ankunft']);
        $this->assertSame('42', $values['Fahrzeug']);
        $this->assertSame(1024, mb_strlen($values['Diagnose']));
        $this->assertSame('❌', $values['Intubiert']);
        $this->assertSame('Unbekannt', $values['Kreislauf']);
        $this->assertSame(15158332, $prereg['color']);
        $this->assertValidEmbed($prereg);
    }

    #[Test]
    public function abgelehnt_wird_nicht_wiederholt_stoerungen_schon(): void
    {
        $this->configure('DISCORD_WEBHOOK_ENOTF_PROTOCOL');
        $job = new SendDiscordWebhookJob('enotf_released', ['enr' => '4711']);

        // Gelöschter Webhook: kein neuer Versuch, nur ein Log-Eintrag.
        $this->answer = ['status' => 404, 'body' => '{"message":"Unknown Webhook","code":10015}'];
        $job->handle();

        foreach ([['status' => 429, 'body' => '{}'], ['status' => 502, 'body' => ''], null] as $answer) {
            $this->answer = $answer;
            try {
                $job->handle();
                $this->fail('Eine Störung muss in den Retry der Queue gehen.');
            } catch (\App\Integrations\DiscordWebhookException $e) {
                $this->assertTrue($e->retryable());
            }
        }
    }

    #[Test]
    public function eine_url_ohne_https_geht_nicht_raus(): void
    {
        $this->configure('DISCORD_WEBHOOK_ENOTF_PROTOCOL', 'http://example.org/hook');

        (new SendDiscordWebhookJob('enotf_released', ['enr' => '4711']))->handle();

        $this->assertSame([], $this->posts);
    }

    #[Test]
    public function in_der_queue_liegt_nur_was_die_meldung_zeigt(): void
    {
        $queue = app(QueueManager::class)->connection();

        app(EventDispatcher::class)->fire(new EnotfProtocolReleased([
            'enr'       => '4711',
            'last_edit' => '2026-10-08 12:00:00',
            'patname'   => 'Erika Mustermann',
            'diagnose'  => 'Vertraulich',
        ]));

        // Andere Tests können Jobs hinterlassen haben; gesucht ist der zu 4711.
        $payloads = [];
        for ($i = 0; $i < 100; $i++) {
            $job = $queue->pop('notifications');
            if ($job === null) {
                break;
            }
            $payloads[] = $job->getRawBody();
        }
        $ours = array_values(array_filter($payloads, static fn (string $payload): bool => str_contains($payload, '4711')));
        $this->assertCount(1, $ours, 'Der Listener hat keinen Job eingereiht.');
        $this->assertStringNotContainsString('Mustermann', $ours[0]);
        $this->assertStringNotContainsString('Vertraulich', $ours[0]);
    }

    /** @param array<string, mixed> $embed */
    private function assertValidEmbed(array $embed): void
    {
        $this->assertLessThanOrEqual(256, mb_strlen($embed['title']));
        foreach ($embed['fields'] as $field) {
            $this->assertIsString($field['value']);
            $this->assertNotSame('', $field['value'], 'Discord lehnt leere Felder ab: ' . $field['name']);
            $this->assertLessThanOrEqual(1024, mb_strlen($field['value']));
        }
        if (isset($embed['url'])) {
            $this->assertNotFalse(filter_var($embed['url'], FILTER_VALIDATE_URL), 'Ungültiger Link: ' . $embed['url']);
            $this->assertStringNotContainsString('https://https://', $embed['url']);
        }
    }
}
