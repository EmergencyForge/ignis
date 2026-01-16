<?php

namespace App\Integrations;

use PDO;
use PDOException;

/**
 * Discord Webhook Integration
 * 
 * Handles sending notifications to Discord channels via webhooks
 */
class DiscordWebhook
{
    private PDO $pdo;
    private static ?array $webhookCache = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Load webhook URLs from config
     * 
     * @return array Array of webhook URLs by type
     */
    private function loadWebhooks(): array
    {
        if (self::$webhookCache !== null) {
            return self::$webhookCache;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT config_key, config_value 
                FROM intra_config 
                WHERE config_key IN ('DISCORD_WEBHOOK_ENOTF_PROTOCOL', 'DISCORD_WEBHOOK_FIRE_PROTOCOL', 'DISCORD_WEBHOOK_ENOTF_PREREG')
            ");
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            self::$webhookCache = [
                'enotf_protocol' => '',
                'fire_protocol' => '',
                'enotf_prereg' => ''
            ];

            foreach ($results as $row) {
                switch ($row['config_key']) {
                    case 'DISCORD_WEBHOOK_ENOTF_PROTOCOL':
                        self::$webhookCache['enotf_protocol'] = $row['config_value'];
                        break;
                    case 'DISCORD_WEBHOOK_FIRE_PROTOCOL':
                        self::$webhookCache['fire_protocol'] = $row['config_value'];
                        break;
                    case 'DISCORD_WEBHOOK_ENOTF_PREREG':
                        self::$webhookCache['enotf_prereg'] = $row['config_value'];
                        break;
                }
            }

            return self::$webhookCache;
        } catch (PDOException $e) {
            \error_log("Failed to load Discord webhooks: " . $e->getMessage());
            return [
                'enotf_protocol' => '',
                'fire_protocol' => '',
                'enotf_prereg' => ''
            ];
        }
    }

    /**
     * Send notification about released eNOTF protocol
     * 
     * @param array $protocolData Protocol data (enr, last_edit, etc.)
     * @return bool Success status
     */
    public function notifyEnotfProtocolReleased(array $protocolData): bool
    {
        $webhooks = $this->loadWebhooks();
        $webhookUrl = $webhooks['enotf_protocol'];

        if (empty($webhookUrl)) {
            return false; // Webhook not configured
        }

        $enr = $protocolData['enr'] ?? 'Unbekannt';
        $timestamp = $protocolData['last_edit'] ?? \date('Y-m-d H:i:s');
        $protokollUrl = 'https://' . (\defined('SYSTEM_URL') ? SYSTEM_URL : '') . '/enotf/protokoll/index.php?enr=' . $enr;

        $payload = [
            'embeds' => [
                [
                    'title' => '📋 eNOTF Protokoll freigegeben',
                    'description' => "Ein neues eNOTF Protokoll wurde fürs QM freigegeben.",
                    'color' => 3447003, // Blue
                    'fields' => [
                        [
                            'name' => 'Einsatznummer',
                            'value' => "**#{$enr}**",
                            'inline' => true
                        ],
                        [
                            'name' => 'Zeitstempel',
                            'value' => $timestamp,
                            'inline' => true
                        ]
                    ],
                    'url' => $protokollUrl,
                    'timestamp' => \date('c')
                ]
            ]
        ];

        return $this->sendWebhook($webhookUrl, $payload);
    }

    /**
     * Send notification about released fireTab protocol
     * 
     * @param array $incidentData Incident data (id, incident_number, location, keyword, etc.)
     * @return bool Success status
     */
    public function notifyFireProtocolReleased(array $incidentData): bool
    {
        $webhooks = $this->loadWebhooks();
        $webhookUrl = $webhooks['fire_protocol'];

        if (empty($webhookUrl)) {
            return false; // Webhook not configured
        }

        $id = $incidentData['id'] ?? 'Unbekannt';
        $incidentNumber = $incidentData['incident_number'] ?? 'Unbekannt';
        $location = $incidentData['location'] ?? 'Unbekannt';
        $keyword = $incidentData['keyword'] ?? 'Unbekannt';
        $startedAt = $incidentData['started_at'] ?? \date('Y-m-d H:i:s');
        $leaderName = $incidentData['leader_name'] ?? 'Unbekannt';
        $protokollUrl = 'https://' . (\defined('SYSTEM_URL') ? SYSTEM_URL : '') . '/einsatz/view.php?id=' . $id;

        $payload = [
            'embeds' => [
                [
                    'title' => '🚒 fireTab-Protokoll freigegeben',
                    'description' => "Ein fireTab-Protokoll wurde zur QM-Sichtung freigegeben.",
                    'color' => 15158332, // Red
                    'fields' => [
                        [
                            'name' => 'Einsatznummer',
                            'value' => "**{$incidentNumber}**",
                            'inline' => true
                        ],
                        [
                            'name' => 'Einsatzort',
                            'value' => $location,
                            'inline' => true
                        ],
                        [
                            'name' => 'Stichwort',
                            'value' => $keyword,
                            'inline' => true
                        ],
                        [
                            'name' => 'Einsatzbeginn',
                            'value' => $startedAt,
                            'inline' => true
                        ],
                        [
                            'name' => 'Einsatzleiter',
                            'value' => $leaderName,
                            'inline' => true
                        ]
                    ],
                    'url' => $protokollUrl,
                    'timestamp' => \date('c')
                ]
            ]
        ];

        return $this->sendWebhook($webhookUrl, $payload);
    }

    /**
     * Send notification about new eNOTF pre-registration
     * 
     * @param array $preregData Pre-registration data (id, priority, arrival, diagnose, etc.)
     * @return bool Success status
     */
    public function notifyEnotfPreregistration(array $preregData): bool
    {
        $logFile = __DIR__ . '/../../enotf/schnittstelle/php_errors.log';
        \file_put_contents($logFile, "[" . \date('Y-m-d H:i:s') . "] [DiscordWebhook] notifyEnotfPreregistration aufgerufen\n", FILE_APPEND);

        $webhooks = $this->loadWebhooks();
        $webhookUrl = $webhooks['enotf_prereg'];

        \file_put_contents($logFile, "[" . \date('Y-m-d H:i:s') . "] [DiscordWebhook] Webhook-URL: " . ($webhookUrl ?: 'LEER') . "\n", FILE_APPEND);
        \error_log("Discord Webhook (Voranmeldung): URL = " . ($webhookUrl ?: 'LEER'));

        if (empty($webhookUrl)) {
            \file_put_contents($logFile, "[" . \date('Y-m-d H:i:s') . "] [DiscordWebhook] ABBRUCH: Keine URL konfiguriert\n", FILE_APPEND);
            \error_log("Discord Webhook (Voranmeldung): Keine Webhook-URL konfiguriert");
            return false; // Webhook not configured
        }

        $id = $preregData['id'] ?? 'Unbekannt';
        $priorityRaw = $preregData['priority'] ?? 'Unbekannt';
        $arrival = $preregData['arrival'] ?? \date('Y-m-d H:i:s');
        $fahrzeug = $preregData['fahrzeug'] ?? 'Unbekannt';
        $diagnose = $preregData['diagnose'] ?? 'Keine Angabe';
        $ziel = $preregData['ziel'] ?? 'Unbekannt';
        $enr = $preregData['enr'] ?? null;
        $intubiert = $preregData['intubiert'] ?? null;
        $kreislauf = $preregData['kreislauf'] ?? null;

        // Priorität-Mapping (Zahl zu Text)
        $priorityMap = [
            2 => 'Sofort',
            1 => 'Dringlich',
            0 => 'Nicht dringlich'
        ];
        $priority = $priorityMap[$priorityRaw] ?? $priorityRaw;

        $priorityEmojis = [
            'Sofort' => '🔴',
            'Dringlich' => '🟡',
            'Nicht dringlich' => '🟢'
        ];
        $priorityEmoji = $priorityEmojis[$priority] ?? '⚪';

        // Farbe basierend auf Priorität
        $priorityColors = [
            2 => 15158332,  // Rot
            1 => 16776960,  // Gelb
            0 => 5763719    // Grün
        ];
        $embedColor = $priorityColors[$priorityRaw] ?? 10181046;

        $protokollUrl = '';
        if ($enr) {
            $protokollUrl = 'https://' . (\defined('SYSTEM_URL') ? SYSTEM_URL : '') . '/enotf/schnittstelle/voranmeldung.php?enr=' . $enr;
        }

        // Intubiert & Kreislauf Status
        $intubiertText = $intubiert == 1 ? '✅' : ($intubiert == 0 ? '❌' : 'Unbekannt');
        $kreislaufText = $kreislauf == 1 ? 'Stabil' : ($kreislauf == 0 ? 'Instabil' : 'Unbekannt');

        $payload = [
            'embeds' => [
                [
                    'title' => '🏥 Neue Klinik-Voranmeldung',
                    'description' => "Eine neue Voranmeldung wurde im eNOTF-System erfasst.",
                    'color' => $embedColor,
                    'fields' => [
                        [
                            'name' => 'Priorität',
                            'value' => "{$priorityEmoji} **{$priority}**",
                            'inline' => true
                        ],
                        [
                            'name' => 'Ankunft',
                            'value' => \date('d.m.Y H:i', \strtotime($arrival)),
                            'inline' => true
                        ],
                        [
                            'name' => 'Fahrzeug',
                            'value' => $fahrzeug,
                            'inline' => true
                        ],
                        [
                            'name' => 'Diagnose',
                            'value' => $diagnose,
                            'inline' => false
                        ],
                        [
                            'name' => 'Zielklinik',
                            'value' => $ziel,
                            'inline' => true
                        ],
                        [
                            'name' => 'Intubiert',
                            'value' => $intubiertText,
                            'inline' => true
                        ],
                        [
                            'name' => 'Kreislauf',
                            'value' => $kreislaufText,
                            'inline' => true
                        ]
                    ],
                    'timestamp' => \date('c')
                ]
            ]
        ];

        $logFile = __DIR__ . '/../../enotf/schnittstelle/php_errors.log';
        \file_put_contents($logFile, "[" . \date('Y-m-d H:i:s') . "] [DiscordWebhook] Sende Webhook: Priorität='" . $priority . "', Fahrzeug='" . $fahrzeug . "'\n", FILE_APPEND);
        \error_log("Discord Webhook (Voranmeldung): Sende Payload mit Priorität '" . $priority . "', Fahrzeug '" . $fahrzeug . "'");
        // Debug: Komplettes Payload ausgeben
        \file_put_contents($logFile, "[" . \date('Y-m-d H:i:s') . "] [DiscordWebhook] JSON Payload:\n" . \json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        $result = $this->sendWebhook($webhookUrl, $payload);

        \file_put_contents($logFile, "[" . \date('Y-m-d H:i:s') . "] [DiscordWebhook] Ergebnis: " . ($result ? 'ERFOLG' : 'FEHLER') . "\n", FILE_APPEND);
        \error_log("Discord Webhook (Voranmeldung): Ergebnis = " . ($result ? 'ERFOLG' : 'FEHLER'));
        return $result;
    }

    /**
     * Send webhook to Discord
     * 
     * @param string $webhookUrl Discord webhook URL
     * @param array $payload JSON payload
     * @return bool Success status
     */
    private function sendWebhook(string $webhookUrl, array $payload): bool
    {
        try {
            $jsonPayload = \json_encode($payload);
            \error_log("Discord Webhook: Sende Request an " . \substr($webhookUrl, 0, 50) . "...");
            \error_log("Discord Webhook: Payload-Größe: " . \strlen($jsonPayload) . " bytes");

            $ch = \curl_init($webhookUrl);
            \curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            \curl_setopt($ch, CURLOPT_POST, 1);
            \curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            \curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Deaktiviere SSL-Verifizierung für Discord
            \curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            \curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = \curl_exec($ch);
            $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = \curl_error($ch);
            \curl_close($ch);

            \error_log("Discord Webhook: HTTP Status = {$httpCode}");
            if ($curlError) {
                \error_log("Discord Webhook: cURL Error = {$curlError}");
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                \error_log("Discord Webhook: Erfolgreich gesendet");
                return true;
            } else {
                \error_log("Discord webhook failed with HTTP {$httpCode}: {$response}");
                return false;
            }
        } catch (\Exception $e) {
            \error_log("Discord webhook exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear webhook cache
     */
    public static function clearCache(): void
    {
        self::$webhookCache = null;
    }
}
