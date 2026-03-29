<?php

namespace App\Federation;

use PDO;
use PDOException;

/**
 * Handles instance pairing: key generation, handshake, and link management.
 */
class FederationPairingService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Ensure this instance has a UUID. Generates one on first call.
     */
    public function ensureInstanceId(): string
    {
        $currentId = defined('FEDERATION_INSTANCE_ID') ? FEDERATION_INSTANCE_ID : '';

        if (!empty($currentId)) {
            return $currentId;
        }

        $uuid = self::generateUuid();

        $stmt = $this->pdo->prepare("
            UPDATE intra_config SET config_value = ? WHERE config_key = 'FEDERATION_INSTANCE_ID'
        ");
        $stmt->execute([$uuid]);

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
        $instanceName = defined('FEDERATION_INSTANCE_NAME') ? FEDERATION_INSTANCE_NAME : '';
        $instanceUrl = defined('SYSTEM_URL') ? SYSTEM_URL : '';

        $apiKey = self::generateApiKey();

        $payload = [
            'url' => rtrim($instanceUrl, '/'),
            'instance_id' => $instanceId,
            'instance_name' => $instanceName ?: (defined('SYSTEM_NAME') ? SYSTEM_NAME : 'intraRP'),
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
        $stmt = $this->pdo->prepare("
            SELECT id FROM intra_federation_links WHERE instance_id = ?
        ");
        $stmt->execute([$remoteInfo['instance_id']]);

        if ($stmt->fetch()) {
            throw new \RuntimeException('Diese Instanz ist bereits verbunden');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO intra_federation_links
            (instance_id, instance_name, instance_url, api_key_outgoing, api_key_incoming, is_active)
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $remoteInfo['instance_id'],
            $remoteInfo['instance_name'],
            rtrim($remoteInfo['url'], '/'),
            $apiKeyOutgoing,
            $apiKeyIncoming,
        ]);

        return (int) $this->pdo->lastInsertId();
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
        $params = [];

        foreach ($settings as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $sets[] = "`{$key}` = ?";
                $params[] = $value;
            }
        }

        if (empty($sets)) {
            return false;
        }

        $params[] = $linkId;

        $stmt = $this->pdo->prepare("
            UPDATE intra_federation_links SET " . implode(', ', $sets) . " WHERE id = ?
        ");

        return $stmt->execute($params);
    }

    /**
     * Delete a link and its cached data.
     */
    public function deleteLink(int $linkId): bool
    {
        // Get instance_id for cache cleanup
        $stmt = $this->pdo->prepare("SELECT instance_id FROM intra_federation_links WHERE id = ?");
        $stmt->execute([$linkId]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$link) {
            return false;
        }

        $instanceId = $link['instance_id'];

        $this->pdo->beginTransaction();
        try {
            // Delete cached data
            $tables = [
                'intra_federation_cache_personnel',
                'intra_federation_cache_enotf',
                'intra_federation_cache_fire',
            ];
            foreach ($tables as $table) {
                $this->pdo->prepare("DELETE FROM `{$table}` WHERE source_instance_id = ?")
                    ->execute([$instanceId]);
            }

            // Delete sync log
            $this->pdo->prepare("DELETE FROM intra_federation_sync_log WHERE link_id = ?")
                ->execute([$linkId]);

            // Delete the link itself
            $this->pdo->prepare("DELETE FROM intra_federation_links WHERE id = ?")
                ->execute([$linkId]);

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Get all linked instances.
     *
     * @return array[]
     */
    public function getAllLinks(): array
    {
        $stmt = $this->pdo->query("
            SELECT * FROM intra_federation_links ORDER BY instance_name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a single link by ID.
     */
    public function getLink(int $linkId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM intra_federation_links WHERE id = ?");
        $stmt->execute([$linkId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
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
