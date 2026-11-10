<?php

declare(strict_types=1);

namespace App\Auth;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use RuntimeException;
use Throwable;

/** Internal Fabrica handoff; authentik's OIDC tokens stay at Fabrica. */
final class FabricaClient
{
    public function __construct(
        public readonly string $origin,
        public readonly string $instanceId,
        private readonly string $credential,
        private readonly ClientInterface $http = new Client(),
    ) {
        $url = parse_url($origin);
        if (!is_array($url) || ($url['scheme'] ?? '') !== 'https' || empty($url['host'])
            || isset($url['user'], $url['pass']) || isset($url['user']) || isset($url['query']) || isset($url['fragment'])
            || !empty($url['path']) || !self::uuid($instanceId)
            || !preg_match('/^' . preg_quote($instanceId, '/') . '\.[A-Za-z0-9_-]{43}$/D', $credential)) {
            throw new RuntimeException('Ungültige Fabrica-Konfiguration.');
        }
    }

    public static function enabled(): bool
    {
        // Unknown modes must never silently enable the old login.
        return self::environment('AUTH_MODE', 'direct-discord') !== 'direct-discord';
    }

    public static function fromEnvironment(): self
    {
        if (self::environment('AUTH_MODE') !== 'emergencyforge') {
            throw new RuntimeException('Ungültiger Anmeldemodus.');
        }
        return new self(rtrim(self::environment('FABRICA_URL'), '/'), self::environment('FABRICA_INSTANCE_ID'), self::environment('FABRICA_INSTANCE_CREDENTIAL'));
    }

    private static function environment(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? getenv($key);
        return is_string($value) && $value !== '' ? $value : $default;
    }

    public static function uuid(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $value) === 1;
    }

    private static function opaque(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9_-]{43}$/D', $value) === 1;
    }

    private static function random(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function context(): string
    {
        return hash('sha256', $this->origin . "\n" . $this->credential);
    }

    /** @param array<string,mixed> $session */
    public function begin(array &$session): string
    {
        $state = self::random();
        $verifier = self::random();
        $session['fabrica_pending'] = ['state' => $state, 'verifier' => $verifier, 'expires' => time() + 300, 'context' => $this->context()];
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        return $this->origin . '/api/auth/instance/authorize?' . http_build_query(['instanceId' => $this->instanceId, 'state' => $state, 'challenge' => $challenge], '', '&', PHP_QUERY_RFC3986);
    }

    /** @param array<string,mixed> $session
     *  @param array<string,mixed> $query
     *  @return array{subject:string,username:?string,discordId:?string,lease:string,expires:int,checked:int,context:string}
     */
    public function finish(array &$session, array $query): array
    {
        $pending = $session['fabrica_pending'] ?? null;
        unset($session['fabrica_pending']);
        if (!is_array($pending) || !self::opaque($query['state'] ?? null) || !self::opaque($query['code'] ?? null)
            || !is_string($pending['state'] ?? null) || !is_string($pending['verifier'] ?? null)
            || ($pending['context'] ?? '') !== $this->context() || ($pending['expires'] ?? 0) <= time()
            || !hash_equals($pending['state'], $query['state'])) {
            throw new RuntimeException('Die Anmeldung ist ungültig oder abgelaufen. Bitte beginne erneut.');
        }
        $started = time();
        return $this->login($this->post('redeem', ['code' => $query['code'], 'verifier' => $pending['verifier']]), $started);
    }

    /**
     * The tablet in the game has no browser for the handoff. The game server
     * vouched for the Discord ID, and Fabrica opens a session of its own.
     *
     * @return array{subject:string,username:?string,discordId:?string,lease:string,expires:int,checked:int,context:string}
     */
    public function tablet(string $subject, string $discordId): array
    {
        $started = time();
        return $this->login($this->post('tablet', ['subject' => $subject, 'discordId' => $discordId]), $started);
    }

    /** @param array<string,mixed> $result
     *  @return array{subject:string,username:?string,discordId:?string,lease:string,expires:int,checked:int,context:string}
     */
    private function login(array $result, int $started): array
    {
        if (!$this->validIdentity($result) || !self::opaque($result['lease'] ?? null)) {
            throw new RuntimeException('Die Anmeldung konnte nicht bestätigt werden.');
        }
        $username = $result['username'] ?? null;
        if ($username !== null && (!is_string($username) || trim($username) === '' || mb_strlen($username) > 200)) {
            throw new RuntimeException('Fabrica hat ein ungültiges Benutzerprofil geliefert.');
        }
        $discordId = $result['discordId'] ?? null;
        // Older consoles send nothing, and a bad value must not cost the login.
        if (!is_string($discordId) || preg_match('/^[0-9]{17,20}$/D', $discordId) !== 1) $discordId = null;
        return ['subject' => $result['subject'], 'username' => $username, 'discordId' => $discordId, 'lease' => $result['lease'], 'expires' => (int) strtotime($result['expiresAt']), 'checked' => $started, 'context' => $this->context()];
    }

    /** @param array<string,mixed> $login */
    public function allows(array &$login, int $localUserId): bool
    {
        if (($login['context'] ?? '') !== $this->context() || ($login['localUserId'] ?? null) !== $localUserId
            || !self::uuid($login['subject'] ?? null) || !self::opaque($login['lease'] ?? null)
            || !is_int($login['expires'] ?? null) || $login['expires'] <= time()) return false;
        $started = time();
        $checked = $login['checked'] ?? 0;
        if (is_int($checked) && $checked <= $started && $checked > $started - 60) return true;
        try {
            $result = $this->post('access', ['lease' => $login['lease']]);
            if (($result['allowed'] ?? null) !== true || !$this->validIdentity($result)
                || $result['subject'] !== $login['subject']) return false;
            $login['checked'] = $started;
            $login['expires'] = min($login['expires'], (int) strtotime($result['expiresAt']));
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** @param array<string,mixed> $result */
    private function validIdentity(array $result): bool
    {
        $expiry = is_string($result['expiresAt'] ?? null) ? strtotime($result['expiresAt']) : false;
        return self::uuid($result['subject'] ?? null) && ($result['instanceId'] ?? '') === $this->instanceId
            && $expiry !== false && $expiry > time() && $expiry <= time() + 43200
            && ($result['maxAge'] ?? null) === 60;
    }

    /**
     * Ob die Konsole dieser Instanz KI-Entwürfe erlaubt und wie viel Budget
     * im Monat übrig ist.
     *
     * @return array{available:bool,enabled:bool,budgetCents:int,spentCents:int,remainingCents:int}
     */
    public function aiState(): array
    {
        $data = $this->instance('GET', '/ai', null, 5);

        return [
            'available'      => ($data['available'] ?? null) === true,
            'enabled'        => ($data['enabled'] ?? null) === true,
            'budgetCents'    => (int) ($data['budgetCents'] ?? 0),
            'spentCents'     => (int) ($data['spentCents'] ?? 0),
            'remainingCents' => (int) ($data['remainingCents'] ?? 0),
        ];
    }

    /**
     * Fragenentwürfe aus Material. Die Konsole prüft und begrenzt die
     * Entwürfe schon; ignis prüft sie vor dem Speichern trotzdem selbst.
     *
     * @param array<string,mixed> $request
     * @return array{questions:list<array<string,mixed>>,rejected:int,costCents:int,remainingCents:int}
     */
    public function aiQuestions(array $request): array
    {
        // Ein Durchgang über ein dickes Skript dauert gut eine Minute.
        $data = $this->instance('POST', '/ai/questions', $request, 240);

        return [
            'questions'      => array_values(array_filter((array) ($data['questions'] ?? []), 'is_array')),
            'rejected'       => (int) ($data['rejected'] ?? 0),
            'costCents'      => (int) ($data['costCents'] ?? 0),
            'remainingCents' => (int) ($data['remainingCents'] ?? 0),
        ];
    }

    /**
     * Raum-Schlüssel für Bildschirm teilen über LiveKit. Die Rechte je Rolle
     * legt die Konsole fest, ignis sagt nur, ob es der Host ist. Der Name ist
     * auf 50 Zeichen gekürzt, weil die Konsole bis 100 UTF-16-Einheiten nimmt
     * und ein Zeichen außerhalb der BMP zwei davon belegt.
     *
     * @return array{url:string, token:string}
     */
    public function liveToken(string $room, string $identity, string $name, bool $host): array
    {
        $data = $this->instance('POST', '/livekit/token', [
            'room' => $room, 'identity' => $identity, 'name' => mb_substr($name, 0, 50), 'role' => $host ? 'host' : 'viewer',
        ], 5);
        $url   = $data['url'] ?? null;
        $token = $data['token'] ?? null;
        if (!is_string($url) || $url === '' || !is_string($token) || $token === '') {
            throw new RuntimeException('Die EmergencyForge-Konsole hat keinen Raum-Schlüssel geliefert.');
        }

        return ['url' => $url, 'token' => $token];
    }

    /**
     * @param array<string,mixed>|null $body
     * @return array<string,mixed>
     */
    private function instance(string $method, string $path, ?array $body, int $timeout): array
    {
        try {
            $response = $this->http->request($method, $this->origin . '/api/instance' . $path, [
                'headers' => ['Authorization' => 'Bearer ' . $this->credential, 'Accept' => 'application/json'],
                ...($body !== null ? ['json' => $body] : []),
                'timeout' => $timeout, 'connect_timeout' => 3, 'allow_redirects' => false,
                'verify' => true, 'http_errors' => false,
            ]);
            $data = json_decode($response->getBody()->read(2 * 1024 * 1024), true, 32);
        } catch (Throwable) {
            throw new RuntimeException('Die EmergencyForge-Konsole ist nicht erreichbar.');
        }
        if ($response->getStatusCode() === 200 && is_array($data)) {
            return $data;
        }
        // Absagen formuliert die Konsole für Menschen (Budget aufgebraucht,
        // nicht freigeschaltet); alles andere bleibt allgemein.
        $error = is_array($data) && is_string($data['error'] ?? null) ? mb_substr($data['error'], 0, 300) : '';
        throw new RuntimeException($error !== '' ? $error . '.' : 'Die EmergencyForge-Konsole hat die Anfrage abgelehnt.');
    }

    /** @param array<string,string> $body
     *  @return array<string,mixed>
     */
    private function post(string $path, array $body): array
    {
        try {
            $response = $this->http->request('POST', $this->origin . '/api/auth/instance/' . $path, [
                'headers' => ['Authorization' => 'Bearer ' . $this->credential, 'Accept' => 'application/json'],
                'json' => $body, 'timeout' => 5, 'connect_timeout' => 3, 'allow_redirects' => false,
                'verify' => true, 'http_errors' => false,
            ]);
            if ($response->getStatusCode() !== 200 || $response->getBody()->getSize() > 8192) throw new RuntimeException();
            $data = json_decode($response->getBody()->read(8193), true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($data)) throw new RuntimeException();
            return $data;
        } catch (Throwable) {
            // Guzzle exceptions can contain the credential or one-use code.
            throw new RuntimeException('EmergencyForge ist nicht erreichbar oder hat den Zugang abgelehnt.');
        }
    }
}
