<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\FabricaClient;
use App\Auth\FabricaIdentity;
use App\Config\ConfigManager;
use App\Helpers\ProtocolDetection;
use App\Http\ErrorPage;
use App\Models\User;
use App\Session\SessionManager;
use App\Utils\AuditLogger;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use Throwable;

/**
 * Anmeldung über ef_bridge (vormals ignisTab). Discord-OAuth läuft im CEF von FiveM nicht, also
 * holt der FiveM-Server mit dem API-Schlüssel einen Einmal-Token für die
 * Discord-ID des Spielers, und das Tablet öffnet damit /auth/tablet.
 *
 * Gespeichert wird nur der SHA-256 des Tokens; der Token selbst gehört in
 * kein Log.
 *
 * Hängt ignis an der zentralen Anmeldung, braucht auch das Tablet dort einen
 * Zugang, sonst wirft FabricaSession die Sitzung beim nächsten Aufruf hinaus.
 * Die Discord-ID steht deshalb an der Tokenzeile: beim Rückfall über den
 * discordtag trägt das Konto selbst noch keine.
 */
final class TabletLoginController
{
    private const TABLE = 'intra_tablet_login_tokens';
    private const TTL = 60;
    private const PER_MINUTE = 10;

    public function __construct(private readonly ?FabricaClient $client = null) {}

    /** POST /api/tablet/login-token (ApiKeyMiddleware), Body {"discord_id": "…"} */
    public function issue(Request $request): Response
    {
        if (!self::enabled()) {
            return Response::json(['success' => false, 'error' => 'disabled'], 404);
        }
        // Der Localhost-Bypass der API (Development) genügt hier nicht: der
        // Token meldet jedes aktive Konto an, auch Admins.
        if ($request->attribute('api_auth') !== 'key') {
            return Response::json(['success' => false, 'error' => 'api_key_required'], 403);
        }

        $discordId = ($request->json() ?? [])['discord_id'] ?? null;
        if (!is_string($discordId) || preg_match('/^[0-9]{17,20}$/D', $discordId) !== 1) {
            return Response::json(['success' => false, 'error' => 'invalid_discord_id'], 422);
        }

        // Nur bestehende, aktive Konten; hier entsteht nie ein neues. Die
        // Discord-ID ist in intra_users nicht eindeutig: teilen sich zwei
        // aktive Konten eine, bekommt keines einen Token. Hat kein Konto
        // die Discord-ID (zentrale Anmeldung), zählt der Mitarbeiter mit
        // diesem discordtag und sein verknüpftes Konto (ADR-0002, Punkt 8).
        $userIds = User::query()->where('discord_id', $discordId)->where('is_active', 1)->limit(2)->pluck('id')->all();
        if ($userIds === []) {
            $people = Capsule::table('intra_mitarbeiter')->where('discordtag', $discordId)->limit(2)->pluck('id')->all();
            if (count($people) > 1) {
                return Response::json(['success' => false, 'error' => 'ambiguous_user'], 409);
            }
            if ($people !== []) {
                $userIds = User::query()->where('aktenid', $people[0])->where('is_active', 1)->pluck('id')->all();
            }
        }
        if ($userIds === []) {
            return Response::json(['success' => false, 'error' => 'unknown_user'], 404);
        }
        if (count($userIds) > 1) {
            return Response::json(['success' => false, 'error' => 'ambiguous_user'], 409);
        }
        $userId = $userIds[0];

        $now = time();
        Capsule::table(self::TABLE)->where('expires_at', '<', date('Y-m-d H:i:s', $now))->delete();

        $recent = Capsule::table(self::TABLE)
            ->where('user_id', $userId)
            ->where('created_at', '>', date('Y-m-d H:i:s', $now - 60))
            ->count();
        if ($recent >= self::PER_MINUTE) {
            return Response::json(['success' => false, 'error' => 'rate_limited'], 429)->withHeader('Retry-After', '60');
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        Capsule::table(self::TABLE)->insert([
            'user_id'    => $userId,
            'discord_id' => $discordId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', $now + self::TTL),
            'created_at' => date('Y-m-d H:i:s', $now),
        ]);

        return Response::json([
            'success'    => true,
            'token'      => $token,
            'expires_in' => self::TTL,
            'login_url'  => ProtocolDetection::buildPublicUrl('auth/tablet'),
        ])->withHeader('Cache-Control', 'no-store');
    }

    /** GET /auth/tablet?token=… */
    public function login(Request $request): Response
    {
        if (!self::enabled()) {
            return ErrorPage::notFound($request->path);
        }

        $token = $request->query['token'] ?? null;
        SessionManager::start();
        $user = is_string($token) && $token !== '' ? $this->redeem($token) : null;

        if ($user === null || !$this->openFabricaLogin($user, (string) $token)) {
            // Bewusst ohne Grund: ob abgelaufen, benutzt oder abgelehnt, verrät die Seite nicht.
            SessionManager::logoutUser();
            SessionManager::setRegistrationError('Die Anmeldung über das Tablet hat nicht geklappt. Öffne das Tablet bitte erneut.');
            return self::private(Response::redirect(BASE_PATH . 'login'));
        }

        SessionManager::loginAccount($user->toArray());
        (new AuditLogger())->log((int) $user->id, 'Anmeldung über ef_bridge', null, 'System', 0, ['user_id' => (int) $user->id]);

        return self::private(Response::redirect(BASE_PATH . 'index'));
    }

    /** Markiert den Token atomar als benutzt; nur wer die Zeile bekommt, meldet an. */
    private function redeem(string $token): ?User
    {
        $hash = hash('sha256', $token);
        $now = date('Y-m-d H:i:s');

        $claimed = Capsule::table(self::TABLE)
            ->where('token_hash', $hash)
            ->whereNull('used_at')
            ->where('expires_at', '>', $now)
            ->update(['used_at' => $now]);
        if ($claimed !== 1) {
            return null;
        }

        $userId = Capsule::table(self::TABLE)->where('token_hash', $hash)->value('user_id');

        return User::query()->whereKey($userId)->where('is_active', 1)->first();
    }

    private function openFabricaLogin(User $user, string $token): bool
    {
        if (!FabricaClient::enabled()) {
            return true;
        }
        try {
            $client = $this->client ?? FabricaClient::fromEnvironment();
            $subject = FabricaIdentity::subject($client->origin, (int) $user->id);
            $discordId = Capsule::table(self::TABLE)->where('token_hash', hash('sha256', $token))->value('discord_id');
            if ($subject === null || !is_string($discordId)) {
                return false;
            }
            $_SESSION['fabrica_login'] = $client->tablet($subject, $discordId) + ['localUserId' => (int) $user->id];

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private static function enabled(): bool
    {
        return (new ConfigManager())->get('TABLET_LOGIN_ENABLED', false) === true;
    }

    private static function private(Response $response): Response
    {
        return $response->withHeader('Cache-Control', 'no-store')->withHeader('Referrer-Policy', 'no-referrer');
    }
}
