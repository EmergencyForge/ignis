<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Anmeldung über ignisTab: Der FiveM-Server holt per API-Schlüssel einen
 * Einmal-Token für eine Discord-ID, das Tablet löst ihn unter /auth/tablet ein.
 */
final class TabletLoginTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setEnabled(true);
    }

    private function setEnabled(bool $on): void
    {
        Capsule::table('intra_config')
            ->where('config_key', 'TABLET_LOGIN_ENABLED')
            ->update(['config_value' => $on ? 'true' : 'false']);
    }

    /** @param array<string,mixed>|string $body */
    private function requestToken(array|string $body, ?string $key = null, bool $withKey = true, string $remote = '192.0.2.10'): Response
    {
        $server = ['REMOTE_ADDR' => $remote];
        if ($withKey) {
            $server['HTTP_X_API_KEY'] = $key ?? (string) constant('API_KEY');
        }

        return $this->router->dispatch(new Request(
            'POST',
            '/api/tablet/login-token',
            server: $server,
            rawBody: is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR),
        ));
    }

    private function tokenFor(User $user): string
    {
        $response = $this->requestToken(['discord_id' => (string) $user->discord_id]);
        $this->assertStatus(200, $response);

        return (string) $this->assertJsonResponse($response)['token'];
    }

    private function tokenCount(User $user): int
    {
        return Capsule::table('intra_tablet_login_tokens')->where('user_id', $user->id)->count();
    }

    #[Test]
    public function ohne_oder_mit_falschem_api_schluessel_gibt_es_keinen_token(): void
    {
        $user = FixtureFactory::user();

        $this->assertStatus(403, $this->requestToken(['discord_id' => (string) $user->discord_id], withKey: false));
        $this->assertStatus(403, $this->requestToken(['discord_id' => (string) $user->discord_id], 'falscher-schluessel'));
        $this->assertSame(0, $this->tokenCount($user));
    }

    /**
     * Die API lässt in Development Anfragen von 127.0.0.1 ohne Schlüssel
     * durch. Für einen Token, der jedes Konto anmeldet, reicht das nicht.
     */
    #[Test]
    public function der_localhost_bypass_der_api_reicht_nicht_fuer_einen_token(): void
    {
        $user = FixtureFactory::user();
        $appEnv = $_ENV['APP_ENV'] ?? null;
        $_ENV['APP_ENV'] = 'development';
        try {
            $response = $this->requestToken(['discord_id' => (string) $user->discord_id], withKey: false, remote: '127.0.0.1');
        } finally {
            if ($appEnv === null) {
                unset($_ENV['APP_ENV']);
            } else {
                $_ENV['APP_ENV'] = $appEnv;
            }
        }

        $this->assertStatus(403, $response);
        $this->assertSame(0, $this->tokenCount($user));
    }

    #[Test]
    public function ausgeschaltet_antworten_beide_endpunkte_mit_404(): void
    {
        $user = FixtureFactory::user();
        $token = $this->tokenFor($user);
        $this->setEnabled(false);

        $this->assertStatus(404, $this->requestToken(['discord_id' => (string) $user->discord_id]));
        $this->assertStatus(404, $this->get('/auth/tablet', ['query' => ['token' => $token]]));
        $this->assertArrayNotHasKey('userid', $_SESSION);
    }

    #[Test]
    public function unbekannte_oder_deaktivierte_konten_bekommen_keinen_token(): void
    {
        $unknown = $this->requestToken(['discord_id' => '123456789012345678']);
        $this->assertStatus(404, $unknown);
        $this->assertSame(['success' => false, 'error' => 'unknown_user'], $this->assertJsonResponse($unknown));

        $inactive = FixtureFactory::user(['is_active' => false]);
        $response = $this->requestToken(['discord_id' => (string) $inactive->discord_id]);
        $this->assertStatus(404, $response);
        $this->assertSame('unknown_user', $this->assertJsonResponse($response)['error']);
        $this->assertSame(0, $this->tokenCount($inactive));
    }

    /** Die Discord-ID ist nicht eindeutig; bei zwei aktiven Konten gibt es keinen Token. */
    #[Test]
    public function eine_discord_id_mit_zwei_aktiven_konten_bekommt_keinen_token(): void
    {
        $first = FixtureFactory::user();
        $second = FixtureFactory::user(['discord_id' => $first->discord_id]);

        $response = $this->requestToken(['discord_id' => (string) $first->discord_id]);

        $this->assertStatus(409, $response);
        $this->assertSame(['success' => false, 'error' => 'ambiguous_user'], $this->assertJsonResponse($response));
        $this->assertSame(0, $this->tokenCount($first) + $this->tokenCount($second));

        // Ein deaktiviertes zweites Konto stört nicht.
        $second->is_active = false;
        $second->save();
        $this->assertSame($first->id, Capsule::table('intra_tablet_login_tokens')->where('token_hash', hash('sha256', $this->tokenFor($first)))->value('user_id'));
    }

    #[Test]
    public function die_discord_id_wird_streng_geprueft(): void
    {
        foreach ([['discord_id' => '12345'], ['discord_id' => 123456789012345678], ['discord_id' => '12345678901234567a'], [], 'kein json'] as $body) {
            $this->assertStatus(422, $this->requestToken($body));
        }
    }

    #[Test]
    public function der_token_meldet_an_und_setzt_die_sitzung(): void
    {
        $role = FixtureFactory::role(['permissions' => ['personnel.view']]);
        $user = FixtureFactory::user(['role' => $role->id]);

        $issued = $this->assertJsonResponse($this->requestToken(['discord_id' => (string) $user->discord_id]));
        $this->assertTrue($issued['success']);
        $this->assertSame(60, $issued['expires_in']);
        $this->assertMatchesRegularExpression('~^https?://[^/]+/auth/tablet$~', $issued['login_url']);
        $this->assertMatchesRegularExpression('~^[A-Za-z0-9_-]{43}$~', $issued['token']);

        // Gespeichert ist nur der Hash.
        $row = Capsule::table('intra_tablet_login_tokens')->where('user_id', $user->id)->first();
        $this->assertNotNull($row);
        $this->assertSame(hash('sha256', $issued['token']), $row->token_hash);

        $response = $this->get('/auth/tablet', ['query' => ['token' => $issued['token']]]);

        $this->assertRedirect($response, '/index');
        $this->assertSame('no-store', $response->headers['Cache-Control'] ?? null);
        $this->assertSame('no-referrer', $response->headers['Referrer-Policy'] ?? null);
        $this->assertSame($user->id, $_SESSION['userid']);
        $this->assertSame($user->discord_id, $_SESSION['discordtag']);
        $this->assertSame(['personnel.view'], $_SESSION['permissions']);
        $this->assertTrue(
            Capsule::table('intra_audit_log')->where('user', $user->id)->where('action', 'Anmeldung über ignisTab')->exists(),
        );
    }

    #[Test]
    public function ein_token_gilt_nur_einmal(): void
    {
        $user = FixtureFactory::user();
        $token = $this->tokenFor($user);

        $this->assertRedirect($this->get('/auth/tablet', ['query' => ['token' => $token]]), '/index');
        $_SESSION = [];

        $second = $this->get('/auth/tablet', ['query' => ['token' => $token]]);
        $this->assertRedirect($second, '/login');
        $this->assertArrayNotHasKey('userid', $_SESSION);
        $this->assertNotEmpty($_SESSION['registration_error'] ?? null);
    }

    #[Test]
    public function ein_abgelaufener_oder_fremder_token_meldet_nicht_an(): void
    {
        $user = FixtureFactory::user();
        $token = $this->tokenFor($user);
        Capsule::table('intra_tablet_login_tokens')
            ->where('token_hash', hash('sha256', $token))
            ->update(['expires_at' => date('Y-m-d H:i:s', time() - 1)]);

        $this->assertRedirect($this->get('/auth/tablet', ['query' => ['token' => $token]]), '/login');
        $this->assertRedirect($this->get('/auth/tablet', ['query' => ['token' => 'erfunden']]), '/login');
        $this->assertRedirect($this->get('/auth/tablet'), '/login');
        $this->assertArrayNotHasKey('userid', $_SESSION);
    }

    #[Test]
    public function ein_deaktiviertes_konto_kann_seinen_token_nicht_einloesen(): void
    {
        $user = FixtureFactory::user();
        $token = $this->tokenFor($user);
        $user->is_active = false;
        $user->save();

        $this->assertRedirect($this->get('/auth/tablet', ['query' => ['token' => $token]]), '/login');
        $this->assertArrayNotHasKey('userid', $_SESSION);
    }

    #[Test]
    public function mehr_als_zehn_token_pro_minute_werden_abgelehnt(): void
    {
        $user = FixtureFactory::user();
        for ($i = 0; $i < 10; $i++) {
            $this->tokenFor($user);
        }

        $response = $this->requestToken(['discord_id' => (string) $user->discord_id]);

        $this->assertStatus(429, $response);
        $this->assertSame('60', $response->headers['Retry-After'] ?? null);
        $this->assertSame(10, $this->tokenCount($user));
    }

    #[Test]
    public function abgelaufene_token_werden_beim_ausstellen_aufgeraeumt(): void
    {
        $user = FixtureFactory::user();
        $old = $this->tokenFor($user);
        Capsule::table('intra_tablet_login_tokens')
            ->where('token_hash', hash('sha256', $old))
            ->update(['expires_at' => date('Y-m-d H:i:s', time() - 5)]);

        $this->tokenFor($user);

        $this->assertFalse(Capsule::table('intra_tablet_login_tokens')->where('token_hash', hash('sha256', $old))->exists());
    }
}
