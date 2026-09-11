<?php

namespace App\Federation;

use App\Models\FederationLink;
use App\Models\FederationSyncLog;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Handles instance pairing: key generation, handshake, and link management.
 */
class FederationPairingService
{
    /**
     * Ensure this instance has a UUID. Generates one on first call.
     */
    public function ensureInstanceId(): string
    {
        $currentId = FederationMiddleware::config('FEDERATION_INSTANCE_ID');

        if (!empty($currentId)) {
            return $currentId;
        }

        $uuid = self::generateUuid();

        Capsule::table('intra_config')
            ->where('config_key', 'FEDERATION_INSTANCE_ID')
            ->update(['config_value' => $uuid]);

        if (!defined('FEDERATION_INSTANCE_ID')) {
            define('FEDERATION_INSTANCE_ID', $uuid);
        }

        return $uuid;
    }

    /**
     * Generate a connection token that another instance can use to pair with us.
     *
     * @return array{token: string, api_key: string} The base64 token and the raw API key
     */
    public function generateConnectionToken(): array
    {
        $instanceId = $this->ensureInstanceId();
        $instanceName = FederationMiddleware::config('FEDERATION_INSTANCE_NAME');
        $instanceUrl = defined('SYSTEM_URL') ? SYSTEM_URL : '';

        $apiKey = self::generateApiKey();

        $payload = [
            'url' => rtrim($instanceUrl, '/'),
            'instance_id' => $instanceId,
            'instance_name' => $instanceName ?: (defined('SYSTEM_NAME') ? SYSTEM_NAME : 'ıgnıs'),
            'api_key' => $apiKey,
        ];

        $token = base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE));

        return ['token' => $token, 'api_key' => $apiKey];
    }

    /**
     * Parse a connection token from another instance.
     *
     * @return array{url: string, instance_id: string, instance_name: string, api_key: string}|null
     */
    public static function parseConnectionToken(string $token): ?array
    {
        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return null;
        }

        $data = json_decode($decoded, true);
        if (!is_array($data)) {
            return null;
        }

        $required = ['url', 'instance_id', 'instance_name', 'api_key'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return null;
            }
        }

        return $data;
    }

    /**
     * Complete pairing: store the link to a remote instance.
     *
     * @param array  $remoteInfo     Parsed connection token data
     * @param string $apiKeyOutgoing The key we will use to authenticate with them
     * @param string $apiKeyIncoming The key they must use to authenticate with us
     * @return int The new link ID
     */
    public function createLink(array $remoteInfo, string $apiKeyOutgoing, string $apiKeyIncoming): int
    {
        // Check if already linked
        if (FederationLink::where('instance_id', $remoteInfo['instance_id'])->exists()) {
            throw new \RuntimeException('Diese Instanz ist bereits verbunden');
        }

        $link = FederationLink::create([
            'instance_id' => $remoteInfo['instance_id'],
            'instance_name' => $remoteInfo['instance_name'],
            'instance_url' => rtrim($remoteInfo['url'], '/'),
            'api_key_outgoing' => $apiKeyOutgoing,
            'api_key_incoming' => $apiKeyIncoming,
            'is_active' => 1,
        ]);

        return (int) $link->id;
    }

    /**
     * Update sync permissions for a link.
     *
     * @param int   $linkId
     * @param array $settings Keys: consume_personnel, consume_enotf, consume_fire,
     *                        provide_personnel, provide_enotf, provide_fire
     */
    public function updateLinkSettings(int $linkId, array $settings): bool
    {
        $allowed = [
            'consume_personnel', 'consume_enotf', 'consume_fire',
            'provide_personnel', 'provide_enotf', 'provide_fire',
            'sync_interval_minutes', 'is_active',
        ];

        $sets = [];

        foreach ($settings as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $sets[$key] = $value;
            }
        }

        if (empty($sets)) {
            return false;
        }

        FederationLink::where('id', $linkId)->update($sets);

        return true;
    }

    /**
     * Delete a link and its cached data.
     */
    public function deleteLink(int $linkId): bool
    {
        // Get instance_id for cache cleanup
        $link = FederationLink::find($linkId);

        if (!$link) {
            return false;
        }

        $instanceId = $link->instance_id;

        Capsule::connection()->transaction(function () use ($linkId, $instanceId): void {
            // Delete cached data
            $tables = [
                'intra_federation_cache_personnel',
                'intra_federation_cache_enotf',
                'intra_federation_cache_fire',
            ];
            foreach ($tables as $table) {
                Capsule::table($table)->where('source_instance_id', $instanceId)->delete();
            }

            // Delete sync log
            FederationSyncLog::where('link_id', $linkId)->delete();

            // Delete the link itself
            FederationLink::where('id', $linkId)->delete();
        });

        return true;
    }

    /**
     * Get all linked instances.
     *
     * @return array[]
     */
    public function getAllLinks(): array
    {
        return FederationLink::orderBy('instance_name', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * Get a single link by ID.
     */
    public function getLink(int $linkId): ?array
    {
        $link = FederationLink::find($linkId);
        return $link ? $link->toArray() : null;
    }

    /**
     * Perform a handshake with a remote instance to verify connectivity.
     *
     * @param string $url    Remote instance base URL
     * @param string $apiKey API key to authenticate with
     * @return array Remote instance info on success
     */
    public function performHandshake(string $url, string $apiKey): array
    {
        $endpoint = rtrim($url, '/') . '/api/federation/handshake.php';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'X-Federation-Key: ' . $apiKey,
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('Verbindung zur Remote-Instanz fehlgeschlagen: ' . $curlError);
        }

        $data = json_decode($response, true);

        if (!is_array($data) || !($data['success'] ?? false)) {
            $error = $data['error'] ?? 'Unbekannter Fehler';
            throw new \RuntimeException("Handshake fehlgeschlagen: {$error}");
        }

        return $data;
    }

    /**
     * Generate a cryptographically secure API key.
     */
    public static function generateApiKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Generate a v4 UUID.
     */
    public static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant RFC 4122

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
