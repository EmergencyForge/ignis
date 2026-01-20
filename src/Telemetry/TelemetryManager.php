<?php

namespace App\Telemetry;

use PDO;

require_once __DIR__ . '/../Config/ConfigManager.php';

use App\Config\ConfigManager;

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
    private PDO $pdo;
    private ConfigManager $config;

    public const HEARTBEAT_INTERVAL = 86400; // 24 Stunden

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->config = new ConfigManager($pdo);
    }

    public function isEnabled(): bool
    {
        return (bool) $this->config->get('TELEMETRY_ENABLED');
    }

    public function enable(?int $userId = null): bool
    {
        $this->getOrCreateInstallationId();
        return $this->config->update('TELEMETRY_ENABLED', 'true', $userId);
    }

    public function disable(?int $userId = null): bool
    {
        return $this->config->update('TELEMETRY_ENABLED', 'false', $userId);
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
                $stmt = $this->pdo->prepare("
                    INSERT INTO intra_config 
                    (config_key, config_value, config_type, category, description, is_editable, display_order)
                    VALUES (?, ?, 'string', 'telemetrie', 'Eindeutige Installations-ID für Telemetrie', 0, 1)
                    ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)
                ");
                $stmt->execute(['INSTALLATION_ID', $installationId]);
            } catch (\PDOException $e) {
                error_log("Failed to save installation ID: " . $e->getMessage());
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
        ];
    }

    private function collectStats(): array
    {
        $stats = [
            'active_employees' => 0,
            'total_employees' => 0,
            'active_users' => 0,
            'vehicles' => 0,
            'enotf_last_30_days' => 0,
            'fire_incidents_last_30_days' => 0,
        ];

        try {
            // Aktive Mitarbeiter - prüfe welche Status-Spalte existiert
            try {
                $stmt = $this->pdo->query("SHOW COLUMNS FROM intra_mitarbeiter LIKE 'status'");
                if ($stmt->rowCount() > 0) {
                    $stmt = $this->pdo->query("
                        SELECT COUNT(*) FROM intra_mitarbeiter 
                        WHERE status IN ('Aktiv', 'aktiv', '1', 1) OR status IS NULL
                    ");
                    $stats['active_employees'] = (int) $stmt->fetchColumn();
                } else {
                    // Fallback: alle zählen
                    $stmt = $this->pdo->query("SELECT COUNT(*) FROM intra_mitarbeiter");
                    $stats['active_employees'] = (int) $stmt->fetchColumn();
                }
            } catch (\PDOException $e) {
                // Tabelle existiert nicht
            }

            // Gesamt Mitarbeiter
            try {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM intra_mitarbeiter");
                $stats['total_employees'] = (int) $stmt->fetchColumn();
            } catch (\PDOException $e) {
            }

            // Aktive User (Login in letzten 30 Tagen)
            try {
                $stmt = $this->pdo->query("
                    SELECT COUNT(*) FROM intra_users 
                    WHERE last_login > DATE_SUB(NOW(), INTERVAL 30 DAY)
                ");
                $stats['active_users'] = (int) $stmt->fetchColumn();
            } catch (\PDOException $e) {
            }

            // Fahrzeuge
            try {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM intra_fahrzeuge");
                $stats['vehicles'] = (int) $stmt->fetchColumn();
            } catch (\PDOException $e) {
            }

            // eNOTF Einträge (letzte 30 Tage)
            try {
                $stmt = $this->pdo->query("
                    SELECT COUNT(*) FROM intra_edivi 
                    WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
                ");
                $stats['enotf_last_30_days'] = (int) $stmt->fetchColumn();
            } catch (\PDOException $e) {
            }

            // Feuerwehr-Einsätze (letzte 30 Tage)
            try {
                $stmt = $this->pdo->query("
                    SELECT COUNT(*) FROM intra_fire_incidents 
                    WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
                ");
                $stats['fire_incidents_last_30_days'] = (int) $stmt->fetchColumn();
            } catch (\PDOException $e) {
            }
        } catch (\PDOException $e) {
            error_log("Telemetry stats collection error: " . $e->getMessage());
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
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM intra_edivi");
                $modules['enotf'] = ((int) $stmt->fetchColumn()) > 0;
            } catch (\PDOException $e) {
            }

            // Feuerwehr-Einsätze
            try {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM intra_fire_incidents");
                $modules['fire_incidents'] = ((int) $stmt->fetchColumn()) > 0;
            } catch (\PDOException $e) {
            }

            // MANV - korrekte Tabelle: intra_manv_lagen
            try {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM intra_manv_lagen");
                $modules['manv'] = ((int) $stmt->fetchColumn()) > 0;
            } catch (\PDOException $e) {
            }

            // Dokumente - Templates oder Mitarbeiter-Dokumente
            try {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM intra_dokument_templates");
                $modules['documents'] = ((int) $stmt->fetchColumn()) > 0;
            } catch (\PDOException $e) {
            }

            // Wissensdatenbank
            try {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM intra_kb_entries");
                $modules['knowledge_base'] = ((int) $stmt->fetchColumn()) > 0;
            } catch (\PDOException $e) {
            }
        } catch (\PDOException $e) {
            error_log("Telemetry module check error: " . $e->getMessage());
        }

        return $modules;
    }

    private function getVersion(): string
    {
        // Primär: version.json (vom SystemUpdater verwendet)
        $versionJsonFile = __DIR__ . '/../../system/updates/version.json';
        if (file_exists($versionJsonFile)) {
            $content = file_get_contents($versionJsonFile);
            $data = json_decode($content, true);
            if (isset($data['version'])) {
                return $data['version'];
            }
        }

        // Fallback 1: VERSION Datei
        $versionFile = __DIR__ . '/../../VERSION';
        if (file_exists($versionFile)) {
            return trim(file_get_contents($versionFile));
        }

        // Fallback 2: composer.json
        $composerFile = __DIR__ . '/../../composer.json';
        if (file_exists($composerFile)) {
            $composer = json_decode(file_get_contents($composerFile), true);
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

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'User-Agent: intraRP-Telemetry/1.0',
                    'X-Installation-ID: ' . $data['installation_id'],
                ],
                'content' => json_encode($data),
                'timeout' => 10,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        try {
            $response = @file_get_contents($endpoint, false, $context);

            if ($response === false) {
                $error = error_get_last();
                return ['success' => false, 'message' => 'Verbindung fehlgeschlagen: ' . ($error['message'] ?? 'Unbekannt')];
            }

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
            $stmt = $this->pdo->prepare("
                INSERT INTO intra_config 
                (config_key, config_value, config_type, category, description, is_editable, display_order)
                VALUES ('TELEMETRY_RATE_LIMIT_UNTIL', ?, 'string', 'telemetrie', 'Rate-Limit Cooldown', 0, 99)
                ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)
            ");
            $stmt->execute([$until]);
        } catch (\PDOException $e) {
            error_log("Failed to set rate limit cooldown: " . $e->getMessage());
        }
    }

    private function clearRateLimitCooldown(): void
    {
        try {
            $this->pdo->exec("DELETE FROM intra_config WHERE config_key = 'TELEMETRY_RATE_LIMIT_UNTIL'");
        } catch (\PDOException $e) {
            // Ignorieren
        }
    }

    private function updateLastHeartbeat(): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO intra_config 
                (config_key, config_value, config_type, category, description, is_editable, display_order)
                VALUES ('TELEMETRY_LAST_HEARTBEAT', ?, 'string', 'telemetrie', 'Letzter Telemetrie-Heartbeat', 0, 2)
                ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)
            ");
            $stmt->execute([date('c')]);
        } catch (\PDOException $e) {
            error_log("Failed to update last heartbeat: " . $e->getMessage());
        }
    }

    public function getDataPreview(): array
    {
        return $this->collectData();
    }
}
