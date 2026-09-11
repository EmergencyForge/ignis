<?php

namespace App\Helpers;

use League\OAuth2\Client\Provider\GenericProvider;

class DiscordOAuth
{
    /**
     * Validate that Discord OAuth credentials are configured
     * 
     * @return array{clientId: string, clientSecret: string}|null Returns credentials if valid, null otherwise
     */
    public static function getCredentials(): ?array
    {
        $clientId = $_ENV['DISCORD_CLIENT_ID'] ?? '';
        $clientSecret = $_ENV['DISCORD_CLIENT_SECRET'] ?? '';

        if (empty($clientId) || empty($clientSecret)) {
            return null;
        }

        return [
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
        ];
    }

    /**
     * Validate Discord OAuth credentials and exit with error message if not configured
     * 
     * @return array{clientId: string, clientSecret: string} The validated credentials
     */
    public static function validateCredentials(): array
    {
        $credentials = self::getCredentials();

        if ($credentials === null) {
            exit('Discord OAuth is not configured. Please set DISCORD_CLIENT_ID and DISCORD_CLIENT_SECRET in your .env file.');
        }

        return $credentials;
    }

    /**
     * Die Adresse, an die Discord nach der Anmeldung zurückschickt.
     *
     * Normalerweise leitet ignis sie aus dem laufenden Request ab, das spart
     * jeder Installation eine Variable. Hinter einem Reverse Proxy kann diese
     * Herleitung danebenliegen, und der Fehler ist still: die Instanz läuft,
     * /healthz bleibt grün, und erst der erste Mensch, der auf Anmelden
     * klickt, sieht von Discord eine Fehlermeldung über eine unbekannte
     * Redirect-URI.
     *
     * DISCORD_REDIRECT_URI schlägt die Herleitung deshalb. Wer sie setzt,
     * braucht sich auf keine Kopfzeile zu verlassen — der Wert wandert
     * unverändert an Discord und muss genauso im Developer Portal stehen.
     */
    public static function redirectUri(string $redirectPath): string
    {
        $configured = trim(env_value('DISCORD_REDIRECT_URI') ?? '');

        return $configured !== ''
            ? $configured
            : ProtocolDetection::buildRedirectUri($redirectPath);
    }

    /**
     * Create a Discord OAuth provider instance
     *
     * @param string $redirectPath The path for the OAuth redirect (e.g., 'auth/callback.php')
     * @return GenericProvider The configured OAuth provider
     */
    public static function createProvider(string $redirectPath): GenericProvider
    {
        $credentials = self::validateCredentials();

        $config = [
            'clientId'                => $credentials['clientId'],
            'clientSecret'            => $credentials['clientSecret'],
            'redirectUri'             => self::redirectUri($redirectPath),
            'urlAuthorize'            => 'https://discord.com/api/oauth2/authorize',
            'urlAccessToken'          => 'https://discord.com/api/oauth2/token',
            'urlResourceOwnerDetails' => 'https://discord.com/api/users/@me',
        ];

        // ONLY for local development: Disable SSL verification if CA bundle is not configured
        // WARNING: Never use this in production!
        if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
            $config['collaborators'] = [
                new \League\OAuth2\Client\Grant\AuthorizationCode(),
            ];
            // This will be passed to Guzzle
            $config['verify'] = false;
        }

        return new GenericProvider($config);
    }
}
