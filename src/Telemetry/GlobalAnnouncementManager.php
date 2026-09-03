<?php

namespace App\Telemetry;

use App\Config\ConfigManager;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * GlobalAnnouncementManager - Holt und verwaltet globale Announcements vom Hub
 *
 * Announcements werden gecacht und periodisch aktualisiert.
 * Benutzer können Announcements ausblenden (dismiss).
 */
class GlobalAnnouncementManager
{
    private ConfigManager $config;

    public const CACHE_DURATION = 3600; // 1 Stunde

    public function __construct()
    {
        $this->config = new ConfigManager();
    }

    public function isEnabled(): bool
    {
        $value = $this->config->get('ANNOUNCEMENTS_ENABLED');
        // String 'false' muss als false interpretiert werden
        if ($value === 'false' || $value === '0' || $value === false || $value === null) {
            return false;
        }
        return true;
    }

    public function enable(): bool
    {
        try {
            Capsule::table('intra_config')
                ->where('config_key', 'ANNOUNCEMENTS_ENABLED')
                ->update([
                    'config_value' => 'true',
                    'updated_at'   => Capsule::raw('NOW()'),
                ]);
            return true;
        } catch (\PDOException $e) {
            \App\Logging\Logger::warning("Failed to enable announcements: " . $e->getMessage());
            return false;
        }
    }

    public function disable(): bool
    {
        try {
            Capsule::table('intra_config')
                ->where('config_key', 'ANNOUNCEMENTS_ENABLED')
                ->update([
                    'config_value' => 'false',
                    'updated_at'   => Capsule::raw('NOW()'),
                ]);
            return true;
        } catch (\PDOException $e) {
            \App\Logging\Logger::warning("Failed to disable announcements: " . $e->getMessage());
            return false;
        }
    }

    public function getHubUrl(): string
    {
        return $this->config->get('HUB_URL') ?? 'https://emergencyforge.de';
    }

    /**
     * Gibt aktive Announcements zurück (gefiltert nach User-Dismissals und Admin-Status)
     */
    public function getActiveAnnouncements(?int $userId = null, bool $isAdmin = false, bool $skipRefresh = false): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        // Cache aktualisieren falls nötig (kann übersprungen werden für non-blocking Laden)
        if (!$skipRefresh) {
            $this->refreshCacheIfNeeded();
        }

        try {
            $query = Capsule::table('intra_global_announcements_cache')
                ->where(function ($q) {
                    $q->whereNull('valid_from')->orWhereRaw('valid_from <= NOW()');
                })
                ->where(function ($q) {
                    $q->whereNull('valid_until')->orWhereRaw('valid_until >= NOW()');
                });

            // Admin-Only Filter: Nur Admins sehen admin_only Announcements
            if (!$isAdmin) {
                $query->where(function ($q) {
                    $q->whereNull('admin_only')->orWhere('admin_only', 0);
                });
            }

            // Ausgeblendete Announcements ausfiltern
            if ($userId !== null) {
                $query->whereNotIn('announcement_id', function ($q) use ($userId) {
                    $q->select('announcement_id')
                        ->from('intra_global_announcements_dismissed')
                        ->where('user_id', $userId);
                });
            }

            return $query
                ->orderByDesc('priority')
                ->orderByDesc('valid_from')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        } catch (\PDOException $e) {
            \App\Logging\Logger::warning("Failed to get announcements: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Markiert ein Announcement als ausgeblendet für einen Benutzer
     */
    public function dismissAnnouncement(string $announcementId, int $userId): bool
    {
        try {
            Capsule::table('intra_global_announcements_dismissed')->insertOrIgnore([
                'announcement_id' => $announcementId,
                'user_id'         => $userId,
                'dismissed_at'    => Capsule::raw('NOW()'),
            ]);
            return true;
        } catch (\PDOException $e) {
            \App\Logging\Logger::warning("Failed to dismiss announcement: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Prüft ob der Cache veraltet ist (ohne ihn zu aktualisieren)
     */
    public function isCacheStale(): bool
    {
        try {
            $lastFetchValue = Capsule::table('intra_global_announcements_cache')->max('fetched_at');

            if (!$lastFetchValue) {
                return true;
            }

            $lastFetch = strtotime($lastFetchValue);
            return (time() - $lastFetch) >= self::CACHE_DURATION;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Prüft ob der Cache aktualisiert werden muss und tut es falls nötig
     */
    private function refreshCacheIfNeeded(): void
    {
        try {
            $lastFetchValue = Capsule::table('intra_global_announcements_cache')->max('fetched_at');

            if (!$lastFetchValue) {
                $this->refreshCache();
                return;
            }

            $lastFetch = strtotime($lastFetchValue);
            if ((time() - $lastFetch) >= self::CACHE_DURATION) {
                $this->refreshCache();
            }
        } catch (\PDOException $e) {
            \App\Logging\Logger::warning("Cache check failed: " . $e->getMessage());
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
        $telemetry = new TelemetryManager();
        $installationId = $telemetry->getOrCreateInstallationId();

        $queryParams = http_build_query([
            'installation_id' => $installationId,
        ]);

        try {
            $result = \App\Utils\HttpClient::request($endpoint . '?' . $queryParams, [
                'headers' => [
                    'Accept: application/json',
                    'User-Agent: ignis-Client/1.0',
                    'X-Installation-ID: ' . $installationId,
                ],
                'timeout' => 3,
            ]);

            if ($result === null) {
                return ['success' => false, 'message' => 'Verbindung zum Hub fehlgeschlagen'];
            }

            $response = $result['body'];

            $data = json_decode($response, true);

            if (!isset($data['success']) || !$data['success']) {
                return ['success' => false, 'message' => $data['error'] ?? $data['message'] ?? 'Unbekannter Fehler vom Hub'];
            }

            // Cache leeren und neu befüllen
            Capsule::table('intra_global_announcements_cache')->delete();

            $announcements = $data['announcements'] ?? [];

            foreach ($announcements as $ann) {
                // valid_from/valid_until: leere Strings als NULL behandeln
                $validFrom = !empty($ann['valid_from']) ? $ann['valid_from'] : null;
                $validUntil = !empty($ann['valid_until']) ? $ann['valid_until'] : null;

                Capsule::table('intra_global_announcements_cache')->insert([
                    'announcement_id' => $ann['id'],
                    'type'            => $ann['type'] ?? 'info',
                    'title'           => $ann['title'],
                    'message'         => $ann['message'] ?? null,
                    'link'            => $ann['link'] ?? null,
                    'priority'        => $ann['priority'] ?? 0,
                    'admin_only'      => $ann['admin_only'] ?? 0,
                    'valid_from'      => $validFrom,
                    'valid_until'     => $validUntil,
                    'fetched_at'      => Capsule::raw('NOW()'),
                ]);
            }

            // Hub-seitig angeforderte Heartbeats prüfen (Admin-Aktion
            // "alle Installationen um aktuelle Daten bitten").
            $this->handleHeartbeatRequest($data['heartbeat_requested_at'] ?? null);

            return [
                'success' => true,
                'message' => count($announcements) . ' Ankündigungen aktualisiert',
                'count' => count($announcements)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Fehler: ' . $e->getMessage()];
        }
    }

    /**
     * Der Hub liefert im Announcements-Response optional
     * heartbeat_requested_at mit — der Zeitpunkt, zu dem ein Hub-Admin
     * zuletzt alle Installationen um einen frischen Heartbeat gebeten hat.
     * Ist diese Anforderung neuer als unser letzter Heartbeat, senden wir
     * sofort einen. sendHeartbeat() prüft dabei selbst Telemetrie-Opt-in
     * und Rate-Limit-Cooldowns, hier wird nur der Anlass erkannt.
     */
    private function handleHeartbeatRequest(mixed $requestedAt): void
    {
        if (!is_string($requestedAt) || $requestedAt === '') {
            return;
        }

        $requestedTs = strtotime($requestedAt);
        if ($requestedTs === false) {
            return;
        }

        try {
            $telemetry = new TelemetryManager();
            if (!$telemetry->isEnabled()) {
                return;
            }

            $lastHeartbeat = $telemetry->getLastHeartbeat();
            if ($lastHeartbeat !== null && strtotime($lastHeartbeat) >= $requestedTs) {
                return; // Anforderung ist älter als unser letzter Heartbeat
            }

            $telemetry->sendHeartbeat();
        } catch (\Throwable $e) {
            \App\Logging\Logger::warning('Angeforderter Heartbeat fehlgeschlagen: ' . $e->getMessage());
        }
    }

    /**
     * Räumt alte Dismissals auf (älter als 90 Tage)
     */
    public function cleanupOldDismissals(int $days = 90): int
    {
        try {
            return Capsule::table('intra_global_announcements_dismissed')
                ->whereRaw('dismissed_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$days])
                ->delete();
        } catch (\PDOException $e) {
            \App\Logging\Logger::warning("Failed to cleanup dismissals: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Gibt Cache-Informationen zurück (für Debug-Zwecke)
     */
    public function getCacheInfo(): array
    {
        try {
            $result = Capsule::table('intra_global_announcements_cache')
                ->selectRaw('COUNT(*) as count, MAX(fetched_at) as last_fetch')
                ->first();
            return [
                'count' => (int) ($result->count ?? 0),
                'last_fetch' => $result->last_fetch ?? null,
            ];
        } catch (\PDOException $e) {
            return ['count' => 0, 'last_fetch' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Gibt ALLE gecachten Announcements zurück (ohne Filter, für Debug)
     */
    public function getAllCached(): array
    {
        try {
            return Capsule::table('intra_global_announcements_cache')
                ->orderByDesc('priority')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Gibt die ignis-Alert-Variantenklasse für einen Announcement-Typ zurück
     */
    public static function getAlertClass(string $type): string
    {
        return match ($type) {
            'critical' => 'ignis-alert--danger',
            'warning' => 'ignis-alert--warning',
            'success' => 'ignis-alert--success',
            'update' => 'ignis-alert--info',
            default => 'ignis-alert--info',
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
            'update' => 'fa-arrow-up-from-bracket',
            'success' => 'fa-circle-check',
            default => 'fa-circle-info',
        };
    }
}
