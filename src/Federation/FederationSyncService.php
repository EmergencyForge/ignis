<?php

namespace App\Federation;

use PDO;
use PDOException;

/**
 * Handles pulling data from linked instances and caching it locally.
 */
class FederationSyncService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Sync personnel from a linked instance.
     *
     * @param int $linkId The intra_federation_links.id to sync from
     * @return array{success: bool, records: int, error?: string}
     */
    public function syncPersonnel(int $linkId): array
    {
        $link = $this->getActiveLink($linkId);

        if (!$link) {
            return ['success' => false, 'records' => 0, 'error' => 'Verbindung nicht gefunden oder inaktiv'];
        }

        if (!$link['consume_personnel']) {
            return ['success' => false, 'records' => 0, 'error' => 'Personal-Abruf ist für diese Verbindung nicht aktiviert'];
        }

        $startTime = microtime(true);
        $totalRecords = 0;

        try {
            $page = 1;
            $hasMore = true;

            while ($hasMore) {
                $endpoint = rtrim($link['instance_url'], '/') . '/api/federation/personnel.php?page=' . $page . '&per_page=100';
                $data = $this->fetchFromRemote($endpoint, $link['api_key_outgoing']);

                if (!$data['success']) {
                    throw new \RuntimeException($data['error'] ?? 'Unbekannter Fehler');
                }

                $personnel = $data['data'] ?? [];

                foreach ($personnel as $person) {
                    $this->upsertPersonnelCache($link['instance_id'], $person);
                    $totalRecords++;
                }

                $pagination = $data['pagination'] ?? [];
                $hasMore = $page < ($pagination['total_pages'] ?? 1);
                $page++;
            }

            // Remove personnel that no longer exist on the remote side
            // (they weren't returned in this full sync)
            $this->cleanStalePersonnel($link['instance_id'], $totalRecords);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->logSync($linkId, 'personnel', 'success', $totalRecords, $durationMs);
            $this->updateLinkSyncStatus($linkId, 'success');

            return ['success' => true, 'records' => $totalRecords];
        } catch (\Exception $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->logSync($linkId, 'personnel', 'error', $totalRecords, $durationMs, $e->getMessage());
            $this->updateLinkSyncStatus($linkId, 'error', $e->getMessage());

            return ['success' => false, 'records' => $totalRecords, 'error' => $e->getMessage()];
        }
    }

    /**
     * Upsert a single personnel record into the cache.
     */
    private function upsertPersonnelCache(string $sourceInstanceId, array $person): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO intra_federation_cache_personnel
            (source_instance_id, remote_id, fullname, dienstnr, dienstgrad_name, dienstgrad_badge,
             quali_rd, quali_fw, quali_fd, cached_data, cached_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                fullname = VALUES(fullname),
                dienstnr = VALUES(dienstnr),
                dienstgrad_name = VALUES(dienstgrad_name),
                dienstgrad_badge = VALUES(dienstgrad_badge),
                quali_rd = VALUES(quali_rd),
                quali_fw = VALUES(quali_fw),
                quali_fd = VALUES(quali_fd),
                cached_data = VALUES(cached_data),
                cached_at = NOW()
        ");

        $stmt->execute([
            $sourceInstanceId,
            (int) $person['id'],
            $person['fullname'] ?? null,
            $person['dienstnr'] ?? null,
            $person['dienstgrad_name'] ?? null,
            $person['dienstgrad_badge'] ?? null,
            $person['quali_rd'] ?? null,
            $person['quali_fw'] ?? null,
            $person['quali_fd'] ?? null,
            json_encode($person, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Remove stale cache entries (personnel that were deleted on the remote side).
     * Only runs if we got a reasonable number of records.
     */
    private function cleanStalePersonnel(string $sourceInstanceId, int $freshCount): void
    {
        if ($freshCount === 0) {
            return; // Don't delete cache if we got nothing (might be an error)
        }

        // Delete entries that weren't updated in this sync cycle (cached_at is older)
        $stmt = $this->pdo->prepare("
            DELETE FROM intra_federation_cache_personnel
            WHERE source_instance_id = ?
            AND cached_at < DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        $stmt->execute([$sourceInstanceId]);
    }

    /**
     * Fetch JSON data from a remote federation endpoint.
     *
     * @return array Decoded JSON response
     */
    private function fetchFromRemote(string $url, string $apiKey): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
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
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('cURL-Fehler: ' . $error);
        }

        if ($httpCode >= 400) {
            $data = json_decode($response, true);
            $msg = $data['error'] ?? "HTTP {$httpCode}";
            throw new \RuntimeException("Remote-Fehler: {$msg}");
        }

        $data = json_decode($response, true);

        if (!is_array($data)) {
            throw new \RuntimeException('Ungültige JSON-Antwort von Remote-Instanz');
        }

        return $data;
    }

    /**
     * Log a sync operation.
     */
    private function logSync(int $linkId, string $type, string $status, int $records, int $durationMs, ?string $error = null): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO intra_federation_sync_log
                (link_id, sync_type, status, records_synced, duration_ms, error_message)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$linkId, $type, $status, $records, $durationMs, $error]);
        } catch (PDOException $e) {
            // Logging failure should not break the sync
        }
    }

    /**
     * Update the link's last sync status.
     */
    private function updateLinkSyncStatus(int $linkId, string $status, ?string $error = null): void
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE intra_federation_links
                SET last_sync_at = NOW(), last_sync_status = ?, last_sync_error = ?
                WHERE id = ?
            ");
            $stmt->execute([$status, $error, $linkId]);
        } catch (PDOException $e) {
            // Non-critical
        }
    }

    /**
     * Get an active link by ID.
     */
    private function getActiveLink(int $linkId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM intra_federation_links WHERE id = ? AND is_active = 1");
        $stmt->execute([$linkId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Sync eNOTF protocols from a linked instance (delta sync).
     *
     * @param int $linkId The intra_federation_links.id to sync from
     * @return array{success: bool, records: int, error?: string}
     */
    public function syncEnotf(int $linkId): array
    {
        $link = $this->getActiveLink($linkId);

        if (!$link) {
            return ['success' => false, 'records' => 0, 'error' => 'Verbindung nicht gefunden oder inaktiv'];
        }

        if (!$link['consume_enotf']) {
            return ['success' => false, 'records' => 0, 'error' => 'eNOTF-Abruf ist für diese Verbindung nicht aktiviert'];
        }

        $startTime = microtime(true);
        $totalRecords = 0;

        try {
            // Get last sync cursor for delta sync
            $since = $this->getLastSyncCursor($linkId, 'enotf');

            $page = 1;
            $hasMore = true;

            while ($hasMore) {
                $url = rtrim($link['instance_url'], '/') . '/api/federation/enotf.php?page=' . $page . '&per_page=50';
                if ($since) {
                    $url .= '&since=' . urlencode($since);
                }

                $data = $this->fetchFromRemote($url, $link['api_key_outgoing']);

                if (!$data['success']) {
                    throw new \RuntimeException($data['error'] ?? 'Unbekannter Fehler');
                }

                $protocols = $data['data'] ?? [];

                foreach ($protocols as $protocol) {
                    $this->upsertEnotfCache($link['instance_id'], $protocol);
                    $totalRecords++;
                }

                // Update cursor from response
                if (!empty($data['sync_cursor'])) {
                    $since = $data['sync_cursor'];
                }

                $pagination = $data['pagination'] ?? [];
                $hasMore = $page < ($pagination['total_pages'] ?? 1);
                $page++;
            }

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->logSync($linkId, 'enotf', 'success', $totalRecords, $durationMs);
            $this->updateLinkSyncStatus($linkId, 'success');

            return ['success' => true, 'records' => $totalRecords];
        } catch (\Exception $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->logSync($linkId, 'enotf', 'error', $totalRecords, $durationMs, $e->getMessage());
            $this->updateLinkSyncStatus($linkId, 'error', $e->getMessage());

            return ['success' => false, 'records' => $totalRecords, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sync fire incidents from a linked instance (delta sync).
     *
     * @param int $linkId The intra_federation_links.id to sync from
     * @return array{success: bool, records: int, error?: string}
     */
    public function syncFireIncidents(int $linkId): array
    {
        $link = $this->getActiveLink($linkId);

        if (!$link) {
            return ['success' => false, 'records' => 0, 'error' => 'Verbindung nicht gefunden oder inaktiv'];
        }

        if (!$link['consume_fire']) {
            return ['success' => false, 'records' => 0, 'error' => 'Einsatz-Abruf ist für diese Verbindung nicht aktiviert'];
        }

        $startTime = microtime(true);
        $totalRecords = 0;

        try {
            $since = $this->getLastSyncCursor($linkId, 'fire');

            $page = 1;
            $hasMore = true;

            while ($hasMore) {
                $url = rtrim($link['instance_url'], '/') . '/api/federation/fire-incidents.php?page=' . $page . '&per_page=50';
                if ($since) {
                    $url .= '&since=' . urlencode($since);
                }

                $data = $this->fetchFromRemote($url, $link['api_key_outgoing']);

                if (!$data['success']) {
                    throw new \RuntimeException($data['error'] ?? 'Unbekannter Fehler');
                }

                $incidents = $data['data'] ?? [];

                foreach ($incidents as $incident) {
                    $this->upsertFireCache($link['instance_id'], $incident);
                    $totalRecords++;
                }

                if (!empty($data['sync_cursor'])) {
                    $since = $data['sync_cursor'];
                }

                $pagination = $data['pagination'] ?? [];
                $hasMore = $page < ($pagination['total_pages'] ?? 1);
                $page++;
            }

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->logSync($linkId, 'fire', 'success', $totalRecords, $durationMs);
            $this->updateLinkSyncStatus($linkId, 'success');

            return ['success' => true, 'records' => $totalRecords];
        } catch (\Exception $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->logSync($linkId, 'fire', 'error', $totalRecords, $durationMs, $e->getMessage());
            $this->updateLinkSyncStatus($linkId, 'error', $e->getMessage());

            return ['success' => false, 'records' => $totalRecords, 'error' => $e->getMessage()];
        }
    }

    /**
     * Upsert a single eNOTF protocol into the cache.
     */
    private function upsertEnotfCache(string $sourceInstanceId, array $protocol): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO intra_federation_cache_enotf
            (source_instance_id, remote_id, cached_data, protocol_date, cached_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                cached_data = VALUES(cached_data),
                protocol_date = VALUES(protocol_date),
                cached_at = NOW()
        ");

        $protocolDate = $protocol['sendezeit'] ?? $protocol['edatum'] ?? null;

        $stmt->execute([
            $sourceInstanceId,
            (int) $protocol['id'],
            json_encode($protocol, JSON_UNESCAPED_UNICODE),
            $protocolDate,
        ]);
    }

    /**
     * Upsert a single fire incident into the cache.
     */
    private function upsertFireCache(string $sourceInstanceId, array $incident): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO intra_federation_cache_fire
            (source_instance_id, remote_id, incident_number, cached_data, incident_date, cached_at)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                incident_number = VALUES(incident_number),
                cached_data = VALUES(cached_data),
                incident_date = VALUES(incident_date),
                cached_at = NOW()
        ");

        $stmt->execute([
            $sourceInstanceId,
            (int) $incident['id'],
            $incident['incident_number'] ?? null,
            json_encode($incident, JSON_UNESCAPED_UNICODE),
            $incident['created_at'] ?? null,
        ]);
    }

    /**
     * Get the last sync cursor (latest synced timestamp) for delta sync.
     */
    private function getLastSyncCursor(int $linkId, string $type): ?string
    {
        $table = match ($type) {
            'enotf' => 'intra_federation_cache_enotf',
            'fire' => 'intra_federation_cache_fire',
            default => null,
        };

        if (!$table) {
            return null;
        }

        try {
            $link = $this->getActiveLink($linkId);
            if (!$link) return null;

            $stmt = $this->pdo->prepare("
                SELECT MAX(cached_at) FROM `{$table}` WHERE source_instance_id = ?
            ");
            $stmt->execute([$link['instance_id']]);
            return $stmt->fetchColumn() ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Get all links that need a personnel sync.
     *
     * @return array[] Links where consume_personnel=1 and sync is due
     */
    public function getPersonnelSyncDueLinks(): array
    {
        $stmt = $this->pdo->query("
            SELECT * FROM intra_federation_links
            WHERE is_active = 1 AND consume_personnel = 1
            AND (
                last_sync_at IS NULL
                OR last_sync_at < DATE_SUB(NOW(), INTERVAL sync_interval_minutes MINUTE)
            )
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
