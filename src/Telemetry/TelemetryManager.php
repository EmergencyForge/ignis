<?php

namespace App\Telemetry;

use App\Config\ConfigManager;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * TelemetryManager - Sammelt und sendet anonymisierte Statistiken
 *
 * DATENSCHUTZ-HINWEIS:
 * - Telemetrie ist standardmäßig DEAKTIVIERT (Opt-In)
 * - Es werden KEINE persönlichen Daten übertragen
 * - Nur aggregierte, anonymisierte Statistiken
 * - Jede Installation erhält eine zufällige UUID
 */
class TelemetryManager
{
    private ConfigManager $config;

    public const HEARTBEAT_INTERVAL = 86400; // 24 Stunden

    public function __construct()
    {
        $this->config = new ConfigManager();
    }

    public function isEnabled(): bool
    {
        $value = $this->config->get('TELEMETRY_ENABLED');
        // String 'false' muss als false interpretiert werden
        if ($value === 'false' || $value === '0' || $value === false || $value === null) {
            return false;
        }
        return true;
    }

    public function enable(?int $userId = null): bool
    {
        $this->getOrCreateInstallationId();
        try {
            Capsule::table('intra_config')
                ->where('config_key', 'TELEMETRY_ENABLED')
                ->update([
                    'config_value' => 'true',
                    'updated_at'   => Capsule::raw('NOW()'),
                ]);
            return true;
        } catch (\PDOException $e) {
            \App\Logging\Logger::warning("Failed to enable telemetry: " . $e->getMessage());
            return false;
        }
    }

    public function disable(?int $userId = null): bool
    {
        try {
            Capsule::table('intra_config')
                ->where('config_key', 'TELEMETRY_ENABLED')
                ->update([
                    'config_value' => 'false',
                    'updated_at'   => Capsule::raw('NOW()'),
                ]);
            return true;
        } catch (\PDOException $e) {
            \App\Logging\Logger::warning("Failed to disable telemetry: " . $e->getMessage());
            return false;
        }
    }

    public function getHubUrl(): string
    {
        return $this->config->get('HUB_URL') ?? 'https://emergencyforge.de';
    }

    public function getLastHeartbeat(): ?string
    {
        return $this->config->get('TELEMETRY_LAST_HEARTBEAT');
    }

    public function getInstallationId(): string
    {
        return $this->getOrCreateInstallationId();
    }

    public function getOrCreateInstallationId(): string
    {
        $installationId = $this->config->get('INSTALLATION_ID');

        if (empty($installationId)) {
            $installationId = $this->generateUUID();

            try {
                Capsule::table('intra_config')->upsert(
                    [[
                        'config_key'    => 'INSTALLATION_ID',
                        'config_value'  => $installationId,
                        'config_type'   => 'string',
                        'category'      => 'telemetrie',
                        'description'   => 'Eindeutige Installations-ID für Telemetrie',
                        'is_editable'   => 0,
                        'display_order' => 1,
                    ]],
                    ['config_key'],
                    ['config_value']
                );
            } catch (\PDOException $e) {
                \App\Logging\Logger::warning("Failed to save installation ID: " . $e->getMessage());
            }
        }

        return $installationId;
    }

    private function generateUUID(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function collectData(): array
    {
        return [
            'installation_id' => $this->getOrCreateInstallationId(),
            'version' => $this->getVersion(),
            'php_version' => PHP_VERSION,
            'timestamp' => date('c'),
            'system' => $this->collectSystemInfo(),
            'stats' => $this->collectStats(),
            'modules' => $this->collectModuleInfo(),
        ];
    }

    private function collectSystemInfo(): array
    {
        return [
            'server_name' => defined('SERVER_NAME') ? SERVER_NAME : null,
            'system_name' => defined('SYSTEM_NAME') ? SYSTEM_NAME : null,
            'org_type' => defined('RP_ORGTYPE') ? RP_ORGTYPE : null,
            'database_version' => $this->getDatabaseVersion(),
            'os' => PHP_OS_FAMILY,
            'webserver' => $_SERVER['SERVER_SOFTWARE'] ?? null,
            'timezone' => date_default_timezone_get(),
            'locale' => setlocale(LC_ALL, '0') ?: null,
        ];
    }

    private function getDatabaseVersion(): ?string
    {
        try {
            $row = Capsule::connection()->selectOne('SELECT VERSION() AS version');
            return $row->version ?? null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    private function collectStats(): array
    {
        $stats = [
            'active_employees' => 0,
            'total_employees' => 0,
            'total_users' => 0,
            'active_users' => 0,
            'logins_last_30_days' => 0,
            'vehicles' => 0,
            'enotf_last_30_days' => 0,
            'enotf_total' => 0,
            'fire_incidents_last_30_days' => 0,
            'fire_incidents_total' => 0,
            'manv_total' => 0,
            'documents_total' => 0,
            'knowledge_base_articles' => 0,
            'discord_webhooks_configured' => 0,
            'days_since_install' => 0,
        ];

        try {
            // Aktive Mitarbeiter - prüfe welche Status-Spalte existiert
            try {
                if (Capsule::schema()->hasColumn('intra_mitarbeiter', 'status')) {
                    $stats['active_employees'] = Capsule::table('intra_mitarbeiter')
                        ->where(function ($query) {
                            $query->whereIn('status', ['Aktiv', 'aktiv', '1', 1])
                                ->orWhereNull('status');
                        })
                        ->count();
                } else {
                    // Fallback: alle zählen
                    $stats['active_employees'] = Capsule::table('intra_mitarbeiter')->count();
                }
            } catch (\PDOException $e) {
                // Tabelle existiert nicht
            }

            // Gesamt Mitarbeiter
            try {
                $stats['total_employees'] = Capsule::table('intra_mitarbeiter')->count();
            } catch (\PDOException $e) {
            }

            // Gesamt User
            try {
                $stats['total_users'] = Capsule::table('intra_users')->count();
            } catch (\PDOException $e) {
            }

            // Aktive User (Login in letzten 30 Tagen via Session-Logs)
            try {
                $stats['active_users'] = (int) Capsule::table('intra_session_logs')
                    ->whereRaw('created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)')
                    ->count(Capsule::raw('DISTINCT user_id'));
            } catch (\PDOException $e) {
                // Fallback: Alle User zählen
                $stats['active_users'] = $stats['total_users'];
            }

            // Logins letzte 30 Tage (Gesamtzahl, nicht distinct)
            try {
                $stats['logins_last_30_days'] = Capsule::table('intra_session_logs')
                    ->whereRaw('created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)')
                    ->count();
            } catch (\PDOException $e) {
            }

            // Fahrzeuge
            try {
                $stats['vehicles'] = Capsule::table('intra_fahrzeuge')->count();
            } catch (\PDOException $e) {
            }

            // eNOTF Einträge (letzte 30 Tage + gesamt)
            try {
                $stats['enotf_last_30_days'] = Capsule::table('intra_edivi')
                    ->whereRaw('created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)')
                    ->count();

                $stats['enotf_total'] = Capsule::table('intra_edivi')->count();
            } catch (\PDOException $e) {
            }

            // Feuerwehr-Einsätze (letzte 30 Tage + gesamt)
            try {
                $stats['fire_incidents_last_30_days'] = Capsule::table('intra_fire_incidents')
                    ->whereRaw('created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)')
                    ->count();

                $stats['fire_incidents_total'] = Capsule::table('intra_fire_incidents')->count();
            } catch (\PDOException $e) {
            }

            // MANV-Lagen gesamt
            try {
                $stats['manv_total'] = Capsule::table('intra_manv_lagen')->count();
            } catch (\PDOException $e) {
            }

            // Dokument-Templates gesamt
            try {
                $stats['documents_total'] = Capsule::table('intra_dokument_templates')->count();
            } catch (\PDOException $e) {
            }

            // Wissensbasis-Artikel gesamt
            try {
                $stats['knowledge_base_articles'] = Capsule::table('intra_kb_entries')->count();
            } catch (\PDOException $e) {
            }

            // Konfigurierte Discord-Webhooks
            try {
                $stats['discord_webhooks_configured'] = Capsule::table('intra_config')
                    ->where('config_key', 'like', 'DISCORD_WEBHOOK_%')
                    ->whereNotNull('config_value')
                    ->where('config_value', '!=', '')
                    ->count();
            } catch (\PDOException $e) {
            }

            // Tage seit Installation (basierend auf ältestem User oder Config-Eintrag)
            try {
                $days = Capsule::table('intra_users')
                    ->selectRaw('DATEDIFF(NOW(), MIN(created_at)) AS days')
                    ->value('days');
                $stats['days_since_install'] = $days !== null ? (int) $days : 0;
            } catch (\PDOException $e) {
            }
        } catch (\PDOException $e) {
            \App\Logging\Logger::warning("Telemetry stats collection error: " . $e->getMessage());
        }

        return $stats;
    }

    private function collectModuleInfo(): array
    {
        $modules = [
            'enotf' => false,
            'fire_incidents' => false,
            'manv' => false,
            'documents' => false,
            'knowledge_base' => false,
        ];

        try {
            // eNOTF - Tabelle existiert und hat Einträge
            try {
                $modules['enotf'] = Capsule::table('intra_edivi')->count() > 0;
            } catch (\PDOException $e) {
            }

            // Feuerwehr-Einsätze
            try {
                $modules['fire_incidents'] = Capsule::table('intra_fire_incidents')->count() > 0;
            } catch (\PDOException $e) {
            }

            // MANV - korrekte Tabelle: intra_manv_lagen
            try {
                $modules['manv'] = Capsule::table('intra_manv_lagen')->count() > 0;
            } catch (\PDOException $e) {
            }

            // Dokumente - Templates oder Mitarbeiter-Dokumente
            try {
                $modules['documents'] = Capsule::table('intra_dokument_templates')->count() > 0;
            } catch (\PDOException $e) {
            }

            // Wissensdatenbank
            try {
                $modules['knowledge_base'] = Capsule::table('intra_kb_entries')->count() > 0;
            } catch (\PDOException $e) {
            }
        } catch (\PDOException $e) {
            \App\Logging\Logger::warning("Telemetry module check error: " . $e->getMessage());
        }

        return $modules;
    }

    private function getVersion(): string
    {
        // Primär: storage/version.json — die Quelle, die Release-Build und
        // SystemUpdater tatsächlich pflegen (auch der Footer liest sie).
        // Der alte Pfad system/updates/version.json existiert nur noch auf
        // Alt-Installationen und meldete auf frischen Installs 'unknown'.
        $candidates = [
            __DIR__ . '/../../storage/version.json',
            __DIR__ . '/../../system/updates/version.json',
        ];
        foreach ($candidates as $versionJsonFile) {
            if (!file_exists($versionJsonFile)) {
                continue;
            }
            $data = json_decode((string) file_get_contents($versionJsonFile), true);
            if (is_array($data) && !empty($data['version']) && is_string($data['version'])) {
                return trim($data['version']);
            }
        }

        // Fallback 1: VERSION Datei
        $versionFile = __DIR__ . '/../../VERSION';
        if (file_exists($versionFile)) {
            return trim((string) file_get_contents($versionFile));
        }

        // Fallback 2: composer.json
        $composerFile = __DIR__ . '/../../composer.json';
        if (file_exists($composerFile)) {
            $composer = json_decode((string) file_get_contents($composerFile), true);
            if (isset($composer['version'])) {
                return $composer['version'];
            }
        }

        return 'unknown';
    }

    public function shouldSendHeartbeat(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        // Rate-Limit Check: Nicht senden wenn wir noch im Cooldown sind
        $rateLimitUntil = $this->config->get('TELEMETRY_RATE_LIMIT_UNTIL');
        if ($rateLimitUntil && strtotime($rateLimitUntil) > time()) {
            return false;
        }

        $lastHeartbeat = $this->config->get('TELEMETRY_LAST_HEARTBEAT');
        if (empty($lastHeartbeat)) {
            return true;
        }

        return (time() - strtotime($lastHeartbeat)) >= self::HEARTBEAT_INTERVAL;
    }

    public function sendHeartbeat(bool $force = false): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'Telemetrie ist deaktiviert'];
        }

        // Rate-Limit Check: Nicht senden wenn wir noch im Cooldown sind
        $rateLimitUntil = $this->config->get('TELEMETRY_RATE_LIMIT_UNTIL');
        if ($rateLimitUntil && strtotime($rateLimitUntil) > time()) {
            $waitSeconds = strtotime($rateLimitUntil) - time();
            return ['success' => false, 'message' => "Rate-Limit aktiv. Bitte warte noch {$waitSeconds} Sekunden."];
        }

        $hubUrl = $this->getHubUrl();
        $endpoint = rtrim($hubUrl, '/') . '/api/telemetry/heartbeat.php';
        $data = $this->collectData();

        // Konfigwerte (Systemname, Stadt, …) können auf Alt-Installationen
        // Latin-1-Reste enthalten — ohne Substitute-Flag liefert json_encode
        // dann false und es ginge ein leerer/kaputter Body raus.
        $payload = json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
        if ($payload === false) {
            \App\Logging\Logger::warning('Telemetry: Payload nicht encodierbar: ' . json_last_error_msg());
            return ['success' => false, 'message' => 'Payload konnte nicht kodiert werden: ' . json_last_error_msg()];
        }

        try {
            $result = \App\Utils\HttpClient::request($endpoint, [
                'method'  => 'POST',
                'headers' => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'User-Agent: ignis-Telemetry/1.0',
                    'X-Installation-ID: ' . $data['installation_id'],
                ],
                'body'    => $payload,
                'timeout' => 3,
            ]);

            if ($result === null) {
                $error = error_get_last();
                return ['success' => false, 'message' => 'Verbindung fehlgeschlagen: ' . ($error['message'] ?? 'Hub nicht erreichbar')];
            }

            $response = $result['body'];

            $result = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['success' => false, 'message' => 'Ungültige JSON-Antwort vom Hub: ' . substr($response, 0, 200)];
            }

            // Rate-Limit-Handling
            if (isset($result['error']) && strpos($result['error'], 'Rate limit') !== false) {
                $retryAfter = $result['retry_after'] ?? 60;
                $this->setRateLimitCooldown($retryAfter);
                return ['success' => false, 'message' => "Rate-Limit erreicht. Nächster Versuch in {$retryAfter} Sekunden."];
            }

            if (isset($result['success']) && $result['success']) {
                $this->updateLastHeartbeat();
                $this->clearRateLimitCooldown();
                return ['success' => true, 'message' => 'Heartbeat erfolgreich gesendet'];
            }

            return ['success' => false, 'message' => $result['error'] ?? $result['message'] ?? 'Hub-Antwort: ' . substr($response, 0, 200)];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Fehler: ' . $e->getMessage()];
        }
    }

    private function setRateLimitCooldown(int $seconds): void
    {
        $until = date('c', time() + $seconds);
        try {
            Capsule::table('intra_config')->upsert(
                [[
                    'config_key'    => 'TELEMETRY_RATE_LIMIT_UNTIL',
                    'config_value'  => $until,
                    'config_type'   => 'string',
                    'category'      => 'telemetrie',
                    'description'   => 'Rate-Limit Cooldown',
                    'is_editable'   => 0,
                    'display_order' => 99,
                ]],
                ['config_key'],
                ['config_value']
            );
        } catch (\PDOException $e) {
            \App\Logging\Logger::warning("Failed to set rate limit cooldown: " . $e->getMessage());
        }
    }

    private function clearRateLimitCooldown(): void
    {
        try {
            Capsule::table('intra_config')
                ->where('config_key', 'TELEMETRY_RATE_LIMIT_UNTIL')
                ->delete();
        } catch (\PDOException $e) {
            // Ignorieren
        }
    }

    private function updateLastHeartbeat(): void
    {
        try {
            Capsule::table('intra_config')->upsert(
                [[
                    'config_key'    => 'TELEMETRY_LAST_HEARTBEAT',
                    'config_value'  => date('c'),
                    'config_type'   => 'string',
                    'category'      => 'telemetrie',
                    'description'   => 'Letzter Telemetrie-Heartbeat',
                    'is_editable'   => 0,
                    'display_order' => 2,
                ]],
                ['config_key'],
                ['config_value']
            );
        } catch (\PDOException $e) {
            \App\Logging\Logger::warning("Failed to update last heartbeat: " . $e->getMessage());
        }
    }

    public function getDataPreview(): array
    {
        return $this->collectData();
    }
}
