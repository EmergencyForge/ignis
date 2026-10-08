<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Discord\DiscordBot;
use App\Http\Controllers\Api\PersonnelController;
use App\Models\RegistrationCode;
use App\Models\User;
use App\Notifications\NotificationManager;
use EmergencyForge\Http\Request;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Queue\QueueManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Der eigene Discord-Bot: Einrichten unter Einstellungen › System ›
 * Discord-Bot, Benachrichtigungen als Direktnachricht (mit Abschalten im
 * Kontomenü) und Einladungen per DM aus dem Mitarbeiterprofil. Discord
 * selbst ersetzt DiscordBot::fake(); jeder Aufruf landet in $calls.
 */
final class DiscordBotTest extends FeatureTestCase
{
    private const BOT_ID = '123456789012345678';
    private const CHANNEL_ID = '223456789012345678';
    private const TOKEN = 'MTIzNDU2Nzg5MDEyMzQ1Njc4.test.geheim';

    /** @var list<array{0:string, 1:string, 2:array<string,mixed>|null}> */
    private array $calls = [];

    /** @var array<string, array{status:int, body:string}> Pfad => abweichende Antwort */
    private array $failures = [];

    protected function setUp(): void
    {
        parent::setUp();
        DiscordBot::forget();
        DiscordBot::fake(function (string $method, string $path, ?array $body): array {
            $this->calls[] = [$method, $path, $body];
            if (isset($this->failures[$path])) {
                return $this->failures[$path];
            }

            return match (true) {
                $path === '/users/@me' => ['status' => 200, 'body' => json_encode([
                    'id'       => self::BOT_ID,
                    'username' => $body['username'] ?? 'Wachbot',
                    'avatar'   => isset($body['avatar']) ? str_repeat('a', 32) : null,
                ], JSON_THROW_ON_ERROR)],
                $path === '/users/@me/channels' => ['status' => 200, 'body' => json_encode(['id' => self::CHANNEL_ID], JSON_THROW_ON_ERROR)],
                $path === '/channels/' . self::CHANNEL_ID . '/messages' => ['status' => 200, 'body' => '{}'],
                default => ['status' => 404, 'body' => '{"message":"Unknown","code":0}'],
            };
        });
    }

    protected function tearDown(): void
    {
        DiscordBot::fake(null);
        DiscordBot::forget();
        parent::tearDown();
    }

    private function loginAdmin(): User
    {
        $admin = FixtureFactory::user();
        $this->actingAs($admin->id, ['permissions' => ['full_admin'], 'cirs_username' => $admin->username, 'discordtag' => (string) $admin->discord_id]);

        return $admin;
    }

    private function activateBot(): void
    {
        DiscordBot::store(['enabled' => true, 'token' => self::TOKEN, 'id' => self::BOT_ID, 'name' => 'Wachbot', 'dm_types' => ['system']]);
    }

    /** @return list<string> an wen der Bot einen DM-Kanal geöffnet hat */
    private function dmRecipients(): array
    {
        return array_values(array_map(
            static fn (array $call): string => (string) $call[2]['recipient_id'],
            array_filter($this->calls, static fn (array $call): bool => $call[1] === '/users/@me/channels'),
        ));
    }

    /** @return list<array<string,mixed>> die verschickten Nachrichten */
    private function sentMessages(): array
    {
        return array_values(array_map(
            static fn (array $call): array => (array) $call[2],
            array_filter($this->calls, static fn (array $call): bool => str_ends_with($call[1], '/messages')),
        ));
    }

    private function runQueue(): void
    {
        $connection = app(QueueManager::class)->connection();
        while (($job = $connection->pop('default')) !== null) {
            $job->fire();
        }
    }

    #[Test]
    public function einrichten_mit_token_name_und_direktnachrichten(): void
    {
        $this->loginAdmin();

        $page = $this->get('/settings/system/discord');
        $this->assertOk($page);
        $this->assertBodyContains('Nicht eingerichtet', $page);

        $response = $this->post('/settings/system/discord', [
            'token'    => self::TOKEN,
            'enabled'  => '1',
            'name'     => 'Leitstelle',
            'dm_types' => ['system', 'dokument', 'gibt-es-nicht'],
        ]);
        $this->assertRedirect($response, '/settings/system/discord');

        $settings = DiscordBot::settings();
        $this->assertTrue($settings['enabled']);
        $this->assertSame(self::TOKEN, $settings['token']);
        $this->assertSame(self::BOT_ID, $settings['id']);
        $this->assertSame('Leitstelle', $settings['name']);
        $this->assertEqualsCanonicalizing(['system', 'dokument'], $settings['dm_types']);
        $this->assertContains(['PATCH', '/users/@me', ['username' => 'Leitstelle']], $this->calls);

        // In der Datenbank liegt das Token verschlüsselt.
        $stored = (string) Capsule::table('intra_config')->where('config_key', 'DISCORD_BOT_TOKEN')->value('config_value');
        $this->assertStringStartsWith('enc:v1:', $stored);
        $this->assertStringNotContainsString(self::TOKEN, $stored);

        // Das Token steht weder im Audit-Log noch auf der Seite.
        $audit = Capsule::table('intra_audit_log')->where('action', 'Discord-Bot geändert')->first();
        $this->assertNotNull($audit);
        $this->assertStringNotContainsString(self::TOKEN, json_encode($audit, JSON_THROW_ON_ERROR));

        $page = $this->get('/settings/system/discord');
        $this->assertBodyContains('Leitstelle', $page);
        $this->assertBodyContains(htmlspecialchars(DiscordBot::inviteUrl(self::BOT_ID)), $page);
        $this->assertBodyNotContains(self::TOKEN, $page);
    }

    #[Test]
    public function ein_abgelehntes_token_wird_nicht_gespeichert(): void
    {
        $this->loginAdmin();
        $this->failures['/users/@me'] = ['status' => 401, 'body' => '{"message":"401: Unauthorized","code":0}'];

        $this->post('/settings/system/discord', ['token' => 'falsch', 'enabled' => '1', 'dm_types' => ['system']]);

        $settings = DiscordBot::settings();
        $this->assertSame('', $settings['token']);
        $this->assertFalse($settings['enabled']);
        $this->assertStringContainsString('lehnt das Token ab', json_encode($_SESSION, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    #[Test]
    public function ein_token_ohne_passenden_schluessel_gilt_als_verloren(): void
    {
        $this->activateBot();
        $this->assertTrue(DiscordBot::active());

        // Anderer Schlüssel, etwa nach verlorenem storage/: nichts geht raus.
        $keyFile = sys_get_temp_dir() . '/discord-key-' . bin2hex(random_bytes(4)) . '.key';
        \App\Security\SecretBox::$keyFile = $keyFile;
        \App\Security\SecretBox::forget();
        DiscordBot::forget();
        try {
            $this->assertTrue(DiscordBot::settings()['token_lost']);
            $this->assertFalse(DiscordBot::active());

            $this->loginAdmin();
            $this->assertBodyContains('id="discordTokenLost"', $this->get('/settings/system/discord'));
        } finally {
            \App\Security\SecretBox::$keyFile = null;
            \App\Security\SecretBox::forget();
            @unlink($keyFile);
        }
    }

    #[Test]
    public function benachrichtigungen_gehen_per_dm_an_wer_sie_will(): void
    {
        $this->activateBot();

        $withAccountId = FixtureFactory::user();
        $viaPersonnel  = FixtureFactory::user(['discord_id' => null]);
        $person = FixtureFactory::personnel(['discordtag' => '323456789012345678']);
        User::query()->whereKey($viaPersonnel->id)->update(['discord_id' => null, 'aktenid' => $person->id]);
        $optedOut = FixtureFactory::user();
        User::query()->whereKey($optedOut->id)->update(['discord_dm' => 0]);
        $unreachable = FixtureFactory::user();
        User::query()->whereKey($unreachable->id)->update(['discord_id' => null]);

        $ids = [$withAccountId->id, $viaPersonnel->id, $optedOut->id, $unreachable->id];
        (new NotificationManager())->notify('system', $ids, ['title' => 'Wartung heute', 'message' => 'Ab 22 Uhr', 'link' => BASE_PATH . 'index']);
        // Dokumente stellt der Bot hier nicht zu.
        (new NotificationManager())->notify('dokument', $ids, ['title' => 'Neues Dokument']);
        $this->runQueue();

        $this->assertEqualsCanonicalizing([(string) $withAccountId->discord_id, '323456789012345678'], $this->dmRecipients());
        $messages = $this->sentMessages();
        $this->assertCount(2, $messages);
        $embed = $messages[0]['embeds'][0];
        $this->assertSame('Wartung heute', $embed['title']);
        $this->assertSame('Ab 22 Uhr', $embed['description']);
        $this->assertMatchesRegularExpression('#^https?://[^/]+/.*index$#', $embed['url']);
        $this->assertSame(['parse' => []], $messages[0]['allowed_mentions']);

        // Die Benachrichtigungen selbst bekommen alle vier.
        $this->assertSame(4, Capsule::table('intra_notifications')->where('title', 'Wartung heute')->count());
    }

    #[Test]
    public function ausgeschaltet_geht_nichts_raus(): void
    {
        $this->activateBot();
        DiscordBot::store(['enabled' => false]);
        $user = FixtureFactory::user();

        (new NotificationManager())->notify('system', [$user->id], ['title' => 'Still']);
        $this->runQueue();

        $this->assertSame([], $this->calls);
    }

    #[Test]
    public function dms_im_kontomenue_abschalten(): void
    {
        $this->activateBot();
        $admin = $this->loginAdmin();

        $this->assertBodyContains('id="topDiscordDm"', $this->get('/settings/system/discord'));

        $this->post('/profile/discord-dm', ['discord_dm' => '0']);
        $this->assertSame(0, (int) User::query()->whereKey($admin->id)->value('discord_dm'));

        (new NotificationManager())->notify('system', [$admin->id], ['title' => 'Nicht per DM']);
        $this->runQueue();
        $this->assertSame([], $this->dmRecipients());

        $this->post('/profile/discord-dm', ['discord_dm' => '1']);
        $this->assertSame(1, (int) User::query()->whereKey($admin->id)->value('discord_dm'));
    }

    #[Test]
    public function testnachricht_an_sich_selbst(): void
    {
        $this->activateBot();
        $admin = $this->loginAdmin();

        $this->assertRedirect($this->post('/settings/system/discord/test'), '/settings/system/discord');

        $this->assertSame([(string) $admin->discord_id], $this->dmRecipients());
        $this->assertSame('Testnachricht', $this->sentMessages()[0]['embeds'][0]['title']);
    }

    #[Test]
    public function einladung_per_discord_aus_dem_profil(): void
    {
        $this->activateBot();
        $this->loginAdmin();
        $person = FixtureFactory::personnel(['fullname' => 'Dora Discord', 'discordtag' => '423456789012345678']);

        $this->assertBodyContains('data-invite-discord', $this->get('/personnel/profile', ['query' => ['id' => (string) $person->id]]));

        $api = new PersonnelController();
        $invite = static fn (int $id): array => json_decode($api->generateInvite(new Request('POST', '/api/personnel/generate-invite', rawBody: json_encode(['label' => 'Dora Discord', 'mitarbeiter_id' => $id, 'via' => 'discord'], JSON_THROW_ON_ERROR)))->body, true, 512, JSON_THROW_ON_ERROR);

        $result = $invite($person->id);
        $this->assertTrue($result['success']);
        $this->assertTrue($result['discord']['sent']);
        $this->assertSame(['423456789012345678'], $this->dmRecipients());
        $message = $this->sentMessages()[0];
        $this->assertSame($result['inviteUrl'], $message['embeds'][0]['url']);
        $this->assertSame($result['inviteUrl'], $message['components'][0]['components'][0]['url']);
        $this->assertSame(1, Capsule::table('intra_audit_log')->where('action', 'Einladung per Discord gesendet')->where('details', 'Dora Discord')->count());

        // Lässt Discord die DM nicht zu, bleibt die Einladung trotzdem bestehen.
        $this->failures['/channels/' . self::CHANNEL_ID . '/messages'] = ['status' => 403, 'body' => '{"message":"Cannot send messages to this user","code":50007}'];
        $again = $invite($person->id);
        $this->assertTrue($again['existing']);
        $this->assertFalse($again['discord']['sent']);
        $this->assertStringContainsString('keine Direktnachricht', $again['discord']['message']);

        // Ohne Discord-ID am Mitarbeiter: keine Einladung.
        $without = FixtureFactory::personnel(['discordtag' => null]);
        $this->assertFalse($invite($without->id)['success']);
        $this->assertSame(0, RegistrationCode::query()->where('mitarbeiter_id', $without->id)->count());
    }
}
