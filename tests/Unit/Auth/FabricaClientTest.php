<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Auth\FabricaClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FabricaClientTest extends TestCase
{
    private const INSTANCE = 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa';
    private const SUBJECT = 'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb';

    /** @return array<string,mixed> */
    private function identity(): array
    {
        return ['subject' => self::SUBJECT, 'instanceId' => self::INSTANCE, 'lease' => str_repeat('x', 43), 'expiresAt' => gmdate('c', time() + 3600), 'maxAge' => 60];
    }

    public function test_handoff_binds_state_pkce_instance_and_one_time_attempt(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], json_encode($this->identity(), JSON_THROW_ON_ERROR))]));
        $stack->push(Middleware::history($history));
        $client = new FabricaClient('https://console.example.test', self::INSTANCE, self::INSTANCE . '.' . str_repeat('s', 43), new Client(['handler' => $stack]));
        $session = [];
        parse_str((string) parse_url($client->begin($session), PHP_URL_QUERY), $params);
        $verifier = $session['fabrica_pending']['verifier'];
        self::assertSame(rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), $params['challenge']);
        $query = ['code' => str_repeat('c', 43), 'state' => $params['state']];
        $login = $client->finish($session, $query);
        self::assertSame(self::SUBJECT, $login['subject']);
        self::assertArrayNotHasKey('fabrica_pending', $session);
        self::assertSame($verifier, json_decode((string) $history[0]['request']->getBody(), true)['verifier']);
        self::assertFalse($history[0]['options']['allow_redirects']);
        self::assertTrue($history[0]['options']['verify']);
        $this->expectException(RuntimeException::class);
        $client->finish($session, $query);
    }

    public function test_invalid_state_never_calls_the_server(): void
    {
        $client = new FabricaClient('https://console.example.test', self::INSTANCE, self::INSTANCE . '.' . str_repeat('s', 43), new Client(['handler' => HandlerStack::create(new MockHandler([]))]));
        $session = [];
        $client->begin($session);
        $this->expectException(RuntimeException::class);
        $client->finish($session, ['state' => str_repeat('z', 43), 'code' => str_repeat('c', 43)]);
    }

    public function test_access_cache_expires_and_fails_closed_on_outage(): void
    {
        $mock = new MockHandler([new Response(200, [], json_encode($this->identity(), JSON_THROW_ON_ERROR)), new Response(503)]);
        $client = new FabricaClient('https://console.example.test', self::INSTANCE, self::INSTANCE . '.' . str_repeat('s', 43), new Client(['handler' => HandlerStack::create($mock)]));
        $session = [];
        $client->begin($session);
        $login = $client->finish($session, ['state' => $session['fabrica_pending']['state'], 'code' => str_repeat('c', 43)]) + ['localUserId' => 7];
        self::assertTrue($client->allows($login, 7));
        self::assertFalse($client->allows($login, 8));
        self::assertSame(1, $mock->count());
        $login['checked'] = time() - 60;
        self::assertFalse($client->allows($login, 7));
        self::assertSame(0, $mock->count());
    }

    public function test_foreign_instance_and_missing_lease_are_rejected(): void
    {
        foreach (['instanceId', 'lease', 'subject', 'maxAge', 'expiresAt'] as $field) {
            $identity = $this->identity();
            $identity[$field] = 'invalid';
            $client = new FabricaClient('https://console.example.test', self::INSTANCE, self::INSTANCE . '.' . str_repeat('s', 43), new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, [], json_encode($identity, JSON_THROW_ON_ERROR))]))]));
            $session = [];
            $client->begin($session);
            try {
                $client->finish($session, ['state' => $session['fabrica_pending']['state'], 'code' => str_repeat('c', 43)]);
                self::fail('Invalid identity accepted: ' . $field);
            } catch (RuntimeException) {
                self::assertArrayNotHasKey('fabrica_pending', $session);
            }
        }
    }

    public function test_profile_is_validated_without_requiring_it_from_older_fabrica_servers(): void
    {
        foreach ([null, 'Sync member', 42, [], '', str_repeat('x', 201)] as $username) {
            $data = $this->identity() + ['username' => $username];
            $client = new FabricaClient('https://console.example.test', self::INSTANCE, self::INSTANCE . '.' . str_repeat('s', 43), new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, [], json_encode($data, JSON_THROW_ON_ERROR))]))]));
            $session = [];
            $client->begin($session);
            try {
                $login = $client->finish($session, ['state' => $session['fabrica_pending']['state'], 'code' => str_repeat('c', 43)]);
                self::assertTrue($username === null || $username === 'Sync member');
                self::assertSame($username, $login['username']);
            } catch (RuntimeException) {
                self::assertFalse($username === null || $username === 'Sync member');
            }
        }
    }

    public function test_configuration_requires_https_and_instance_scoped_secret(): void
    {
        $this->expectException(RuntimeException::class);
        new FabricaClient('http://console.example.test', self::INSTANCE, self::INSTANCE . '.' . str_repeat('s', 43));
    }
}
