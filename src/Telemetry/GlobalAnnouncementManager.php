<?php

namespace App\Telemetry;

use PDO;
use App\Config\ConfigManager;

/**
 * GlobalAnnouncementManager - Holt und verwaltet globale Announcements vom Hub
 * 
 * Announcements werden gecacht und periodisch aktualisiert.
 * Benutzer können Announcements ausblenden (dismiss).
 */
class GlobalAnnouncementManager
{
    private PDO $pdo;
    private ConfigManager $config;

    public const CACHE_DURATION = 3600; // 1 Stunde

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->config = new ConfigManager($pdo);
    }

    public function isEnabled(): bool
    {
        return (bool) $this->config->get('ANNOUNCEMENTS_ENABLED');
    }

    public function getHubUrl(): string
    {
        return $this->config->get('HUB_URL') ?? 'https://hub.intrarp.de';
    }

    /**
     * Gibt aktive Announcements zurück (gefiltert nach User-Dismissals)
     */
    public function getActiveAnnouncements(?int $userId = null): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        // Cache aktualisieren falls nötig
        $this->refreshCacheIfNeeded();

        try {
            $sql = "
                SELECT c.* FROM intra_global_announcements_cache c
                WHERE c.valid_from <= NOW() 
                AND (c.valid_until IS NULL OR c.valid_until >= NOW())
            ";
            $params = [];

            // Ausgeblendete Announcements ausfiltern
            if ($userId !== null) {
                $sql .= " AND c.announcement_id NOT IN (
                    SELECT announcement_id FROM intra_global_announcements_dismissed 
                    WHERE user_id = ?
                )";
                $params[] = $userId;
            }

            $sql .= " ORDER BY c.priority DESC, c.valid_from DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Failed to get announcements: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Markiert ein Announcement als ausgeblendet für einen Benutzer
     */
    public function dismissAnnouncement(string $announcementId, int $userId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO intra_global_announcements_dismissed 
                (announcement_id, user_id, dismissed_at)
                VALUES (?, ?, NOW())
            ");
            return $stmt->execute([$announcementId, $userId]);
        } catch (\PDOException $e) {
            error_log("Failed to dismiss announcement: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Prüft ob der Cache aktualisiert werden muss und tut es falls nötig
     */
    private function refreshCacheIfNeeded(): void
    {
        try {
            $stmt = $this->pdo->query("
                SELECT MAX(fetched_at) as last_fetch 
                FROM intra_global_announcements_cache
            ");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result || !$result['last_fetch']) {
                $this->refreshCache();
                return;
            }

            $lastFetch = strtotime($result['last_fetch']);
            if ((time() - $lastFetch) >= self::CACHE_DURATION) {
                $this->refreshCache();
            }
        } catch (\PDOException $e) {
            error_log("Cache check failed: " . $e->getMessage());
        }
    }

    /**
     * Aktualisiert den lokalen Cache mit Daten vom Hub
     */
    public function refreshCache(): array
    {
        $hubUrl = $this->getHubUrl();
        $endpoint = rtrim($hubUrl, '/') . '/api/hub-announcements.php';

        // Installation-ID und Version für optionales Filtering
        $telemetry = new TelemetryManager($this->pdo);
        $installationId = $telemetry->getOrCreateInstallationId();

        $queryParams = http_build_query([
            'installation_id' => $installationId,
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Accept: application/json',
                    'User-Agent: intraRP-Client/1.0',
                    'X-Installation-ID: ' . $installationId,
                ],
                'timeout' => 5,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        try {
            $response = @file_get_contents($endpoint . '?' . $queryParams, false, $context);

            if ($response === false) {
                return ['success' => false, 'message' => 'Verbindung zum Hub fehlgeschlagen'];
            }

            $data = json_decode($response, true);

            if (!isset($data['success']) || !$data['success']) {
                return ['success' => false, 'message' => $data['message'] ?? 'Unbekannter Fehler'];
            }

            // Cache leeren und neu befüllen
            $this->pdo->exec("DELETE FROM intra_global_announcements_cache");

            $announcements = $data['announcements'] ?? [];

            if (!empty($announcements)) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO intra_global_announcements_cache 
                    (announcement_id, type, title, message, link, priority, valid_from, valid_until, fetched_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");

                foreach ($announcements as $ann) {
                    $stmt->execute([
                        $ann['id'],
                        $ann['type'] ?? 'info',
                        $ann['title'],
                        $ann['message'] ?? null,
                        $ann['link'] ?? null,
                        $ann['priority'] ?? 0,
                        $ann['valid_from'] ?? date('Y-m-d H:i:s'),
                        $ann['valid_until'] ?? null,
                    ]);
                }
            }

            return [
                'success' => true,
                'message' => count($announcements) . ' Announcements aktualisiert',
                'count' => count($announcements)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Fehler: ' . $e->getMessage()];
        }
    }

    /**
     * Räumt alte Dismissals auf (älter als 90 Tage)
     */
    public function cleanupOldDismissals(int $days = 90): int
    {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM intra_global_announcements_dismissed 
                WHERE dismissed_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            return $stmt->rowCount();
        } catch (\PDOException $e) {
            error_log("Failed to cleanup dismissals: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Gibt das Bootstrap-Alert-Klasse für einen Announcement-Typ zurück
     */
    public static function getAlertClass(string $type): string
    {
        return match ($type) {
            'critical' => 'alert-danger',
            'warning' => 'alert-warning',
            'success' => 'alert-success',
            'update' => 'alert-primary',
            default => 'alert-info',
        };
    }

    /**
     * Gibt das FontAwesome-Icon für einen Announcement-Typ zurück
     */
    public static function getIcon(string $type): string
    {
        return match ($type) {
            'critical' => 'fa-circle-exclamation',
            'warning' => 'fa-triangle-exclamation',
            'success' => 'fa-circle-check',
            'update' => 'fa-arrow-up-from-bracket',
            default => 'fa-circle-info',
        };
    }
}
