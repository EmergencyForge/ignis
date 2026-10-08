<?php

declare(strict_types=1);

namespace App\Discord;

use App\Security\SecretBox;
use App\Utils\HttpClient;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Der eigene Discord-Bot der Installation: ein Bot-Token aus dem Discord
 * Developer Portal, gesprochen wird nur über die REST-API (kein Gateway,
 * kein dauerhaft laufender Prozess). Er schickt Direktnachrichten, etwa
 * Benachrichtigungen (DiscordNotifier) oder Einladungen aus dem
 * Mitarbeiterprofil.
 *
 * Die Werte stehen in intra_config (Kategorie `discord`, nicht editierbar),
 * bearbeitet werden sie unter Einstellungen › System › Discord-Bot. Name,
 * ID und Avatar-Hash spiegeln, was Discord zuletzt gemeldet hat.
 */
final class DiscordBot
{
    public const API = 'https://discord.com/api/v10';

    /** Benachrichtigungstypen, die ein neu eingerichteter Bot zustellt. */
    public const DEFAULT_DM_TYPES = ['mail', 'system'];

    private const KEYS = [
        'enabled'  => 'DISCORD_BOT_ENABLED',
        'token'    => 'DISCORD_BOT_TOKEN',
        'id'       => 'DISCORD_BOT_ID',
        'name'     => 'DISCORD_BOT_NAME',
        'avatar'   => 'DISCORD_BOT_AVATAR',
        'dm_types' => 'DISCORD_BOT_DM_TYPES',
    ];

    /** @var array{enabled:bool, token:string, token_lost:bool, id:string, name:string, avatar:string, dm_types:list<string>}|null */
    private static ?array $settings = null;

    /** @var (\Closure(string, string, array<string, mixed>|null): array{status:int, body:string}|null)|null */
    private static ?\Closure $transport = null;

    /**
     * @return array{enabled:bool, token:string, token_lost:bool, id:string, name:string, avatar:string, dm_types:list<string>}
     */
    public static function settings(): array
    {
        if (self::$settings !== null) {
            return self::$settings;
        }

        try {
            $rows = Capsule::table('intra_config')->whereIn('config_key', array_values(self::KEYS))->pluck('config_value', 'config_key')->all();
        } catch (\Throwable) {
            $rows = [];
        }
        $value = static fn (string $field): string => trim((string) ($rows[self::KEYS[$field]] ?? ''));

        // Das Token liegt verschlüsselt (SecretBox). Lässt es sich nicht
        // öffnen (Schlüssel verloren), gilt der Bot als ohne Token.
        try {
            $token = SecretBox::decrypt($value('token'));
        } catch (\Throwable) {
            $token = null;
        }

        return self::$settings = [
            'enabled'    => in_array($value('enabled'), ['1', 'true', 'yes'], true),
            'token'      => $token ?? '',
            'token_lost' => $token === null,
            'id'         => $value('id'),
            'name'       => $value('name'),
            'avatar'     => $value('avatar'),
            'dm_types'   => isset($rows[self::KEYS['dm_types']])
                ? array_values(array_filter(array_map('trim', explode(',', $value('dm_types'))), static fn (string $t): bool => $t !== ''))
                : self::DEFAULT_DM_TYPES,
        ];
    }

    /**
     * Schreibt Werte nach intra_config.
     *
     * @param array<string, string|bool|list<string>> $values Feld aus KEYS => Wert
     */
    public static function store(array $values, ?int $userId = null): void
    {
        foreach ($values as $field => $value) {
            if (!isset(self::KEYS[$field])) {
                continue;
            }
            $stored = match (true) {
                is_bool($value)  => $value ? 'true' : 'false',
                is_array($value) => implode(',', $value),
                $field === 'token' => SecretBox::encrypt($value),
                default          => $value,
            };
            Capsule::table('intra_config')->where('config_key', self::KEYS[$field])->update([
                'config_value' => $stored,
                'updated_by'   => $userId,
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
        }
        self::$settings = null;
    }

    /** Eingeschaltet und mit Token: dann gehen Nachrichten raus. */
    public static function active(): bool
    {
        $settings = self::settings();

        return $settings['enabled'] && $settings['token'] !== '';
    }

    /** Liest die Werte beim nächsten Zugriff neu (nach dem Speichern, in Tests). */
    public static function forget(): void
    {
        self::$settings = null;
    }

    /**
     * Ersetzt die HTTP-Anbindung, für Tests. Bekommt Methode, Pfad unter
     * der API und den JSON-Body und liefert Status und Antworttext; null
     * spielt einen Netzfehler.
     *
     * @param (\Closure(string, string, array<string, mixed>|null): array{status:int, body:string}|null)|null $transport
     */
    public static function fake(?\Closure $transport): void
    {
        self::$transport = $transport;
    }

    /** Eine Discord-Nutzer-ID (Snowflake, 17 bis 20 Ziffern)? */
    public static function snowflake(?string $value): bool
    {
        return $value !== null && preg_match('/^\d{17,20}$/', $value) === 1;
    }

    /** Link, mit dem jemand mit „Server verwalten“ den Bot auf seinen Server holt. */
    public static function inviteUrl(string $applicationId): string
    {
        return 'https://discord.com/oauth2/authorize?client_id=' . rawurlencode($applicationId) . '&scope=bot&permissions=0';
    }

    /** Das Profilbild des Bots, ohne eigenes Bild Discords Standardbild. */
    public static function avatarUrl(string $id, string $avatar, int $size = 128): ?string
    {
        if (!self::snowflake($id)) {
            return null;
        }
        if ($avatar !== '' && preg_match('/^(a_)?[0-9a-f]{32}$/', $avatar) === 1) {
            return 'https://cdn.discordapp.com/avatars/' . $id . '/' . $avatar . '.png?size=' . $size;
        }

        return 'https://cdn.discordapp.com/embed/avatars/' . (((int) $id >> 22) % 6) . '.png';
    }

    // ── API ────────────────────────────────────────────────────

    /**
     * Der Bot hinter einem Token: prüft das Token und liefert ID, Name und
     * Avatar-Hash.
     *
     * @return array{id:string, name:string, avatar:string}
     */
    public static function me(string $token): array
    {
        return self::profile(self::call($token, 'GET', '/users/@me'));
    }

    /**
     * Ändert Name und/oder Profilbild des Bots bei Discord. Den Namen lässt
     * Discord nur ein paar Mal pro Stunde ändern.
     *
     * @param array{username?:string, avatar?:string} $changes avatar als data:-URI
     * @return array{id:string, name:string, avatar:string}
     */
    public static function updateProfile(string $token, array $changes): array
    {
        return self::profile(self::call($token, 'PATCH', '/users/@me', $changes));
    }

    /**
     * Schickt einer Person eine Direktnachricht. Das geht nur, wenn sie
     * einen Server mit dem Bot teilt und DMs von Servermitgliedern zulässt.
     *
     * @param array<string, mixed> $message Discord-Nachricht (content, embeds, components)
     */
    public static function sendDirectMessage(string $discordId, array $message, ?string $token = null): void
    {
        if (!self::snowflake($discordId)) {
            throw new DiscordBotException('Keine gültige Discord-ID.');
        }
        $token ??= self::settings()['token'];
        if ($token === '') {
            throw new DiscordBotException('Der Discord-Bot ist nicht eingerichtet.');
        }

        $channel = self::call($token, 'POST', '/users/@me/channels', ['recipient_id' => $discordId]);
        $channelId = (string) ($channel['id'] ?? '');
        if (!self::snowflake($channelId)) {
            throw new DiscordBotException('Discord hat keinen Direktnachrichten-Kanal geöffnet.');
        }

        self::call($token, 'POST', '/channels/' . $channelId . '/messages', $message + ['allowed_mentions' => ['parse' => []]]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{id:string, name:string, avatar:string}
     */
    private static function profile(array $data): array
    {
        return [
            'id'     => (string) ($data['id'] ?? ''),
            'name'   => (string) ($data['global_name'] ?? $data['username'] ?? ''),
            'avatar' => (string) ($data['avatar'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private static function call(string $token, string $method, string $path, ?array $body = null): array
    {
        if (self::$transport !== null) {
            $response = (self::$transport)($method, $path, $body);
        } else {
            $response = HttpClient::request(self::API . $path, [
                'method'  => $method,
                'headers' => array_merge([
                    'Authorization: Bot ' . $token,
                    'User-Agent: DiscordBot (https://github.com/EmergencyForge/ignis, 1)',
                ], $body !== null ? ['Content-Type: application/json'] : []),
                'body'    => $body !== null ? json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'timeout' => 10,
            ]);
        }
        if ($response === null) {
            throw new DiscordBotException('Discord ist nicht erreichbar.');
        }

        $status = (int) $response['status'];
        $data   = json_decode($response['body'], true);
        $data   = is_array($data) ? $data : [];
        if ($status >= 200 && $status < 300) {
            return $data;
        }

        $code = (int) ($data['code'] ?? 0);
        throw new DiscordBotException(match (true) {
            $status === 401          => 'Discord lehnt das Token ab. Bitte im Developer Portal ein neues Token erzeugen und hier eintragen.',
            $code === DiscordBotException::CANNOT_DM => 'Discord stellt keine Direktnachricht zu. Die Person muss einen Server mit dem Bot teilen und Direktnachrichten von Servermitgliedern erlauben.',
            $status === 429          => 'Discord bremst gerade (Rate-Limit). Bitte gleich noch einmal versuchen.',
            $status >= 500           => 'Discord hat gerade eine Störung (' . $status . ').',
            default                  => 'Discord meldet: ' . self::reason($data, $status),
        }, $status, $code);
    }

    /** @param array<string, mixed> $data */
    private static function reason(array $data, int $status): string
    {
        // Feldfehler stecken verschachtelt in `errors`, etwa beim Namen.
        $details = [];
        $errors  = is_array($data['errors'] ?? null) ? $data['errors'] : [];
        array_walk_recursive($errors, static function (mixed $value, string|int $key) use (&$details): void {
            if ($key === 'message' && is_string($value)) {
                $details[] = $value;
            }
        });
        $message = $details !== [] ? implode(' ', array_unique($details)) : (string) ($data['message'] ?? '');

        return ($message !== '' ? $message : 'Fehler') . ' (' . $status . ')';
    }
}
